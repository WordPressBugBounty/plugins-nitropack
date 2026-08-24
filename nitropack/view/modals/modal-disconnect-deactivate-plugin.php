<?php
global $pagenow;
$deactivating = ( $pagenow === 'plugins.php' ? true : false );
$state = ( $deactivating ? 'deactivate' : 'disconnect' );
$components = new NitroPack\WordPress\Settings\Components();
$is_connected = get_nitropack()->isConnected();
$oneclick = get_nitropack()->getDistribution() == "oneclick";

$support_url = ( $is_connected && ! $oneclick ) ? admin_url( 'admin.php?page=nitropack&contact_support=1' ) : NitroPack\WordPress\Admin::support_link();

$show_disconnected_descriptions = ! $is_connected;
$layout_broken_desc = ! $show_disconnected_descriptions
	? esc_html__( 'Enable Test Mode to check if NitroPack is causing it. If it is, our team can help.', 'nitropack' )
	: esc_html__( 'NitroPack is disconnected so Test Mode is not available. If you think NitroPack was causing this, our support team can help you get back up and running with a working configuration.', 'nitropack' );
$maintenance_desc = ! $show_disconnected_descriptions
	? esc_html__( 'Enable Test Mode to serve your original site to visitors while you do maintenance. You can switch back with one click.', 'nitropack' )
	: esc_html__( 'NitroPack is already disconnected so your visitors are seeing your site without optimizations. You can make your changes safely.', 'nitropack' );
$just_deactivate_label = $deactivating
	? esc_html__( 'I just want to deactivate', 'nitropack' )
	: esc_html__( 'I just want to disconnect', 'nitropack' );
$options = array(
	array( 'id' => 'layout_issue',
		'name' => esc_html__( 'My site doesn\'t look right', 'nitropack' ),
		'description' => $layout_broken_desc,
		'icon' => 'bulb.svg',

	),
	array( 'id' => 'broken_website',
		'name' => esc_html__( 'Something on my site stopped working', 'nitropack' ),
		'description' => $layout_broken_desc,
		'icon' => 'bulb.svg',

	),
	array( 'id' => 'speed_issue',
		'name' => esc_html__( 'I don\'t see a difference in speed', 'nitropack' ),
		'description' => esc_html__( 'Your site may need a different configuration. Our team can take a look and optimize it for you.', 'nitropack' ),
		'icon' => 'bulb.svg',

	),
	array( 'id' => 'site_maintenance',
		'name' => esc_html__( 'I\'m doing site maintenance', 'nitropack' ),
		'description' => $maintenance_desc,
		'icon' => 'bulb.svg',
	),
	array( 'id' => 'just_deactivate',
		'name' => $just_deactivate_label,
		'description' => sprintf( esc_html__( 'No problem. You can %s anytime from the Plugins page.', 'nitropack' ), ( $deactivating ? esc_html__( 'reactivate', 'nitropack' ) : esc_html__( 'reconnect', 'nitropack' ) ) ),
		'icon' => 'bulb.svg',
	),
	array( 'id' => 'different_plugin',
		'name' => esc_html__( 'I\'m moving to a different plugin', 'nitropack' ),
		'description' => esc_html__( 'We\'d love to learn from this. Which plugin are you switching to?', 'nitropack' ),
		'form_fields' => array(
			array(
				'type' => 'text',
				'name' => 'new_plugin',
				'placeholder' => esc_attr__( 'Plugin name (optional)', 'nitropack' ),
			),
			array(
				'type' => 'textarea',
				'name' => 'free_text',
				'placeholder' => esc_attr__( 'Anything we could have done better (optional)', 'nitropack' ),
			),
		),
	),
	array( 'id' => 'something_else',
		'name' => esc_html__( 'Something else', 'nitropack' ),
		'description' => esc_html__( 'Is there anything we can improve?', 'nitropack' ),
		'form_fields' => array(
			array(
				'type' => 'textarea',
				'name' => 'free_text',
				'placeholder' => esc_attr__( 'Anything we could have done better (optional)', 'nitropack' ),
			),
		),
	),
);

$heading = ( $deactivating ? esc_html__( 'Are you sure you want to deactivate NitroPack?', 'nitropack' ) : esc_html__( 'Are you sure you want to disconnect NitroPack?', 'nitropack' ) );
$text = ( $deactivating ? esc_html__( 'Deactivating NitroPack will pause your optimizations and speed gains. Please let us know why you\'re deactivating.', 'nitropack' ) : esc_html__( 'Disconnecting NitroPack will delete your page optimizations, potentially slowing down your website and worsening the user experience.', 'nitropack' ) );

?>
<div id="disconnect-deactivate-plugin-modal" data-modal-backdrop="dynamic" tabindex="-1" aria-hidden="true"
	class="hidden modal-wrapper">
	<div class="modal-container">
		<div class="modal-inner">
			<!-- Modal header -->
			<div class="modal-header">
				<div>
					<h3><?php echo esc_html( $heading ); ?></h3>
					<p class="text-error"><?php echo wp_kses_post( $text ); ?></p>
				</div>
				<button type="button" class="close-modal" data-modal-hide="disconnect-deactivate-plugin-modal">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd"
							d="M0.293031 1.29308C0.480558 1.10561 0.734866 1.00029 1.00003 1.00029C1.26519 1.00029 1.5195 1.10561 1.70703 1.29308L6.00003 5.58608L10.293 1.29308C10.3853 1.19757 10.4956 1.12139 10.6176 1.06898C10.7396 1.01657 10.8709 0.988985 11.0036 0.987831C11.1364 0.986677 11.2681 1.01198 11.391 1.06226C11.5139 1.11254 11.6255 1.18679 11.7194 1.28069C11.8133 1.37458 11.8876 1.48623 11.9379 1.60913C11.9881 1.73202 12.0134 1.8637 12.0123 1.99648C12.0111 2.12926 11.9835 2.26048 11.9311 2.38249C11.8787 2.50449 11.8025 2.61483 11.707 2.70708L7.41403 7.00008L11.707 11.2931C11.8892 11.4817 11.99 11.7343 11.9877 11.9965C11.9854 12.2587 11.8803 12.5095 11.6948 12.6949C11.5094 12.8803 11.2586 12.9855 10.9964 12.9878C10.7342 12.99 10.4816 12.8892 10.293 12.7071L6.00003 8.41408L1.70703 12.7071C1.51843 12.8892 1.26583 12.99 1.00363 12.9878C0.741432 12.9855 0.49062 12.8803 0.305212 12.6949C0.119804 12.5095 0.0146347 12.2587 0.0123563 11.9965C0.0100779 11.7343 0.110873 11.4817 0.293031 11.2931L4.58603 7.00008L0.293031 2.70708C0.10556 2.51955 0.000244141 2.26525 0.000244141 2.00008C0.000244141 1.73492 0.10556 1.48061 0.293031 1.29308Z"
							fill="#1B004E" />
					</svg>

				</button>
			</div>
			<!-- Modal body -->
			<div class="modal-body modal-body-scrollable">
				<?php if ( ! empty( $options ) ) : ?>
					<div class="accordion-collapse deactivate-options">
						<?php foreach ( $options as $option ) : ?>
							<div id="accordion-collapse-heading-<?php echo esc_attr( $option['id'] ); ?>"
								class="accordion-collapse-item">
								<div class="accordion-header">
									<?php $components->render_fancy_radio( $option['id'], $option['id'], 'deactivate-option', false, $option['name'], false ); ?>
								</div>
								<div class="accordion-content <?php echo isset( $option['form_fields'] ) && is_array( $option['form_fields'] ) ? 'has-form-fields' : ''; ?>"
									id="accordion-collapse-content-<?php echo esc_attr( $option['id'] ); ?>">
									<div class="description">
										<?php if ( isset( $option['icon'] ) && $option['icon'] ) : ?>
											<img src="<?php echo esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/' . esc_attr( $option['icon'] ) ); ?>"
												alt="" class="option-icon">
										<?php endif; ?>
										<p><?php echo wp_kses_post( $option['description'] ); ?></p>
									</div>
									<?php if ( isset( $option['form_fields'] ) && is_array( $option['form_fields'] ) ) : ?>
										<div class="form-fields">
											<?php foreach ( $option['form_fields'] as $field ) : ?>
												<div class="form-field">
													<?php if ( $field['type'] === 'text' ) : ?>
														<input type="text" name="<?php echo esc_attr( $field['name'] ); ?>"
															placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
													<?php elseif ( $field['type'] === 'textarea' ) : ?>
														<textarea name="<?php echo esc_attr( $field['name'] ); ?>"
															placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"></textarea>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="modal-footer">
				<div class="left-side">
					<?php if ( $is_connected ) : ?>
						<button type="button" class="btn btn-secondary hidden" id="enable-test-mode"
							data-default-text="<?php esc_attr_e( 'Enable test mode', 'nitropack' ); ?>"
							data-loading-text="<?php esc_attr_e( 'Enabling Test mode...', 'nitropack' ); ?>"
							data-success-text="<?php esc_attr_e( 'Test Mode enabled', 'nitropack' ); ?>"
							data-loading-icon="<?php echo esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/loading.svg' ); ?>"
							data-success-icon="<?php echo esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/check.svg' ); ?>"><?php esc_html_e( 'Enable test mode', 'nitropack' ); ?></button>
					<?php endif; ?>
					<a href="<?php echo esc_url( $support_url ); ?>" target="_blank"
						class="btn btn-secondary hidden"
						id="nitro-contact-support"><?php esc_html_e( 'Contact Support', 'nitropack' ); ?> <img
							src="<?php echo esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/external_link.svg' ); ?>"
							alt="" class="ml-2" /></a>
				</div>
				<div class="right-side"><button data-modal-hide="disconnect-deactivate-plugin-modal" type="button"
						class="btn btn-primary hidden" id="deactivate-np" data-state="<?php echo esc_attr( $state ); ?>"
						data-default-text="<?php echo esc_attr( ucfirst( $state ) ); ?>"
						data-loading-text="<?php echo esc_attr( $deactivating ? esc_html__( 'Deactivating...', 'nitropack' ) : esc_html__( 'Disconnecting...', 'nitropack' ) ); ?>"
						data-success-text="<?php echo esc_attr( $deactivating ? esc_html__( 'Deactivated', 'nitropack' ) : esc_html__( 'Disconnected', 'nitropack' ) ); ?>"
						data-loading-icon="<?php echo esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/loading.svg' ); ?>"
						data-success-icon="<?php echo esc_url( NITROPACK_PLUGIN_DIR_URL . 'assets/img/check.svg' ); ?>"><?php echo esc_html( ucfirst( $state ) ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>