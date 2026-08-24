<?php
/*
Plugin Name:  NitroPack
Plugin URI:   https://nitropack.io/platform/wordpress
Description:  Automatic optimization for site speed and Core Web Vitals. Use 35+ features, including Caching, image optimization, critical CSS, and Cloudflare CDN.
Version:      1.20.0
Author:       NitroPack Inc.
Author URI:   https://nitropack.io/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  nitropack
Domain Path:  /languages
*/

use NitroPack\Util\Utils;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! defined( 'NITROPACK_MAIN_FILE' ) ) {
	define( 'NITROPACK_MAIN_FILE', __FILE__ );
}

if ( ! defined( 'NITROPACK_BASENAME' ) ) {
	define( 'NITROPACK_BASENAME', plugin_basename( __FILE__ ) );
}

$np_basePath = dirname( __FILE__ ) . '/';

require_once $np_basePath . 'functions.php';
require_once $np_basePath . 'helpers.php';

new NitroPack\PageSpeedBoost();

if ( Utils::is_wp_cli() ) {
	$nitropack_cli = new \NitroPack\WordPress\CLI();
	$nitropack_cli->init();
}

if ( \NitroPack\Integration\Plugin\Ezoic::isActive() ) {
	if ( ! Utils::is_optimizer_nitropack_request() ) {
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

	$canPrintScripts = ! Utils::is_amp_page() // Make sure we don't accidentally print a non-amp compatible script to an amp page
		&& ( ! isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) || $_SERVER['HTTP_SEC_FETCH_DEST'] === 'document' || $isPrefetch )
		&& ( ! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) !== 'xmlhttprequest' );

	if ( $canPrintScripts ) {
		return $script;
	} else {
		return "";
	}
} );

\NitroPack\WordPress\Invalidations::getInstance();
\NitroPack\WordPress\Scripts::getInstance();
\NitroPack\WordPress\Admin::getInstance();
\NitroPack\WordPress\ActivateDeactivate::getInstance();

if ( is_admin() ) {
} else {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		$GLOBALS["NitroPack.instance"] = $nitro;
		if ( get_option( 'nitropack-enableCompression' ) == 1 ) {
			$nitro->enableCompression();
		}
		add_action( 'wp', 'nitropack_init' );
	}
}