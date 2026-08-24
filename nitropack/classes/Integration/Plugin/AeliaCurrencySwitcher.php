<?php

namespace NitroPack\Integration\Plugin;
use \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher;

class AeliaCurrencySwitcher {
	const STAGE = "very_early";
	/**
	 * Standart check if the Aelia Currency Switcher is active.
	 *
	 * @return bool
	 */
	public static function isActive() {
		return class_exists( "\Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher" );
	}
	/**
	 * Check if the Aelia Currency Switcher is active from the config due to very_early check.
	 *
	 * @return bool
	 * @since 1.18.2
	 */
	private function isAeliaActive() {
		try {
			$nitropack = get_nitropack();
			if ( ! $nitropack ) {
				return false;
			}

			$siteConfig = $nitropack->getSiteConfig();
			return ! empty( $siteConfig["isAeliaCurrencySwitcherActive"] );
		} catch (Exception $e) {
			return false;
		}
	}
	public function init( $stage ) {

		switch ( $stage ) {
			case "very_early":
				if ( ! $this->isAeliaActive() ) {
					return;
				}

				if ( ! $this->isAeliaGeolocationEnabled() ) {
					return;
				}
				//TODO: Returns always GBP on first load.
				// if ( isset( $_SERVER["HTTP_CF_IPCOUNTRY"] ) ) {				
				// 	add_action( 'set_nitropack_geo_cache_prefix', function () {						
				// 		\NitroPack\SDK\NitroPack::addCustomCachePrefix( $_SERVER["HTTP_CF_IPCOUNTRY"] );
				// 	} );
				// 	return;
				// }
				add_filter( "nitropack_passes_cookie_requirements", [ $this, "can_serve_cache" ] );
				return true;
			case "late":
				if ( ! self::isAeliaActive() ) {
					return;
				}
				
				add_action( 'woocommerce_init', [ $this, 'set_custom_currency_cookie' ] );
				add_action( 'wc_aelia_currencyswitcher_settings_saved', [ $this, 'on_aelia_settings_saved' ] );

				if ( \NitroPack\Util\Utils::is_optimizer_nitropack_request() ) {
					add_filter( 'wc_aelia_cs_selected_currency', [ $this, 'modify_cookie_currency' ] );
				}
				return true;
		}
	}
	/**
	 * Disable first cache serving if the Aelia Currency Switcher cookie is not set, so it can properly display the selected currency afterwards.
	 * Otherwise, it will always serve cached GBP page on first load.
	 *
	 * @param bool $currentState The current state of whether the cache can be served.
	 * @return bool
	 * @since 1.18.2
	 */
	public function can_serve_cache( $currentState ) {
		if ( empty( $_COOKIE["aelia_cs_selected_currency"] ) ) {
			nitropack_header( "X-Nitro-Disabled-Reason: Aelia cookie bypass" );
			return false;
		}
		return $currentState;
	}

	/**
	 * Get the Aelia Currency Switcher instance
	 * 
	 * @return WC_Aelia_CurrencySwitcher|null
	 * @since 1.18.2
	 */
	private function get_currency_switcher_instance() {
		if ( isset( $GLOBALS[ WC_Aelia_CurrencySwitcher::$plugin_slug ] ) ) {
			return $GLOBALS[ WC_Aelia_CurrencySwitcher::$plugin_slug ];
		}
		return null;
	}
	/**
	 * Set a custom cookie for the selected currency
	 * 
	 * This is used to ensure that the correct currency is served in the cache.
	 * @return void
	 * @since 1.18.2
	 */
	public function set_custom_currency_cookie() {
		if ( is_admin() )
			return;

		$currency_switcher = $this->get_currency_switcher_instance();

		if ( $currency_switcher ) {
			$currency = $currency_switcher->get_selected_currency();

			if ( ! empty( $currency ) ) {
				$cookie_expiration = time() + 604800; // 1 week
				setcookie( 'np_wc_currency', $currency, $cookie_expiration, '/' );
			}
		}

	}
	/**
	 * Modifies the currency based on the stored np_wc_currency cookie value above.
	 *
	 * @param string $currency The default currency code (e.g., 'USD')
	 * @return string The currency code from the cookie if available, otherwise the original currency
	 *
	 * @since 1.18.2
	 */
	public function modify_cookie_currency( $currency ) {
		if ( ! empty( $_COOKIE['np_wc_currency'] ) ) {
			$currency = $_COOKIE['np_wc_currency'];
		}
		return $currency;
	}
	/**
	 * Returns the enabled currency codes from the NitroPack config cache.
	 *
	 * @return array<string> Array of currency codes (e.g. ['EUR', 'GBP']), empty array if unavailable.
	 * @since 1.18.2
	 */
	public function get_enabled_currencies() {
		try {
			$nitropack = get_nitropack();
			if ( ! $nitropack ) {
				return [];
			}
			$siteConfig = $nitropack->getSiteConfig();
			$currencies = $siteConfig['options_cache']['wc_aelia_currency_switcher']['enabled_currencies'] ?? [];
			return is_array( $currencies ) ? array_values( array_filter( $currencies ) ) : [];
		} catch (\Exception $e) {
			return [];
		}
	}
	/**
	 * Grab the updated currencies from Aelia and populate them in the NitroPack config cache and app (Cache Settings -> Cache -> Cookies).
	 * Hook 'wc_aelia_currencyswitcher_settings_saved'
	 * @return void
	 */
	public function on_aelia_settings_saved() {
		try {
			$enabled_currencies = $this->get_enabled_currencies_from_option();

			// Update the local NitroPack config cache so get_enabled_currencies() stays in sync.
			$nitropack = get_nitropack();
			if ( $nitropack ) {
				$config = $nitropack->Config->get();
				$configKey = \NitroPack\WordPress\NitroPack::getConfigKey();

				if ( ! isset( $config[ $configKey ]['options_cache']['wc_aelia_currency_switcher'] ) || ! is_array( $config[ $configKey ]['options_cache']['wc_aelia_currency_switcher'] ) ) {
					$config[ $configKey ]['options_cache']['wc_aelia_currency_switcher'] = [];
				}
				$config[ $configKey ]['options_cache']['wc_aelia_currency_switcher']['enabled_currencies'] = $enabled_currencies;
				$nitropack->Config->set( $config );
			}

			// Sync the updated currency list to the NitroPack app.
			get_nitropack_sdk()->getApi()->setVariationCookie( 'aelia_cs_selected_currency', $enabled_currencies );

		} catch (\Exception $e) {
			// Silently fail — config update is best-effort.
		}
	}

	/**
	 * Reads enabled currency codes directly from the Aelia WP option.
	 * Safe to call before the NitroPack config cache is built.
	 *
	 * @return array<string>
	 * @since 1.18.2
	 */
	private function get_enabled_currencies_from_option() {
		$aelia_settings = get_option( 'wc_aelia_currency_switcher', [] );
		if ( isset( $aelia_settings['enabled_currencies'] ) && is_array( $aelia_settings['enabled_currencies'] ) ) {
			return array_values( array_filter( $aelia_settings['enabled_currencies'] ) );
		}
		return [];
	}

	/**
	 * Check if the Aelia Currency Switcher geolocation is enabled.
	 *
	 * @return bool
	 */
	public function isAeliaGeolocationEnabled() {
		$siteConfig = get_nitropack()->getSiteConfig();

		return ! empty( $siteConfig['options_cache']['wc_aelia_currency_switcher']['ipgeolocation_enabled'] )
			&& $siteConfig['options_cache']['wc_aelia_currency_switcher']['ipgeolocation_enabled'] == 1;
	}
	public function doesWoocommerceHandleCache() {
		$siteConfig = get_nitropack()->getSiteConfig();

		return ! empty( $siteConfig['isWoocommerceActive'] )
			&& ! empty( $siteConfig['options_cache']['woocommerce_default_customer_address'] )
			&& "geolocation_ajax" === $siteConfig['options_cache']['woocommerce_default_customer_address'];
	}
}