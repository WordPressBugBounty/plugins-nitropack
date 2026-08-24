<?php

namespace NitroPack\WordPress;
use NitroPack\Util\Utils;
/**
 * Handle NitroPack webhooks
 */
class Webhooks {
	/**
	 * @var string The name of the option used to store the webhook token.
	 */
	public $option_name;

	public function __construct() {
		$this->option_name = 'nitropack-webhookToken';
		add_action( 'wp_ajax_nitropack_reconfigure_webhooks', [ $this, 'nitropack_reconfigure_webhooks' ] );
	}

	/**
	 * Generate the webhook token with a CSPRNG.
	 */
	public function generate_webhook_token() {
		if ( defined( "NITROPACK_WEBHOOK_TOKEN" ) && ! empty( NITROPACK_WEBHOOK_TOKEN ) ) {
			return NITROPACK_WEBHOOK_TOKEN;
		}

		if ( function_exists( "wp_generate_password" ) ) {
			$generatedToken = wp_generate_password( 32, false );
			if ( ! empty( $generatedToken ) && $this->validate_webhook_token( $generatedToken ) ) {
				return $generatedToken;
			}
		}

		if ( function_exists( "random_bytes" ) ) {
			return bin2hex( random_bytes( 16 ) );
		}

		return "";
	}

	/**
	 * Handles the NitroPack webhook request.
	 * 3 types of webhooks are handled:
	 * 1. config - Fetches the latest configuration from NitroPack and resets the SDK instances.
	 * 2. cache_ready - Downloads the new cache file for the specified URLs and purges the proxy cache.
	 * 3. cache_clear - Clears the cache for the specified URLs or the entire cache, depending on the request parameters.
	 *
	 * @return void
	 */
	public function handle_webhook() {
		if ( defined( 'NITROPACK_DEBUG_MODE' ) ) {
			do_action( 'nitropack_debug_webhook', $_REQUEST );
		}
		if ( ! defined( "NITROPACK_WEBHOOK_HANDLED" ) ) {
			define( "NITROPACK_WEBHOOK_HANDLED", 1 );
		} else {
			return;
		}

		$siteConfig = nitropack_get_site_config();
		$incomingToken = isset( $_GET["token"] ) ? (string) $_GET["token"] : "";
		$storedToken = ( $siteConfig && ! empty( $siteConfig["webhookToken"] ) ) ? (string) $siteConfig["webhookToken"] : "";
		$isValidWebhookToken = $storedToken !== "" && ( function_exists( "hash_equals" ) ? hash_equals( $storedToken, $incomingToken ) : $storedToken === $incomingToken );

		if ( $siteConfig && $isValidWebhookToken ) {
			switch ( $_GET["nitroWebhook"] ) {
				case "config":
					nitropack_fetch_config();
					get_nitropack()->resetSdkInstances(); // This is needed in order to obtain a new SDK instance with the fresh config
					\NitroPack\WordPress\CoreFiles::set_htaccess_rules( true );
					if ( null !== $nitro = get_nitropack_sdk() ) {
						$nitro->purgeProxyCache();
					}
					do_action( 'nitropack_integration_purge_all' );
					break;
				case "cache_ready":
					if ( isset( $_POST['url'] ) ) {
						$urls = array( $_POST['url'] );
					} elseif ( isset( $_POST['urls'] ) ) {
						$urls = $_POST['urls'];
					} else {
						$urls = array();
					}
					if ( ! empty( $urls ) ) {
						$readyUrls = [];
						foreach ( $urls as $url ) {
							$readyUrl = Utils::sanitize_url( $url );
							if ( $readyUrl ) {
								$readyUrls[] = $readyUrl;
							}
						}

						if ( $readyUrls && null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"], $readyUrls[0] ) ) {
							$hasCache = $nitro->hasRemoteCacheMulti( $readyUrls, "default", false ); // Download the new cache file
							foreach ( $readyUrls as $readyUrl ) {
								$nitro->purgeProxyCache( $readyUrl );
								do_action( 'nitropack_integration_purge_url', $readyUrl );
							}
						}
					}
					break;
				case "cache_clear":
					if ( isset( $_POST['url'] ) ) {
						$urls = array( $_POST['url'] );
					} elseif ( isset( $_POST['urls'] ) ) {
						$urls = $_POST['urls'];
					}

					$proxyPurgeOnly = ! empty( $_POST["proxyPurgeOnly"] );
					$doAction = ! empty( $_POST['useInvalidate'] )
						? static function ( $url = null ) {
							nitropack_sdk_invalidate_local( $url );
						}
						: static function ( $url = null ) {
							nitropack_sdk_purge_local( $url );
						};

					if ( ! empty( $_POST["url"] ) ) {
						$urls = is_array( $_POST["url"] ) ? $_POST["url"] : array( $_POST["url"] );
						foreach ( $urls as $url ) {
							$sanitizedUrl = Utils::sanitize_url( $url );
							if ( $proxyPurgeOnly ) {
								if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
									$nitro->purgeProxyCache( $sanitizedUrl );
								}
								do_action( 'nitropack_integration_purge_url', $sanitizedUrl );
							} else {
								$doAction( $sanitizedUrl );
							}
						}
					} else {
						if ( $proxyPurgeOnly ) {
							if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
								$nitro->purgeProxyCache();
							}
							do_action( 'nitropack_integration_purge_all' );
						} else {
							$doAction();
							nitropack_sdk_delete_backlog();
						}
					}
					break;
			}
		}
		\NitroPack\ModuleHandler::onCriticalInit( function () {
			nitropack_json_and_exit( array(
				"type" => "success",
			) );
		} );
	}

	/**
	 * AJAX handler to reconfigure the NitroPack webhooks by generating a new token and setting up the webhooks in the NitroPack SDK.
	 * Used on a notification called "Reconfigure Webhooks" and the user confirms the action.
	 * @return void
	 */
	public function nitropack_reconfigure_webhooks() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$siteConfig = nitropack_get_site_config();

		if ( $siteConfig && ! empty( $siteConfig["siteId"] ) ) {
			if ( null !== $nitro = get_nitropack_sdk() ) {
				$token = $this->generate_webhook_token();
				try {
					$this->nitropack_setup_webhooks( $nitro, $token );
					update_option( $this->option_name, $token );
					nitropack_json_and_exit( array( "status" => "success", 'message' => __( 'Connection reconfigured successfully', 'nitropack' ) ) );
				} catch (\NitroPack\SDK\WebhookException $e) {
					NitroPack\WordPress\NitroPack::getInstance()->getLogger()->error( 'Webhook Error: ' . $e );
					nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Webhook Error: ', 'nitropack' ) . $e->getMessage() ) );
				}
			} else {
				NitroPack\WordPress\NitroPack::getInstance()->getLogger()->error( 'Unable to get SDK instance' );
				nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Unable to get SDK instance', 'nitropack' ) ) );
			}
		} else {
			NitroPack\WordPress\NitroPack::getInstance()->getLogger()->error( 'Incomplete site config. Please reinstall the plugin' );
			nitropack_json_and_exit( array( "status" => "error", "message" => __( 'Incomplete site config. Please reinstall the plugin!', 'nitropack' ) ) );
		}
	}

	/**
	 * Set up all NitroPack webhooks by registering them in the NitroPack SDK.
	 * @param object $nitro
	 * @param string | null $token
	 * @throws \NitroPack\SDK\WebhookException
	 * @return void
	 */
	public function nitropack_setup_webhooks( $nitro, $token = null ) {
		if ( ! $nitro || ! $token ) {
			throw new \NitroPack\SDK\WebhookException( 'Webhook token cannot be empty.' );
		}

		$homeUrl = strtolower( get_home_url() );
		$configUrl = new \NitroPack\Url\Url( $homeUrl . "?nitroWebhook=config&token=$token" );
		$cacheClearUrl = new \NitroPack\Url\Url( $homeUrl . "?nitroWebhook=cache_clear&token=$token" );
		$cacheReadyUrl = new \NitroPack\Url\Url( $homeUrl . "?nitroWebhook=cache_ready&token=$token" );

		$nitro->getApi()->setWebhook( "config", $configUrl );
		$nitro->getApi()->setWebhook( "cache_clear", $cacheClearUrl );
		$nitro->getApi()->setWebhook( "cache_ready", $cacheReadyUrl );
	}

	/**
	 * Reset all NitroPack webhooks by unsetting them in the NitroPack SDK.
	 * @param object $nitroSDK
	 * @return void
	 */
	public function reset_webhooks( $nitroSDK ) {
		$nitroSDK->getApi()->unsetWebhook( "config" );
		$nitroSDK->getApi()->unsetWebhook( "cache_clear" );
		$nitroSDK->getApi()->unsetWebhook( "cache_ready" );

		delete_option( $this->option_name );
	}
	/**
	 * Check if the current request is a valid NitroPack webhook.
	 * @return bool
	 */
	public function is_valid_webhook() {
		return ! empty( $_GET["nitroWebhook"] ) && ! empty( $_GET["token"] ) && $this->validate_webhook_token( $_GET["token"] );
	}

	/**
	 * Validate a NitroPack webhook token.
	 * @param string $token
	 * @return bool
	 */
	public function validate_webhook_token( $token ) {
		return preg_match( "/^([a-zA-Z0-9]{32})$/", $token );
	}
	public function get_token() {
		return get_option( $this->option_name );
	}

}