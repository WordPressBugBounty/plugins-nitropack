<?php

namespace NitroPack\WordPress;

use NitroPack\Util\Utils;
/**
 * Core WordPress admin functionality for NitroPack plugin
 */
class Admin {
	/**
	 * Instance of the Admin class
	 * @var Admin $instance
	 */
	private static $instance = null;

	private function __construct() {
		add_action( 'init', [ $this, 'nitropack_load_textdomain' ] );
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_filter( 'parent_file', [ $this, 'highlight_submenus' ] );
		add_filter( 'plugin_action_links_' . NITROPACK_BASENAME, [ $this, 'nitropack_action_links' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'load_nitropack_scripts_styles' ] );
		add_action( 'plugins_loaded', [ $this, 'deactivate_plugin_assets' ] );
		add_action( 'update_option_active_plugins', [ $this, 'update_platform_in_app' ], 10, 3 );
		//display admin topbar menu on non admin pages as well
		add_action( 'init', function () {
			( new \NitroPack\WordPress\Cron() )->schedule_events();
			if ( current_user_can( 'manage_options' ) ) {
				// Enqueue admin bar menu custom stylesheet
				add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_topbar_admin_menu_stylesheet' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_topbar_admin_menu_stylesheet' ] );

				// Enqueue admin menu custom javascript
				add_action( 'wp_enqueue_scripts', [ $this, 'nitropack_admin_bar_script' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'nitropack_admin_bar_script' ] );

				// Add our admin menu bar entry
				add_action( 'admin_bar_menu', [ $this, 'topbar_admin_menu' ], PHP_INT_MAX - 10 );

				//remove external notices in nitropack dashboard
				add_action( 'in_admin_header', [ $this, 'remove_external_notices_in_nitropack_dashboard' ] );
			}
		} );
	}

	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Load text domain for translations
	 * @return void
	 */
	public function nitropack_load_textdomain() {
		global $l10n;

		$domain = 'nitropack';

		if ( isset( $l10n[ $domain ] ) ) {
			return;
		}

		load_plugin_textdomain( $domain, false, dirname( plugin_basename( NITROPACK_FILE ) ) . '/languages/' );
	}
	/**
	 * Render NitroPack admin templates. 
	 * Templates: Connect, Dashboard, System Report
	 */
	public function templates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'nitropack' ) );
		}

		if ( get_nitropack()->isConnected() ) {
			$helpLabels = [ 'wordpress_plugin_help' ];
			if ( get_nitropack()->getDistribution() == "oneclick" ) {
				$helpLabels = [ 'wordpress_plugin_help_oneclick' ];
				$oneClickVendorWidget = apply_filters( "nitropack_oneclick_vendor_widget", "" );
				include plugin_dir_path( NITROPACK_FILE ) . nitropack_trailingslashit( 'view' ) . 'oneclick.php';
			} else {
				include plugin_dir_path( NITROPACK_FILE ) . nitropack_trailingslashit( 'view' ) . 'admin.php';
			}

		} else {
			if ( get_nitropack()->getDistribution() == "oneclick" ) {
				$oneClickConnectUrl = apply_filters( "nitropack_oneclick_connect_url", "" );
				include plugin_dir_path( NITROPACK_FILE ) . nitropack_trailingslashit( 'view' ) . 'connect-oneclick.php';
			} else {
				include plugin_dir_path( NITROPACK_FILE ) . nitropack_trailingslashit( 'view' ) . 'connect.php';
			}
		}
	}
	/**
	 * Add submenu pages under NitroPack menu
	 * @return void
	 */
	public function menu() {
		global $submenu;

		add_menu_page(
			'NitroPack Options',
			'NitroPack',
			'manage_options',
			'nitropack',
			[ $this, 'templates' ],
			'dashicons-performance',
			25
		);
		if ( get_nitropack()->getDistribution() !== "oneclick" ) {
			add_submenu_page(
				'nitropack',
				'System Report',
				'System Report',
				'manage_options',
				'admin.php?page=nitropack&subpage=system-report'
			);
		}
		if ( isset( $submenu['nitropack'] ) ) {
			foreach ( $submenu['nitropack'] as &$item ) {
				if ( $item[0] === 'NitroPack' ) {
					$item[0] = 'Dashboard';
				}
			}
		}
	}

	/**
	 * Hightlight submenu items when on a subpage
	 * @param mixed $parent_file
	 */
	public function highlight_submenus( $parent_file ) {
		if ( isset( $_GET['page'] ) && isset( $_GET['subpage'] ) ) {
			global $submenu_file;
			$submenu_file = 'admin.php?page=' . $_GET['page'] . '&subpage=' . $_GET['subpage'];
		}

		return $parent_file;
	}

	/**
	 * Display action links in Plugins => NitroPack
	 * @param mixed $links
	 * @return array
	 */
	public function nitropack_action_links( $links ) {
		$nitroLinks = array(
			'<a href="https://support.nitropack.io/hc/en-us/categories/360005122034-Frequently-Asked-Questions-FAQs-" target="_blank" rel="noopener noreferrer">FAQ</a>',
			'<a href="https://support.nitropack.io/hc/en-us" target="_blank" rel="noopener noreferrer">Docs</a>',
			'<a href="https://support.nitropack.io/en/" target="_blank" rel="noopener noreferrer">Support</a>',
		);

		if ( get_nitropack()->getDistribution() == "oneclick" ) {
			$nitroLinks = apply_filters( "nitropack_oneclick_action_links", $nitroLinks );
		}

		array_unshift( $nitroLinks, '<a href="' . admin_url( 'admin.php?page=nitropack' ) . '" rel="noopener noreferrer">Settings</a>' );

		return array_merge( $nitroLinks, $links );
	}
	public static function support_link() {
		$url = "https://support.nitropack.io/en/";
		if ( get_nitropack()->getDistribution() == "oneclick" ) {
			$url = "https://wpengine.com/support/nitropack-documentation/";
		}
		return $url;
	}
	/**
	 * Summary of localized_common_data
	 * @return array
	 */
	private function localized_common_data() {
		$data = [
			'nitroNonce' => wp_create_nonce( NITROPACK_NONCE ),
			'nitro_plugin_url' => plugin_dir_url( NITROPACK_FILE ),
			'error_msg' => esc_html__( 'Something went wrong.', 'nitropack' ),
			'success_msg' => esc_html__( 'Settings updated.', 'nitropack' ),
		];
		return $data;
	}
	/**
	 * Load assets (js/css)
	 * @param mixed $page
	 * @return void
	 */
	public function load_nitropack_scripts_styles( $page ) {
		//global WP
		wp_enqueue_style( 'nitropack-notifications', plugin_dir_url( NITROPACK_FILE ) . 'assets/css/nitro-notifications.min.css', array(), NITROPACK_VERSION );
		wp_enqueue_script( 'nitropack_notices_js', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/np_notices.min.js', array(), NITROPACK_VERSION, true );
		wp_localize_script( 'nitropack_notices_js', 'nitropack_notices_vars', array(
			'nonce' => wp_create_nonce( NITROPACK_NONCE ),
		) );
		//plugin only
		if ( $page === 'toplevel_page_nitropack' ) {
			//css
			wp_enqueue_style( 'nitropack', plugin_dir_url( NITROPACK_FILE ) . 'assets/css/style.min.css', array(), NITROPACK_VERSION );
			wp_add_inline_style( 'nitropack', '#modal-modes .modal-container{height:min(80vh,calc(100vh - 2rem));height:min(80dvh,calc(100dvh - 2rem))}#modal-modes .modal-inner{height:100%}#modal-modes .modal-body{flex:1 1 auto;overflow:hidden}#modal-modes .modern-table{display:flex;flex-direction:column;min-height:0}#modal-modes .modern-table .scrollbar-default{flex:1 1 auto;min-height:0;max-height:none!important}' );
			wp_enqueue_style( 'nitropack-connect', plugin_dir_url( NITROPACK_FILE ) . 'assets/css/connect.min.css', array(), NITROPACK_VERSION );
			//json animations
			wp_enqueue_script( 'lottie', 'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js', array(), null, false );
			//js
			wp_enqueue_script( 'nitropack_ui', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/nitropackUI.min.js', array(), NITROPACK_VERSION, true );
			if ( get_nitropack()->isConnected() ) {
				$passed_onboarding = get_option( 'nitropack-onboardingPassed' );
				if ( ! $passed_onboarding ) {
					wp_enqueue_script( 'nitropack_preview_site', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/preview_site.min.js', array( 'nitropack_ui' ), NITROPACK_VERSION, true );
					wp_localize_script(
						'nitropack_preview_site',
						'np_onboarding',
						array(
							'nitro_plugin_url' => plugin_dir_url( NITROPACK_FILE ),
							'select_mode' => esc_html__( 'Select Mode', 'nitropack' ),
							'active_mode' => esc_html__( 'Active Mode', 'nitropack' ),
							'switching_mode' => esc_html__( 'Switching Optimization Mode.', 'nitropack' ),
							'est_cachewarmup_msg' => esc_html__( 'Estimating optimizations usage', 'nitropack' )
						)
					);
				}
				//dashboard page
				if ( ! isset( $_GET['subpage'] ) ) {
					wp_enqueue_style( 'np-select', plugin_dir_url( NITROPACK_FILE ) . 'assets/css/np_select2.min.css', array( 'nitropack' ), NITROPACK_VERSION );
					wp_enqueue_script( 'np-select', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/np_select2.min.js', array( 'jquery' ), NITROPACK_VERSION, true );
					//js
					wp_enqueue_script( 'nitropack_deactivate_plugin', NITROPACK_PLUGIN_DIR_URL . 'assets/js/disconnect_deactivate_plugin.min.js', array( 'jquery' ) );
					wp_enqueue_script( 'nitropack_settings', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/np_settings.min.js', array( 'np-select' ), NITROPACK_VERSION, true );
					wp_localize_script( 'nitropack_deactivate_plugin',
						'nitropackDisconnectModalTexts',
						(array) Settings\TestMode::modal_ajax_translations()
					);
					wp_localize_script( 'nitropack_settings',
						'np_settings',
						array_merge(
							$this->localized_common_data(),
							(array) Settings\TestMode::modal_ajax_translations(),
							array(
								'est_cachewarmup_msg' => esc_html__( 'Estimating optimizations usage', 'nitropack' ),
								'quickSetupSaveUrl' => get_nitropack_integration_url( "quicksetup" ),
								//compression status
								'testing_compression' => esc_html__( 'Testing current compression status', 'nitropack' ),
								'compression_already_enabled' => esc_html__( 'Compression is already enabled on your server! There is no need to enable it in NitroPack.', 'nitropack' ),
								'compression_not_detected' => esc_html__( 'No compression was detected! We will now enable it in NitroPack.', 'nitropack' ),
								'compression_not_determined' => esc_html__( 'Could not determine compression status automatically. Please configure it manually.', 'nitropack' )
							)
						)
					);
				}
				//system report page
				if ( isset( $_GET['subpage'] ) && $_GET['subpage'] === 'system-report' ) {
					wp_enqueue_script( 'nitropack_system_report', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/system_report.min.js', array(), NITROPACK_VERSION, true );
					wp_localize_script(
						'nitropack_system_report',
						'np_system_report',
						array_merge(
							$this->localized_common_data(),
							array(
								'report_empty_options' => esc_html__( 'Please select at least one of the report options.', 'nitropack' ),
								'report_success' => esc_html__( 'Report generated successfully.', 'nitropack' ),
								'report_empty' => esc_html__( 'Response is empty. Report generation failed.', 'nitropack' ),
								'report_error' => esc_html__( 'There was an error while generating the report.', 'nitropack' ),
							)
						)
					);
				}
			}
		}

		/* Enqueue single post purge/invalidation script */
		if ( get_nitropack()->isConnected() ) {
			$CPTOptimization = Settings\CPTOptimization::getInstance();
			$cacheableObjectTypes = $CPTOptimization->nitropack_get_cacheable_object_types();

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$current_post_type = $screen && ! empty( $screen->post_type ) ? $screen->post_type : ( $GLOBALS['typenow'] ?? $GLOBALS['post_type'] ?? null );


			/* Enqueue the post/page cache clearing script only on edit/add new post/page screens and only if the post type is cacheable. */
			if ( in_array( $page, [ 'post.php', 'post-new.php', 'edit.php' ], true ) && in_array( $current_post_type, $cacheableObjectTypes, true ) ) {
				$editor = get_option( "nitropack-canEditorClearCache" );
				$allowed_capabilities = current_user_can( 'manage_options' ) || ( $editor && current_user_can( 'editor' ) );
				if ( ! $allowed_capabilities ) {
					return;
				}
				wp_enqueue_script( 'nitropack_post_clear_cache', NITROPACK_PLUGIN_DIR_URL . 'assets/js/post_clear_cache.min.js?np_v=' . NITROPACK_VERSION, true );
				wp_localize_script(
					'nitropack_post_clear_cache',
					'np_post_clear_cache',
					array(
						'nitroNonce' => wp_create_nonce( NITROPACK_NONCE ),
						'nitro_plugin_url' => NITROPACK_PLUGIN_DIR_URL,
						'working' => esc_html__( 'Working...', 'nitropack' ),
						'success' => esc_html__( 'Success.', 'nitropack' ),
						'error' => esc_html__( 'Error.', 'nitropack' )
					)
				);
			}

		}
		// Elementor Tools page integration
		if ( $page === 'elementor_page_elementor-tools' && get_nitropack()->isConnected() ) {
			wp_enqueue_script(
				'nitropack_elementor_integration',
				plugin_dir_url( NITROPACK_FILE ) . 'assets/js/elementor_cache_integration.min.js',
				array( 'jquery' ),
				NITROPACK_VERSION,
				true
			);

			wp_localize_script(
				'nitropack_elementor_integration',
				'nitropack_elementor',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'nitropack_elementor_clear_cache' )
				)
			);
		}
	}

	/**
	 * Render the admin topbar menu
	 * @param \WP_Admin_Bar $wp_admin_bar
	 * @return void
	 */
	public function topbar_admin_menu( \WP_Admin_Bar $wp_admin_bar ) {
		if ( Utils::is_amp_page() ) {
			return;
		}
		$notifications = Notifications\Notifications::getInstance();
		$counter_data = $notifications->admin_bar_notices_counter();

		if ( ! get_nitropack()->isConnected() ) {
			$node = array(
				'id' => 'nitropack-top-menu',
				'title' => '&nbsp;&nbsp;<i style="" class="circle nitro nitro-status nitro-status-not-connected" aria-hidden="true"></i>&nbsp;&nbsp;NitroPack is disconnected',
				'href' => admin_url( 'admin.php?page=nitropack' ),
				'meta' => array(
					'class' => 'nitropack-menu'
				)
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'nitropack-top-menu',
					'id' => 'optimizations-plugin-status',
					'title' => __( 'Connect NitroPack&nbsp;&nbsp;', 'nitropack' ),
					'href' => admin_url( 'admin.php?page=nitropack' ),
					'meta' => array(
						'class' => 'nitropack-plugin-status',
					)
				)
			);
		} else {
			$test_mode = Settings\TestMode::getInstance();
			$title = '&nbsp;&nbsp;<i style="" class="circle nitro nitro-status nitro-status-' . $counter_data['status'] . ( $test_mode->is_test_mode_enabled() ? ' test-mode-on' : '' ) . '" aria-hidden="true"></i>&nbsp;&nbsp;NitroPack';
			if ( $counter_data['status'] != "ok" ) {
				$title .= ' <span class="circle-with-text nitro-color-issues" id="nitro-total-issues-count">' . ( $counter_data['issues'] + $counter_data['notifications'] ) . '</span>';
			} else if ( $counter_data['notifications'] > 0 ) {
				$title .= ' <span class="circle-with-text nitro-color-notification" id="nitro-total-issues-count">' . $counter_data['notifications'] . '</span>';
			}
			$node = array(
				'id' => 'nitropack-top-menu',
				'title' => $title,
				'href' => admin_url( 'admin.php?page=nitropack' ),
				'meta' => array(
					'class' => 'nitropack-menu'
				)
			);

			$settings_extra = '';

			if ( $counter_data['notifications'] ) {
				$settings_extra .= ' <span class="circle-with-text nitro-color-notification" id="nitro-notification-issues-count">' . $counter_data['notifications'] . '</span>';
			}
			$wp_admin_bar->add_node( array(
				'id' => 'nitropack-top-menu-settings',
				'parent' => 'nitropack-top-menu',
				'title' => __( 'Settings', 'nitropack' ) . ' ' . $settings_extra . '',
				'href' => admin_url( 'admin.php?page=nitropack' ),
				'meta' => array(
					'class' => 'nitropack-settings'
				)

			) );

			$wp_admin_bar->add_node(
				array(
					'id' => 'nitropack-top-menu-purge-entire-cache',
					'parent' => 'nitropack-top-menu',
					'title' => __( 'Purge Entire Cache', 'nitropack' ),
					'href' => '#',
					'meta' => array(
						'class' => 'nitropack-purge-cache-entire-site',
					),
				)
			);
			$wp_admin_bar->add_node(
				array(
					'id' => 'nitropack-top-menu-invalidate-entire-cache',
					'parent' => 'nitropack-top-menu',
					'title' => __( 'Invalidate Entire Cache', 'nitropack' ),
					'href' => '#',
					'meta' => array(
						'class' => 'nitropack-invalidate-cache-entire-site',
					),
				)
			);

			if ( ! is_admin() ) { // menu otions available when browsing front-end pages

				$wp_admin_bar->add_node(
					array(
						'parent' => 'nitropack-top-menu',
						'id' => 'optimizations-purge-cache',
						'title' => __( 'Purge Current Page', 'nitropack' ),
						'href' => "#",
						'meta' => array(
							'class' => 'nitropack-purge-cache',
						)
					)
				);

				$wp_admin_bar->add_node(
					array(
						'parent' => 'nitropack-top-menu',
						'id' => 'optimizations-invalidate-cache',
						'title' => __( 'Invalidate Current Page', 'nitropack' ),
						'href' => "#",
						'meta' => array(
							'class' => 'nitropack-invalidate-cache',
						)
					)
				);
			}

			if ( $counter_data['status'] != "ok" ) {
				$wp_admin_bar->add_node(
					array(
						'parent' => 'nitropack-top-menu',
						'id' => 'optimizations-plugin-status',
						'title' => 'Issues <span class="circle-with-text nitro-color-issues">' . $counter_data['issues'] . '</span>',
						'href' => admin_url( 'admin.php?page=nitropack' ),
						'meta' => array(
							'class' => 'nitropack-plugin-status',
						)
					)
				);
			}
		}
		$wp_admin_bar->add_node( $node );
	}
	public function nitropack_admin_bar_script() {
		if ( ! Utils::is_amp_page() ) {
			wp_enqueue_script( 'topbar_admin_menu_script', plugin_dir_url( NITROPACK_FILE ) . 'assets/js/admin_bar_menu.min.js?np_v=' . NITROPACK_VERSION, [ 'jquery' ], false, true );
			wp_localize_script( 'topbar_admin_menu_script', 'frontendajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'nitroNonce' => wp_create_nonce( NITROPACK_NONCE ), 'nitro_plugin_url' => plugin_dir_url( NITROPACK_FILE ) ) );
		}
	}


	public function enqueue_topbar_admin_menu_stylesheet() {
		if ( ! Utils::is_amp_page() ) {
			wp_enqueue_style( 'topbar_admin_menu_stylesheet', plugin_dir_url( NITROPACK_FILE ) . 'assets/css/admin_bar_menu.min.css?np_v=' . NITROPACK_VERSION );
		}
	}
	/* Deactivate modal */
	public function deactivate_plugin_assets() {
		global $pagenow;
		$test_mode = Settings\TestMode::getInstance();
		if ( $pagenow == 'plugins.php' ) {
			add_action( 'admin_enqueue_scripts', function () {
				wp_enqueue_script( 'nitropack_deactivate_plugin', NITROPACK_PLUGIN_DIR_URL . 'assets/js/disconnect_deactivate_plugin.min.js', array( 'jquery' ) );
				wp_enqueue_style( 'nitropack_deactivate_plugin', NITROPACK_PLUGIN_DIR_URL . 'assets/css/disconnect-deactivate-plugin.min.css' );
				wp_add_inline_style( 'nitropack_deactivate_plugin', '#disconnect-deactivate-plugin-modal .modal-container{max-height:min(80vh,calc(100vh - 2rem));max-height:min(80dvh,calc(100dvh - 2rem));background-color:#fff;border-radius:8px;overflow:hidden}#disconnect-deactivate-plugin-modal .modal-inner{display:flex;flex-direction:column;height:auto;max-height:inherit;min-height:0;background-color:#fff}#disconnect-deactivate-plugin-modal .modal-body{padding:0;height:auto;flex:0 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden}#disconnect-deactivate-plugin-modal #enable-test-mode,#disconnect-deactivate-plugin-modal #deactivate-np{display:inline-flex;align-items:center;justify-content:center;gap:.5rem}#disconnect-deactivate-plugin-modal .modal-action-status-icon{width:14px;height:14px}' );
				wp_localize_script(
					'nitropack_deactivate_plugin',
					'nitropackDisconnectModalTexts',
					array_merge(
						(array) Settings\TestMode::modal_ajax_translations(),
						array(
							'support_link' => self::support_link()
						)
					)
				);
			} );
			add_action( 'admin_footer', function () {
				require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-disconnect-deactivate-plugin.php';
			} );
		}
	}
	public function remove_external_notices_in_nitropack_dashboard() {
		$screen = get_current_screen();
		if ( $screen->id === 'toplevel_page_nitropack' ) {
			remove_all_actions( 'user_admin_notices' );
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}
	/**
	 * Send a notification to our app that the platform has been changed to/from WooCommerce/WordPress
	 * @param mixed $old_value
	 * @param mixed $value
	 * @param string $option
	 * @return void
	 */
	public function update_platform_in_app( $old_value, $value, string $option ) {
		if ( $old_value === $value )
			return;

		$activated_plugins = array_diff( $value, $old_value );
		$deactivated_plugins = array_diff( $old_value, $value );

		if ( in_array( 'woocommerce/woocommerce.php', $activated_plugins ) ) {
			nitropack_event( "platform_change", null, array( "platform" => 'WooCommerce' ) );
		} else if ( in_array( 'woocommerce/woocommerce.php', $deactivated_plugins ) ) {
			nitropack_event( "platform_change", null, array( "platform" => 'WordPress' ) );
		}
	}
}