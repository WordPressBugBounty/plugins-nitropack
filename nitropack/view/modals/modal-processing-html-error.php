<div id="processing-html-error-modal" data-modal-backdrop="dynamic" tabindex="-1" aria-hidden="true"
	class="hidden modal-wrapper popup-modal">
	<div class="popup-container">
		<div class="popup-inner">
			<!-- Modal header -->
			<div class="popup-header">
				<button type="button" class="close-modal" data-modal-hide="processing-html-error-modal">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd"
							d="M0.293031 1.29308C0.480558 1.10561 0.734866 1.00029 1.00003 1.00029C1.26519 1.00029 1.5195 1.10561 1.70703 1.29308L6.00003 5.58608L10.293 1.29308C10.3853 1.19757 10.4956 1.12139 10.6176 1.06898C10.7396 1.01657 10.8709 0.988985 11.0036 0.987831C11.1364 0.986677 11.2681 1.01198 11.391 1.06226C11.5139 1.11254 11.6255 1.18679 11.7194 1.28069C11.8133 1.37458 11.8876 1.48623 11.9379 1.60913C11.9881 1.73202 12.0134 1.8637 12.0123 1.99648C12.0111 2.12926 11.9835 2.26048 11.9311 2.38249C11.8787 2.50449 11.8025 2.61483 11.707 2.70708L7.41403 7.00008L11.707 11.2931C11.8892 11.4817 11.99 11.7343 11.9877 11.9965C11.9854 12.2587 11.8803 12.5095 11.6948 12.6949C11.5094 12.8803 11.2586 12.9855 10.9964 12.9878C10.7342 12.99 10.4816 12.8892 10.293 12.7071L6.00003 8.41408L1.70703 12.7071C1.51843 12.8892 1.26583 12.99 1.00363 12.9878C0.741432 12.9855 0.49062 12.8803 0.305212 12.6949C0.119804 12.5095 0.0146347 12.2587 0.0123563 11.9965C0.0100779 11.7343 0.110873 11.4817 0.293031 11.2931L4.58603 7.00008L0.293031 2.70708C0.10556 2.51955 0.000244141 2.26525 0.000244141 2.00008C0.000244141 1.73492 0.10556 1.48061 0.293031 1.29308Z"
							fill="#1B004E" />
					</svg>
				</button>
				<svg class="icon" width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path
						d="M20.5776 7.71992L3.6376 35.9999C3.28834 36.6048 3.10353 37.2905 3.10158 37.989C3.09962 38.6874 3.28058 39.3742 3.62645 39.981C3.97232 40.5878 4.47105 41.0934 5.07302 41.4476C5.67498 41.8018 6.3592 41.9922 7.0576 41.9999H40.9376C41.636 41.9922 42.3202 41.8018 42.9222 41.4476C43.5242 41.0934 44.0229 40.5878 44.3688 39.981C44.7146 39.3742 44.8956 38.6874 44.8936 37.989C44.8917 37.2905 44.7069 36.6048 44.3576 35.9999L27.4176 7.71992C27.0611 7.13213 26.559 6.64616 25.96 6.30889C25.3609 5.97162 24.6851 5.79443 23.9976 5.79443C23.3101 5.79443 22.6343 5.97162 22.0352 6.30889C21.4362 6.64616 20.9341 7.13213 20.5776 7.71992Z"
						stroke="#FFA400" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round" />
					<path d="M24 18V26" stroke="#FFA400" stroke-width="3.6" stroke-linecap="round"
						stroke-linejoin="round" />
					<path d="M24 34.0002H24.02" stroke="#FFA400" stroke-width="3.6" stroke-linecap="round"
						stroke-linejoin="round" />
				</svg>

				<h3><?php esc_html_e( 'The preview couldn\'t be generated', 'nitropack' ); ?></h3>
			</div>
			<!-- Modal body -->
			<div class="popup-body">
				<p class="text-center">
					<?php esc_html_e( 'We were unable to generate the optimized preview of your home page.', 'nitropack' ); ?><br>
					<?php esc_html_e( 'Please try again, or let our support team look into it for you.', 'nitropack' ); ?>
				</p>
			</div>
			<div class="popup-footer">
				<button data-modal-hide="processing-html-error-modal" type="button"
					class="btn btn-primary modal-action"><?php esc_html_e( 'Try again', 'nitropack' ); ?></button>
				<button data-modal-hide="processing-html-error-modal" type="button"
					class="btn btn-secondary contact-support"><?php esc_html_e( 'Contact Support', 'nitropack' ); ?></button>
			</div>
		</div>
	</div>
</div>