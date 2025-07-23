<?php

/**
 * WCML Class
 *
 * @package nitropack
 */

namespace NitroPack\Integration\Plugin;

/**
 * WCML Class
 */
class WCML {
	const STAGE = 'late';

	/**
	 * Check if WooCommerce Multilingual is active
	 *
	 * @return bool
	 */
	public static function isActive() {     //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' );
	}

	/**
	 * Init function
	 *
	 * @param string $stage Stage.
	 *
	 * @return void
	 */
	public function init( $stage ) {    //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		if ( ! self::isActive() )
			return;

		add_filter( 'wcml_user_store_strategy', [ $this, 'change_wcml_user_store_strategy' ] );
		add_action( 'wcml_switch_currency', [ $this, 'wcml_set_custom_currency_cookie' ] );
		add_action( 'woocommerce_init', [ $this, 'wcml_set_custom_currency_cookie' ] );
		add_action( 'woocommerce_init', [ $this, 'wcml_set_custom_language_cookie' ] );

	}
	/**
	 * https://git.onthegosystems.com/glue-plugins/wpml/woocommerce-multilingual/-/wikis/Integrate-caching-for-multicurrency
	 * /wp-admin/edit.php?post_type=shop_order - becomes unresponsive, so we keep it wc-session
	 * @return string
	 */
	public function change_wcml_user_store_strategy() {
		if ( is_admin() ) {
			if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'shop_order' ) {
				return 'wc-session';
			}
		}
		return 'cookie';
	}
	/**
	 * Set np_wc_currency currency cookie based on WCML currency
	 *
	 * @param string $currency Currency code - USD, EUR, etc.
	 *
	 * @return void
	 */
	public function wcml_set_custom_currency_cookie( $currency = false ) {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$cookie_expiration = time() + 604800; // 60 * 60 * 24 * 7 = 604800 seconds (1 week)

		if ( ! empty( $_COOKIE['wcml_client_currency'] ) ) {
			$cookie_value = $_COOKIE['wcml_client_currency'];
		} else if ( $currency ) {
			$cookie_value = $currency;
		} else {
			//uses default currency setup in WC
			$cookie_value = get_woocommerce_currency();
		}
		setcookie( 'np_wc_currency', $cookie_value, $cookie_expiration, '/' ); // 1 week
	}

	/**
	 * Set np_wc_currency_language custom language cookie based on WCML or fallbacks to WP language - 'en', 'de', etc.
	 *
	 * @return void
	 */
	public function wcml_set_custom_language_cookie() {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}
		$cookie_expiration = time() + 604800; // 60 * 60 * 24 * 7 = 604800 seconds (1 week)

		$wcCurrencyLanguage = ( isset( WC()->session ) && WC()->session->has_session() ) ? WC()->session->get( "client_currency_language" ) : 0;

		if ( ! empty( $_COOKIE['wcml_client_currency_language'] ) ) {
			$cookie_value = $_COOKIE['wcml_client_currency_language'];
		} else if ( $wcCurrencyLanguage ) {
			$cookie_value = $wcCurrencyLanguage;
		} else {
			/** Fallbacks to WP default language **/
			$locale = get_locale();
			$lang = substr( $locale, 0, 2 ); //Converts 'en_GB' to 'en'
			$cookie_value = $lang;
		}
		setcookie( 'np_wc_currency_language', $cookie_value, $cookie_expiration, '/' );
	}
}
