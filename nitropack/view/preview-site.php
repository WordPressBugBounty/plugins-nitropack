<?php

//force test mode in Preview Screen
$testMode = new \NitroPack\WordPress\Settings\TestMode();
if ( null !== $nitro = get_nitropack_sdk() ) {
	$nitro->enableSafeMode();
}
$optimization_level_class = NitroPack\WordPress\Settings\OptimizationLevel::getInstance();
$active_mode_name = $optimization_level_class->fetch_optimization_name();

//preview
$GeneratePreview = new NitroPack\WordPress\Settings\GeneratePreview();
$preview_url = $GeneratePreview->get_homepage_preview_url($active_mode_name); ?>

<div id="nitropack-preview-site-container" class="card">
	<div class="progress-wrapper">
		<div class="progress-bar">
			<div class="progress" style="width: 66.66%;"></div>
		</div>
		<div class="step"><?php esc_html_e( 'Step', 'nitropack' ); ?> 2/3</div>
	</div>
	<div class="text-content">
		<h1><?php esc_html_e( 'Preview your site and go live', 'nitropack' ); ?></h1>
		<p><?php printf( __( 'We\'ve activated <span class="active-mode">%s</span> mode on your home page in a safe testing environment, so changes are visible only to you. Take a moment to:', 'nitropack' ), esc_html( ucfirst( $active_mode_name ) ) ); ?>
		</p>
		<ol>
			<li><?php _e( '<b>Preview</b> the newly optimized version and give it your final approval', 'nitropack' ); ?>
			</li>
			<li><?php _e( 'Click "<b>Go live</b>" to launch the faster version for all your visitors', 'nitropack' ); ?>
			</li>
		</ol>
		<p><?php esc_html_e( 'If you\'d like to try a different balance of settings, switch to another Optimization mode and repeat the preview process.', 'nitropack' ); ?>
		</p>
	</div>

	<?php $optimization_level_class->preview_render(); ?>
	
	<div class="go-live-container">
		<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank"
			class="btn btn-primary preview-home"><?php esc_html_e( 'Preview Home Page', 'nitropack' ); ?> <svg
				xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17" fill="none">
				<path
					d="M12.2969 9.45964V13.4596C12.2969 13.8133 12.1564 14.1524 11.9064 14.4024C11.6563 14.6525 11.3172 14.793 10.9635 14.793H3.63021C3.27659 14.793 2.93745 14.6525 2.6874 14.4024C2.43735 14.1524 2.29688 13.8133 2.29688 13.4596V6.1263C2.29688 5.77268 2.43735 5.43354 2.6874 5.18349C2.93745 4.93344 3.27659 4.79297 3.63021 4.79297H7.63021"
					stroke="white" stroke-linecap="round" stroke-linejoin="round" />
				<path d="M10.2969 2.79297H14.2969V6.79297" stroke="white" stroke-linecap="round"
					stroke-linejoin="round" />
				<path d="M6.96484 10.1263L14.2982 2.79297" stroke="white" stroke-linecap="round"
					stroke-linejoin="round" />
			</svg></a>
		<a class="btn btn-secondary" id="go-live"><?php esc_html_e( 'Go live', 'nitropack' ); ?></a>


	</div>
	<div class="text-smaller mt-4">
		<p>
			<?php printf( __( 'Need help? <a href="%s" target="_blank">Visit our Help Center</a> or contact our <a href="%s">Support team</a>.', 'nitropack' ), 'https://support.nitropack.io/en/', 'https://support.nitropack.io/en/' ); ?>
		</p>
	</div>
</div>
<?php require_once NITROPACK_PLUGIN_DIR . 'view/templates/template-toast.php';

require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-processing-html.php';
require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-processing-html-success.php';
require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-processing-html-error.php';
require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-go-live.php'; ?>