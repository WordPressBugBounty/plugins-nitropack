<?php
namespace NitroPack\WordPress\Settings;

use NitroPack\WordPress\Nitropack;

class StockRefresh {
	private static $instance = NULL;
	public $option_name;
	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_stock_reduce_status', [ $this, 'nitropack_set_stock_reduce_status' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this, 'invalidate_pages_on_product_stock_change' ], 10, 1 );
		add_action( 'woocommerce_product_set_stock', [ $this, 'invalidate_pages_on_product_stock_change' ], 10, 1 );
		$this->option_name = 'nitropack-stockReduceStatus';
	}
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * AJAX - enable or disable the setting in Dashboard
	 * @return void
	 */
	public function nitropack_set_stock_reduce_status() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = (int) ! empty( $_POST["data"]["stockReduceStatus"] );
		$updated = update_option( $this->option_name, $option );
		if ( $updated ) {
			Nitropack::getInstance()->getLogger()->notice( 'Stock Reduce Status is ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), "stockReduceStatus" => $option ) );
		} else {
			Nitropack::getInstance()->getLogger()->error( 'Stock Reduce Status cannot be' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}
	/**
	 * Invalidate related pages when stock crosses zero (to 0 or from 0 to positive).
	 * For all other stock changes, invalidate only the product page.
	 * @param \WC_Product $product_with_stock The WooCommerce product object.
	 * @return void
	 */
	public function invalidate_pages_on_product_stock_change( $product_with_stock ) {
		if ( (int) get_option( $this->option_name ) !== 1 ) {
			return;
		}
		$product_manage_stock = $product_with_stock->managing_stock();
		if ( ! $product_manage_stock ) {
			return;
		}
		$product_id = (int) $product_with_stock->get_id();
		$post = get_post( $product_id );
		$product_new_stock = $product_with_stock->get_stock_quantity();
		$product_data = method_exists( $product_with_stock, 'get_data' ) ? $product_with_stock->get_data() : [];
		$product_old_stock = isset( $product_data['stock_quantity'] ) ? $product_data['stock_quantity'] : null;

		$product_old_stock = is_numeric( $product_old_stock ) ? (float) $product_old_stock : null;
		$product_new_stock = is_numeric( $product_new_stock ) ? (float) $product_new_stock : null;

		$went_out_of_stock = null !== $product_new_stock && 0.0 === $product_new_stock && ( null === $product_old_stock || 0.0 !== $product_old_stock );
		$went_back_in_stock = null !== $product_old_stock && 0.0 === $product_old_stock && null !== $product_new_stock && $product_new_stock > 0;
		
		if ( $went_out_of_stock || $went_back_in_stock ) {
			$reason = $went_out_of_stock ? "out of stock" : "back in stock";
			nitropack_clean_post_cache( $post, array( 'added' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to %s change on product '%s'", $reason, $post->post_title ), true );
		} else {
			nitropack_clean_post_cache( $post, NULL, false, sprintf( "Invalidate stock change on product '%s'", $post->post_title ) );
		}
	}
	/**
	 * Renders the setting in Dashboard
	 * @return void
	 */
	public function render() {
		$stockReduceStatus = get_option( $this->option_name );
		?>
		<div class="nitro-option" id="real-time-stock-refresh-widget">
			<div class="nitro-option-main">
				<div class="text-box">
					<h6><?php esc_html_e( 'Real-time Stock Refresh', 'nitropack' ); ?></h6>
					<p>
						<?php esc_html_e( 'Keeps product availability accurate by refreshing the cache whenever stock levels change. Best for stores that show stock quantities.', 'nitropack' ); ?>
					</p>

				</div>
				<?php $components = new Components();
				$components->render_toggle( 'woo-stock-reduce-status', $stockReduceStatus );
				?>
			</div>
		</div>
		<?php
	}
}