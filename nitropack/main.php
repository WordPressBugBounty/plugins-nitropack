<?php
/*
Plugin Name:  NitroPack
Plugin URI:   https://nitropack.io/platform/wordpress
Description:  Automatic optimization for site speed and Core Web Vitals. Use 35+ features, including Caching, image optimization, critical CSS, and Cloudflare CDN.
Version:      1.19.4
Author:       NitroPack Inc.
Author URI:   https://nitropack.io/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  nitropack
Domain Path:  /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! defined( 'NITROPACK_BASENAME' ) ) {
	define( 'NITROPACK_BASENAME', plugin_basename( __FILE__ ) );
}

$np_basePath = dirname( __FILE__ ) . '/';

require_once $np_basePath . 'functions.php';
require_once $np_basePath . 'helpers.php';

new NitroPack\PageSpeedBoost();

if ( nitropack_is_wp_cli() ) {
	$nitropack_cli = new \NitroPack\WordPress\CLI();
	$nitropack_cli->init();
}

if ( \NitroPack\Integration\Plugin\Ezoic::isActive() ) {
	if ( ! nitropack_is_optimizer_request() ) {
		// We need to serve the cached content after Ezoic's output buffering has started at plugins_loaded,0
		add_action( 'plugins_loaded', function () {
			add_filter( 'home_url', [ '\NitroPack\Integration\Plugin\Ezoic', 'getHomeUrl' ] );
			nitropack_handle_request( "plugin-ezoic" );
			remove_filter( 'home_url', [ '\NitroPack\Integration\Plugin\Ezoic', 'getHomeUrl' ] );
		}, 1 );
	} else {
		add_action( 'plugins_loaded', [ '\NitroPack\Integration\Plugin\Ezoic', 'disable' ], 1 );
	}
} else {
	nitropack_handle_request( "plugin" );
}

add_filter( 'nitro_script_output', function ( $script ) {
	$isPrefetch = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] )
		&& $_SERVER['HTTP_SEC_FETCH_DEST'] === 'empty'
		&& (
			( isset( $_SERVER['HTTP_SEC_PURPOSE'] ) && $_SERVER['HTTP_SEC_PURPOSE'] === 'prefetch' )
			||
			( isset( $_SERVER['HTTP_PURPOSE'] ) && $_SERVER['HTTP_PURPOSE'] === 'prefetch' )
		);

	$canPrintScripts = ! nitropack_is_amp_page() // Make sure we don't accidentally print a non-amp compatible script to an amp page
		&& ( ! isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) || $_SERVER['HTTP_SEC_FETCH_DEST'] === 'document' || $isPrefetch )
		&& ( ! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) !== 'xmlhttprequest' );

	if ( $canPrintScripts ) {
		return $script;
	} else {
		return "";
	}
} );

add_action( 'pre_post_update', 'nitropack_log_post_pre_update', 10, 3 );
add_filter( 'woocommerce_rest_pre_insert_product_object', 'nitropack_log_product_pre_api_update', 10, 3 );
add_action( 'transition_post_status', 'nitropack_handle_post_transition', 10, 3 );
add_action( 'transition_comment_status', 'nitropack_handle_comment_transition', 10, 3 );
add_action( 'comment_post', 'nitropack_handle_comment_post', 10, 2 );
add_action( 'switch_theme', 'nitropack_theme_handler' );
//add invalidations
\NitroPack\WordPress\Invalidations::getInstance();

register_shutdown_function( 'nitropack_execute_purges' );
register_shutdown_function( 'nitropack_execute_invalidations' );
register_shutdown_function( 'nitropack_execute_warmups' );



if ( nitropack_has_advanced_cache() ) {
	// Handle automated updates
	if ( ! defined( "NITROPACK_ADVANCED_CACHE_VERSION" ) || NITROPACK_VERSION != NITROPACK_ADVANCED_CACHE_VERSION ) {
		add_action( 'plugins_loaded', 'nitropack_install_advanced_cache' );
	}
}

add_action( 'wp_footer', 'nitropack_print_heartbeat_script' );
add_action( 'admin_footer', 'nitropack_print_heartbeat_script' );
add_action( 'get_footer', 'nitropack_print_heartbeat_script' );

add_action( 'wp_footer', 'nitropack_print_cookie_handler_script' );
add_action( 'admin_footer', 'nitropack_print_cookie_handler_script' );
add_action( 'admin_footer', function () {
	nitropack_setcookie( "nitroCachedPage", 0, time() - 86400 );
} ); // Clear the nitroCachePage cookie
add_action( 'get_footer', 'nitropack_print_cookie_handler_script' );

\NitroPack\WordPress\Admin::getInstance();

if ( is_admin() ) {
	add_action( 'wp_ajax_nitropack_verify_connect', 'nitropack_verify_connect_ajax' );
	add_action( 'wp_ajax_nitropack_disconnect', 'nitropack_disconnect' );

	add_action( 'wp_ajax_nitropack_dismiss_hosting_notice', 'nitropack_dismiss_hosting_notice' );
	add_action( 'wp_ajax_nitropack_reconfigure_webhooks', 'nitropack_reconfigure_webhooks' );

	add_action( 'activated_plugin', 'nitropack_upgrade_handler' );
	add_action( 'deactivated_plugin', 'nitropack_upgrade_handler' );
	add_action( 'upgrader_process_complete', 'nitropack_upgrade_handler' );
	add_action( 'update_option_nitropack-enableCompression', 'nitropack_handle_compression_toggle', 10, 2 );

} else {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		$GLOBALS["NitroPack.instance"] = $nitro;
		if ( get_option( 'nitropack-enableCompression' ) == 1 ) {
			$nitro->enableCompression();
		}
		add_action( 'wp', 'nitropack_init' );
	}
}
/**
 * This function is called when the plugin is activated/deactivated. Works for wp-cli as well.
 */
register_activation_hook( __FILE__, 'nitropack_activate' );
register_deactivation_hook( __FILE__, 'nitropack_deactivate' );

add_action( 'init', function () {
	if ( current_user_can( 'manage_options' ) ) {

		\NitroPack\PluginStateHandler::init();

		add_action( 'in_admin_header', function () {
			$screen = get_current_screen();
			if ( $screen->id === 'toplevel_page_nitropack' ) {
				remove_all_actions( 'user_admin_notices' );
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
			}
		}, 10 );
	}
	( new \NitroPack\WordPress\Cron() )->schedule_events();
} );

/**
 * Load text domain for translations
 * http://stackoverflow.com/questions/79198701/notice-function-load-textdomain-just-in-time-was-called-incorrectly - for WP 6.7
 * @return void
 */
function nitropack_load_textdomain() {
	global $l10n;

	$domain = 'nitropack';

	if ( isset( $l10n[ $domain ] ) ) {
		return;
	}

	load_plugin_textdomain( $domain, false, basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action( 'init', 'nitropack_load_textdomain' );
