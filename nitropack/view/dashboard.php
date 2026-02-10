<?php
$settings = new \NitroPack\WordPress\Settings();
$components = new \NitroPack\WordPress\Settings\Components();
$notifications = new \NitroPack\WordPress\Notifications\Notifications();
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

if ( empty( $dismissed_notices['skip_cache_warmup'] ) && ! $cache_warmup_enabled ) : ?>
	<div class="card cache-warmup">
		<div class="progress-wrapper mb-4">
			<div class="progress-bar">
				<div class="progress" style="width: 100%;"></div>
			</div>
			<div class="step"><?php esc_html_e( 'Step', 'nitropack' ); ?> 3/3</div>
		</div>
		<div class="card-body">
			<h3><?php esc_html_e( 'Enable proactive optimizations', 'nitropack' ); ?>
			</h3>
			<p><?php esc_html_e( 'Turn on Cache Warmup so NitroPack can start optimizing your pages immediately, without waiting for traffic. This guarantees a fast site for every visitor right from the start.', 'nitropack' ); ?>
			</p>
		</div>
		<div class="card-footer">
			<button id="enable-cache-warmup"
				class="btn btn-primary"><?php esc_html_e( 'Enable Cache Warmup', 'nitropack' ); ?></button>
			<a id="skip-cache-warmup" class="btn btn-secondary ml-2"><?php esc_html_e( 'Skip', 'nitropack' ); ?></a>
		</div>
	</div>
<?php endif; ?>
<div class="grid grid-cols-2 gap-6 grid-col-1-tablet items-start nitropack-dashboard">
	<div class="col-span-1">
		<!-- Go to app Card -->
		<div class="card app-card">
			<div class="card-header">
				<h3><?php esc_html_e( 'Customize NitroPack\'s Optimization Settings in Your Account', 'nitropack' ); ?>
				</h3>
			</div>
			<div class="card-body">
				<div class="flex items-center justify-between">
					<div class="text-box">
						<p>
							<?php esc_html_e( 'You can further configure how NitroPack\'s optimization behaves through your account.', 'nitropack' ); ?>
						</p>
					</div>
					<?php
					function getNitropackDashboardUrl() {
						$siteId = nitropack_get_current_site_id();
						$dashboardUrl = 'https://app.nitropack.io/dashboard';

						if ( $siteId !== null ) {
							$dashboardUrl .= '?update_session_website_id=' . urlencode( $siteId );
						}

						return $dashboardUrl;
					}
					echo $components->render_button( [ 'text' => 'Go to app', 'type' => null, 'classes' => 'btn btn-primary ml-2 flex-shrink-0', 'href' => esc_url( getNitropackDashboardUrl() ), 'attributes' => [ 'target' => '_blank' ] ] );
					?>

				</div>
			</div>
		</div>
		<!-- Go to app card End -->
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
					<?php $settings->auto_purge->render();
					$settings->cpt_optimization->render(); ?>
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
					<?php $settings->shortcodes->render(); ?>
				</div>
			</div>
		</div>
		<!-- Go to app card End -->
	</div>
	<div class="col-span-1">
		<!-- Subscription Card -->
		<?php $settings->subscription->render(); ?>

		<!-- Subscription Card End -->
		<!-- Basic Settings Card -->
		<div class="card card-basic-settings">
			<div class="card-header">
				<h3><?php esc_html_e( 'Basic Settings', 'nitropack' ); ?></h3>
			</div>
			<div class="card-body">
				<div class="options-container">
					<?php
					$settings->cache_warmup->render();
					$settings->test_mode->render();
					$settings->html_compression->render();
					$settings->beaver_builder->render();
					$settings->editor_clear_cache->render();
					if ( class_exists( 'WooCommerce' ) ) { ?>
						<?php $settings->cart_cache->render(); ?>
						<?php $settings->stock_refresh->render(); 
					} ?>
				</div>
			</div>
			<div class="card-footer disconnect-container">
				<a class="text-primary btn-link"
					id="disconnect-btn"><?php esc_html_e( 'Disconnect NitroPack plugin', 'nitropack' ); ?></a>
				<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-disconnect.php'; ?>
			</div>
		</div>
		<!-- Basic Settings Card End -->

	</div>
	<?php $CPTOptimization = NitroPack\WordPress\Settings\CPTOptimization::getInstance();
	$notOptimizedCPTs = $CPTOptimization->nitropack_filter_non_optimized();
	$notices = get_option( 'nitropack-dismissed-notices', [] );
	$optimizedCPT_notice = in_array( 'OptimizeCPT', $notices, true ) ? true : false;
	if ( ! $optimizedCPT_notice && ! empty( $notOptimizedCPTs ) )
		require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-not-optimized-CPT.php'; ?>

</div>
<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-unsaved-changes.php'; ?>