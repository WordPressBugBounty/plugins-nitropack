<?php

namespace NitroPack\WordPress;
use NitroPack\SDK\Filesystem;
/**
 * Used when plugin is activated or deactivated.
 * Sets the WP_CACHE constant, create the .htaccess files and install the advanced-cache.php file.
 * @package NitroPack\WordPress
 */
class ActivateDeactivate {
	/**
	 * Instance of the ActivateDeactivate class
	 * @var ActivateDeactivate $instance
	 */
	private static $instance = null;
	public function __construct() {
		register_activation_hook( NITROPACK_MAIN_FILE, [ $this, 'nitropack_activate' ] );
		register_deactivation_hook( NITROPACK_MAIN_FILE, [ $this, 'nitropack_deactivate' ] );
		add_action( 'admin_init', [ $this, 'activation_redirect' ] );
	}
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Runs plugin activation.
	 * Sets the WP_CACHE constant, creates the .htaccess files and installs the advanced-cache.php file.
	 * @return void
	 */
	public function nitropack_activate() {
		\NitroPack\WordPress\CoreFiles::set_wp_cache_const( true );

		$htaccessFile = nitropack_trailingslashit( NITROPACK_DATA_DIR ) . ".htaccess";
		if ( ! Filesystem::fileExists( $htaccessFile ) && get_nitropack()->initDataDir() ) {
			Filesystem::filePutContents( $htaccessFile, "deny from all" );
		}

		$pluginHtaccessFile = nitropack_trailingslashit( NITROPACK_PLUGIN_DATA_DIR ) . ".htaccess";
		if ( ! Filesystem::fileExists( $pluginHtaccessFile ) && get_nitropack()->initPluginDataDir() ) {
			Filesystem::filePutContents( $pluginHtaccessFile, "deny from all" );
		}
		$advanced_cache = new \NitroPack\WordPress\AdvancedCache\AdvancedCache();
		$advanced_cache->install_advanced_cache();

		// Htaccess mods need to happen after installing the advanced cache file so the healthcheck can execute fast
		\NitroPack\WordPress\CoreFiles::set_htaccess_rules( true );

		try {
			do_action( 'nitropack_integration_purge_all' );
		} catch (\Exception $e) {
			// Exception while signaling our 3rd party integration addons to purge their cache
		}

		if ( get_nitropack()->isConnected() ) {
			nitropack_event( "enable_extension" );
			// Refresh needed to make sure we have the latest config
			get_nitropack()->settings->set_required_settings();
		} else {
			setcookie( "nitropack_after_activate_notice", 1, time() + 3600 );
		}

		if ( function_exists( "opcache_reset" ) ) {
			opcache_reset();
		}
		( new \NitroPack\WordPress\Cron() )->schedule_events();

		// Avoid redirecting when bulk activating plugins
		if (
			( isset( $_REQUEST['action'] ) && 'activate-selected' === $_REQUEST['action'] ) &&
			( isset( $_POST['checked'] ) && count( $_POST['checked'] ) > 1 ) ) {
			return;
		}
		add_option( 'nitropack-activation-redirect', wp_get_current_user()->ID );

	}
	/**
	 * Immediately redirect after single plugin activation.
	 * Doesn't work when bulk plugin activation.
	 * @return void
	 */
	public function activation_redirect() {
		if ( defined( 'DOING_AJAX' ) || defined( 'WP_CLI' ) ) {
			return;
		}
		global $pagenow;
		$allowed_pages = [ 'plugins.php', 'plugin-install.php' ];
		if ( ! in_array( $pagenow, $allowed_pages, true ) ) {
			return;
		}
		// Make sure it's the correct user
		if ( intval( get_option( 'nitropack-activation-redirect', false ) ) === wp_get_current_user()->ID ) {
			delete_option( 'nitropack-activation-redirect' );
			wp_safe_redirect( admin_url( 'admin.php?page=nitropack' ) );
			exit;
		}
	}

	/**
	 * Deactivation of the plugin.
	 * Removes all rules, constants and extra files (advanced-cache.php) and cron events.
	 * @return void
	 */
	public function nitropack_deactivate() {
		\NitroPack\WordPress\CoreFiles::set_htaccess_rules( false );
		\NitroPack\WordPress\CoreFiles::set_wp_cache_const( false );
		$advanced_cache = new \NitroPack\WordPress\AdvancedCache\AdvancedCache();
		$advanced_cache->uninstall_advanced_cache();

		try {
			do_action( 'nitropack_integration_purge_all' );
		} catch (\Exception $e) {
			// Exception while signaling our 3rd party integration addons to purge their cache
		}

		if ( get_nitropack()->isConnected() ) {
			nitropack_event( "disable_extension", null, self::get_disconnect_deactivation_metadata() );
		}

		if ( function_exists( "opcache_reset" ) ) {
			opcache_reset();
		}
		// Unscheduling events from the cron.
		\NitroPack\WordPress\Cron::unschedule_events();
	}

	/**
	 * Returns sanitized feedback metadata for deactivation and disconnect events.
	 *
	 * @param array|null $request_data Request data to use instead of the current request.
	 * @return array
	 */
	public static function get_disconnect_deactivation_metadata( $request_data = null ) {
		$request_data = is_array( $request_data ) ? $request_data : $_REQUEST;

		return array_filter( array(
			'reason' => ! empty( $request_data['reason'] ) ? sanitize_text_field( $request_data['reason'] ) : '',
			'new_plugin' => ! empty( $request_data['new_plugin'] ) ? sanitize_text_field( $request_data['new_plugin'] ) : '',
			'free_text' => ! empty( $request_data['free_text'] ) ? sanitize_textarea_field( $request_data['free_text'] ) : '',
		) );
	}
}