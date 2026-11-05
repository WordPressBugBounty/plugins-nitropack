<?php

namespace NitroPack\WordPress\Settings;

use NitroPack\WordPress\NitroPack;
use NitroPack\Integration\Theme\Divi;

/**
 * Toggle for moving Divi's inline fallback CSS into <head>, see NitroPack\Integration\Theme\Divi
 */
class DiviCssToHead {
	const OPTION_NAME = 'nitropack-divi-CssToHead';
	/** @var DiviCssToHead|null */
	private static $instance = null;
	/** @var string */
	public $option_name = self::OPTION_NAME;

	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_divi_css_to_head_ajax', [ $this, 'nitropack_set_divi_css_to_head_ajax' ] );
	}

	/**
	 * Returns the shared instance.
	 *
	 * @return DiviCssToHead
	 */
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * AJAX handler when toggling the setting in the Dashboard.
	 *
	 * @return void
	 */
	public function nitropack_set_divi_css_to_head_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = (int) ! empty( $_POST['diviCssToHeadStatus'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above.
		// update_option() returns false when the value is unchanged, which isn't an error.
		if ( (int) get_option( $this->option_name, 1 ) === $option || update_option( $this->option_name, $option ) ) {
			NitroPack::getInstance()->getLogger()->notice( 'Divi CSS to head is ' . ( 1 === $option ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit(
				[
					'type'                => 'success',
					'message'             => nitropack_admin_toast_msgs( 'success' ),
					'diviCssToHeadStatus' => $option,
				]
			);
		} else {
			NitroPack::getInstance()->getLogger()->error( 'Divi CSS to head cannot be ' . ( 1 === $option ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit(
				[
					'type'    => 'error',
					'message' => nitropack_admin_toast_msgs( 'error' ),
				]
			);
		}
	}

	/**
	 * Renders the option in the Dashboard if Divi 5 is active.
	 * Default: Enabled.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Divi::is_active() ) {
			return;
		}
		$status = get_option( $this->option_name, null );
		if ( null === $status ) {
			$status = 1;
			add_option( $this->option_name, $status ); // Persist the default so it exists in the DB.
		}
		$status = (int) $status;
		?>
		<div class="nitro-option" id="divi-css-to-head-widget">
			<div class="nitro-option-main">
				<div class="text-box">
					<h6><?php esc_html_e( 'Divi - Move inline CSS to head', 'nitropack' ); ?></h6>
					<p>
						<?php esc_html_e( 'Moves the inline styles Divi prints before the closing body tag into the head, which prevents layout shifts on pages without a generated Divi CSS file.', 'nitropack' ); ?>
					</p>
				</div>
				<?php
				$components = new Components();
				$components->render_toggle( 'divi-css-to-head-status', $status );
				?>
			</div>
		</div>
		<?php
	}
}
