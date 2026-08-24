<?php

namespace NitroPack\WordPress;
use NitroPack\WordPress\AdvancedCache\AdvancedCache;
use NitroPack\WordPress\Webhooks;
/**
 * Here we handle the connection and disconnection of the plugin to NitroPack.
 */
class Connect {
	private $nitropack;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_connect', [ $this, 'nitropack_connect' ] );
		add_action( 'wp_ajax_nitropack_disconnect', [ $this, 'nitropack_disconnect' ] );
	}

	/**
	 * AJAX request
	 * @return void
	 */
	public function nitropack_connect() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$siteId = ! empty( $_POST["siteId"] ) ? $_POST["siteId"] : "";
		$siteSecret = ! empty( $_POST["siteSecret"] ) ? $_POST["siteSecret"] : "";
		$this->nitropack_verify_connect( $siteId, $siteSecret );
	}

	/**
	 * The core logic for connecting NitroPack (when clicking on Connect button and connecting through the WP-CLI).
	 * Make few validations and then proceed to connect to NitroPack API and setup webhooks, variation cookies (currencies/languages), nitropack config, advanced-cache.php, connect it and start optimizing the front page.
	 * @param string $siteId
	 * @param string $siteSecret
	 * @return void
	 */
	public function nitropack_verify_connect( string $siteId, string $siteSecret ) {
		$blogId = get_current_blog_id();
		$multisite_reason = ' ';
		if ( $blogId ) {
			$multisite_reason .= is_main_site() ? "in main site" : "in multisite: $blogId";
		}
		$this->nitropack = NitroPack::getInstance();

		$this->nitropack->getLogger()->notice( 'Verifying connection to NitroPack API' . $multisite_reason );

		$this->validate_host_requirements();
		$siteId = $this->validate_and_sanitize_credentials( $siteId, $siteSecret, $multisite_reason );

		try {
			$nitro = get_nitropack_sdk( $siteId, $siteSecret, null, true );
			if ( $nitro === null ) {
				$this->nitropack->getLogger()->error( 'Error verifying connection to NitroPack' . $multisite_reason );
				nitropack_json_and_exit( array( "status" => "error" ) );
			}

			$this->verify_health_status( $nitro );
			$this->verify_no_duplicate_connection( $nitro );
			$this->setup_connection( $nitro, $siteId, $siteSecret, $blogId );

			$this->nitropack->getLogger()->notice( 'NitroPack connected' . $multisite_reason );

			$onboarding = get_option( 'nitropack-onboardingPassed' );
			$url = $onboarding === '1' ? get_admin_url( $blogId, "admin.php?page=nitropack" ) : get_admin_url( $blogId, "admin.php?page=nitropack&onboarding=1" );
			nitropack_json_and_exit( array(
				"status" => "success",
				"url" => $url,
				"message" => __( "Connected", "nitropack" )
			) );
		} catch (\NitroPack\SDK\WebhookException $e) {
			$this->nitropack->getLogger()->error( $e );
			nitropack_json_and_exit( array( "status" => "error", "message" => $e ) );
		} catch (\NitroPack\SDK\StorageException $e) {
			$this->nitropack->getLogger()->error( 'Permission Error: ' . $e );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Permission Error: ', 'nitropack' ) . $e ) );
		} catch (\NitroPack\SDK\EmptyConfigException $e) {
			$this->nitropack->getLogger()->error( 'Error while fetching remote config: ' . $e );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Error while fetching remote config: ', 'nitropack' ) . $e ) );
		} catch (\NitroPack\SocketOpenException $e) {
			$this->nitropack->getLogger()->error( 'Can\'t establish connection with NitroPack\'s servers. ' . $e );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Can\'t establish connection with NitroPack\'s servers', 'nitropack' ) ) );
		} catch (\Exception $e) {
			$this->nitropack->getLogger()->error( 'Incorrect API credentials. Please make sure that you copied them correctly and try again. ' . $e );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Incorrect API credentials. Please make sure that you copied them correctly and try again.', 'nitropack' ) ) );
		}

		$this->nitropack->getLogger()->error( 'Error verifying connection to NitroPack' . $multisite_reason );
		nitropack_json_and_exit( array( "status" => "error" ) );
	}

	private function validate_host_requirements() {
		if ( ! self::check_func_availability( 'stream_socket_client' ) ) {
			$this->nitropack->getLogger()->error( 'stream_socket_client function is not allowed by your host.' );
			nitropack_json_and_exit( array( "status" => "error", "message" => "stream_socket_client function is not allowed by your host. <a href=\"https://support.nitropack.io/hc/en-us/articles/360020898137\" target=\"_blank\" rel=\"noreferrer noopener\">Read more</a>" ) );
		}

		if ( ! self::check_func_availability( 'stream_context_create' ) ) {
			$this->nitropack->getLogger()->error( 'stream_context_create function is not allowed by your host.' );
			nitropack_json_and_exit( array( "status" => "error", "message" => "stream_context_create function is not allowed by your host." ) );
		}
	}

	private function validate_and_sanitize_credentials( string $siteId, string $siteSecret, string $multisite_reason ) {
		if ( empty( $siteId ) || empty( $siteSecret ) ) {
			$this->nitropack->getLogger()->error( 'Invalid API key or API secret key value' );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Invalid API key or API secret key value', 'nitropack' ) ) );
		}

		$siteId = trim( esc_attr( $siteId ) );
		$siteSecret = trim( esc_attr( $siteSecret ) );

		if ( ! $this->validate_site_id( $siteId ) || ! $this->validate_site_secret( $siteSecret ) ) {
			$this->nitropack->getLogger()->error( 'Invalid API key or API secret key value' . $multisite_reason );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Invalid API key or API secret key value', 'nitropack' ) ) );
		}

		return $siteId;
	}

	private function verify_health_status( $nitro ) {
		if ( ! $nitro->checkHealthStatus() ) {
			$this->nitropack->getLogger()->error( 'Error when trying to communicate with NitroPack\'s servers. Current health status: ' . $nitro->getHealthStatus() );
			nitropack_json_and_exit( array(
				"status" => "error",
				"message" => __( 'Error when trying to communicate with NitroPack\'s servers. Please try again in a few minutes. If the issue persists, please', 'nitropack' ) . " <a href='https://support." . NITROPACK_HOST . "/hc/en-us' target='_blank'>contact us</a>."
			) );
		}
	}

	private function verify_no_duplicate_connection( $nitro ) {
		$preventParing = apply_filters( 'nitropack_prevent_connect', $this->prevent_connecting( $nitro ) );
		if ( $preventParing ) {
			$this->nitropack->getLogger()->error( 'It looks like another site ' . $preventParing['remote'] . ' is already connected using these credentials. Either disconnect it or register a new site in your NitroPack dashboard.' );
			nitropack_json_and_exit( array(
				"status" => "error",
				"message" => "It looks like another site <strong>({$preventParing['remote']})</strong> is already connected using these credentials. Either disconnect it or register a new site in your NitroPack dashboard.<br/>
                    <a href='https://support.nitropack.io/hc/en-us/articles/4405254569745' target='_blank' rel='noreferrer noopener'>Read more</a>"
			) );
		}
	}

	private function setup_connection( $nitro, string $siteId, string $siteSecret, $blogId ) {
		$webhooks = new Webhooks();
		$token = $webhooks->generate_webhook_token();
		get_nitropack()->settings->set_required_settings( $token );
		$webhooks->nitropack_setup_webhooks( $nitro, $token );

		// _icl_current_language is WPML cookie, it is added here for compatibility with this module
		$customVariationCookies = array( "np_wc_currency", "np_wc_currency_language", "_icl_current_language" );
		$variationCookies = $nitro->getApi()->getVariationCookies();
		foreach ( $variationCookies as $cookie ) {
			$index = array_search( $cookie["name"], $customVariationCookies );
			if ( $index !== false ) {
				array_splice( $customVariationCookies, $index, 1 );
			}
		}

		foreach ( $customVariationCookies as $cookieName ) {
			$nitro->getApi()->setVariationCookie( $cookieName );
		}

		$nitro->fetchConfig();
		get_nitropack()->updateCurrentBlogConfig( $siteId, $siteSecret, $blogId );

		$advanced_cache = new AdvancedCache();
		$advanced_cache->install_advanced_cache();

		try {
			do_action( 'nitropack_integration_purge_all' );
		} catch (\Exception $e) {
			// Exception while signaling our 3rd party integration addons to purge their cache
		}

		nitropack_event( "connect", $nitro );
		nitropack_event( "enable_extension", $nitro );

		$siteConfig = nitropack_get_site_config();
		if ( $siteConfig ) {
			$nitro->getApi()->runWarmup( [ $siteConfig['home_url'] ], true );
		}
	}

	/**
	 * Validation of the site id
	 * @param string $siteId
	 * @return bool|int
	 */
	private function validate_site_id( $siteId ) {
		return preg_match( "/^([a-zA-Z]{32})$/", trim( $siteId ) );
	}
	/**
	 * Validation of the site secret
	 * @param string $siteSecret
	 * @return bool|int
	 */
	private function validate_site_secret( $siteSecret ) {
		return preg_match( "/^([a-zA-Z0-9]{64})$/", trim( $siteSecret ) );
	}

	/**
	 * Checks if another site is already connected.
	 * @param \NitroPack\SDK\NitroPack $nitroSDK
	 * @return array{local: string, remote: string|bool}|bool
	 */
	private function prevent_connecting( $nitroSDK ) {
		$remoteUrl = $nitroSDK->getApi()->getWebhook( "config" );
		if ( empty( $remoteUrl ) ) {
			return false;
		}
		$siteConfig = nitropack_get_site_config();
		$localUrl = new \NitroPack\Url\Url( $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url() );
		$localHome = strtolower( $localUrl->getHost() . $localUrl->getPath() );
		$storedUrl = new \NitroPack\Url\Url( $remoteUrl );
		$remoteHome = strtolower( $storedUrl->getHost() . $storedUrl->getPath() );
		if ( $localHome === $remoteHome ) {
			return false;
		}
		return array( 'local' => $localHome, 'remote' => $remoteHome );
	}
	public static function check_func_availability( $func_name ) {
		if ( function_exists( 'ini_get' ) ) {
			$existsResult = stripos( ini_get( 'disable_functions' ), $func_name ) === false;
		} else {
			$existsResult = function_exists( $func_name );
		}
		return $existsResult;
	}

	/**
	 * Core logic when disconnecting NitroPack from the admin dashboard.
	 * Delete all extra files - advanced-cache.php & wp-content/config-[hash]-nitropack/config.php
	 * @return void
	 */
	public function nitropack_disconnect() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$blogId = get_current_blog_id();
		$multisite_reason = ' ';
		if ( $blogId ) {
			$multisite_reason .= is_main_site() ? "in main site" : "in multisite: $blogId";
		}
		$this->nitropack = NitroPack::getInstance();
		$this->nitropack->getLogger()->notice( 'NitroPack disconnecting... ' . $multisite_reason );

		//Uninstall advanced cache
		$advanced_cache = new AdvancedCache();
		$advanced_cache->uninstall_advanced_cache();

		try {
			//send the reason for disconnection to the NitroPack API
			$nitro = get_nitropack_sdk();
			nitropack_event( "disconnect", $nitro, ActivateDeactivate::get_disconnect_deactivation_metadata() );
			if ( null !== $nitro = get_nitropack_sdk() ) {
				$webhooks = new Webhooks();
				$webhooks->reset_webhooks( $nitro );
			}
		} catch (\Exception $e) {
			$this->nitropack->getLogger()->error( 'NitroPack cannot be disconnected. Error: ' . $e );
			nitropack_json_and_exit( array( "status" => "error", "message" => $e ) );
		}

		get_nitropack()->unsetCurrentBlogConfig();

		$hostingNoticeFile = \NitroPack\WordPress\Notifications\Notifications::getInstance()->get_hosting_notice_file();
		if ( file_exists( $hostingNoticeFile ) ) {
			if ( WP_DEBUG ) {
				unlink( $hostingNoticeFile );
			} else {
				@unlink( $hostingNoticeFile );
			}
		}
		$this->nitropack->getLogger()->notice( 'NitroPack disconnected.' . $multisite_reason );
		nitropack_json_and_exit( array( "status" => "success", "message" => __( "Disconnected", "nitropack" ) ) );
	}

}