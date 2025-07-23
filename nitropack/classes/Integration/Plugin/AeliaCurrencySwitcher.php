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

				if ( nitropack_is_optimizer_request() ) {
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