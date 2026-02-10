<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;
use NitroPack\HttpClient\HttpClient;

class HTMLCompression {
	private static $instance = null;
	public $option_name;
	/**
	 * Get the singleton instance of the HTMLCompression class
	 *
	 * @return HTMLCompression
	 */
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new HTMLCompression();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_ajax_nitropack_test_compression_ajax', [ $this, 'nitropack_test_compression_ajax' ] );
		add_action( 'wp_ajax_nitropack_set_compression_ajax', [ $this, 'nitropack_set_compression_ajax' ] );

		$this->option_name = 'nitropack-enableCompression';
	}

	/**
	 * AJAX handler when toggling (saving) the compression option
	 * @return void
	 */
	public function nitropack_set_compression_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = (int) ! empty( $_POST["data"]["compressionStatus"] );
		$updated = update_option( $this->option_name, $option );

		if ( $updated ) {
			NitroPack::getInstance()->getLogger()->notice( 'HTML Compression is ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), "hasCompression" => $option ) );
		} else {
			NitroPack::getInstance()->getLogger()->error( 'HTML Compression cannot be ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}

	/**
	 * AJAX handler when testing the compression on page load.
	 * Most servers have compression enabled by default - br or gzip.
	 * If not, we force enable NitroPack GZIP compression.
	 * @return void
	 */
	public function nitropack_test_compression_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$hasCompression = true;
		try {
			if ( \NitroPack\Integration\Hosting\Flywheel::detect() ) { // Flywheel: Compression is enabled by default
				$hasCompression = true;
				update_option( $this->option_name, 0 );
				nitropack_json_and_exit( array( "type" => "success", "hasCompression" => $hasCompression ) );
			} else {
				/* Reset setting each time when testing */
				update_option( $this->option_name, 0 );

				require_once plugin_dir_path( NITROPACK_FILE ) . nitropack_trailingslashit( 'nitropack-sdk' ) . 'autoload.php';
				$http = new HTTPClient( get_site_url() );
				$http->setHeader( "X-NitroPack-Request", 1 );
				$http->timeout = 25;
				$http->fetch();
				$headers = $http->getHeaders();

				/* Check for content-encoding header - br, gzip, deflate, zstd, etc. Most servers support these and have it enabled. */
				if ( ! empty( $headers["content-encoding"] ) ) {
					$hasCompression = true;
					nitropack_json_and_exit( array( "type" => "success", "hasCompression" => $hasCompression ) );
				} else {
					/* If not found, we enable NitroPack GZIP compression */
					$hasCompression = false;
					update_option( $this->option_name, 1 );
					nitropack_json_and_exit( array( "type" => "success", "hasCompression" => $hasCompression ) );
				}
			}
		} catch (\Exception $e) {
			nitropack_json_and_exit( array( "type" => "error", "message" => nitropack_admin_toast_msgs( 'error' ) ) );
		}
	}

	public function render() {
		$enableCompression = get_option( $this->option_name );
		?>
		<div class="nitro-option" id="compression-widget">
			<div class="nitro-option-main">
				<div class="text-box">
					<h6><span id="detected-compression"><?php esc_html_e( 'HTML Compression', 'nitropack' ); ?>
						</span></h6>
					<p>
						<?php esc_html_e( 'Compressing the structure of your HTML, ensures faster page rendering and an optimized browsing experience for your users.', 'nitropack' ); ?>
						<a href="https://support.nitropack.io/en/articles/8390333-nitropack-plugin-settings-in-wordpress#h_29b7ab4836"
							class="text-blue" target="_blank"><?php esc_html_e( 'Learn more', 'nitropack' ); ?></a>
					</p>
				</div>
				<?php $components = new Components();
				$components->render_toggle( 'compression-status', $enableCompression, [ 'disabled' => true ] );
				?>
			</div>
			<div class="mt-4 text-primary">
				<a href="javascript:void(0);" id="compression-test-btn"
					class="text-primary"><?php esc_html_e( 'Run compression test', 'nitropack' ); ?></a>
				<div class="flex items-start msg-container hidden">
					<span class="msg"></span>
				</div>
			</div>
		</div>
		<?php
	}
}