<?php

namespace NitroPack\WordPress\Settings;

class CacheWarmup {
	private static $instance = NULL;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_skip_cache_warmup', array( $this, 'nitropack_skip_cache_warmup' ) );

	}
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    /* Dismiss cache warmup notice in the final third step when Onboarding */
	public function nitropack_skip_cache_warmup() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$nitropack_notices = get_option( 'nitropack-dismissed-notices', array() );
		$nitropack_notices['skip_cache_warmup'] = 1;
		update_option( 'nitropack-dismissed-notices', $nitropack_notices );

		nitropack_json_and_exit( array(
			"type" => "success",
			"message" => nitropack_admin_toast_msgs( 'success', esc_html__( 'Cache warmup skipped.', 'nitropack' ) )
		) );
	}
    
    /* Render cache warmup setting widget */
	public function render() {
		$nitro = get_nitropack_sdk();
		$cache_warmup_stats = $nitro->getApi()->getWarmupStats();
		?>
		<div class="nitro-option" id="cache-warmup-widget">
			<div class="nitro-option-main">
				<div class="text-box" id="warmup-status-slider">

					<?php $sitemap = get_option( 'nitropack-warmup-sitemap', false );
					$toolTipDisplayState = $sitemap ? '' : 'hidden'; ?>

					<h6><?php esc_html_e( 'Cache warmup', 'nitropack' ); ?> <span
							class="badge badge-primary ml-2"><?php esc_html_e( 'Recommended', 'nitropack' ); ?></span>
						<span class="tooltip-icon <?php echo $toolTipDisplayState; ?>" data-tooltip-target="tooltip-sitemap">
							<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/info.svg'; ?>">
						</span>
					</h6>
					<div id="tooltip-sitemap" role="tooltip" class="tooltip-container hidden">
						<?php echo $sitemap; ?>
						<div class="tooltip-arrow" data-popper-arrow></div>
					</div>
					<p><?php esc_html_e( 'Automatically pre-caches your website\'s page content', 'nitropack' ); ?>.
						<a href="https://support.nitropack.io/en/articles/8390320-cache-warmup" class="text-blue"
							target="_blank"><?php esc_html_e( 'Learn more', 'nitropack' ); ?></a>
					</p>
				</div>
				<label class="inline-flex items-center cursor-pointer ml-auto">
					<input id="warmup-status" type="checkbox" class="sr-only peer" <?php echo $cache_warmup_stats['status'] === 1 ? "checked" : ""; ?>>
					<div class="toggle"></div>
				</label>
			</div>
			<div class="msg-container hidden" id="loading-warmup-status">
				<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/loading.svg'; ?>" alt="loading" class="icon">
				<span class="msg"><?php esc_html_e( 'Loading cache warmup status', 'nitropack' ); ?></span>
			</div>
		</div>
	<?php }
}
