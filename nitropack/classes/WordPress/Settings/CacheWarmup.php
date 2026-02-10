<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;

class CacheWarmup {
	private static $instance = NULL;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_skip_cache_warmup', [ $this, 'nitropack_skip_cache_warmup' ] );
		add_action( 'wp_ajax_nitropack_enable_warmup', [ $this, 'nitropack_enable_warmup' ] );
		add_action( 'wp_ajax_nitropack_disable_warmup', [ $this, 'nitropack_disable_warmup' ] );
		add_action( 'wp_ajax_nitropack_warmup_stats', [ $this, 'nitropack_warmup_stats' ] );
		add_action( 'wp_ajax_nitropack_estimate_warmup', [ $this, 'nitropack_estimate_warmup' ] );
		add_action( 'wp_ajax_nitropack_run_warmup', [ $this, 'nitropack_run_warmup' ] );
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
	/**
	 * AJAX handler to enable cache warmup
	 * @return void
	 */
	public function nitropack_enable_warmup() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$prepend = '';
		if ( nitropack_is_wp_cli() )
			$prepend = '[CLI] ';
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->getApi()->enableWarmup();
				$nitro->getApi()->setWarmupHomepage( get_home_url() );
				$nitro->getApi()->runWarmup();
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( $prepend . 'Cache warmup cannot be enabled. Error: ' . $e->getTraceAsString() );
			}
			NitroPack::getInstance()->getLogger()->notice( $prepend . 'Cache warmup is enabled' );
			nitropack_json_and_exit( array(
				"type" => "success",
				"message" => __( 'Cache warmup has been enabled successfully!', 'nitropack' )
			) );
		}
		NitroPack::getInstance()->getLogger()->notice( $prepend . 'Cache warmup cannot be enabled' );
		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'There was an error while enabling the cache warmup!', 'nitropack' )
		) );
	}
	/**
	 * AJAX handler to disable cache warmup
	 * @return void
	 */
	public function nitropack_disable_warmup() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$prepend = '';
		if ( nitropack_is_wp_cli() )
			$prepend = '[CLI] ';
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->getApi()->disableWarmup();
				$nitro->getApi()->resetWarmup();
				delete_option( 'nitropack-warmup-sitemap' );
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( $prepend . 'Cache warmup cannot be disabled. Error: ' . $e->getTraceAsString() );
			}
			NitroPack::getInstance()->getLogger()->notice( $prepend . 'Cache warmup is disabled' );
			nitropack_json_and_exit( array(
				"type" => "success",
				"message" => __( 'Cache warmup has been disabled successfully!', 'nitropack' )
			) );
		}
		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'There was an error while disabling the cache warmup!', 'nitropack' )
		) );
	}

	/**
	 * AJAX handler to run cache warmup
	 * @return void
	 */
	public function nitropack_run_warmup() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		NitroPack::getInstance()->getLogger()->notice( 'Running warmup' );

		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->getApi()->runWarmup();
				NitroPack::getInstance()->getLogger()->notice( 'Cache warmup has been started' );
				nitropack_json_and_exit( array(
					"type" => "success",
					"message" => __( 'Success! Cache warmup has been started successfully!', 'nitropack' )
				) );
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'There was an error while starting the cache warmup. Error: ' . $e->getTraceAsString() );
			}
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Error! There was an error while starting the cache warmup!', 'nitropack' )
		) );
	}

	/**
	 * AJAX handler to estimate cache warmup
	 * @return void
	 */
	public function nitropack_estimate_warmup() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				if ( ! session_id() ) {
					session_start();
				}
				$id = ! empty( $_POST["estId"] ) ? preg_replace( "/[^a-fA-F0-9]/", "", (string) $_POST["estId"] ) : NULL;
				if ( $id !== NULL && ( ! is_string( $id ) || $id != $_SESSION["nitroEstimateId"] ) ) {
					nitropack_json_and_exit( array(
						"type" => "error",
						"message" => __( 'Invalid estimation ID!', 'nitropack' )
					) );
				}

				$sitemapUrls = nitropack_active_sitemap_plugins() ? nitropack_get_site_maps() : NULL;
				$configuredSitemap = false;

				if ( $sitemapUrls === NULL ) {

					$defaultSitemap = get_default_sitemap();
					if ( $defaultSitemap ) {
						$nitro->getApi()->setWarmupSitemap( $defaultSitemap );
						$configuredSitemap = true;
					}

					delete_option( 'nitropack-warmup-sitemap' );
				} else {

					$warmupSitemap = evaluate_warmup_sitemap( $sitemapUrls );
					if ( $warmupSitemap ) {
						$nitro->getApi()->setWarmupSitemap( $warmupSitemap );
						$configuredSitemap = true;
					}
				}

				if ( ! $configuredSitemap ) {
					$nitro->getApi()->setWarmupSitemap( NULL );
				}

				$nitro->getApi()->setWarmupHomepage( get_home_url() );

				$optimizationsEstimate = $nitro->getApi()->estimateWarmup( $id );

				if ( $id === NULL ) {
					$_SESSION["nitroEstimateId"] = $optimizationsEstimate; // When id is NULL, $optimizationsEstimate holds the ID for the newly started estimate
				}
			} catch (\Exception $e) {
			}
			$json_data = array(
				"type" => "success",
				"res" => $optimizationsEstimate,
				"sitemap_indication" => get_option( 'nitropack-warmup-sitemap', false ),
				"message" => __( 'Warmup estimation failed.', 'nitropack' ),
			);
			if ( is_int( $optimizationsEstimate ) && $optimizationsEstimate > 0 ) {
				$json_data["message"] = __( 'Cache warmup has been estimated successfully.', 'nitropack' );
			} else if ( $optimizationsEstimate === 0 ) {
				$json_data["message"] = __( 'We could not find any links for warming up on your home page.', 'nitropack' );
			} else {
				$json_data["message"] = __( 'Warmup estimation failed. Please try again or contact support if the issue persists', 'nitropack' );
			}
			nitropack_json_and_exit( $json_data );
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Warmup estimation failed.', 'nitropack' )
		) );
	}
	/**
	 * AJAX handler to get cache warmup stats
	 * @return void
	 */
	public function nitropack_warmup_stats() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$stats = $nitro->getApi()->getWarmupStats();
			} catch (\Exception $e) {
				nitropack_json_and_exit( array(
					"type" => "error",
					"message" => __( 'Error! There was an error while fetching warmup stats!', 'nitropack' )
				) );
			}

			nitropack_json_and_exit( array(
				"type" => "success",
				"stats" => $stats
			) );
		}


		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Error! There was an error while fetching warmup stats!', 'nitropack' )
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
				<?php $components = new Components();
				$components->render_toggle( 'warmup-status', $cache_warmup_stats['status'] );
				?>
			</div>
			<div class="msg-container hidden" id="loading-warmup-status">
				<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/loading.svg'; ?>" alt="loading"
					class="icon">
				<span class="msg"><?php esc_html_e( 'Loading cache warmup status', 'nitropack' ); ?></span>
			</div>
		</div>
	<?php }
}
