<?php

namespace NitroPack\Integration\Plugin;
/**
 * CURCY_MultiCurrency Class
 *
 * @package nitropack
 */


class CURCY_MultiCurrency {
	/**
	 * The stage of the plugin integration.
	 * Late due to waiting for the plugin to be initialized and grab its options!
	 * @var string
	 */
	const STAGE = 'late';

	/**
	 * @var array|false $curcy_options Stores the options for the CURCY MultiCurrency plugin integration.
	 */

	private $curcy_options;

	public function __construct() {
		$this->curcy_options = false;
	}
	/**
	 * Check if CURCY Multicurrency free or pro is active
	 *
	 * @return bool
	 */
	public static function isActive() {     //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid		
		return is_plugin_active( 'woo-multi-currency/woo-multi-currency.php' ) || is_plugin_active( 'woocommerce-multi-currency/woocommerce-multi-currency.php' );
	}

	/**
	 * Requires the plugin to be activated.
	 * If its settings are not found - bail out.
	 * Then from its settings it must be enabled, the session option (requires premium) must NOT be set.
	 * If session option is on, it works as it is.
	 * @return bool|void
	 */
	public function init( $stage ) {
		if ( ! self::isActive() )
			return;

		$this->get_options();

		if ( $this->curcy_options === false )
			return false;

		if ( isset( $this->curcy_options['enable'] ) && $this->curcy_options['enable'] === '1' ) {
			if ( ! isset( $this->curcy_options['use_session'] ) ) {
				add_action( 'woocommerce_init', [ $this, 'set_custom_currency_cookie' ] );
			}
		}

		if ( nitropack_is_optimizer_request() ) {
			add_filter( 'wmc_get_currency_code', [ $this, 'modify_cookie_currency' ] );
		}
	}
	/**
	 *  Get the options for the CURCY MultiCurrency plugin and stores them as array.
	 * @return void
	 */
	private function get_options() {
		$this->curcy_options = get_option( 'woo_multi_currency_params' );
	}
	/**
	 * Set np_wc_currency currency cookie based on CURCY Multicurrency
	 *
	 * @param string $currency Currency code - USD, EUR, etc.
	 *
	 * @return void
	 */
	public function set_custom_currency_cookie() {
		if ( is_admin() )
			return;

		$cookie_expiration = time() + 604800; // 60 * 60 * 24 * 7 = 604800 seconds (1 week)
		if ( ! empty( $_COOKIE['wmc_current_currency'] ) ) {
			$currency = $_COOKIE['wmc_current_currency'];
			setcookie( 'np_wc_currency', $currency, $cookie_expiration, '/' ); // 1 week
		}
	}

	/**
	 * Checks if the NitroPack optimizer is visiting the page 
	 * and assigns the already updated cookie np_wc_currency from set_custom_currency_cookie.
	 * @param string $currency The currency code to be modified.
	 * @return string The modified currency code.
	 */
	public function modify_cookie_currency( $currency ) {
		if ( ! empty( $_COOKIE['np_wc_currency'] ) ) {
			$currency = $_COOKIE['np_wc_currency'];
		}
		return $currency;
	}
}
