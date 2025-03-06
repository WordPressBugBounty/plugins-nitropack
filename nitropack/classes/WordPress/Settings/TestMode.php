<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\WordPress\NitroPack;

class TestMode {
	private static $instance = NULL;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_safemode_status', [ $this, 'nitropack_safemode_status' ] );
		add_action( 'wp_ajax_nitropack_enable_safemode', [ $this, 'nitropack_enable_safemode' ] );
		add_action( 'wp_ajax_nitropack_disable_safemode', [ $this, 'nitropack_disable_safemode' ] );
		add_action( 'plugins_loaded', [ $this, 'nitropack_offer_safemode' ] );
	}
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new TestMode();
		}

		return self::$instance;
	}
	/* Offer test mode instead of disabling NitroPack from Plugins page */
	public function nitropack_offer_safemode() {
		global $pagenow;
		if ( $pagenow == 'plugins.php' ) {
			$smStatus = get_option( 'nitropack-safeModeStatus', "-1" );
			if ( $smStatus === "0" ) {
				add_action( 'admin_enqueue_scripts', function () {
					wp_enqueue_script( 'np_safemode', NITROPACK_PLUGIN_DIR_URL . 'view/javascript/np_safemode.js', array( 'jquery' ) );
					wp_enqueue_style( 'np_safemode', NITROPACK_PLUGIN_DIR_URL . 'view/stylesheet/safemode.min.css' );
				} );
				add_action( 'admin_footer', function () {
					require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-safemode.php';
				} );
			}
		}
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
				return NULL;
			}

			$this->nitropack_cache_safemode_status( $isEnabled );
			if ( ! $dontExit ) {
				nitropack_json_and_exit( array(
					"type" => "success",
					"isEnabled" => $isEnabled,
				) );
			}
			return $isEnabled;
		}

		$this->nitropack_cache_safemode_status();
		if ( ! $dontExit ) {
			NitroPack::getInstance()->getLogger()->error( 'There was an SDK error while fetching status of safe mode' );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => __( 'Error! There was an SDK error while fetching status of safe mode!', 'nitropack' )
			) );
		}
		return NULL;
	}

	public function nitropack_cache_safemode_status( $operation = null ) {
		$sm = "-1";
		if ( is_bool( $operation ) ) {
			$sm = $operation ? '1' : '0';
		}
		return update_option( 'nitropack-safeModeStatus', $sm );
	}

	public function nitropack_enable_safemode() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->enableSafeMode();
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Test mode cannot be enabled. Error: ' . $e );
			}

			$this->nitropack_cache_safemode_status( true );
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

			$this->nitropack_cache_safemode_status( false );
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
		$test_mode_option = get_option( 'nitropack-safeModeStatus' );
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

				<label class="inline-flex items-center cursor-pointer ml-auto">
					<input type="checkbox" class="sr-only peer" id="safemode-status" <?php echo (int) $test_mode_option === 1 ? "checked" : ""; ?>>

					<div class="toggle"></div>
				</label>
			</div>
			<div class="msg-container hidden" id="loading-safemode-status">
				<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/loading.svg'; ?>" alt="loading"
					class="icon">
				<?php esc_html_e( 'Loading test mode status', 'nitropack' ); ?>
			</div>
			<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-test-mode.php'; ?>
		</div>
		<?php
	}
}