<?php
$settings = new \NitroPack\WordPress\Settings();
$notifications = new \NitroPack\WordPress\Notifications\Notifications();

$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
$conflictingPlugins_list = $conflictingPlugins->nitropack_get_conflicting_plugins();
if ( $conflictingPlugins_list ) {
	require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-plugin-deactivate.php';
}

$notifications->nitropack_display_admin_notices();
$dismissed_notices = get_option( 'nitropack-dismissed-notices' );

$nitro = get_nitropack_sdk();
if ( ! $nitro ) {
	?>
	<div class="card">
		<div class="card-header">
			<h3><?php esc_html_e( 'NitroPack failed to connect correctly', 'nitropack' ); ?>
			</h3>
		</div>
		<div class="card-body">
			<p><?php esc_html_e( 'This usually happens when the plugin is installed and the connection process is started, but for some reason the connection process is not completed.', 'nitropack' ); ?><br>
			<?php esc_html_e( 'Please disconnect and re-connect the plugin again and make sure your API key is correct and the site is connected in', 'nitropack' ); ?>
				<a href="https://app.nitropack.io/dashboard" target="_blank">https://app.nitropack.io/dashboard</a>			
			</p>
			<p><?php esc_html_e( 'If the issue persists, please contact our support team.', 'nitropack' ); ?></p>
		</div>
		<div class="card-footer disconnect-container">
			<a class="btn btn-primary" id="disconnect-btn"><?php esc_html_e( 'Disconnect NitroPack', 'nitropack' ); ?></a>
			<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-disconnect.php'; ?>
		</div>
	</div>
	<?php
	return;
}
try {
	$cache_warmup_stats = $nitro->getApi()->getWarmupStats();
} catch ( \Exception $e ) {
	$cache_warmup_stats = [ 'status' => 0 ];
}
$cache_warmup_enabled = ! empty( $cache_warmup_stats['status'] ) && $cache_warmup_stats['status'] === 1 ? true : false;
?>
<div class="grid grid-cols-2 gap-6 grid-col-1-tablet items-start nitropack-dashboard">
	<div class="col-span-1">
		<!-- Optimized Pages Card -->
		<?php $settings->optimizations->render(); ?>

		<!-- Optimization Mode Card -->
		<?php $settings->optimization_level->render(); ?>

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
					?>
				</div>
			</div>
		</div>
	</div>
	<?php
	$CPTOptimization = NitroPack\WordPress\Settings\CPTOptimization::getInstance();
	$notOptimizedCPTs = $CPTOptimization->nitropack_filter_non_optimized();
	$notices = get_option( 'nitropack-dismissed-notices', [] );
	$optimizedCPT_notice = in_array( 'OptimizeCPT', $notices, true ) ? true : false;
	if ( ! $optimizedCPT_notice && ! empty( $notOptimizedCPTs ) )
		require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-not-optimized-CPT.php'; ?>
</div>
<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-unsaved-changes.php'; ?>