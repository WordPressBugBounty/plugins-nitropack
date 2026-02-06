<?php
$settings = new \NitroPack\WordPress\Settings();
$notifications = new \NitroPack\WordPress\Notifications\Notifications();
$usage = '0 MB';
$max_usage = '1 GB';
$page_views = '0';
$max_page_views = '10000';

$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
$conflictingPlugins_list = $conflictingPlugins->nitropack_get_conflicting_plugins();
if ( $conflictingPlugins_list ) {
	require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-plugin-deactivate.php';
}

$notifications->nitropack_display_admin_notices();
$dismissed_notices = get_option( 'nitropack-dismissed-notices' );

$nitro = get_nitropack_sdk();
$cache_warmup_stats = $nitro->getApi()->getWarmupStats();
$cache_warmup_enabled = ! empty( $cache_warmup_stats['status'] ) && $cache_warmup_stats['status'] === 1 ? true : false;
?>
<div class="grid grid-cols-2 gap-6 grid-col-1-tablet items-start nitropack-dashboard">
	<div class="col-span-1">
		<!-- Optimized Pages Card -->
		<?php $settings->optimizations->render(); ?>	
		<!-- Optimized Pages Card End -->
		<!-- Optimization Mode Card -->
		<?php $settings->optimization_level->render(); ?>	
		<!-- Optimization Mode Card End -->
		<!-- Automated Behavior Card -->
		<div class="card card-automated-behavior">
			<div class="card-header">
				<h3><?php esc_html_e( 'Automated Behavior', 'nitropack' ); ?></h3>
			</div>
			<div class="card-body">
				<div class="options-container">
					<div class="nitro-option" id="purge-cache-widget">
						<div class="nitro-option-main">
							<div class="text-box">
								<h6><?php esc_html_e( 'Purge cache', 'nitropack' ); ?></h6>
								<p><?php esc_html_e( 'Purge affected cache when content is updated or published', 'nitropack' ); ?>
								</p>
							</div>
							<label class="inline-flex items-center cursor-pointer ml-auto">
								<input type="checkbox" value="" class="sr-only peer" name="purge_cache"
									id="auto-purge-status" <?php if ( $autoCachePurge )
										echo "checked"; ?>>
								<div class="toggle"></div>
							</label>
						</div>
					</div>
					<div class="nitro-option" id="page-optimization-widget">
						<div class="nitro-option-main">
							<div class="text-box">
								<h6><?php esc_html_e( 'Page optimization', 'nitropack' ); ?></h6>
								<p><?php esc_html_e( 'Select what post/page types get optimized', 'nitropack' ); ?></p>
							</div>
							<a data-modal-target="modal-posttypes" data-modal-toggle="modal-posttypes"
								class="btn btn-secondary btn-icon">
								<img src="<?php echo plugin_dir_url( __FILE__ ); ?>images/setting-icon.svg">
							</a>
						</div>
						<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-posttypes.php'; ?>
					</div>
				</div>
			</div>
		</div>
		<!-- Automated Behavior Card End -->
		<!-- Go to app Card -->
		<div class="card exclusion-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Exclusions', 'nitropack' ); ?></h3>
			</div>
			<div class="card-body">
				<div class="options-container">
					<div class="nitro-option" id="ajax-shortcodes-widget">
						<?php $settings->render_ajax_shortcodes_setting(); ?>
					</div>
				</div>
			</div>
		</div>
		<!-- Go to app card End -->
	</div>
	<div class="col-span-1">
		<!-- Basic Settings Card -->
		<div class="card card-basic-settings">
			<div class="card-header">
				<h3><?php esc_html_e( 'Basic Settings', 'nitropack' ); ?></h3>
			</div>
			<div class="card-body">
				<div class="options-container">

					<?php $settings->cache_warmup->render();
					$settings->test_mode->render(); ?>

					<div class="nitro-option" id="compression-widget">
						<div class="nitro-option-main">
							<div class="text-box">
								<h6><span
										id="detected-compression"><?php esc_html_e( 'HTML Compression', 'nitropack' ); ?>
									</span></h6>
								<p>
									<?php esc_html_e( 'Compressing the structure of your HTML, ensures faster page rendering and an optimized browsing experience for your users.', 'nitropack' ); ?>
									<a href="https://support.nitropack.io/en/articles/8390333-nitropack-plugin-settings-in-wordpress#h_29b7ab4836"
										class="text-blue"
										target="_blank"><?php esc_html_e( 'Learn more', 'nitropack' ); ?></a>
								</p>
							</div>
							<label class="inline-flex items-center cursor-pointer ml-auto">
								<input type="checkbox" id="compression-status" class="sr-only peer" <?php echo (int) $enableCompression === 1 ? "checked" : ""; ?>>
								<div class="toggle"></div>
							</label>
						</div>
						<div class="mt-4 text-primary">
							<a href="javascript:void(0);" id="compression-test-btn"
								class="text-primary"><?php esc_html_e( 'Run compression test', 'nitropack' ); ?></a>
							<div class="flex items-start msg-container hidden">
								<span class="msg"></span>
							</div>
						</div>
					</div>
					<?php if ( \NitroPack\Integration\Plugin\BeaverBuilder::isActive() ) { ?>
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
								<label class="inline-flex items-center cursor-pointer ml-auto">
									<input type="checkbox" class="sr-only peer" id="bb-purge-status" <?php if ( $bbCacheSyncPurge )
										echo "checked"; ?>>
									<div class="toggle"></div>
								</label>
							</div>
						</div>
					<?php } ?>
					<div class="nitro-option" id="can-editor-clear-cache-widget">
						<div class="nitro-option-main">
							<div class="text-box">
								<h6><?php esc_html_e( 'Allow Editors to purge cache', 'nitropack' ); ?></h6>
								<p><?php esc_html_e( 'Give Editors the right to purge cache when content is updated.', 'nitropack' ); ?>
								</p>
							</div>
							<label class="inline-flex items-center cursor-pointer ml-auto">
								<input type="checkbox" id="can-editor-clear-cache" class="sr-only peer" <?php echo (int) $canEditorClearCache === 1 ? "checked" : ""; ?>>
								<div class="toggle"></div>
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php $notOptimizedCPTs = nitropack_filter_non_optimized();
	$notices = get_option( 'nitropack-dismissed-notices', [] );
	$optimizedCPT_notice = in_array( 'OptimizeCPT', $notices, true ) ? true : false;
	if ( ! $optimizedCPT_notice && ! empty( $notOptimizedCPTs ) )
		require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-not-optimized-CPT.php'; ?>
</div>
<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-unsaved-changes.php'; ?>