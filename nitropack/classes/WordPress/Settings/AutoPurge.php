<?php

namespace NitroPack\WordPress\Settings;
use Nitropack\WordPress\NitroPack;

/**
 * Auto Purge NitroPack cache on post/page save
 */
class AutoPurge {
	/** @var string */
	public $option_name;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_auto_cache_purge_ajax', [ $this, 'nitropack_set_auto_cache_purge_ajax' ] );
		$this->option_name = 'nitropack-autoCachePurge';
	}

	/**
	 * AJAX handler when toggling the setting in the Dashboard
	 * @return void
	 */
	public function nitropack_set_auto_cache_purge_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = (int) ! empty( $_POST["autoCachePurgeStatus"] );
		$updated = update_option( $this->option_name, $option );
		if ( $updated ) {
			NitroPack::getInstance()->getLogger()->notice( 'Auto cache purge is ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( [ "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), 'autoCachePurgeStatus' => $option ] );
		} else {
			NitroPack::getInstance()->getLogger()->error( 'Auto cache purge cannot be ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( [
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			] );
		}
	}
	/**
	 * Renders the Auto Purge option in the Dashboard
	 * @return void
	 */
	public function render() {
		$autoCachePurge = get_option( $this->option_name, 1 );
		?>
		<div class="nitro-option" id="purge-cache-widget">
			<div class="nitro-option-main">
				<div class="text-box">
					<h6><?php esc_html_e( 'Purge cache', 'nitropack' ); ?></h6>
					<p><?php esc_html_e( 'Purge affected cache when content is updated or published', 'nitropack' ); ?>
					</p>
				</div>
				<?php $components = new Components();
				$components->render_toggle( 'auto-purge-status', $autoCachePurge ); ?>
			</div>
		</div>
		<?php
	}
}