<?php

namespace NitroPack;

use \WP_CLI;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

class CLI {
	/**
	 * Pair of public and private keys
	 *
	 * @var null|object
	 */
	protected $keys;
	private $logger;
	public function init() {
		add_action( 'init', [ $this, 'register_hooks' ] );
		$this->logger = WordPress\NitroPack::getInstance()->getLogger();
	}
	public function register_hooks() {

		$is_wp_cli = nitropack_is_wp_cli();

		if ( ! is_admin() && ! $is_wp_cli && ! is_user_logged_in() )
			return;

		WP_CLI::add_command( "nitropack connect", [ $this, "nitropack_cli_connect" ] );
		WP_CLI::add_command( "nitropack disconnect", [ $this, "nitropack_cli_disconnect" ] );
		WP_CLI::add_command( "nitropack purge", [ $this, "nitropack_cli_purge" ] );
		WP_CLI::add_command( "nitropack invalidate", [ $this, "nitropack_cli_invalidate" ] );
		WP_CLI::add_command( "nitropack mode", [ $this, "nitropack_mode" ] );
		WP_CLI::add_command( 'nitropack preview', [ $this, 'nitropack_preview' ] );
		WP_CLI::add_command( "nitropack warmup", [ $this, "nitropack_warmup" ] );
		WP_CLI::add_command( 'nitropack urls', [ $this, 'nitropack_urls' ] );
		WP_CLI::add_command( 'nitropack excludedurls', [ $this, 'nitropack_excluded_urls' ] );
		WP_CLI::add_command( 'nitropack excludes', [ $this, 'nitropack_excludes' ] );
	}
	/**
	 * Get site configuration.
	 *
	 * @return null|array Returns site configuration on success or exits with an error.
	 */
	protected function get_site_config() {
		$site_config = nitropack_get_site_config();
		if ( empty( $site_config['siteId'] ) || empty( $site_config['siteSecret'] ) ) {
			$this->logger->error( 'Cannot connect. The Site ID or Site Secret is missing' );
			WP_CLI::error( 'Cannot connect. The Site ID or Site Secret is missing!' );
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
	 * Get vendor API.
	 *
	 * @return \NitroPack\SDK\Api API.
	 */
	protected function get_vendor_api() {
		$nitro = get_nitropack_sdk();
		return $nitro->getApi();
	}
	/**
	 * Connects a website to NitroPack
	 *
	 * ## OPTIONS
	 *
	 * <siteID>
	 * : The API Key obtained from https://nitropack.io/user/connect
	 *
	 * <siteSecret>
	 * : The API Secret Key obtained from https://nitropack.io/user/connect
	 * 
	 * Example: wp nitropack connect siteID siteSecret
	 */

	public function nitropack_cli_connect( $args, $assocArgs ) {
		$siteId = ! empty( $args[0] ) ? $args[0] : "";
		$siteSecret = ! empty( $args[1] ) ? $args[1] : "";
		nitropack_verify_connect( $siteId, $siteSecret );
	}

	/**
	 * Disconnects a website from NitroPack
	 * Example: wp nitropack disconnect
	 */

	public function nitropack_cli_disconnect( $args, $assocArgs ) {
		nitropack_disconnect();
	}

	/**
	 * Purges a website's cache
	 * Example: wp nitropack purge
	 */

	public function nitropack_cli_purge( $args, $assocArgs ) {
		$host = ! empty( $assocArgs["purge-host"] ) ? $assocArgs["purge-host"] : NULL;
		$url = ! empty( $assocArgs["purge-url"] ) ? $assocArgs["purge-url"] : NULL;
		$tag = ! empty( $assocArgs["purge-tag"] ) ? $assocArgs["purge-tag"] : NULL;
		$reason = ! empty( $assocArgs["purge-reason"] ) ? $assocArgs["purge-reason"] : NULL;

		if ( ! empty( $host ) ) {
			/**
			 * Override the site url by the purge-host parameter
			 *
			 * @param string $host
			 * @return string
			 */
			add_filter(
				'nitropack_current_host',
				function () use ($host) {
					if ( ! preg_match( '#^http(s)?://#', $host ) ) {
						$host = 'https://' . $host;
					}
					return $host;
				}
			);
		}

		if ( $url || $tag || $reason ) {
			try {
				if ( nitropack_sdk_purge( $url, $tag, $reason ) ) {
					$this->logger->notice( 'Cache has been purged' );
					WP_CLI::success( 'Cache has been purged.' );
				}
			} catch (\Exception $e) {
				$this->logger->error( 'Cannot purge cache. Error: ' . $e );
				WP_CLI::error( sprintf( 'Cannot purge cache. Error: %s', $e ) );
			}
		} else {
			nitropack_purge_cache();
		}
	}

	/**
	 * Invalidate a website's cache
	 * Example: wp nitropack invalidate
	 */

	public function nitropack_cli_invalidate( $args, $assocArgs ) {
		$url = ! empty( $assocArgs["purge-url"] ) ? $assocArgs["purge-url"] : NULL;
		$tag = ! empty( $assocArgs["purge-tag"] ) ? $assocArgs["purge-tag"] : NULL;
		$reason = ! empty( $assocArgs["purge-reason"] ) ? $assocArgs["purge-reason"] : NULL;
		if ( $url || $tag || $reason ) {
			try {
				if ( nitropack_sdk_invalidate( $url, $tag, $reason ) ) {
					$this->logger->notice( 'Cache has been invalidated' );
					WP_CLI::success( 'Cache has been invalidated.' );
				}
			} catch (\Exception $e) {
				$this->logger->error( 'Cannot invalidate cache. Error: ' . $e );
				WP_CLI::error( sprintf( 'Error, cannot invalidate cache. %s', $e ) );
			}
		} else {
			nitropack_invalidate_cache();
		}
	}
	/**
	 * Start of PSB commands from WPEngine below.
	 * 
	 * Set NitroPack mode.
	 * 
	 * ## OPTIONS
	 *
	 * [<mode>]
	 * : Read or change mode.
	 * 
	 * options:
	 *   - 0 - off - readable only
	 *   - 1 - standard
	 *   - 2 - medium
	 *   - 3 - strong
	 *   - 4 - ludicrous
	 *   - 5 - custom - readable only
	 * 
	 * Example: wp nitropack mode 3
	 * @when before_wp_load
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command parameters.
	 */
	private function nitropack_modes( $mode ) {
		$modes = [ 0 => 'Off', 1 => 'Standard', 2 => 'Medium', 3 => 'Strong', 4 => 'Ludicrous', 5 => 'Custom' ];
		if ( $mode !== null ) {
			return $modes[ $mode ];
		}
		return $modes;
	}
	public function nitropack_mode( $args ) {
		$mode = isset( $args[0] ) ? intval( $args[0] ) : null;
		if ( $mode !== null && ( 1 > $mode || 4 < $mode ) ) {
			$this->logger->error( 'The mode is invalid! Valid modes are from 1-4' );
			WP_CLI::error( 'The mode is invalid! Valid modes are from 1-4.' );
			return;
		}
		$change = $mode > 1 && $mode < 4;
		$site_config = $this->get_site_config();
		$keys = $this->keys_instance();
		$url = new \NitroPack\SDK\IntegrationUrl( $change ? 'quicksetup' : 'quicksetup_json', $site_config['siteId'], $site_config['siteSecret'] );
		$headers = [ 
			'X-Nitro-Public-Key' => base64_encode( $keys->publicKey ), // phpcs:ignore
		];

		if ( $change ) {
			$response = \wp_remote_post(
				$url->getUrl(),
				[ 
					'headers' => $headers,
					'body' => [ 
						'setting' => $mode,
					],
				]
			);
		} else {
			$response = \wp_remote_get( $url->getUrl(), [ 'headers' => $headers ] );
		}

		if ( is_wp_error( $response ) ) {
			/**
			 * Response error.
			 *
			 * @var WP_Error $error
			 */
			$error = $response;
			$this->logger->error( 'Optimization mode failed. Error: ' . $error->get_error_message() );
			WP_CLI::error( $error->get_error_message() );
			return;
		}

		if ( 200 !== $response['response']['code'] ) {
			$this->logger->error( 'Optimization mode failed. Response: ' . $response['response']['code'] );
			WP_CLI::debug( sprintf( 'Response body: %s.', $response['body'] ) );
			WP_CLI::error( sprintf( 'Request has failed with %d %s.', $response['response']['code'], $response['response']['message'] ) );
			return;
		}

		if ( $change ) {
			$this->logger->notice( 'Mode has been changed to ' . $this->nitropack_modes( $mode ) );
			WP_CLI::success( 'Mode has been changed to ' . $this->nitropack_modes( $mode ) );
			return;
		}

		$body = @json_decode( $response['body'], true ); // phpcs:ignore
		if ( empty( $body['optimization_level'] ) ) {
			$this->logger->error( 'Mode is missing in the response body' );
			WP_CLI::error( 'Mode is missing in the response body!' );
			return;
		}

		$this->logger->notice( 'Mode is: ' . $this->nitropack_modes( $body['optimization_level'] ) );
		WP_CLI::success( sprintf( 'Mode is: %s.', $this->nitropack_modes( $body['optimization_level'] ) ) );
	}
	/**
	 * NitroPack test mode.
	 *
	 * ## OPTIONS
	 *
	 * [<command>]
	 * : Get test mode status or change it.
	 * ---
	 * default: status
	 * options:
	 *   - status
	 *   - disable
	 *   - enable
	 * ---
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command parameters.
	 */
	public function nitropack_preview( $args, $assoc_args ) {
		$this->get_site_config();

		/**
		 * SDK.
		 *
		 * @var \NitroPack\SDK\NitroPack $sdk
		 */
		$sdk = get_nitropack()->getSdk();
		try {
			$command = empty( $args[0] ) ? 'status' : $args[0];
			if ( 'enable' === $command ) {
				$sdk->enableSafeMode();
				nitropack_cache_safemode_status( 1 );
			} elseif ( 'disable' === $command ) {
				$sdk->disableSafeMode();
				nitropack_cache_safemode_status( 0 );
			} else {
				$api = $this->get_vendor_api();
				$status = $api->isSafeModeEnabled();
				$this->logger->notice( 'Test mode is ' . ( $status ? 'enabled' : 'disabled' ) );
				WP_CLI::success( sprintf( 'Test mode is %s.', $status ? 'enabled' : 'disabled' ) );
				nitropack_cache_safemode_status( $status ? 1 : 0 );
				return;
			}
		} catch (\Exception $e) {
			$this->logger->error( 'Fail to ' . $command . ' test mode. Error: ' . $e->getMessage() );
			WP_CLI::error( $e->getMessage(), false );
			WP_CLI::error( sprintf( 'Failed to %s test mode.', $command ) );
			return;
		}
		$this->logger->notice( 'Test mode has been ' . $command . 'd' );
		WP_CLI::success( sprintf( 'Test mode has been %sd.', $command ) );
	}
	/**
	 * NitroPack cache warmup.
	 *
	 * ## OPTIONS
	 *
	 * [<command>]
	 * : Get cache warmup status or change it.
	 * ---
	 * default: status
	 * options:
	 *   - status
	 *   - disable
	 *   - enable
	 * ---
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command parameters.
	 */
	public function nitropack_warmup( $args, $assoc_args ) {
		$this->get_site_config();

		$command = empty( $args[0] ) ? 'status' : $args[0];

		/* Starts a warmup process for a website */
		if ( 'run' === $command ) {
			nitropack_run_warmup();
			return;
		}
		/* Enables AND runs a warmup process for a website */
		if ( 'enable' === $command ) {
			nitropack_enable_warmup();
			return;
		}
		/* Disables the warmup process for a website */
		if ( 'disable' === $command ) {
			nitropack_disable_warmup();
			return;
		}

		try {
			$api = $this->get_vendor_api();
			$stats = $api->getWarmupStats();
			if ( isset( $stats['status'] ) ) {
				$stats['status'] = $stats['status'] ? 'enabled' : 'disabled';
			}
			WP_CLI\Utils\format_items(
				'yaml',
				array(
					array(
						'warmup' => $stats,
					),
				),
				array(
					'warmup',
				)
			);
			$this->logger->notice( 'Get warmup status: ' . $stats['status'] );
		} catch (\Exception $e) {
			$this->logger->error( 'Failed to fetch warmup status. Error: ' . $e->getMessage() );
			WP_CLI::error( $e->getMessage(), false );
			WP_CLI::error( 'Failed to fetch warmup stats.' );
			return;
		}
	}
	/**
	 * NitroPack optimized URLs.
	 *
	 * ## OPTIONS
	 *
	 * [<command>]
	 * : Get optimized URLs or pending optimization.
	 * ---
	 * default: optimized
	 * options:
	 *   - optimized
	 *   - pending
	 * ---
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command parameters.
	 */
	public function nitropack_urls( $args, $assoc_args ) {
		$this->get_site_config();

		$command = empty( $args[0] ) ? 'optimized' : $args[0];

		try {
			$api = $this->get_vendor_api();
			$result = array();
			if ( 'optimized' === $command ) {
				$result = $api->getUrls();
				$this->logger->notice( 'Get optimized URLs' );
			} elseif ( 'pending' === $command ) {
				$result = $api->getPendingUrls();
				$this->logger->notice( 'Get pending for optimization URLs' );
			}

			WP_CLI\Utils\format_items(
				'yaml',
				array(
					array(
						'urls' => $result,
					),
				),
				array(
					'urls',
				)
			);
		} catch (\Exception $e) {
			$this->logger->error( 'Get Optimized URLs. Error - ' . $e->getMessage() );
			WP_CLI::error( $e->getMessage(), false );
			WP_CLI::error( 'Failed to fetch URLs.' );
			return;
		}
	}
	/**
	 * NitroPack excluded URLs.
	 *
	 * ## OPTIONS
	 *
	 * [<command>]
	 * : Control excluded URLs.
	 * ---
	 * default: get
	 * options:
	 *   - enable
	 *   - disable
	 *   - get
	 *   - add
	 *   - remove
	 * ---
	 *
	 * [<url_pattern>]
	 * : URL pattern.
	 *
	 * ## EXAMPLES
	 *
	 *     # Enable feature to exclude any URL
	 *     $ wp psb excludedurls enable
	 *
	 *     # Exclude a contact page
	 *     $ wp psb excludedurls add *\/contact
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command parameters.
	 */
	public function nitropack_excluded_urls( $args, $assoc_args ) {
		$site_config = $this->get_site_config();

		$command = empty( $args[0] ) ? '' : $args[0];
		$url_pattern = empty( $args[1] ) ? '' : $args[1];

		if ( ( 'add' === $command || 'remove' === $command ) && ! $url_pattern ) {
			$this->logger->error( 'Enter valid URL' );
			WP_CLI::error( 'Enter valid URL.' );
			return;
		}

		try {
			$api = $this->get_vendor_api();
			switch ( $command ) {
				case 'enable':
					$api->enableExcludedUrls();
					break;
				case 'disable':
					$api->disableExcludedUrls();
					break;
				case 'get':
					$fetcher = new \NitroPack\SDK\Api\RemoteConfigFetcher( $site_config['siteId'], $site_config['siteSecret'] );
					$response = $fetcher->get();
					$config = @json_decode( $response, true ); // phpcs:ignore
					if ( ! array_key_exists( 'DisabledURLs', $config ) ) {
						$this->logger->error( 'Disabled URLs are not present in the respone. Vendor API might changed' );
						WP_CLI::error( 'Disabled URLs are not present in the respone. Vendor API might changed.' );
						return;
					}
					WP_CLI\Utils\format_items(
						'yaml',
						array(
							array(
								'excludes' => $config['DisabledURLs'],
							),
						),
						array(
							'excludes',
						)
					);
					$this->logger->notice( 'Get excluded URLs' );
					return;
				case 'add':
					$api->addExcludedUrl( $url_pattern );
					$this->logger->notice( 'Add excluded URLs' );
					break;
				case 'remove':
					$api->removeExcludedUrl( $url_pattern );
					$this->logger->notice( 'Remove excluded URLs' );
					break;
				default:
					$this->logger->error( 'Excluded URLs - enter valid sub-command.' );
					WP_CLI::error( 'Enter valid sub-command.' );
					return;
			}
		} catch (\Exception $e) {
			$this->logger->error( "Failed to '" . $command . "' excluded URLs. Error: " . $e->getMessage() );
			WP_CLI::error( $e->getMessage(), false );
			WP_CLI::error( sprintf( 'Failed in %s excluded URL(s).', $command ) );
			return;
		}
		$this->logger->notice( "Succeeded to '" . $command . "' excluded URL(s)" );
		WP_CLI::success( sprintf( 'Succeeded to %s excluded URL(s).', $command ) );
	}
	/**
	 * NitroPack - JS, CSS, image, font excludes.
	 *
	 * ## OPTIONS
	 *
	 * [<command>]
	 * : Control excluded asset.
	 * ---
	 * default: get
	 * options:
	 *   - enable
	 *   - disable
	 *   - get
	 *   - add
	 *   - remove
	 * ---
	 *
	 * [<url_pattern>]
	 * : URL pattern.
	 *
	 * [--resource=<type>]
	 * : Limit exclusion to a specific resource type.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - css
	 *   - js
	 *   - font
	 *   - image
	 * ---
	 *
	 * [--device=<type>]
	 * : Limit exclusion to a specific device.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - desktop
	 *   - tablet
	 *   - mobile
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Enable feature to exclude any resource
	 *     $ wp psb excludes enable
	 *
	 *     # Exclude a contact page
	 *     $ wp psb excludes add *\/script.js --resource=js
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command parameters.
	 */
	public function nitropack_excludes( $args, $assoc_args ) {
		$this->get_site_config();

		if ( ! class_exists( '\NitroPack\SDK\ExcludeEntry' ) ) {
			$this->logger->error( 'The dependent plugin is incompatible' );
			WP_CLI::error( 'The dependent plugin is incompatible.' );
			return;
		}

		$command = empty( $args[0] ) ? '' : $args[0];
		$url_pattern = empty( $args[1] ) ? '' : $args[1];

		if ( ( 'add' === $command || 'remove' === $command ) && ! $url_pattern ) {
			$this->logger->error( 'Enter valid URL' );
			WP_CLI::error( 'Enter valid URL.' );
			return;
		}

		try {
			$api = $this->get_vendor_api();
			switch ( $command ) {
				case 'enable':
					$api->enableExcludes();
					break;
				case 'disable':
					$api->disableExcludes();
					break;
				case 'get':
					$all_excludes = $api->getExcludes();
					WP_CLI\Utils\format_items(
						'yaml',
						array(
							array(
								'excludes' => $all_excludes,
							),
						),
						array(
							'excludes',
						)
					);
					$this->logger->notice( 'Get excludes' );
					return;
				case 'add':
					$all_excludes = $api->getExcludes();

					$new_exclude = new \NitroPack\SDK\ExcludeEntry();
					$new_exclude->string = $url_pattern;
					$new_exclude->device = empty( $assoc_args['device'] ) || 'any' === $assoc_args['device'] ? null : $assoc_args['device'];
					$new_exclude->resourceType = empty( $assoc_args['resource'] ) || 'any' === $assoc_args['resource'] ? null : $assoc_args['resource']; // phpcs:ignore
					$new_exclude->operation->all = true;

					$all_excludes[] = $new_exclude;
					$api->setExcludes( $all_excludes );
					break;
				case 'remove':
					$all_excludes = $api->getExcludes();
					$all_excludes = array_filter(
						$all_excludes,
						function ($exclusion) use ($url_pattern) {
							return $exclusion->string !== $url_pattern;
						}
					);
					$api->setExcludes( $all_excludes );
					break;
				default:
					$this->logger->error( 'Enter valid sub-command' );
					WP_CLI::error( 'Enter valid sub-command.' );
					return;
			}
		} catch (\Exception $e) {
			$this->logger->error( "Failed to '" . $command . "' exclude. Error: " . $e->getMessage() );
			WP_CLI::error( $e->getMessage(), false );
			WP_CLI::error( sprintf( 'Failed to %s exclude(s).', $command ) );
			return;
		}
		$this->logger->notice( "Succeeded to '" . $command . "' exclude(s)" );
		WP_CLI::success( sprintf( 'Succeeded to %s exclude(s).', $command ) );
	}
}
