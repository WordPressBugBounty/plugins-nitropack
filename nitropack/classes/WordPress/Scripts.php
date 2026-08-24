<?php

namespace NitroPack\WordPress;

/**
 * NitroPack scripts enqueued on admin and front
 * Heartbeat script
 * Cookie handler script
 */
class Scripts {
	/**
	 * instance of the class
	 * @var Scripts|null
	 */
	private static $instance = null;

	public function __construct() {
		add_action( 'wp_footer', [ $this, 'add_heartbeat_script' ] );
		add_action( 'admin_footer', [ $this, 'add_heartbeat_script' ] );
		add_action( 'get_footer', [ $this, 'add_heartbeat_script' ] );

		add_action( 'wp_footer', [ $this, 'add_cookie_handler_script' ] );
		add_action( 'admin_footer', [ $this, 'add_cookie_handler_script' ] );
		add_action( 'admin_footer', function () {
			nitropack_setcookie( "nitroCachedPage", "0", time() - 86400 );
		} ); // Clear the nitroCachePage cookie
		add_action( 'get_footer', [ $this, 'add_cookie_handler_script' ] );
	}
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Checks whether we need to add the heartbeat script.
	 * @return bool
	 */
	public function is_heartbeat_needed() {
		return ! \NitroPack\Util\Utils::is_optimizer_nitropack_request() &&
			! \NitroPack\Util\Utils::is_amp_page() &&
			! nitropack_is_heartbeat_running() &&
			( ! nitropack_is_heartbeat_completed() || time() - nitropack_last_heartbeat() > NITROPACK_HEARTBEAT_INTERVAL );
	}

	/**
	 * Add the heartbeat script in the footer both admin and front pages
	 * @return void
	 */
	public function add_heartbeat_script() {
		if ( $this->is_heartbeat_needed() ) {
			if ( defined( "NITROPACK_HEARTBEAT_PRINTED" ) ) {
				return;
			}
			define( "NITROPACK_HEARTBEAT_PRINTED", true );
			echo apply_filters( "nitro_script_output", $this->heartbeat_script() );
		}
	}
	/**
	 * The heartbeat script itself
	 * @return string
	 */
	public function heartbeat_script() {
		$siteConfig = nitropack_get_site_config();
		if ( $siteConfig && ! empty( $siteConfig["siteId"] ) && ! empty( $siteConfig["siteSecret"] ) ) {
			if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
				if ( is_admin() ) {
					$credentials = "same-origin";
				} else {
					$credentials = "omit";
				}

				return "<script nitro-exclude>
                        var heartbeatData = new FormData(); heartbeatData.append('nitroHeartbeat', '1');
                        fetch(location.href, {method: 'POST', body: heartbeatData, credentials: '$credentials'});
                    </script>";
			}
		}
	}
	public function add_cookie_handler_script() {
		if ( defined( "NITROPACK_COOKIE_HANDLER_PRINTED" ) ) {
			return;
		}
		define( "NITROPACK_COOKIE_HANDLER_PRINTED", true );

		echo apply_filters( "nitro_script_output", $this->get_cookie_handler_script() );
	}

	public function get_cookie_handler_script() {
		return "<script nitro-exclude>
                document.cookie = 'nitroCachedPage=' + (!window.NITROPACK_STATE ? '0' : '1') + '; path=/; SameSite=Lax';
            </script>";
	}
}