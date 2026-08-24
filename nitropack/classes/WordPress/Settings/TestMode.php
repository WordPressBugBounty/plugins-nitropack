<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\WordPress\NitroPack;

class TestMode {

	/**
	 * Instance of the class when initialized repeatedly. Used to implement singleton pattern.
	 * @var TestMode $instance
	 */
	private static $instance = null;

	public function __construct() {
		add_action( 'wp_ajax_nitropack_safemode_status', [ $this, 'nitropack_safemode_status' ] );
		add_action( 'wp_ajax_nitropack_enable_safemode', [ $this, 'nitropack_enable_safemode' ] );
		add_action( 'wp_ajax_nitropack_disable_safemode', [ $this, 'nitropack_disable_safemode' ] );
	}
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new TestMode();
		}

		return self::$instance;
	}

	/* Checks test mode in Settings page every visit */
	public function nitropack_safemode_status( $dontExit = false ) {
		nitropack_verify_ajax_nonce( $_REQUEST );
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$isEnabled = $nitro->getApi()->isSafeModeEnabled();
			} catch (\Exception $e) {
				if ( ! $dontExit ) {
					NitroPack::getInstance()->getLogger()->error( 'Test mode cannot be ' . ( $isEnabled ? 'enabled' : 'disabled' ) );
					nitropack_json_and_exit( array(
						"type" => "error",
						"message" => nitropack_admin_toast_msgs( 'success' )
					) );
				}
				return null;
			}

			if ( ! $dontExit ) {
				nitropack_json_and_exit( array(
					"type" => "success",
					"isEnabled" => $isEnabled,
				) );
			}
			return $isEnabled;
		}

		if ( ! $dontExit ) {
			NitroPack::getInstance()->getLogger()->error( 'There was an SDK error while fetching status of safe mode' );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => __( 'Error! There was an SDK error while fetching status of safe mode!', 'nitropack' )
			) );
		}
		return null;
	}

	/**
	 * Check if the user has test mode enabled
	 *
	 * @return bool
	 */
	public function is_test_mode_enabled(): bool {
		try {
			$nitro = get_nitropack_sdk();
			if ( ! $nitro ) {
				// Return the default value (not enabled) in case we can't get the SDK.
				return false;
			}

			if ( isset( $nitro->getConfig()->SafeMode ) ) {
				return (bool) $nitro->getConfig()->SafeMode;
			}

			nitropack_fetch_config();
			return (bool) $nitro->getApi()->isSafeModeEnabled();
		} catch (\Exception $e) {
			NitroPack::getInstance()->getLogger()->error( 'There was an SDK error while fetching status of safe mode: ' . $e );
			// Return the default value (not enabled) in case of error.
			return false;
		}
	}

	public function nitropack_enable_safemode() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->enableSafeMode();
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Test mode cannot be enabled. Error: ' . $e );
			}

			NitroPack::getInstance()->getLogger()->notice( 'Test mode is enabled' );
			nitropack_json_and_exit( array(
				"type" => "success",
				"message" => nitropack_admin_toast_msgs( 'success' )

			) );
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => nitropack_admin_toast_msgs( 'error' )
		) );
	}

	public function nitropack_disable_safemode() {
		nitropack_verify_ajax_nonce( $_REQUEST );


		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->disableSafeMode();
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Test mode cannot be disabled. Error: ' . $e );
			}

			NitroPack::getInstance()->getLogger()->notice( 'Test mode is disabled' );
			nitropack_json_and_exit( array(
				"type" => "success",
				"message" => nitropack_admin_toast_msgs( 'success' )
			) );
		}
		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => nitropack_admin_toast_msgs( 'error' )
		) );
	}
	public function render() {
		?>
		<div class="nitro-option" id="test-mode-widget">
			<div class="nitro-option-main">
				<div class="text-box" id="safemode-status-slider">
					<h6><?php esc_html_e( 'Test Mode', 'nitropack' ); ?></h6>
					<p><?php esc_html_e( 'Test NitroPack\'s features without affecting your visitors\' experience', 'nitropack' ); ?>.
						<a href="https://support.nitropack.io/en/articles/8390292-test-mode" class="text-blue"
							target="_blank"><?php esc_html_e( 'Learn more', 'nitropack' ); ?></a>
					</p>
				</div>
				<?php $components = new Components();
				$components->render_toggle( 'safemode-status', $this->is_test_mode_enabled() );
				?>
			</div>
			<div class="msg-container hidden" id="loading-safemode-status">
				<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'assets/img/loading.svg'; ?>" alt="loading"
					class="icon">
				<?php esc_html_e( 'Loading test mode status', 'nitropack' ); ?>
			</div>
			<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-test-mode.php'; ?>
		</div>
		<?php
	}
	/**
	 * Used in Admin.php to localize the translations for np_settings.js, used in modal-test-mode.php
	 * @return array{disable_test_mode_action_btn: mixed, disable_test_mode_close_btn: mixed, disable_test_mode_heading: mixed, disable_test_mode_text: mixed, enable_test_mode_action_btn: mixed, enable_test_mode_cancel_btn: mixed, enable_test_mode_footer_text: mixed, enable_test_mode_heading: mixed, enable_test_mode_highlight_text: mixed, enable_test_mode_text: mixed}
	 */
	public static function modal_ajax_translations() {
		return array(
			//enable
			'enable_test_mode_heading' => esc_html__( 'Enable Test Mode', 'nitropack' ),
			'enable_test_mode_text' => esc_html__( 'When you enable Test Mode, we disable all NitroPack’s optimizations and your site visitors are accessing your regular, unoptimized URLs.', 'nitropack' ),
			'enable_test_mode_highlight_text' => esc_html__( 'To view how a NitroPack optimised page will load and behave simply append <b>?testnitro=1</b> to any URL (e.g. https://yourwebsite.com/?testnitro=1)', 'nitropack' ),
			'enable_test_mode_footer_text' => esc_html__( 'This allows you to assess and fine-tune NitroPack’s performance before implementing optimizations site-wide.', 'nitropack' ),
			'enable_test_mode_cancel_btn' => esc_html__( 'Cancel', 'nitropack' ),
			'enable_test_mode_action_btn' => esc_html__( 'Enable', 'nitropack' ),
			//disable testmode
			'disable_test_mode_heading' => esc_html__( 'Purge cache after disabling Test Mode', 'nitropack' ),
			'disable_test_mode_text' => esc_html__( 'If you have made changes to NitroPack configuration or your website while you were using test mode, we recommend you to purge your cache. In this way we will update NitroPack cache with your recent changes.', 'nitropack' ),
			'disable_test_mode_close_btn' => esc_html__( 'I will do it later', 'nitropack' ),
			'disable_test_mode_action_btn' => esc_html__( 'Purge cache now', 'nitropack' ),
			// Disconnect/Deactivate modal - Test Mode texts
			'disconnect_modal_layout_issue_testmode' => esc_html__( 'Test Mode is already on so your visitors are browsing without NitroPack\'s optimizations. Open a private window to see what they see. If the issue is no longer reproducible, reach out to support', 'nitropack' ),
			'disconnect_modal_broken_website_testmode' => esc_html__( 'Test Mode is already on so your visitors are browsing without NitroPack\'s optimizations. Open a private window to see what they see. If the issue is no longer reproducible, reach out to support', 'nitropack' ),
			'disconnect_modal_site_maintenance_testmode' => esc_html__( 'Test Mode is already active. Your visitors are seeing your site without NitroPack\'s optimizations, so you can make changes safely.', 'nitropack' ),
			// Disconnect/Deactivate modal - Standard texts
			'disconnect_modal_layout_issue_standard' => esc_html__( 'Enable Test Mode to check if NitroPack is causing it. If it is, our team can help.', 'nitropack' ),
			'disconnect_modal_broken_website_standard' => esc_html__( 'Enable Test Mode to check if NitroPack is causing it. If it is, our team can help.', 'nitropack' ),
			'disconnect_modal_speed_issue_standard' => esc_html__( 'Your site may need a different configuration. Our team can take a look and optimize it for you.', 'nitropack' ),
			'disconnect_modal_site_maintenance_standard' => esc_html__( 'Enable Test Mode to serve your original site to visitors while you do maintenance. You can switch back with one click.', 'nitropack' ),
			'disconnect_modal_just_deactivate_standard' => esc_html__( 'No problem. You can %s anytime from the plugin settings.', 'nitropack' ),
			'disconnect_modal_different_plugin_standard' => esc_html__( 'We\'d love to learn from this. Which plugin are you switching to?', 'nitropack' ),
			'disconnect_modal_something_else_standard' => esc_html__( 'Is there anything we can improve?', 'nitropack' ),
			// Disconnect modal - disconnected texts
			'disconnect_modal_layout_issue_disconnected' => esc_html__( 'NitroPack is disconnected so Test Mode is not available. If you think NitroPack was causing this, our support team can help you get back up and running with a working configuration.', 'nitropack' ),
			'disconnect_modal_broken_website_disconnected' => esc_html__( 'NitroPack is disconnected so Test Mode is not available. If you think NitroPack was causing this, our support team can help you get back up and running with a working configuration.', 'nitropack' ),
			'disconnect_modal_site_maintenance_disconnected' => esc_html__( 'NitroPack is already disconnected so your visitors are seeing your site without optimizations. You can make your changes safely.', 'nitropack' ),
		);
	}
}