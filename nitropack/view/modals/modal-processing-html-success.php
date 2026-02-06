<?php
$modal_header = esc_html__( 'NitroPack has successfully optimized your home page', 'nitropack' );
$plan_details = $optimization_level_class->fetch_plan();
$is_free_plan = $plan_details === 'Free';
?>
<div id="processing-html-success-modal" data-modal-backdrop="dynamic" tabindex="-1" aria-hidden="true"
	class="hidden modal-wrapper popup-modal">
	<div class="popup-container">
		<div class="popup-inner">
			<!-- Modal header -->
			<div class="popup-header">
				<button type="button" class="close-modal" data-modal-hide="processing-html-success-modal">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd"
							d="M0.293031 1.29308C0.480558 1.10561 0.734866 1.00029 1.00003 1.00029C1.26519 1.00029 1.5195 1.10561 1.70703 1.29308L6.00003 5.58608L10.293 1.29308C10.3853 1.19757 10.4956 1.12139 10.6176 1.06898C10.7396 1.01657 10.8709 0.988985 11.0036 0.987831C11.1364 0.986677 11.2681 1.01198 11.391 1.06226C11.5139 1.11254 11.6255 1.18679 11.7194 1.28069C11.8133 1.37458 11.8876 1.48623 11.9379 1.60913C11.9881 1.73202 12.0134 1.8637 12.0123 1.99648C12.0111 2.12926 11.9835 2.26048 11.9311 2.38249C11.8787 2.50449 11.8025 2.61483 11.707 2.70708L7.41403 7.00008L11.707 11.2931C11.8892 11.4817 11.99 11.7343 11.9877 11.9965C11.9854 12.2587 11.8803 12.5095 11.6948 12.6949C11.5094 12.8803 11.2586 12.9855 10.9964 12.9878C10.7342 12.99 10.4816 12.8892 10.293 12.7071L6.00003 8.41408L1.70703 12.7071C1.51843 12.8892 1.26583 12.99 1.00363 12.9878C0.741432 12.9855 0.49062 12.8803 0.305212 12.6949C0.119804 12.5095 0.0146347 12.2587 0.0123563 11.9965C0.0100779 11.7343 0.110873 11.4817 0.293031 11.2931L4.58603 7.00008L0.293031 2.70708C0.10556 2.51955 0.000244141 2.26525 0.000244141 2.00008C0.000244141 1.73492 0.10556 1.48061 0.293031 1.29308Z"
							fill="#1B004E" />
					</svg>
				</button>
				<svg class="icon bg-green-500 border-rounded" xmlns="http://www.w3.org/2000/svg" width="48" height="49"
					viewBox="0 0 48 49" fill="none">
					<path fill-rule="evenodd" clip-rule="evenodd"
						d="M40.0958 12.8824C40.5458 13.3324 40.7985 13.9428 40.7985 14.5792C40.7985 15.2156 40.5458 15.8259 40.0958 16.276L20.8958 35.476C20.4458 35.9259 19.8354 36.1787 19.199 36.1787C18.5626 36.1787 17.9523 35.9259 17.5022 35.476L7.90222 25.876C7.46504 25.4233 7.22314 24.8171 7.22861 24.1878C7.23407 23.5585 7.48648 22.9566 7.93146 22.5116C8.37644 22.0666 8.97839 21.8142 9.60766 21.8088C10.2369 21.8033 10.8432 22.0452 11.2958 22.4824L19.199 30.3856L36.7022 12.8824C37.1523 12.4324 37.7626 12.1797 38.399 12.1797C39.0354 12.1797 39.6458 12.4324 40.0958 12.8824Z"
						fill="white" />
				</svg>
				<h3><?php echo $modal_header; ?></h3>
			</div>
			<!-- Modal body -->
			<div class="popup-body">
			</div>
			<div class="popup-footer">
				<button data-modal-hide="processing-html-success-modal" type="button"
					class="btn btn-primary modal-action"><?php esc_html_e( 'Continue', 'nitropack' ); ?></button>
			</div>
			<?php if ( $is_free_plan ) : ?>
				<div class="free-plan-info">
					<p class="text-smaller text-center">
						<?php esc_html_e( 'NitroPack badge was added to your site\'s footer as part of the Free plan', 'nitropack' ); ?>
					</p>
					<div class="nitropack-badge-wrapper">
						<div class="logo-wrapper">
							<span class="optimized-by"><?php esc_html_e( 'optimized by', 'nitropack' ); ?></span>
							<img src="<?php echo plugin_dir_url( __FILE__ ) . '../images/nitropack-logo-horizontal.svg'; ?>"
								height="13" />
						</div>
						<span
							class="badge-text"><?php esc_html_e( 'Automated page speed optimizations for fast site performance', 'nitropack' ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>