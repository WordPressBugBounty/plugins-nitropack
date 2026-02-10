<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\HttpClient\HttpClient;

class OptimizationLevel {
	private static $instance = NULL;
	private $levels = [
		1 => "standard",
		2 => "medium",
		3 => "strong",
		4 => "ludicrous",
		5 => "custom"
	];
	public $level_name = '';
	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_optimization_mode', [ $this, 'nitropack_set_optimization_mode' ] );
	}
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/* Ajax handler to set optimization mode. Use our HTTPClient to send data.
	 * @return void
	 */
	public function nitropack_set_optimization_mode() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$mode_name = ! empty( $_POST["mode_name"] ) ? sanitize_text_field( $_POST["mode_name"] ) : null;

		$quickSetupSave = get_nitropack_integration_url( "optimization_preset" );
		$quickSetupHTTP = new HttpClient( $quickSetupSave );
		$quickSetupHTTP->setHeader( "Content-Type", "application/x-www-form-urlencoded" );
		$quickSetupHTTP->setPostData( "optimization_preset=" . $mode_name );
		$quickSetupHTTP->fetch( true, "POST" );
		switch ( $quickSetupHTTP->getStatusCode() ) {
			case 200:
				\NitroPack\WordPress\NitroPack::getInstance()->getLogger()->notice( '[Preview] Optimization mode set to: ' . $mode_name );
				nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), "mode" => $mode_name ) );
				break;
			case 400:
				\NitroPack\WordPress\NitroPack::getInstance()->getLogger()->error( '[Preview] Tried to set optimization mode to: ' . $mode_name );
				nitropack_json_and_exit( array( "type" => "error", 'message' => json_decode( $quickSetupHTTP->getBody(), true )['error_message'] ) );
				break;
			case 503:
				\NitroPack\WordPress\NitroPack::getInstance()->getLogger()->error( '[Preview] Tried to set optimization mode to: ' . $mode_name );
				nitropack_json_and_exit( array( "type" => "error", "message" => json_decode( $quickSetupHTTP->getBody(), true )['error_message'] ) );
				break;
			default:
				\NitroPack\WordPress\NitroPack::getInstance()->getLogger()->error( '[Preview] Tried to set optimization mode to: ' . $mode_name );
				nitropack_json_and_exit( array( "type" => "error", "message" => "An unexpected error occurred. Please try again later." ) );
				break;
		}
	}
	/**
	 * Fetch user plan from NitroPack App
	 * @return string Plan name - Free, etc.
	 */
	public function fetch_plan() {
		$planDetailsUrl = get_nitropack_integration_url( "plan_details_json" );
		$quickSetupHTTP = new HttpClient( $planDetailsUrl );
		$quickSetupHTTP->timeout = 30;
		$quickSetupHTTP->fetch();
		$resp = $quickSetupHTTP->getStatusCode() == 200 ? json_decode( $quickSetupHTTP->getBody(), true ) : false;
		$plan = $resp && isset( $resp['plan_title'] ) ? $resp['plan_title'] : null;
		return $plan;
	}
	/**
	 * Fetch optimization modes data from NitroPack App
	 * @return array
	 */
	public function optimization_modes() {
		$quickSetupUrl = get_nitropack_integration_url( "optimization_preset" );
		$quickSetupHTTP = new HttpClient( $quickSetupUrl );

		$quickSetupHTTP->timeout = 30;
		$quickSetupHTTP->fetch();
		$resp = $quickSetupHTTP->getStatusCode() == 200 ? json_decode( $quickSetupHTTP->getBody(), true ) : false;
		$modes = null;
		if ( $resp && ! empty( $resp['optimization_options'] ) ) {
			$modes = $resp;
		} else {
			$modes = $this->static_modes();
		}
		return $modes;
	}
	/**
	 * Static optimization modes data when NitroPack App is unreachable
	 * @return array
	 */
	private function static_modes() {
		$free_plan = $this->fetch_plan() === 'Free' ? true : false;
		$modes = [
			'optimization_selected' => 'medium',
			'optimization_options' => [
				'standard' => [
					'human_readable_name' => 'Standard',
					'description' => 'Standard optimization features enabled for your site. Ideal choice for maximum stability.',
					'description_onboarding' => 'Applies basic optimizations.',
					'is_available' => true,
				],
				'medium' => [
					'human_readable_name' => 'Medium',
					'description' => 'Adds image lazy loading to standard optimizations. Uses built-in browser techniques for loading resources.',
					'description_onboarding' => 'Implements moderate optimizations.',
					'is_available' => true,
				],
				'strong' => [
					'human_readable_name' => 'Strong',
					'description' => 'Includes smart resource loading on top of Medium optimizations. Balances speed boost with stability.',
					'description_onboarding' => 'Strikes a great balance between stability and website speed.',
					'is_available' => true,
				],
				'ludicrous' => [
					'human_readable_name' => 'Ludicrous',
					'description' => 'Applies deferred JS and advanced resource loading for optimal performance and Core Web Vitals.',
					'description_onboarding' => 'The most powerful optimization setting, aiming for best possible performance.',
					'is_available' => $free_plan ? false : true,
				],
				'custom' => [
					'human_readable_name' => 'Custom',
					'description' => 'Activated when manual setups are made. Ideal for advanced NitroPack optimizations.',
					'description_onboarding' => 'Activated when manual setups are made. Ideal for advanced NitroPack optimizations.',
					'is_available' => false,
				],
			],
		];
		return $modes;
	}
	/**
	 * Fetch optimization name from NitroPack App
	 * @return array Optimization name (standard, medium, etc.)
	 */
	public function fetch_optimization_name() {
		if ( ! empty( $this->optimization_modes() ) ) {
			$optimization_name = $this->optimization_modes()['optimization_selected'];
		}

		return $optimization_name;
	}
	/**
	 * Render optimization level setting HTML
	 */
	public function render() {
		$modes = $this->optimization_modes(); ?>
		<div class="card card-optimization-mode">
			<div class="card-header no-border mb-0">
				<div class="flex items-center">
					<h3 class="mb-0"><?php esc_html_e( 'Optimization mode', 'nitropack' ); ?></h3>
					<span class="tooltip-icon" data-tooltip-target="tooltip-optimization">
						<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/info.svg'; ?>">
					</span>
					<div id="tooltip-optimization" role="tooltip" class="tooltip-container hidden">
						<?php esc_html_e( 'Select from our range of predefined optimization modes to boost your site\'s performance.', 'nitropack' );
						?>
						<div class="tooltip-arrow" data-popper-arrow></div>
					</div>
				</div>
			</div>
			<?php ?>
			<div class="tabs-wrapper">
				<div class="tabs" id="optimization-modes">
					<?php
					$upgrade_msg = false;
					$optimization_level_name = $modes['optimization_selected'];
					foreach ( $modes['optimization_options'] as $mode_id => $mode ) :
						$active = $optimization_level_name === $mode_id;
						$css = [];
						$css[] = $active ? 'active btn-primary' : 'btn-link';
						$css[] = "mode-{$mode_id}";
						if ( ! $mode['is_available'] ) {
							$css[] = 'disabled';
						}
						if ( ! $mode['is_available'] && $mode_id === 'ludicrous' ) {
							$upgrade_msg = true;
						}
						$css = implode( ' ', $css );
						?>
						<a class="btn tab-link <?php echo $css; ?>" data-mode="<?php echo $mode_id; ?>"
							data-modal-target="modal-optimization-mode"
							data-modal-toggle="modal-optimization-mode"><?php echo $mode['human_readable_name']; ?>
							<?php if ( ! $mode['is_available'] && $mode['human_readable_name'] === 'Ludicrous' ) { ?>
								<img src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/lock.svg'; ?>" />
							<?php } ?>
						</a>
					<?php endforeach; ?>
				</div>

				<?php if ( $upgrade_msg ) : ?>
					<div class="upgrade-message bg-success">
						<p class="">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
								<g clip-path="url(#clip0_1039_2988)">
									<path
										d="M10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39765 14.6024 1.66669 10 1.66669C5.39763 1.66669 1.66667 5.39765 1.66667 10C1.66667 14.6024 5.39763 18.3334 10 18.3334Z"
										stroke="#33D2B5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
									<path d="M13.3333 10L10 6.66669L6.66667 10" stroke="#33D2B5" stroke-width="1.5"
										stroke-linecap="round" stroke-linejoin="round" />
									<path d="M10 13.3334V6.66669" stroke="#33D2B5" stroke-width="1.5" stroke-linecap="round"
										stroke-linejoin="round" />
								</g>
								<defs>
									<clipPath id="clip0_1039_2988">
										<rect width="20" height="20" fill="white" />
									</clipPath>
								</defs>
							</svg>
							<?php _e( 'Ludicrous mode available on <b>Starter</b> Plan.', 'nitropack' ); ?> <a
								href="https://app.nitropack.io/subscription/buy" class="upgrade-here"
								target="_blank"><?php esc_html_e( 'Upgrade here', 'nitropack' ); ?></a>
						</p>
					</div>
				<?php endif; ?>

				<p><?php esc_html_e( 'Active Mode', 'nitropack' ); ?>: <span
						class="active-mode"><?php echo esc_html( $optimization_level_name ); ?></span></p>

				<div class="tab-content-wrapper">
					<?php foreach ( $modes['optimization_options'] as $mode_id => $mode ) :
						$active = ( $optimization_level_name === $mode_id ? true : false );
						$css = [];
						$css[] = ( $active ? '' : 'hidden' );
						$css = implode( ' ', array_filter( $css ) );
						?>
						<div class="tab-content <?php echo esc_attr( $css ); ?>" role="tabpanel"
							data-tab="<?php echo $mode_id; ?>-tab">
							<p class="text-secondary mt-2">
							<?php echo esc_html( $mode['description'] ); ?>
							</p>
						</div>
					<?php endforeach; ?>

					<div class="hidden tab-content" role="tabpanel" data-tab="custom-tab">
						<p class="text-secondary mt-2">
							<?php esc_html_e( 'Activated when manual setups are made. Ideal for advanced NitroPack optimizations.', 'nitropack' ); ?>
						</p>
					</div>
				</div>
			</div>
			<div class="card-footer">
				<div class="flex flex-row">
					<p class=""><?php esc_html_e( 'Which optimization mode to choose?', 'nitropack' ); ?></p>
					<a class="text-primary btn-link ml-auto see-modes" data-modal-target="modes-modal"
						data-modal-toggle="modes-modal"><?php esc_html_e( 'See modes comparison', 'nitropack' ); ?></a>
					<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-modes.php'; ?>
				</div>
			</div>
			<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-optimization-mode.php'; ?>
		</div>
	<?php }
	public function preview_render() {
		$optimization_modes = $this->optimization_modes();
		unset( $optimization_modes['optimization_options']['custom'] );
		?>
		<div class="optimization-modes">
			<div class="flex flex-row">
				<h2><?php esc_html_e( 'Optimization modes', 'nitropack' ); ?></h2>
				<a class="text-primary btn-link ml-auto see-modes" data-modal-target="modes-modal"
					data-modal-toggle="modes-modal"><?php esc_html_e( 'See modes comparison', 'nitropack' ); ?></a>
				<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-modes.php'; ?>
			</div>
			<div class="modes-container">
				<?php $chevrons = 1;
				$active_mode_name = $optimization_modes['optimization_selected'];
				foreach ( $optimization_modes['optimization_options'] as $key => $mode ) :
					$active = ( $active_mode_name === $key ? true : false );
					$css = [];
					$css[] = $active ? 'active' : '';
					$css[] = "mode-{$key}";
					$css = implode( ' ', $css );
					?>
					<div class="mode <?php echo $css; ?>" data-mode="<?php echo esc_attr( $key ); ?>">
						<div class="header-text">
							<div class="mode-header">
								<div class="chevron-wrapper">
									<?php if ( $mode['human_readable_name'] === 'Ludicrous' ) : ?>
										<svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path
												d="M9.63151 2.12634L2.96484 10.1263H8.96484L8.29818 15.4597L14.9648 7.45968H8.96484L9.63151 2.12634Z"
												stroke="#776795" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php else :
										for ( $i = 0; $i < $chevrons; $i++ ) : ?>
											<svg xmlns="http://www.w3.org/2000/svg" width="10" height="6" viewBox="0 0 10 6" fill="none">
												<path d="M9.29688 4.79297L5.29688 0.792969L1.29688 4.79297" stroke="#776795"
													stroke-linecap="round" stroke-linejoin="round" />
											</svg>
										<?php endfor;
									endif; ?>
								</div>
								<h3><?php echo esc_html( $mode['human_readable_name'] ); ?></h3>
								<?php if ( ! $mode['is_available'] && $mode['human_readable_name'] === 'Ludicrous' ) : ?>
									<div class="ml-auto">
										<?php echo '<span class="badge badge-success">' . esc_html__( 'Available on Starter', 'nitropack' ) . '</span>' ?>
									</div>
								<?php endif; ?>
							</div>
							<p><?php echo esc_html( $mode['description_onboarding'] ); ?></p>
						</div>
						<?php if ( $active ) : ?>

							<a class="btn btn-secondary select-mode selected">
								<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9"
									fill="none">
									<path d="M11.4674 0.792969L4.13411 8.1263L0.800781 4.79297" stroke="#4600CC" stroke-linecap="round"
										stroke-linejoin="round" />
								</svg><?php esc_html_e( 'Active Mode', 'nitropack' ); ?></a>
						<?php else :
							if ( ! $mode['is_available'] ) {
								?>
								<a class="btn btn-secondary text-center <?php echo esc_attr( $css ); ?>"
									href="https://app.nitropack.io/subscription/buy" target="_blank"><img
										src="<?php echo plugin_dir_url( NITROPACK_FILE ) . 'view/images/lock.svg'; ?>" />
									<?php esc_html_e( 'Upgrade plan', 'nitropack' ); ?></a>
							<?php } else { ?>
								<a
									class="btn btn-secondary select-mode <?php echo esc_attr( $css ); ?>"><?php esc_html_e( 'Select Mode', 'nitropack' ); ?></a>
							<?php }
						endif; ?>
					</div>
					<?php
					$chevrons++;
				endforeach; ?>

			</div>
		</div>
	<?php }
}