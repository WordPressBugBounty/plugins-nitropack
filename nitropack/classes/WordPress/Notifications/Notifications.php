<?php
namespace NitroPack\WordPress\Notifications;

use NitroPack\WordPress\Settings\TestMode;
use NitroPack\WordPress\NitroPack;
use NitroPack\WordPress\AdvancedCache\AdvancedCache;
use NitroPack\SDK\Filesystem;

/* 
 * Class Notifications
 *
 * This class handles the notifications for the NitroPack plugin in WordPress.
 *
 * @package NitroPack\WordPress\Notifications
 */
class Notifications {
	/**
	 * Singleton instance of the Notifications class.
	 *
	 * @var Notifications|null
	 */
	private static $instance = null;
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'nitropack_admin_notices' ] );
		//set notification when a plugin is activated or deactivated, so we can show a warning to purge cache
		add_action( 'activated_plugin', [ $this, 'plugin_activity_and_upgrader' ] );
		add_action( 'deactivated_plugin', [ $this, 'plugin_activity_and_upgrader' ] );
		/* Using 'init' because it fixes issue when get_home_url() in updateCurrentBlogConfig() is not found in multisites */
		add_action( 'init', function () {
			add_action( 'plugins_loaded', [ $this, 'nitropack_plugin_notices' ] );
		} );


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

		if ( ! empty( $_COOKIE["nitropack_after_activate_notice"] ) && ! get_nitropack()->isConnected() ) {
			$components->render_notification( "Please complete the setup process to activate optimizations.",
				'promo',
				esc_html__( 'Connect your website to enable NitroPack\'s optimizations', 'nitropack' ),
				'<a href="' . admin_url( 'admin.php?page=nitropack' ) . '" class="btn btn-primary">' . esc_html__( 'Connect your website', 'nitropack' ) . '</a>' );
		}

		$this->render_app_notifications();
		$this->nitropack_print_hosting_notice();
		$this->nitropack_print_woocommerce_notice();
	}
	public function get_hosting_notice_file() {
		return nitropack_trailingslashit( NITROPACK_DATA_DIR ) . "hosting_notice";
	}

	/**
	 * Display NitroPack plugin notices in the WordPress admin area.
	 *
	 * This function is responsible for showing various notifications related to the NitroPack plugin.
	 *
	 * @return null|array
	 */
	public function nitropack_plugin_notices() {
		if ( ! self::pass_notification_capabilities() ) {
			return;
		}
		static $notices = null;
		if ( null !== $notices ) {
			return $notices;
		}

		$errors = [];
		$warnings = [];
		$infos = [];

		/* Sets a warning if there are any conflicting plugins - mostly caching plugins. */
		$warnings = array_merge(
			$warnings,
			$this->conflicting_plugins_notification()
		);
		/* Add residual cache notices if found */
		$warnings = array_merge(
			$warnings,
			$this->residual_cache_notification()
		);
		/* Sets a warning if there is any activity in the plugins such as new activations, updates, or deletions. */
		$warnings = array_merge(
			$warnings,
			$this->plugin_activity_notification()
		);

		/* Sets a warning if the Test Mode is enabled. */
		$warnings = array_merge(
			$warnings,
			$this->test_mode_notification()
		);
		$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
		$nitropackIsConnected = get_nitropack()->isConnected();
		$advanced_cache = new AdvancedCache();
		if ( $nitropackIsConnected ) {

			/* Advanved Cache */
			$advanced_cache_notifications = $this->advanced_cache_notification( $advanced_cache, $conflictingPlugins );
			$errors = array_merge( $errors, $advanced_cache_notifications['errors'] );
			$infos = array_merge( $infos, $advanced_cache_notifications['infos'] );

			/* WP_CACHE constant notification */
			$wp_cache_notification = $this->wp_cache_notification();
			$warnings = array_merge( $warnings, $wp_cache_notification['warnings'] );
			$errors = array_merge( $errors, $wp_cache_notification['errors'] );

			/* litespeed issue */
			$errors = array_merge(
				$errors,
				$this->litespeed_htaccess_notification()
			);

			/* Critical errors when NitroPack directories are not writable -> wp-content/cache/[hash]-nitropack and wp-content/config-[hash]-nitropack */
			$directory_errors = $this->data_directories_notification();
			if ( $directory_errors ) {
				$errors = array_merge( $errors, $directory_errors );
				return [
					'error' => $errors,
					'warning' => $warnings,
					'info' => $infos
				];
			}

			/* Site Config notifications -> config.json */
			$site_config_notifications = $this->site_config_notification();
			$errors = array_merge( $errors, $site_config_notifications['errors'] );
			$warnings = array_merge( $warnings, $site_config_notifications['warnings'] );
			$siteConfig = $site_config_notifications['site_config'];
			$siteId = $site_config_notifications['site_id'];
			$siteSecret = $site_config_notifications['site_secret'];
			$blogId = $site_config_notifications['blog_id'];
			$webhookToken = $site_config_notifications['webhook_token'];
			$nitropack_v1_3_notice_id = $site_config_notifications['upgrade_notice_id'];

			if ( $site_config_notifications['check_mismatch'] ) {
				$errors = array_merge(
					$errors,
					$this->config_mismatch_notification( $siteConfig, $siteId, $siteSecret, $blogId ),
				);

				$warnings = array_merge(
					$warnings,
					$this->connection_problems( $siteConfig, $webhookToken ),
				);

				$errors = array_merge(
					$errors,
					$this->htaaccess_notification()
				);
			}
			if ( $nitropack_v1_3_notice_id ) {
				$warnings[] = array(
					'title' => esc_html__( "NitroPack upgraded to 1.3", 'nitropack' ),
					'msg' => esc_html__( 'Your new version of NitroPack has a new better way of recaching updated content. However, it is incompatible with the page relationships built by your previous version. Please invalidate your cache manually one-time so that content updates start working with the updated logic.', 'nitropack' ),
					'dismissibleId' => $nitropack_v1_3_notice_id,
					'dismissBy' => 'option',
				);
			}

			/* Sets a warning if the Cloudflare APO is active but the Cache By Device Type is not enabled. */
			$warnings = array_merge(
				$warnings,
				$this->cloudflare_apo_notification()
			);
		}
		$notices = [
			'error' => $errors,
			'warning' => $warnings,
			'info' => $infos
		];

		return $notices;
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
		if ( ! $this->pass_notification_capabilities() ) {
			return;
		}

		$noticesArray = $this->nitropack_plugin_notices();
		$components = new \NitroPack\WordPress\Settings\Components;
		foreach ( $noticesArray as $type => $notices ) {
			foreach ( $notices as $notice ) {
				$components->render_notification( $notice['msg'], $type, $notice['title'], isset( $notice['actions'] ) ? $notice['actions'] : null, isset( $notice['classes'] ) ? $notice['classes'] : null, isset( $notice['dismissibleId'] ) ? $notice['dismissibleId'] : null, isset( $notice['dismissBy'] ) ? $notice['dismissBy'] : null );
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

			$components->render_notification( $msg, $type, $title, '', [ 'app-notification' ], $notification['id'], 'transient', $notification );
		}
	}
	/**
	 * Prints a hosting notice for NitroPack.
	 *
	 * @return void
	 */
	private function nitropack_print_hosting_notice() {

		$hostingNoticeFile = $this->get_hosting_notice_file();
		if ( ! get_nitropack()->isConnected() || Filesystem::fileExists( $hostingNoticeFile ) )
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
			if ( $siteConfig["hosting"] == "flywheel" && defined( "WP_CACHE" ) && WP_CACHE ) {
				$showNotice = false;
			}

			if ( $showNotice ) {
				$components = new \NitroPack\WordPress\Settings\Components;
				$components->render_notification( esc_html__( "Please follow the instructions in order to make sure that everything works correctly.", 'nitropack' ), 'info',
					/* translators: %s: Name of the hosting provider */
					sprintf( esc_html__( 'It looks like you are hosted on %s', 'nitropack' ), $hostingInfo['name'] ),
					'<a href="' . $hostingInfo["helpUrl"] . '" target="_blank" class="btn btn-info btn-ghost">' . esc_html__( 'Read Instructions', 'nitropack' ) . '</a>',
					[ 'hosting-notice' ], 'hosting-' . $siteConfig["hosting"], 'option' );
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
				$np_notices = get_option( 'nitropack-dismissed-notices', [] );
				$woocommerce_notice = in_array( 'WooCommerce', $np_notices, true ) ? true : false;

				if ( ! $woocommerce_notice ) {
					$components = new \NitroPack\WordPress\Settings\Components;
					$components->render_notification( __( 'Your <strong>account</strong>, <strong>cart</strong>, and <strong>checkout</strong> pages are automatically excluded from optimization.', 'nitropack' ),
						'success',
						esc_html__( 'WooCommerce detected', 'nitropack' ),
						'<a class="btn btn-secondary" href="' . admin_url( 'admin.php?page=nitropack' ) . '">' . esc_html__( 'Settings', 'nitropack' ) . '</a>',
						[ 'woocommerce-notice' ],
						'WooCommerce', 'option' );
				}
			}
		}
	}

	public function admin_bar_notices_counter() {
		if ( ! $this->pass_notification_capabilities() )
			return;

		$notices = $this->nitropack_plugin_notices();

		$errors = 0;
		$warnings = 0;
		$notifications_count = 0;
		foreach ( array( "warning", "error", "info" ) as $type ) {

			foreach ( $notices[ $type ] as $notice ) {
				switch ( $type ) {
					case "error":
						$errors++;
						break;
					case "warning":
						$warnings++;
						break;
					case "info":
						$notifications_count++;
						break;
				}
			}
		}

		/* Notifications from the app */
		$app_notifications = AppNotifications::getInstance();
		foreach ( $app_notifications->get( 'system' ) as $notification ) {

			if ( ! empty( $notification['id'] ) ) {

				/* Don't count if dismissed by transient and the time has passed  */
				$notice = get_transient( $notification['id'] );
				if ( ! empty( $notice ) && ( $notice && time() < $notice ) ) {
					continue;
				}

				if ( ! empty( $notification['type'] ) ) {
					switch ( $notification['type'] ) {
						case 'error':
							$errors++;
							break;
						case 'warning':
							$warnings++;
							break;
						case 'info':
							$notifications_count++;
							break;
					}
				} else {
					$notifications_count++;
				}
			}
		}

		$total_issues = $errors + $warnings;

		if ( $errors > 0 ) {
			$pluginStatus = 'error';
		} else if ( $warnings > 0 ) {
			$pluginStatus = 'warning';
		} else {
			$pluginStatus = 'ok';
		}
		return [ 'issues' => $total_issues, 'status' => $pluginStatus, 'errors' => $errors, 'warnings' => $warnings, 'notifications' => $notifications_count ];
	}
	/**
	 * Checks if the user has capabilities to manage options - administrators typically have this capability.
	 * @return bool
	 */
	public static function pass_notification_capabilities() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handles plugin update events.
	 * Sets a warning cookie if a non-NitroPack active plugin was updated.
	 *
	 * @param array $options The upgrade options.
	 * @param string $nitropack The NitroPack plugin identifier.
	 * @return void
	 */
	private function handle_plugin_update( $options, $nitropack ) {
		$plugins = ! empty( $options['plugins'] ) ? $options['plugins'] : ( ! empty( $options['plugin'] ) ? [ $options['plugin'] ] : [] );
		foreach ( $plugins as $plugin ) {
			if ( $plugin !== $nitropack && is_plugin_active( $plugin ) ) {
				nitropack_setcookie( 'nitropack_apwarning', "1", time() + 600 );
				break;
			}
		}
	}
	/**
	 * Handles plugin activation/deactivation events.
	 * Sets a warning cookie if a non-NitroPack plugin is activated/deactivated.
	 *
	 * @param mixed $upgrader The plugin slug when activated/deactivated.
	 * @param string $nitropack The NitroPack plugin identifier.
	 * @return void
	 */
	private function handle_plugin_activation_deactivation( $upgrader, $nitropack ) {
		if ( is_string( $upgrader ) && $upgrader !== $nitropack ) {
			nitropack_setcookie( 'nitropack_apwarning', "1", time() + 600 );
		}
	}
	/**
	 * Handle plugin and theme updates.
	 * Display a warning message in the dashboard if the plugin/theme update is not for NitroPack.
	 * Purge cache when the active theme is updated.
	 *
	 * @param \WP_Upgrader $upgrader The upgrader instance.
	 * @param array $options The options for the upgrade.
	 * @return void
	 */
	public function plugin_activity_and_upgrader( $upgrader, $options = null ) {
		$nitropack = 'nitropack/main.php';

		// Called from activated_plugin or deactivated_plugin: $upgrader is the plugin slug, $options is a bool
		if ( ! is_array( $options ) ) {
			$this->handle_plugin_activation_deactivation( $upgrader, $nitropack );
			return;
		}

		if ( $options['type'] == 'plugin' && $options['action'] == 'update' ) {
			$this->handle_plugin_update( $options, $nitropack );
		}
	}

	/**
	 * Displays a notice when there is a conflicting plugin, mostly caching plugins are the ones.
	 * @return array{actions: string, classes: array, msg: mixed, title: string[]}
	 */
	private function conflicting_plugins_notification() {
		$warnings = [];

		$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
		$conflictingPlugins_list = $conflictingPlugins->nitropack_get_conflicting_plugins();

		if ( $conflictingPlugins_list ) {

			foreach ( $conflictingPlugins_list as $clashingPlugin ) {
				$warnings[] = array(
					'title' => sprintf( "%s is active and may conflict with NitroPack", $clashingPlugin['name'] ),
					'msg' => esc_html__( "Some of its features overlap with NitroPack's optimizations which could lead to issues. We recommend disabling it to avoid potential conflicts.", 'nitropack' ),
					'actions' => '<a class="btn btn-secondary modal-plugin-deactivate" data-plugin-path="' . $clashingPlugin['plugin'] . '" data-plugin-name="' . $clashingPlugin['name'] . '" title="Disable ' . $clashingPlugin['name'] . ' ">' . sprintf( "Deactivate %s", $clashingPlugin['name'] ) . '</a>',
					'classes' => [ 'conflicting-plugins plugin-' . sanitize_title( $clashingPlugin['name'] ) ],
				);
				NitroPack::getInstance()->getLogger()->notice( sprintf( "Conflicting plugin detected: %s", $clashingPlugin['name'] ) );
			}

		}

		return $warnings;
	}

	/**
	 * Residual cache files from other plugins.
	 * @return array{actions: string, msg: string, title: mixed[]}
	 */
	private function residual_cache_notification() {
		$warnings = [];

		$residualCachePlugins = \NitroPack\Integration\Plugin\RC::detectThirdPartyCaches();
		foreach ( $residualCachePlugins as $rcpName ) {
			$warnings[] = array(
				'title' => esc_html__( "Residual cache files", 'nitropack' ),
				/* translators: %s: Name of the plugin that left residual cache files */
				'msg' => sprintf( esc_html__( 'We found residual cache files from %s. These files can interfere with the caching process and must be deleted.', 'nitropack' ), $rcpName, $rcpName ),
				'actions' => '<a class="btn btn-warning" nitropack-rc-data="' . $rcpName . '">' . esc_html__( 'Delete now', 'nitropack' ) . '</a>',
			);
			NitroPack::getInstance()->getLogger()->notice( sprintf( "Residual cache files detected from plugin: %s", $rcpName ) );
		}

		return $warnings;
	}

	/**
	 * Checks and installs the advanced-cache.php file when needed.
	 *
	 * @param AdvancedCache $advanced_cache Advanced cache manager.
	 * @param object        $conflictingPlugins Conflicting plugins manager.
	 * @return array{errors: array, infos: array}
	 */
	private function advanced_cache_notification( AdvancedCache $advanced_cache, $conflictingPlugins ) {
		$errors = [];
		$infos = [];
		$notification_title = esc_html__( 'File advanced-cache.php cannot be created', 'nitropack' );
		$notification_class = [ 'advanced-cache' ];

		if ( ! $advanced_cache->is_advanced_cache_allowed() ) {
			if ( $advanced_cache->has_advanced_cache() ) {
				$advanced_cache->uninstall_advanced_cache();
			}

			return [
				'errors' => $errors,
				'infos' => $infos,
			];
		}

		$advanced_cache_file = nitropack_trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
		$advanced_cache_needs_install = ! $advanced_cache->has_advanced_cache() && (
			! Filesystem::fileExists( $advanced_cache_file ) ||
			strpos( file_get_contents( $advanced_cache_file ), 'NITROPACK_ADVANCED_CACHE' ) === false
		);
		$advanced_cache_needs_update = $advanced_cache->has_advanced_cache() && (
			! defined( 'NITROPACK_ADVANCED_CACHE_VERSION' ) ||
			NITROPACK_VERSION != NITROPACK_ADVANCED_CACHE_VERSION
		);

		if ( ! $advanced_cache_needs_install && ! $advanced_cache_needs_update ) {
			return [
				'errors' => $errors,
				'infos' => $infos,
			];
		}

		if ( $advanced_cache->install_advanced_cache() ) {
			if ( $advanced_cache_needs_install && ! \NitroPack\Integration\Hosting\WPEngine::detect() ) {
				$infos[] = array(
					'title' => esc_html__( 'File advanced-cache.php re-installed', 'nitropack' ),
					'msg' => esc_html__( 'The file /wp-content/advanced-cache.php was either missing or not the one generated by NitroPack. NitroPack re-installed its version of the file, so it can function properly. Possibly, there is another active page caching plugin in your system. For correct operation, please deactivate any other page caching plugins.', 'nitropack' ),
					'actions' => '<a href="' . admin_url() . 'plugins.php" target="_blank" class="btn btn-secondary">' . esc_html__( 'Plugins page', 'nitropack' ) . '</a>',
					'classes' => $notification_class,
				);
				NitroPack::getInstance()->getLogger()->info( 'File advanced-cache.php re-installed.' );
			}

			return [
				'errors' => $errors,
				'infos' => $infos,
			];
		}

		if ( $advanced_cache_needs_install && ! $conflictingPlugins->nitropack_is_conflicting_plugin_active() ) {
			$errors[] = array(
				'title' => $notification_title,
				'msg' => __( 'Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
				'classes' => $notification_class,
			);
			NitroPack::getInstance()->getLogger()->error( 'Please make sure that the /wp-content/ directory is writable.' );
		} elseif ( $advanced_cache_needs_update ) {
			if ( $conflictingPlugins->nitropack_is_conflicting_plugin_active() ) {
				$errors[] = array(
					'title' => $notification_title,
					'msg' => esc_html__( 'The file /wp-content/advanced-cache.php cannot be created because a conflicting plugin is active. Please make sure to disable all conflicting plugins.', 'nitropack' ),
					'actions' => '<a href="' . admin_url() . 'plugins.php" target="_blank" class="btn btn-primary">Plugins page</a>',
					'classes' => $notification_class,
				);
				NitroPack::getInstance()->getLogger()->error( 'The file /wp-content/advanced-cache.php cannot be created because a conflicting plugin is active.' );
			} else {
				$errors[] = array(
					'title' => $notification_title,
					'msg' => esc_html__( 'The file /wp-content/advanced-cache.php cannot be created. Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
					'classes' => $notification_class,
				);
				NitroPack::getInstance()->getLogger()->error( 'The file /wp-content/advanced-cache.php cannot be created. Please make sure that the /wp-content/ directory is writable.' );
			}
		}

		return [
			'errors' => $errors,
			'infos' => $infos,
		];
	}


	/**
	 * Checks whether WP_CACHE is enabled and reports configuration issues.
	 *
	 * @return array{errors: array, warnings: array}
	 */
	private function wp_cache_notification() {
		$errors = [];
		$warnings = [];

		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			return [
				'errors' => $errors,
				'warnings' => $warnings,
			];
		}

		$notification_class = [ 'wp-cache' ];
		if ( \NitroPack\Integration\Hosting\Flywheel::detect() ) {
			$warnings[] = array(
				'title' => esc_html__( 'Constant WP_CACHE not enabled', 'nitropack' ),
				'msg' => esc_html__( 'Please go to your FlyWheel control panel and enable this setting.', 'nitropack' ),
				'actions' => '<a href="https://getflywheel.com/wordpress-support/how-to-enable-wp_cache/" target="_blank" class="btn btn-primary">View more</a>',
				'classes' => $notification_class,
			);
			NitroPack::getInstance()->getLogger()->notice( 'Constant WP_CACHE not enabled.' );
		} elseif ( ! \NitroPack\WordPress\CoreFiles::set_wp_cache_const( true ) ) {
			$errors[] = array(
				'title' => esc_html__( 'Constant WP_CACHE cannot be set', 'nitropack' ),
				'msg' => esc_html__( 'This can lead to slower cache delivery. Please make sure that the /wp-config.php file is writable and refresh this page.', 'nitropack' ),
				'classes' => $notification_class,
			);
			NitroPack::getInstance()->getLogger()->error( 'Constant WP_CACHE cannot be set.' );
		}

		return [
			'errors' => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Checks whether NitroPack data directories can be initialized.
	 *
	 * @return array
	 */
	private function data_directories_notification() {
		if ( ! get_nitropack()->dataDirExists() && ! get_nitropack()->initDataDir() ) {
			NitroPack::getInstance()->getLogger()->error( 'NitroPack data directory cannot be created.' );
			return [
				[
					'title' => esc_html__( 'NitroPack data directory cannot be created', 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
					'classes' => [ 'np-data-dir' ],
				]
			];
		}

		if ( ! get_nitropack()->pluginDataDirExists() && ! get_nitropack()->initPluginDataDir() ) {
			NitroPack::getInstance()->getLogger()->error( 'NitroPack plugin data directory cannot be created.' );
			return [
				[
					'title' => esc_html__( 'NitroPack plugin data directory cannot be created', 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/ directory is writable and refresh this page.', 'nitropack' ),
					'classes' => [ 'np-data-dir' ],
				]
			];
		}

		return [];
	}

	/**
	 * Whenever a plugin has been activated, deactivated, a notice will appear to suggest to purge the cache.
	 * @return array{actions: string, classes: array, msg: mixed, title: mixed[]}
	 */
	private function plugin_activity_notification() {
		$warnings = [];

		if ( isset( $_COOKIE['nitropack_apwarning'] ) ) {
			$cookie_path = nitropack_cookiepath();
			$warnings[] = array(
				'title' => esc_html__( "Plugins activity", 'nitropack' ),
				'msg' => esc_html__( 'It seems plugins have been activated, deactivated or updated. It is recommended that you purge the cache to reflect the latest changes.', 'nitropack' ),
				'actions' => "<a class=\"btn btn-secondary\" href=\"javascript:void(0);\" id=\"np-onstate-cache-purge\" onclick=\"document.cookie = 'nitropack_apwarning=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=" . $cookie_path . "';window.location.reload();\">" . esc_html__( 'Dismiss', 'nitropack' ) . "</a>",
				'classes' => [ 'plugins-state' ],
			);
			NitroPack::getInstance()->getLogger()->notice( "Plugin activity detected: new activations, updates, or deletions." );
		}

		return $warnings;
	}

	/**
	 * Displayed whenever the test mode is activated
	 * @return array{classes: array, msg: mixed, title: mixed[]}
	 */
	private function test_mode_notification() {
		$warnings = [];

		if ( TestMode::getInstance()->is_test_mode_enabled() ) {
			$safeModeMessage = __( 'Visitors are accessing your unoptimized pages. Make sure to disable it once you are done testing.', 'nitropack' );
			if ( get_nitropack()->getDistribution() === "oneclick" ) {
				$safeModeMessage = apply_filters( "nitropack_oneclick_safemode_message", $safeModeMessage );
			}

			$warnings[] = array(
				'title' => esc_html__( "Test Mode Enabled", 'nitropack' ),
				'msg' => $safeModeMessage,
				'classes' => [ 'test-mode' ],
			);

		}
		return $warnings;
	}

	/**
	 * Displayed whenever LiteSpeed .htaccess changes are needed
	 * @return array{classes: array, msg: mixed, title: mixed[]}
	 */
	private function litespeed_htaccess_notification() {
		$errors = [];

		if ( apply_filters( 'nitropack_needs_htaccess_changes', false ) ) {
			if ( ! \NitroPack\WordPress\CoreFiles::set_htaccess_rules( true ) ) {
				$errors[] = array(
					'title' => esc_html__( "LiteSpeed configuration needed", 'nitropack' ),
					'msg' => esc_html__( 'NitroPack is optimizing your pages but it can\'t set up the caching rules your LiteSpeed server needs. Your site will work but it will be slower than it should be. Make .htaccess writable and reload this page to fix it.', 'nitropack' ),
					'actions' => '<a href="https://support.nitropack.io/en/articles/14301910-how-nitropack-works-with-litespeed-servers/" target="_blank" class="btn btn-primary">How to fix this</a>',
					'classes' => [ 'litespeed' ],
				);
				NitroPack::getInstance()->getLogger()->error( "LiteSpeed configuration needed." );
			}
		}

		return $errors;
	}

	/**
	 * Creates or updates the NitroPack site configuration and configures webhooks.
	 *
	 * @return array{errors: array, warnings: array, site_config: array|null, site_id: string, site_secret: string, blog_id: int, webhook_token: string, check_mismatch: bool, upgrade_notice_id: string|null}
	 */
	private function site_config_notification() {
		$errors = [];
		$warnings = [];
		$siteConfig = nitropack_get_site_config();
		$siteId = $siteConfig ? $siteConfig['siteId'] : null;
		$siteSecret = $siteConfig ? $siteConfig['siteSecret'] : null;
		$webhookToken = esc_attr( get_option( 'nitropack-webhookToken' ) );
		$blogId = get_current_blog_id();
		$isConfigOutdated = ! nitropack_is_config_up_to_date();
		$configExists = get_nitropack()->Config->exists();
		$upgradeNoticeId = null;
		$shouldConfigureWebhooks = false;

		if ( ! $configExists ) {
			if ( ! get_nitropack()->updateCurrentBlogConfig( $siteId, $siteSecret, $blogId ) ) {
				$errors[] = array(
					'title' => esc_html__( 'NitroPack static config file cannot be created', 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/config-nitropack/ directory is writable and refresh this page.', 'nitropack' ),
				);
				NitroPack::getInstance()->getLogger()->error( 'NitroPack static config file cannot be created.' );
			} elseif ( $isConfigOutdated ) {
				$shouldConfigureWebhooks = true;
			}
		} elseif ( $isConfigOutdated ) {
			$shouldConfigureWebhooks = true;
		}

		if ( $shouldConfigureWebhooks ) {
			if ( ! get_nitropack()->updateCurrentBlogConfig( $siteId, $siteSecret, $blogId ) ) {
				$errors[] = array(
					'title' => esc_html__( 'NitroPack static config file cannot be updated', 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that the /wp-content/config-[hash]-nitropack/ directory is writable and refresh this page.', 'nitropack' ),
				);
				NitroPack::getInstance()->getLogger()->error( 'NitroPack static config file cannot be updated.' );
			} else {
				if ( ! $siteConfig ) {
					/* If config.json is missing, we update it and send an update event to the app. */
					nitropack_event( 'update' );
				} else {
					$prevVersion = ! empty( $siteConfig['pluginVersion'] ) ? $siteConfig['pluginVersion'] : '1.1.4 or older';
					nitropack_event( 'update', null, [ 'previous_version' => $prevVersion ] );
					if ( empty( $siteConfig['pluginVersion'] ) || version_compare( $siteConfig['pluginVersion'], '1.4', '<' ) ) {
						$upgradeNoticeId = 'nitropack_upgrade_to_1_3';
					}
				}
			}

			try {
				$webhooks = new \NitroPack\WordPress\Webhooks();
				$webhooks->nitropack_setup_webhooks( get_nitropack_sdk(), $webhookToken );
			} catch (\NitroPack\SDK\WebhookException $e) {
				$warnings[] = array(
					'title' => esc_html__( 'Unable to configure webhooks', 'nitropack' ),
					'msg' => esc_html__( 'This can impact the stability of the plugin. Please disconnect and connect again in order to retry configuring the webhooks.', 'nitropack' ),
				);
				NitroPack::getInstance()->getLogger()->notice( 'Unable to configure webhooks.' );
			}
		}

		return [
			'errors' => $errors,
			'warnings' => $warnings,
			'site_config' => $siteConfig,
			'site_id' => $siteId,
			'site_secret' => $siteSecret,
			'blog_id' => $blogId,
			'webhook_token' => $webhookToken,
			'check_mismatch' => $configExists && ! $isConfigOutdated,
			'upgrade_notice_id' => $upgradeNoticeId,
		];
	}
	/**
	 * Displayed whenever the config.json file is outdated or cannot be updated.
	 * @param mixed $siteConfig The site configuration.
	 * @param mixed $siteId The site ID.
	 * @param mixed $siteSecret The site secret.
	 * @param mixed $blogId The blog ID if multisite
	 * @return array{actions: string, classes: array, msg: mixed, title: mixed[]}
	 */
	private function config_mismatch_notification( $siteConfig, $siteId, $siteSecret, $blogId ) {
		$errors = [];

		$optionsMismatch = false;
		if ( array_key_exists( 'options_cache', $siteConfig ) ) {
			foreach ( NitroPack::$optionsToCache as $opt ) {
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
				NitroPack::getInstance()->getLogger()->error( "NitroPack static config file cannot be updated." );
			}
		}
		return $errors;
	}
	/**
	 * Displayed whenever the webhook token is not matching the one in the NitroPack app.
	 * @param mixed $siteConfig The site configuration.
	 * @param mixed $webhookToken The webhook token.
	 * @return array{actions: string, classes: array, msg: mixed, title: mixed[]}
	 */
	private function connection_problems( $siteConfig, $webhookToken ) {
		$warnings = [];

		if ( empty( $_COOKIE["nitropack_webhook_sync"] ) || ! $siteConfig["webhookToken"] ) {
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
									'msg' => esc_html__( 'Most likely you have used the same API credentials to connect another website (e.g. dev or staging). Click to restore the connection to this site.', 'nitropack' ),
									'actions' => '<a id="nitro-restore-connection-btn" class="btn btn-warning">Restore connection</a>',
								);
								NitroPack::getInstance()->getLogger()->notice( "Connection problems detected. Webhook token mismatch." );
							}
						}
					}
				} catch (\Exception $e) {
					//Do nothing
				}
			}
		}

		return $warnings;
	}
	/**
	 * Displayed when .htaccess cannot be modified.
	 * @return array{classes: array, msg: mixed, title: mixed[]}
	 */
	private function htaaccess_notification() {
		$errors = [];
		if ( apply_filters( 'nitropack_should_modify_htaccess', false ) && ( empty( $_SERVER["NitroPackHtaccessVersion"] ) || NITROPACK_VERSION != $_SERVER["NitroPackHtaccessVersion"] ) ) {
			if ( ! \NitroPack\WordPress\CoreFiles::set_htaccess_rules( true ) ) {
				$errors[] = array(
					'title' => esc_html__( "The .htaccess file cannot be modified", 'nitropack' ),
					'msg' => esc_html__( 'Please make sure that it is writable and refresh this page.', 'nitropack' ),
				);
				NitroPack::getInstance()->getLogger()->error( "The .htaccess file cannot be modified." );
			}
		}
		return $errors;
	}
	/**
	 * Cloudflare APO
	 * @return array{msg: mixed, title: mixed[]}
	 */
	private function cloudflare_apo_notification() {
		$warnings = [];

		if ( \NitroPack\Integration\Plugin\Cloudflare::isApoActive() && ! \NitroPack\Integration\Plugin\Cloudflare::isApoCacheByDeviceTypeEnabled() ) {
			$warnings[] = array(
				'title' => esc_html__( "Cache By Device Type is not active", 'nitropack' ),
				'msg' => esc_html__( 'It seems Cache By Device Type is not active with the Cloudflare APO. It is recommended that you enable it for a more optimized experience.', 'nitropack' ),
			);
			NitroPack::getInstance()->getLogger()->notice( "Cache By Device Type is not activate with the Cloudflare APO." );
		}

		return $warnings;
	}
}
