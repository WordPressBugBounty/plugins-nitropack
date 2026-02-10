<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\HttpClient\HttpClient;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/* Optimizations class to handle optimization related functionalities */
class Optimizations {
	private static $instance = null;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_fetch_optimizations', [ $this, 'nitropack_fetch_optimizations' ] );
	}
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Fetch optimization details from NitroPack API
	 */
	public function fetch_optimizations() {
		$planDetailsUrl = get_nitropack_integration_url( "optimization_details_json" );
		$quickSetupHTTP = new HttpClient( $planDetailsUrl );
		$quickSetupHTTP->timeout = 30;
		$quickSetupHTTP->fetch( true, "GET" );
		$resp = $quickSetupHTTP->getStatusCode() == 200 ? json_decode( $quickSetupHTTP->getBody(), true ) : false;
		return $resp;
	}
	/**
	 * AJAX handler to fetch optimizations data 
	 */
	public function nitropack_fetch_optimizations() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$optimizations = $this->fetch_optimizations();
		if ( ! $optimizations ) {
			wp_send_json_error( 'Could not fetch optimizations data.' );
		}
		wp_send_json_success( $optimizations );
	}

	/**
	 * Render optimizations box in our Dashboard
	 */
	public function render() {
		$pending_optimizations = 0;
		$total_optimized_pages = 0;
		$purge_reason = 'N/A';
		$last_cache_purge = 'Never';
		$optimizations = $this->fetch_optimizations();
		if ( $optimizations ) {
			$last_cache_purge = ! empty( $optimizations['last_cache_purge']['timeAgo'] ) ? $optimizations['last_cache_purge']['timeAgo'] : $last_cache_purge;
			$purge_reason = ! empty( $optimizations['last_cache_purge']['reason'] ) ? $optimizations['last_cache_purge']['reason'] : $purge_reason;
			$pending_optimizations = ! empty( $optimizations['pending_count'] ) ? $optimizations['pending_count'] : $pending_optimizations;
			$total_optimized_pages = ! empty( $optimizations['optimized_pages']['total'] ) ? $optimizations['optimized_pages']['total'] : $total_optimized_pages;
		}
		?>
		<div class="card card-optimized-pages">
			<div class="card-header">
				<h3><?php esc_html_e( 'Optimized pages', 'nitropack' ); ?></h3>
				<div class="flex flex-row items-center" style="<?php echo $pending_optimizations ? '' : 'display: none;'; ?>"
					id="pending-optimizations-section">
					<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/loading.svg'; ?>" alt="loading"
						class="w-4 h-4">
					<span class="ml-2 mr-1 text-primary"> <?php esc_html_e( 'Processing', 'nitropack' ); ?>
						<span id="pending-optimizations-count"><?php echo esc_html( $pending_optimizations ); ?></span>
						<?php esc_html_e( 'page(s) in the background', 'nitropack' ); ?></span>
				</div>
			</div>
			<div class="card-body">
				<div class="card-body-inner">
					<div class="optimized-pages"><span
							data-optimized-pages-total><?php echo esc_html( $total_optimized_pages ); ?></span></div>
					<div class="text-box">
						<div class="time-ago"><?php esc_html_e( 'Last cache purge', 'nitropack' ); ?>: <span
								data-last-cache-purge><?php echo esc_html( $last_cache_purge ); ?></span></div>
						<div class="reason"><?php esc_html_e( 'Reason', 'nitropack' ); ?>: <span
								data-purge-reason><?php echo esc_html( $purge_reason ); ?></span></div>
					</div>
					<?php $components = new Components();
					echo $components->render_button( ['text' => 'Purge cache', 'classes' => 'btn btn-secondary', 'type' => 'button', 'attributes' => ['id' => 'optimizations-purge-cache', 'data-modal-target' => 'modal-purge-cache', 'data-modal-toggle' => 'modal-purge-cache' ] ] );					
					?>
				
				</div>
			</div>
			<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-purge-cache.php'; ?>
		</div>
		<?php
	}
}