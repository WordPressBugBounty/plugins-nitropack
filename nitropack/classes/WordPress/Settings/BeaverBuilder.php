<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;
use NitroPack\Integration\Plugin\BeaverBuilder as BeaverBuilderPlugin;

/**
 * Sync Beaver Builder cache when purging NitroPack cache in class NitroPack\Integration\Plugin\BeaverBuilder
 */
class BeaverBuilder {
	/** @var string */
	public $option_name;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_bb_cache_purge_sync_ajax', [ $this, 'nitropack_set_bb_cache_purge_sync_ajax' ] );
		$this->option_name = 'nitropack-bbCacheSyncPurge';
	}

	/**
	 * AJAX handler when toggling the setting in the Dashboard
	 * @return void
	 */
	public function nitropack_set_bb_cache_purge_sync_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = (int) ! empty( $_POST["bbCachePurgeSyncStatus"] );
		$updated = update_option( $this->option_name, $option );
		if ( $updated ) {
			NitroPack::getInstance()->getLogger()->notice( 'Beaver Builder Status is ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), 'bbCacheSyncPurgeStatus' => $option ) );
		} else {
			NitroPack::getInstance()->getLogger()->error( 'Beaver Builder Status cannot be ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}

	/**
	 * Renders the Beaver Builder option in the Dashboard if the plugin is active
	 * @return void
	 */
	public function render() {
		if ( BeaverBuilderPlugin::isActive() ) {
			$beaver_setting = get_option( $this->option_name, 0 );
			?>
			<div class="nitro-option" id="beaver-builder-widget">
				<div class="nitro-option-main">
					<div class="text-box">
						<h6><span
								id="detected-compression"><?php esc_html_e( 'Sync NitroPack Purge with Beaver Builder', 'nitropack' ); ?>
							</span></h6>
						<p>
							<?php esc_html_e( 'When Beaver Builder cache is purged, NitroPack will perform a full cache purge keeping your site\'s content up-to-date.', 'nitropack' ); ?>
						</p>
					</div>
					<?php $components = new Components();
					$components->render_toggle( 'bb-purge-status', $beaver_setting );
					?>
				</div>
			</div>
		<?php }
	}
}
