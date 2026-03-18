<?php
/**
 * WP Engine NitroPack.
 *
 * @owner wpengine/vortechs
 */

namespace NitroPack;

use WP_CLI;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * NitroPack class adds WP Engine specific content and navigation to NitroPack plugin.
 */
class PageSpeedBoost {

	/**
	 * Pair of public and private keys
	 *
	 *
	 * @var null|object
	 */
	protected $keys;
	/**
	 * Registers hooks.
	 */
	public function __construct() {

		global $pagenow;
		
		$is_wp_cli = nitropack_is_wp_cli();

		if ( ! is_admin() && ! $is_wp_cli ) {
			return;
		}

		// These filters are triggered only when the dependent plugin is in OneClick mode.
		add_filter( 'nitropack_oneclick_connect_url', array( $this, 'get_connect_url' ), 10, 1 );
		add_filter( 'nitropack_oneclick_vendor_widget', array( $this, 'get_vendor_widget' ), 10, 1 );
		add_filter( 'nitropack_oneclick_action_links', array( $this, 'get_action_links' ), 10, 1 );
		add_filter( 'nitropack_oneclick_safemode_message', array( $this, 'get_safe_mode_warning' ), 10, 1 );

		// Make sure to run these filters when the dependent plugin is in OneClick mode.
		if ( $this->is_one_click() ) {
			add_filter( 'plugin_row_meta', array( $this, 'add_plugin_metadata' ), 10, 4 );
			add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 4 );
			add_filter( 'network_admin_plugin_action_links', array( $this, 'plugin_action_links' ), 10, 4 );
		}

		if ( $is_wp_cli ) {
			WP_CLI::add_command( 'psb connect', array( $this, 'command_psb_connect' ) );
		}
	}

	/**
	 * Gets the product landing page URL.
	 *
	 * @return string Returns the product landing page URL.
	 */
	protected function get_product_landing_page_url() {
		return 'https://wpengine.com/page-speed-boost';
	}

	/**
	 * Gets the product page URL.
	 *
	 * @return string Returns the product page URL.
	 */
	protected function get_product_page_url() {
		return 'https://my.wpengine.com/products/nitropack';
	}

	/**
	 * Gets the URL to complete product enablement.
	 *
	 * @param string $url The URL to filter.
	 *
	 * @return string Returns the URL.
	 */
	public function get_connect_url( $url = '' ) {
		return $this->get_product_page_url() . ( defined( 'PWP_NAME' ) ? '/enable?selected=' . PWP_NAME : '/add' );
	}

	/**
	 * Gets WP Engine specific content for NitroPack settings page.
	 *
	 * @param string $html HTML to filter.
	 *
	 * @return string Returns HTML.
	 */
	public function get_vendor_widget( $html = '' ) {
		return '<h5 class="card-title">'
			. __( 'What is NitroPack?', 'wpengine' )
			. '</h5>'
			. '<div class="row mt-4 mx-0" style="line-height:24px">'
			. __( 'NitroPack caching solution is preconfigured with essential features for immediate use. Activate it effortlessly and enjoy an instant boost in page speed.', 'wpengine' )
			. '</div>'
			. '<div class="row mt-4 mx-0">'
			. '<a href="' . $this->get_product_landing_page_url() . '" target="_blank">' . __( 'Learn more' ) . '</a>'
			. '</div>'
			. '<div class="row mt-4 mx-0">'
			. '<a href="' . $this->get_product_page_url() . '" target="_blank" class="btn btn-light btn-outline-primary">' . __( 'Manage' ) . '</a>'
			. '</div>';
	}

	/**
	 * Gets the message to complete product enablement.
	 *
	 * @param string $html HTML message to filter.
	 *
	 * @return string Returns HTML message.
	 */
	public function get_safe_mode_warning( $html = '' ) {
		return '<strong>Important:</strong> Product enablement is not finished. Currently, your website does not serve optimizations to regular users. Complete product enablement in the '
			. '<a href="' . $this->get_connect_url() . '" target="_blank">User Portal</a>.';
	}

	/**
	 * Gets action links displayed on the plugins list.
	 *
	 * @param array $links An array of HTML links.
	 *
	 * @return array Returns the array of HTML links.
	 */
	public function get_action_links( $links = array() ) {
		return array(
			'<a href="https://wpengine.com/support/page-speed-boost" target="_blank" rel="noopener noreferrer">' . __( 'Support Center', 'wpengine' ) . '</a>',
		);
	}

	/**
	 * Filters the action links displayed for each plugin in the Plugins list table.
	 *
	 * @param array  $actions     An array of plugin action links.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $context     The plugin context. By default this can include 'all', 'active', 'inactive', 'recently_activated', 'upgrade', 'mustuse', 'dropins', and 'search'.
	 *
	 * @return array Returns an array of plugin action links.
	 */
	public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context = 'all' ) {
		if ( strpos( $plugin_file, 'nitropack/' ) !== 0 ) {
			return $actions;
		}
		;

		if ( isset( $actions['edit'] ) ) {
			unset( $actions['edit'] );
		}

		if ( isset( $actions['delete'] ) ) {
			unset( $actions['delete'] );
		}

		return $actions;
	}

	/**
	 * Adds the link to the product landing page.
	 * Filters the array of row meta for each plugin in the Plugins list table.
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $status      Status filter currently applied to the plugin list. Possible values are: 'all', 'active', 'inactive', 'recently_activated', 'upgrade', 'mustuse', 'dropins', 'search', 'paused', 'auto-update-enabled', 'auto-update-disabled'.
	 *
	 * @return array Returns an array of the plugin's metadata.
	 */
	public function add_plugin_metadata( $plugin_meta, $plugin_file, $plugin_data, $status = 'all' ) {
		if ( strpos( $plugin_file, 'nitropack/' ) === 0 ) {
			array_splice( $plugin_meta, 2, 0, '<a href="' . $this->get_product_landing_page_url() . '" target="_blank">WP Engine</a>' );
		}

		return $plugin_meta;
	}

	/**
	 * Checks if the plugin distribution is OneClick.
	 *
	 * @return bool
	 */
	protected function is_one_click() {
		if ( function_exists( 'get_nitropack' ) ) {
			if ( method_exists( get_nitropack(), 'getDistribution' ) ) {
				return 'oneclick' === get_nitropack()->getDistribution();
			}
		}

		return 'oneclick' === get_option( 'nitropack-distribution' );
	}

	/**
	 * Get vendor API.
	 *
	 * @return \NitroPack\SDK\Api API.
	 */
	protected function get_vendor_api() {
		return get_nitropack()->getSdk()->getApi();
	}

	/**
	 * Get site configuration.
	 *
	 * @return null|array Returns site configuration on success or exits with an error.
	 */
	protected function get_site_config() {
		if ( ! function_exists( 'get_nitropack' ) ) {
			WP_CLI::error( 'The dependent plugin is missing or is inactive!' );
			return;
		}

		$site_config = get_nitropack()->getSiteConfig();
		if ( empty( $site_config['siteId'] ) || empty( $site_config['siteSecret'] ) ) {
			WP_CLI::error( 'The Site ID or Site Secret is missing!' );
			return;
		}

		return $site_config;
	}

	/**
	 * Generate and get public and private keys.
	 *
	 * @return object
	 */
	protected function keys_instance() {
		// This must be executed only once per request.
		if ( empty( $this->keys ) ) {
			$this->keys = \NitroPack\SDK\Crypto::generateKeyPair();
		}

		return $this->keys;
	}

	/**
	 * Get vendor plugin file relative to plugins directory.
	 *
	 * @return string
	 */
	protected function get_vendor_plugin_file() {
		$plugins = get_plugins();
		foreach ( $plugins as $plugin_file => $plugin ) {
			if ( strpos( $plugin_file, 'nitropack/' ) === 0 ) {
				return $plugin_file;
			}
		}

		return '';
	}

	/**
	 * PSB connect. Do not run it manually.
	 *
	 * ## OPTIONS
	 *
	 * <site_ID>
	 * : The site ID.
	 *
	 * <site_secret>
	 * : The site secret.
	 *
	 * @when before_wp_load
	 * @param array $psb_command_args       Command arguments.
	 * @param array $psb_command_assoc_args Command parameters.
	 */
	public function command_psb_connect( $psb_command_args, $psb_command_assoc_args ) {
		if ( ! function_exists( 'nitropack_verify_connect' ) ) {
			$psb_vendor_plugin_file = $this->get_vendor_plugin_file();
			include_once WP_PLUGIN_DIR . '/' . $psb_vendor_plugin_file;
			if ( ! function_exists( 'nitropack_verify_connect' ) ) {
				WP_CLI::error( 'The dependent plugin in incompatible!' );
				return;
			}
		}

		nitropack_verify_connect(
			empty( $psb_command_args[0] ) ? '' : $psb_command_args[0],
			empty( $psb_command_args[1] ) ? '' : $psb_command_args[1]
		);
	}
}