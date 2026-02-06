<?php 
$modal_header = esc_html__( 'Optimizing your home page', 'nitropack' );
?>
<div id="processing-html-modal" data-modal-backdrop="dynamic" tabindex="-1" aria-hidden="true"
	class="hidden modal-wrapper popup-modal">
	<div class="popup-container">
		<div class="popup-inner">
			<!-- Modal header -->
			<div class="popup-header">			
				<h3><?php echo $modal_header; ?></h3>
			</div>
			<!-- Modal body -->
			<div class="popup-body">
				<div id="optimization-animation"></div>
				<script>
					lottie.loadAnimation({
						container: document.getElementById('optimization-animation'),
						renderer: 'svg',
						loop: true,
						autoplay: true,
						path: '<?php echo plugin_dir_url( __FILE__ ) . '../animations/setting-html.json'; ?>'
					});
				</script>
				<p class="text-center">
					<?php _e( 'This may take up to 1 minute ...', 'nitropack' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>