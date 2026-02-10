<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\HttpClient\HttpClient;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/* Subscription class to handle subscription related functionalities */
class Subscription {
	private static $instance = null;

	/**
	 * Fetch plan details from NitroPack API
	 */
	public function fetch_plan() {
		$planDetailsUrl = get_nitropack_integration_url( "plan_details_json" );
		$quickSetupHTTP = new HttpClient( $planDetailsUrl );
		$quickSetupHTTP->timeout = 30;
		$quickSetupHTTP->fetch();
		$resp = $quickSetupHTTP->getStatusCode() == 200 ? json_decode( $quickSetupHTTP->getBody(), true ) : false;
		return $resp;
	}
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Render subscription box in our Dashboard
	 */
	public function render() {
		$cdn_bandwidth_used = 'N/A';
		$max_cdn_bandwidth = 'N/A';
		$page_views = 'N/A';
		$max_page_views = 'N/A';
		$plan = $this->fetch_plan();
		if ( $plan ) {
			$plan_title = isset( $plan['plan_title'] ) ? $plan['plan_title'] : 'N/A';
			$next_reset = isset( $plan['next_reset'] ) ? $plan['next_reset'] : 'N/A';
			$next_billing = isset( $plan['next_billing'] ) ? $plan['next_billing'] : 'N/A';
			$page_views = isset( $plan['page_views'] ) ? $plan['page_views'] : 'N/A';
			$max_page_views = isset( $plan['max_page_views'] ) ? $plan['max_page_views'] : 'N/A';
			$cdn_bandwidth_used = isset( $plan['cdn_bandwidth'] ) ? $plan['cdn_bandwidth'] : 'N/A';
			$max_cdn_bandwidth = isset( $plan['max_cdn_bandwidth'] ) ? $plan['max_cdn_bandwidth'] : 'N/A';
		}
		?>
		<div class="card card-subscription">
			<div class="card-header">
				<h3><?php esc_html_e( 'Subscription', 'nitropack' ); ?></h3>
			</div>
			<div class="card-body">
				<div class="flex flex-row items-center">
				<div class="plan-name"><?php echo esc_html( $plan_title ); ?></div>
					<?php $components = new Components();
					echo $components->render_button( [ 'text' => 'Manage subscription', 'type' => null, 'classes' => 'btn btn-secondary ml-auto', 'href' => 'https://app.nitropack.io/account/billing', 'attributes' => [ 'id' => 'btn-manage-subscription', 'target' => '_blank' ] ] );
					?>
				</div>
				<div class="table-wrapper">
					<table class="w-full">
						<tbody>
							<tr>
								<td class="key"><?php esc_html_e( 'Next reset', 'nitropack' ); ?></td>
							<td class="value" data-next-reset><?php echo esc_html( $next_reset ); ?></td>
							</tr>
							<tr>
								<td class="key"><?php esc_html_e( 'Next billing', 'nitropack' ); ?></td>
							<td class="value" data-next-billing><?php echo esc_html( $next_billing ); ?></td>
							</tr>
							<tr>
								<td class="key"><?php esc_html_e( 'Page views', 'nitropack' ); ?></td>
								<td class="value" data-page-views>
									<?php
									/* translators: %1$s: current page views, %2$s: maximum page views */
									printf( esc_html__( '%1$s out of %2$s', 'nitropack' ), $page_views, $max_page_views );
									?>
								</td>
							</tr>
							<tr>
								<td class="key"><?php esc_html_e( 'CDN bandwidth', 'nitropack' ); ?></td>
								<td class="value" data-cdn-bandwidth>
									<?php
									/* translators: %1$s: used CDN bandwidth, %2$s: maximum CDN bandwidth */
									printf( esc_html__( '%1$s out of %2$s', 'nitropack' ), $cdn_bandwidth_used, $max_cdn_bandwidth );
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="card-footer">
				<p class="text-secondary text-smaller">
					<?php esc_html_e( 'You will be notified by email when your website reaches the subscription resource limits.', 'nitropack' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}