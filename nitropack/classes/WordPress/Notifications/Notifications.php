<?php
namespace NitroPack\WordPress\Notifications;

use NitroPack\WordPress\Settings\TestMode;
/* 
 * Class Notifications
 *
 * This class handles the notifications for the NitroPack plugin in WordPress.
 *
 * @package NitroPack\WordPress\Notifications
 */
class Notifications {
	private static $instance = NULL;
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'nitropack_admin_notices' ] );

		/* Using 'init' because it fixes issue when get_home_url() in updateCurrentBlogConfig() is not found in multisites */
		add_action( 'init', function () { 			
		    add_action( 'plugins_loaded', [ $this, 'nitropack_plugin_notices' ] );
		});

		//ajax
		add_action( 'wp_ajax_nitropack_safemode_notification', [ $this, 'nitropack_safemode_notification' ] );
		add_action( 'wp_ajax_nitropack_dismiss_notification', [ $this, 'nitropack_dismiss_notification' ] );
		//WC notice
		add_action( 'wp_ajax_nitropack_dismiss_woocommerce_notice', [ $this, 'nitropack_dismiss_woocommerce_notice' ] );
		//modal for non-optimized CPTs notice
		add_action( 'wp_ajax_nitropack_dismiss_notice_forever', [ $this, 'nitropack_dismiss_notice_forever' ] );

	}
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new Notifications();
		}

		return self::$instance;
	}
	/**
	 * Displays general admin notices for the NitroPack plugin in WordPress dashboard.
	 *
	 * @return void
	 */
	public function nitropack_admin_notices() {
		$components = new \NitroPack\WordPress\Settings\Components;
		if ( defined( 'NITROPACK_DATA_DIR_WARNING' ) ) {
			$components->render_notification( NITROPACK_DATA_DIR_WARNING, 'warning', 'Unable to initialize cache dir' );
		}

		if ( defined( 'NITROPACK_PLUGIN_DATA_DIR_WARNING' ) ) {
			$components->render_notification( NITROPACK_PLUGIN_DATA_DIR_WARNING, 'warning', 'Unable to initialize plugin data dir' );
		}

		if ( ! empty( $_COOKIE["nitropack_after_activate_notice"] ) ) {
			$components->render_notification( "<script>document.cookie = 'nitropack_after_activate_notice=1; expires=Thu, 01 Jan 1970 00:00:01 GMT;';</script>  Go to its' settings to connect it in order to start optimizing your site.",
				'info',
				esc_html__( 'NitroPack activated but not connected', 'nitropack' ),
				'<a href="' . admin_url( 'admin.php?page=nitropack' ) . '">' . esc_html__( 'NitroPack Settings', 'nitropack' ) . '</a>' );
		}

		$this->render_app_notifications();
		$this->nitropack_print_hosting_notice();
		$this->nitropack_print_woocommerce_notice();
	}
	/**
	 * Display NitroPack plugin notices in the WordPress admin area.
	 *
	 * This function is responsible for showing various notifications related to the NitroPack plugin.
	 *
	 * @return null|array
	 */
	public function nitropack_plugin_notices() {
		if ( ! $this->pass_notification_capabilities() )
			return;

		static $npPluginNotices = NULL;

		if ( $npPluginNotices !== NULL ) {
			return $npPluginNotices;
		}

		$errors = [];
		$warnings = [];
		$infos = [];

		/* Sets a warning if there are any conflicit plugins - mostly caching plugins. */
		$conflictingPlugins = nitropack_get_conflicting_plugins();
		foreach ( $conflictingPlugins as $clashingPlugin ) {
			$warnings[] = array(
				'title' => sprintf( "%s is active", $clashingPlugin ),
				'msg' => sprintf( __( "NitroPack and %s have overlapping functionality and can interfere with each other. Please deactivate %s for best results in NitroPack.", 'nitropack' ), $clashingPlugin, $clashingPlugin ),
				'actions' => '<a href="' . admin_url() . 'plugins.php" target="_blank" class="btn btn-secondary">Plugins page</a>',
				'classes' => [ 'conflicting-plugins plugin-' . sanitize_title( $clashingPlugin ) ],
			);
		}

		/* Add residual cache notices if found */
		$residualCachePlugins = \NitroPack\Integration\Plugin\RC::detectThirdPartyCaches();
		foreach ( $residualCachePlugins as $rcpName ) {
			$warnings[] = array(
				'title' => esc_html__( "Residual cache files", 'nitropack' ),
				'msg' => sprintf( esc_html__( 'We found residual cache files from %s. These files can interfere with the caching process and must be deleted.', 'nitropack' ), $rcpName, $rcpName ),
				'actions' => '<a class="btn btn-warning" nitropack-rc-data="' . $rcpName . '">' . esc_html__( 'Delete now', 'nitropack' ) . '</a>',
			);
		}
		/* Sets a warning if there is any activity in the plugins such as new activations, updates, or deletions. */
		if ( isset( $_COOKIE['nitropack_apwarning'] ) ) {
			$cookie_path = nitropack_cookiepath();
			$warnings[] = array(
				'title' => esc_html__( "Plugins activity", 'nitropack' ),
				'msg' => esc_html__( 'It seems plugins have been activated, deactivated or updated. It is recommended that you purge the cache to reflect the latest changes.', 'nitropack' ),
				'actions' => "<a class=\"btn btn-secondary\" href=\"javascript:void(0);\" id=\"np-onstate-cache-purge\" onclick=\"document.cookie = 'nitropack_apwarning=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=$cookie_path';window.location.reload();\">" . esc_html__( 'Dismiss', 'nitropack' ) . "</a>",
				'classes' => [ 'plugins-state' ],
			);
		}

		/* Sets a warning if the Test Mode is enabled. */
		$smStatus = get_option( 'nitropack-safeModeStatus', "-1" );
		if ( $smStatus === "-1" ){
			$smStatus = TestMode::getInstance()->nitropack_safemode_status(true);
		}
		if ( $smStatus ) {
			$safeModeMessage = __( 'Visitors are accessing your unoptimized pages. Make sure to disable it once you are done testing.', 'nitropack' );
			if ( get_nitropack()->getDistribution() == "oneclick" ) {
				$safeModeMessage = apply_filters( "nitropack_oneclick_safemode_message", $safeModeMessage );
			}

			$warnings[] = array(
				'title' => esc_html__( "Test Mode Enabled", 'nitropack' ),
				'msg' => $safeModeMessage,
				'classes' => [ 'test-mode' ],
			);
		}

		$nitropackIsConnected = get_nitropack()->isConnected();

		if ( $nitropackIsConnected ) {
			if ( nitropack_is_advanced_cache_allowed() ) {
				$notification_title = esc_html__( "File advanced-cache.php cannot be created", 'nitropack' );
				$notification_class = [ 'advanced-cache' ];

				if ( ! nitropack_has_advanced_cache() ) {

					$advancedCacheFile = nitropack_trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
					if ( ! file_exists( $advancedCacheFile ) || strpos( file_get_contents( $advancedCacheFile ), "NITROPACK_ADVANCED_CACHE" ) === false ) { // For some reason we get the notice right after connecting (even though the advanced-cache file is already in place). This check works around this issue :(

						if ( nitropack_install_advanced_cache() ) {
							if ( ! \NitroPack\Integration\Hosting\WPEngine::detect() ) { // The advanced-cache.php file in WP Engine is reset fairly often and we don't want to show the notice every time. This is an info we can skip in this case.

								/* Sets an info notifications if the advanced-cache.php file was re-installed. */
								$infos[] = array(
									'title' => esc_html__( 'File advanced-cache.php re-installed', 'nitropack' ),
									'msg' => esc_html__( 'The file /wp-content/advanced-cache.php was either missing or not the one generated by NitroPack. NitroPack re-installed its version of the file, so it can function properly. Possibly, there is another active page caching plugin in your system. For correct operation, please deactivate any other page caching plugins.', 'nitropack' ),
									'actions' => '<a href="' . admin_url() . 'plugins.php" target="_blank" class="btn btn-secondary">' . esc_html__( 'Plugins page' ) . '</a>',
									'classes' => $notification_class,
								);
							}
						} else {
							if ( ! nitropack_is_conflicting_plugin_active() ) {

								/* Sets an error notifications for not being able to create the advanced-cache.php file due to conflicting caching plugins */

								$errors[] = array(
									'title' => $notification_title,
									'msg' => __( 'Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
									'classes' => $notification_class,
								);
							}
						}
					}
				} else {
					if ( ! defined( "NITROPACK_ADVANCED_CACHE_VERSION" ) || NITROPACK_VERSION != NITROPACK_ADVANCED_CACHE_VERSION ) {
						if ( ! nitropack_install_advanced_cache() ) {
							if ( nitropack_is_conflicting_plugin_active() ) {
								$errors[] = array(
									'title' => $notification_title,
									'msg' => esc_html__( 'The file /wp-content/advanced-cache.php cannot be created because a conflicting plugin is active. Please make sure to disable all conflicting plugins.', 'nitropack' ),
									'actions' => '<a href="' . admin_url() . 'plugins.php" target="_blank" class="btn btn-primary">Plugins page</a>',
									'classes' => $notification_class,
								);
							} else {
								$errors[] = array(
									'title' => $notification_title,
									'msg' => esc_html__( 'The file /wp-content/advanced-cache.php cannot be created. Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
									'classes' => $notification_class,
								);
							}
						}
					}
				}
			} else {
				if ( nitropack_has_advanced_cache() ) {
					nitropack_uninstall_advanced_cache();
				}
			}

			if ( ( ! defined( "WP_CACHE" ) || ! WP_CACHE ) ) {
				$notification_class = [ 'wp-cache' ];
				if ( \NitroPack\Integration\Hosting\Flywheel::detect() ) { // Flywheel: This is configured throught the FW control panel
					$warnings[] = array(
						'title' => esc_html__( "WP_CACHE not enabled", 'nitropack' ),
						'msg' => esc_html__( "Please go to your FlyWheel control panel and enable this setting.", 'nitropack' ),
						'actions' => '<a href="https://getflywheel.com/wordpress-support/how-to-enable-wp_cache/" target="_blank" class="btn btn-primary">View more</a>',
						'classes' => $notification_class,
					);
				} else if ( ! nitropack_set_wp_cache_const( true ) ) {
					$errors[] = array(
						'title' => esc_html__( 'Constant WP_CACHE cannot be set', 'nitropack' ),
						'msg' => esc_html__( 'This can lead to slower cache delivery. Please make sure that the /wp-config.php file is writable and refresh this page.', 'nitropack' ),
						'classes' => $notification_class,
					);
				}
			}

			if ( apply_filters( 'nitropack_needs_htaccess_changes', false ) ) {
				if ( ! nitropack_set_htaccess_rules( true ) ) {
					$warnings[] = array(
						'title' => esc_html__( "File .htaccess is not writable", 'nitropack' ),
						'msg' => esc_html__( 'Unable to configure LiteSpeed specific rules for maximum performance. Please make sure your .htaccess file is writable or contact support.', 'nitropack' ),
						'classes' => [ 'htaccess' ],
					);
				}
			}

			if ( ! get_nitropack()->dataDirExists() && ! get_nitropack()->initDataDir() ) {
				$errors[] = array(
					'title' => esc_html__( 'NitroPack data directory cannot be created', 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
					'classes' => [ 'np-data-dir' ],
				);
				return [ 
					'error' => $errors,
					'warning' => $warnings,
					'info' => $infos
				];
			}

			if ( ! get_nitropack()->pluginDataDirExists() && ! get_nitropack()->initPluginDataDir() ) {
				$errors[] = array(
					'title' => esc_html__( 'NitroPack plugin data directory cannot be created', 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),

					'classes' => [ 'np-data-dir' ],
				);
				return [ 
					'error' => $errors,
					'warning' => $warnings,
					'info' => $infos
				];
			}

			$siteConfig = nitropack_get_site_config();
			$siteId = $siteConfig ? $siteConfig["siteId"] : NULL;
			$siteSecret = $siteConfig ? $siteConfig["siteSecret"] : NULL;
			$webhookToken = esc_attr( get_option( 'nitropack-webhookToken' ) );
			$blogId = get_current_blog_id();
			$isConfigOutdated = ! nitropack_is_config_up_to_date();

			if ( ! get_nitropack()->Config->exists() && ! get_nitropack()->updateCurrentBlogConfig( $siteId, $siteSecret, $blogId ) ) {
				$errors[] = array(
					'title' => esc_html__( "NitroPack static config file cannot be created", 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/config-nitropack/ directory is writable and refresh this page.', 'nitropack' ),
				);
			} else if ( $isConfigOutdated ) {
				if ( ! get_nitropack()->updateCurrentBlogConfig( $siteId, $siteSecret, $blogId ) ) {
					$errors[] = array(
						'title' => esc_html__( "NitroPack static config file cannot be updated", 'nitropack' ),
						'msg' => esc_html__( 'Please make sure that the /wp-content/config-nitropack/ directory is writable and refresh this page.', 'nitropack' ),
					);
				} else {
					if ( ! $siteConfig ) {
						nitropack_event( "update" );
					} else {
						$prevVersion = ! empty( $siteConfig["pluginVersion"] ) ? $siteConfig["pluginVersion"] : "1.1.4 or older";
						nitropack_event( "update", null, array( "previous_version" => $prevVersion ) );

						if ( empty( $siteConfig["pluginVersion"] ) || version_compare( $siteConfig["pluginVersion"], "1.4", "<" ) ) {
							$nitropack_v1_3_notice_id = 'nitropack_upgrade_to_1_3';
							if ( ! headers_sent() ) {
								setcookie( "dismissed_notice_" . $nitropack_v1_3_notice_id, 1, time() + 3600 );
							}
							$_COOKIE[ "dismissed_notice_" . $nitropack_v1_3_notice_id ] = 1;
						}
					}
				}

				try {
					nitropack_setup_webhooks( get_nitropack_sdk(), $webhookToken );
				} catch (\NitroPack\SDK\WebhookException $e) {
					$warnings[] = array(
						'title' => esc_html__( "Unable to configure webhooks", 'nitropack' ),
						'msg' => esc_html__( 'This can impact the stability of the plugin. Please disconnect and connect again in order to retry configuring the webhooks.', 'nitropack' ),
					);
				}
			} else {
				$optionsMismatch = false;
				if ( array_key_exists( 'options_cache', $siteConfig ) ) {
					foreach ( \NitroPack\WordPress\NitroPack::$optionsToCache as $opt ) {
						if ( is_array( $opt ) ) {
							foreach ( $opt as $option => $suboption ) {
								// Handle both nested and flat structures
								if ( is_array( $suboption ) ) {
									// Nested structure
									if ( ! isset( $siteConfig['options_cache'][ $option ] ) || ! is_array( $siteConfig['options_cache'][ $option ] ) ) {
										$optionsMismatch = true;
										break 2;
									}
									foreach ( $suboption as $subkey => $subvalue ) {
										if (
											! isset( $siteConfig['options_cache'][ $option ][ $subkey ] ) ||
											$siteConfig['options_cache'][ $option ][ $subkey ] !== get_option( $option )[ $subkey ]
										) {
											$optionsMismatch = true;
											break 3;
										}
									}
								} else {
									// Flat structure within the nested loop
									if (
										! isset( $siteConfig['options_cache'][ $option ] ) ||
										$siteConfig['options_cache'][ $option ] !== get_option( $option )
									) {
										$optionsMismatch = true;
										break 2;
									}
								}
							}
						} else {
							// Flat structure outside the nested loop
							if (
								! isset( $siteConfig['options_cache'][ $opt ] ) ||
								is_bool( $siteConfig['options_cache'][ $opt ] ) ||
								$siteConfig['options_cache'][ $opt ] !== get_option( $opt )
							) {
								$optionsMismatch = true;
								break;
							}
						}
					}
				} else {
					$optionsMismatch = true;
				}

				if (
					$optionsMismatch ||
					( ! array_key_exists( "isEzoicActive", $siteConfig ) || $siteConfig["isEzoicActive"] !== \NitroPack\Integration\Plugin\Ezoic::isActive() ) ||
					( ! array_key_exists( "isLateIntegrationInitRequired", $siteConfig ) || $siteConfig["isLateIntegrationInitRequired"] !== nitropack_is_late_integration_init_required() ) ||
					( ! array_key_exists( "isDlmActive", $siteConfig ) || $siteConfig["isDlmActive"] !== \NitroPack\Integration\Plugin\DownloadManager::isActive() ) ||
					( ! array_key_exists( "isAeliaCurrencySwitcherActive", $siteConfig ) || $siteConfig["isAeliaCurrencySwitcherActive"] !== \NitroPack\Integration\Plugin\AeliaCurrencySwitcher::isActive() ) ||
					( ! array_key_exists( "isGeoTargetingWPActive", $siteConfig ) || $siteConfig["isGeoTargetingWPActive"] !== \NitroPack\Integration\Plugin\GeoTargetingWP::isActive() ) ||
					( ! array_key_exists( "isWoocommerceActive", $siteConfig ) || $siteConfig["isWoocommerceActive"] !== \NitroPack\Integration\Plugin\WooCommerce::isActive() ) ||
					( ! array_key_exists( "isWoocommerceCacheHandlerActive", $siteConfig ) || $siteConfig["isWoocommerceCacheHandlerActive"] !== \NitroPack\Integration\Plugin\WoocommerceCacheHandler::isActive() )
				) {
					if ( ! get_nitropack()->updateCurrentBlogConfig( $siteId, $siteSecret, $blogId ) ) {
						$errors[] = array(
							'title' => esc_html__( "NitroPack static config file cannot be updated", 'nitropack' ),
							'msg' => esc_html__( 'Please make sure that the /wp-content/config-nitropack/ directory is writable and refresh this page.', 'nitropack' ),
						);
					}
				}

				if ( empty( $_COOKIE["nitropack_webhook_sync"] ) || !$siteConfig["webhookToken"]) {
					if ( null !== $nitro = get_nitropack_sdk() ) {
						try {
							if ( ! headers_sent() ) {
								nitropack_setcookie( "nitropack_webhook_sync", "1", time() + 300 ); // Do these checks in 5 minute intervals.
							}
							$configWebhook = $nitro->getApi()->getWebhook( "config" );
							if ( ! empty( $configWebhook ) ) {
								$query = parse_url( $configWebhook, PHP_URL_QUERY );
								if ( $query ) {
									parse_str( $query, $webhookParams );
									if ( empty( $webhookParams["token"] ) || $webhookParams["token"] != $webhookToken ) {
										$warnings[] = array(
											'title' => esc_html__( "Connection problems detected", 'nitropack' ),
											'msg' => esc_html__( 'Most likely you have used the same API credentials to connect another website (e.g. dev or staging). Click to restore the connection to this site.', 'nitropaack' ),
											'actions' => '<a id="nitro-restore-connection-btn" class="btn btn-warning">Restore connection</a>',
										);
									}
								}
							}
						} catch (\Exception $e) {
							//Do nothing
						}
					}
				}

				if ( apply_filters( 'nitropack_should_modify_htaccess', false ) && ( empty( $_SERVER["NitroPackHtaccessVersion"] ) || NITROPACK_VERSION != $_SERVER["NitroPackHtaccessVersion"] ) ) {
					if ( ! nitropack_set_htaccess_rules( true ) ) {
						$errors[] = array(
							'title' => esc_html__( "The .htaccess file cannot be modified", 'nitropack' ),
							'msg' => esc_html__( 'Please make sure that it is writable and refresh this page.', 'nitropack' ),
						);
					}
				}
			}
			if ( isset( $nitropack_v1_3_notice_id ) && $this->nitropack_is_dismissed_notice( $nitropack_v1_3_notice_id ) ) {
				$warnings[] = array(
					'title' => esc_html__( "NitroPack upgraded to 1.3", 'nitropack' ),
					'msg' => esc_html__( 'Your new version of NitroPack has a new better way of recaching updated content. However, it is incompatible with the page relationships built by your previous version. Please invalidate your cache manually one-time so that content updates start working with the updated logic.', 'nitropack' ),
					'dismissableId' => $nitropack_v1_3_notice_id,
				);
			}

			if ( \NitroPack\Integration\Plugin\Cloudflare::isApoActive() && ! \NitroPack\Integration\Plugin\Cloudflare::isApoCacheByDeviceTypeEnabled() ) {
				$warnings[] = array(
					'title' => esc_html__( "Cache By Device Type is not activate", 'nitropack' ),
					'msg' => esc_html__( 'It seems Cache By Device Type is not activate with the Cloudflare APO. It is recommended that you enable it for a more optimized experience.', 'nitropack' ),
				);
			}
		}

		$npPluginNotices = [ 
			'error' => $errors,
			'warning' => $warnings,
			'info' => $infos
		];


		return $npPluginNotices;
	}

	/**
	 * Display admin notices in the NitroPack -> Dashboard plugin page.
	 *
	 * This function checks if the current user has the necessary capabilities to view the notices.
	 * It renders specific notifications related to hosting information, system, compatibilities and notifications coming from the NitroPack app
	 *
	 * @return void
	 */
	public function nitropack_display_admin_notices() {
		if ( ! $this->pass_notification_capabilities() )
			return;

		$noticesArray = $this->nitropack_plugin_notices();
		$components = new \NitroPack\WordPress\Settings\Components;
		foreach ( $noticesArray as $type => $notices ) {
			foreach ( $notices as $notice ) {
				$components->render_notification( $notice['msg'], $type, $notice['title'], isset( $notice['actions'] ) ? $notice['actions'] : null, isset( $notice['classes'] ) ? $notice['classes'] : null, isset( $notice['dismissibleId'] ) ? $notice['dismissibleId'] : null );
			}
		}
		//render app notifications
		$this->render_app_notifications();
	}

	/**
	 * Render notifications coming from notifications.json file such as ones from the NitroPack app.
	 *
	 * @return void
	 */
	public function render_app_notifications() {
		$components = new \NitroPack\WordPress\Settings\Components();
		$app_notifications = AppNotifications::getInstance();
		foreach ( $app_notifications->get( 'system' ) as $notification ) {
			$msg = $notification['message'];
			$type = 'info';
			$title = '';

			if ( ! empty( $notification['type'] ) ) {
				$type = $notification['type'];
			}
			if ( ! empty( $notification['message_details']['title'] ) ) {
				$title = $notification['message_details']['title'];
			}
			if ( ! empty( $notification['message_details']['message'] ) ) {
				$msg = $notification['message_details']['message'];
			}

			$components->render_notification( $msg, $type, $title, '', [ 'app-notification' ], $notification['id'], $notification );
		}
	}
	/**
	 * Prints a hosting notice for NitroPack.
	 *
	 * @return void
	 */
	private function nitropack_print_hosting_notice() {

		$hostingNoticeFile = nitropack_get_hosting_notice_file();
		if ( ! get_nitropack()->isConnected() || file_exists( $hostingNoticeFile ) )
			return;

		$documentedHostingSetups = array(
			"flywheel" => array(
				"name" => "Flywheel",
				"helpUrl" => "https://getflywheel.com/wordpress-support/how-to-enable-wp_cache/"
			),
			"cloudways" => array(
				"name" => "Cloudways",
				"helpUrl" => "https://support.nitropack.io/hc/en-us/articles/360060916674-Cloudways-Hosting-Configuration-for-NitroPack"
			)
		);

		$siteConfig = nitropack_get_site_config();
		if ( $siteConfig && ! empty( $siteConfig["hosting"] ) && array_key_exists( $siteConfig["hosting"], $documentedHostingSetups ) ) {
			$hostingInfo = $documentedHostingSetups[ $siteConfig["hosting"] ];
			$showNotice = true;
			if ( $siteConfig["hosting"] == "flywheel" && defined( "WP_CACHE" ) && WP_CACHE )
				$showNotice = false;

			if ( $showNotice ) {
				$components = new \NitroPack\WordPress\Settings\Components;
				$components->render_notification( esc_html__( "Please follow the instructions in order to make sure that everything works correctly.", 'nitropack' ), 'info',
					sprintf( esc_html__( 'It looks like you are hosted on %s', 'nitropack' ), $hostingInfo['name'] ),
					'<a href="' . $hostingInfo["helpUrl"] . '" target="_blank" class="btn btn-info btn-ghost">' . esc_html__( 'Read Instructions', 'nitropack' ) . '</a>',
					[ 'hosting-notice' ] );
			}
		}
	}
	/**
	 * Prints a WooCommerce notice for NitroPack across WordPress admin
	 * @return void
	 */
	private function nitropack_print_woocommerce_notice() {
		if ( get_nitropack()->isConnected() ) {
			if ( class_exists( 'WooCommerce' ) ) {
				$wcOneTimeNotice = get_option( 'nitropack-wcNotice' );

				if ( ! $wcOneTimeNotice ) {
					$components = new \NitroPack\WordPress\Settings\Components;
					$components->render_notification( __( 'Your <strong>account</strong>, <strong>cart</strong>, and <strong>checkout</strong> pages are automatically excluded from optimization.', 'nitropack' ),
						'success',
						esc_html__( 'WooCommerce detected', 'nitropack' ),
						'<a class="btn btn-secondary" href="' . admin_url( 'admin.php?page=nitropack' ) . '">' . esc_html__( 'Settings', 'nitropack' ) . '</a>',
						[ 'woocommerce-notice' ],
						'nitropack_dismiss_woocommerce_notice' );
				}
			}
		}
	}


	/**
	 * Checks if the user has capabilities to manage options - administrators typically have this capability.
	 * @return void|bool
	 */
	private function pass_notification_capabilities() {
		if ( ! current_user_can( 'manage_options' ) )
			return;
		else {
			return true;
		}
	}

	public function test_mode_notification_html() {
		$components = new \NitroPack\WordPress\Settings\Components;
		$components->render_notification( 'Visitors are accessing your unoptimized pages. Make sure to disable it once you are done testing.', 'warning', 'Test Mode Enabled', false, [ 'test-mode' ] );
	}
	public function nitropack_safemode_notification() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$this->test_mode_notification_html();
		wp_die();
	}
	public function nitropack_is_dismissed_notice( $id ) {
		return isset( $_COOKIE[ "dismissed_notice_" . $id ] );
	}
	/* Dissmiss notification by using set_transient -> temporary dismissal with auto-expiry */
	public function nitropack_dismiss_notification() {
		if ( ! $this->pass_notification_capabilities() ) {
			wp_die( __( 'You do not have sufficient permissions.' ) );
		}

		if ( ! isset( $_POST ) ) {
			return;
		}

		nitropack_verify_ajax_nonce( $_REQUEST );

		$notification_id = $_POST['notification_id'];
		$notification_end = $_POST['notification_end'];
		$midpoint = get_date_midpoint( $notification_end );
		$notification_end = strtotime( $notification_end ) - time();
		$transient_status = set_transient( $notification_id, $midpoint, $notification_end );

		nitropack_json_and_exit( array(
			"transient_status" => $transient_status,
		) );
	}

	/* Dissmiss permanent a notification by using update_option - db way */
	public function nitropack_dismiss_woocommerce_notice() {
		if ( ! $this->pass_notification_capabilities() ) {
			wp_die( __( 'You do not have sufficient permissions.' ) );
		}

		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = update_option( 'nitropack-wcNotice', 1 );
		if ( $option ) {
			nitropack_json_and_exit( array(
				"type" => "success",
			) );
		} else {
			nitropack_json_and_exit( array(
				"type" => "error",
			) );
		}
	}
	/* Dissmiss permanent a notification by using update_option - db way */
	public function nitropack_dismiss_notice_forever() {
		if ( ! $this->pass_notification_capabilities() ) {
			wp_die( __( 'You do not have sufficient permissions.' ) );
		}

		nitropack_verify_ajax_nonce( $_REQUEST );

		update_option( 'nitropack-noticeOptimizeCPT', 1 );

		nitropack_json_and_exit( array(
			"type" => "success",
		) );
	}
}
