<?php

namespace NitroPack\WordPress;

use WC_Product;

/**
 * Post invalidations on specific events
 */
class Invalidations {
	private static $instance = null;
	private $updated_product_props = [];

	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_update_product', [ $this, 'invalidate_product_on_update' ], 10, 2 );
		add_action( 'woocommerce_product_object_updated_props', [ $this, 'capture_product_updated_props' ], 10, 2 );
		add_action( 'set_object_terms', [ $this, 'nitropack_sot' ], 10, 6 );
	}

	/**
	 * Capture updated WooCommerce product props for the current request.
	 *
	 * @param WC_Product $product       Product object.
	 * @param array      $updated_props Updated props.
	 * @return void
	 */
	public function capture_product_updated_props( $product, $updated_props ) {
		if ( ! $product instanceof WC_Product || ! is_array( $updated_props ) ) {
			return;
		}

		$this->updated_product_props[ (int) $product->get_id() ] = $updated_props;
	}
	/**
	 * Fires after a single post taxonomy (categories, tags etc) has been updated -> assigned or removed.
	 * @param int $object_id
	 * @param array $terms
	 * @param array $tt_ids
	 * @param string $taxonomy
	 * @param bool $append
	 * @param array $old_tt_ids
	 * @return void
	 */
	public function nitropack_sot( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		$post = get_post( $object_id );
		$post_status = $post->post_status;

		if ( $post_status === 'auto-draft' || $post_status === 'draft' ) {
			return;
		}

		if ( ! defined( 'NITROPACK_PURGE_CACHE' ) ) {
			$purgeCache = ! nitropack_compare_posts( $tt_ids, $old_tt_ids );
			if ( $purgeCache ) {
				\NitroPack\WordPress\NitroPack::$np_loggedWarmups[] = get_permalink( $post );
				nitropack_clean_post_cache( $post );
				define( 'NITROPACK_PURGE_CACHE', true );
			}
		}
	}
	/**
	 * Invalidate product on update.
	 *
	 * @param int        $id      Product ID.
	 * @param WC_Product $product Product object.
	 * @return void
	 */
	public function invalidate_product_on_update( $id, $product ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		if ( $this->should_skip_for_stock_only_update( $id ) ) {
			return;
		}

		if ( ! defined( 'NITROPACK_PURGE_CACHE' ) ) {
			try {
				$post = get_post( $id );
				nitropack_detect_changes_and_clean_post_cache( $post );
				define( 'NITROPACK_PURGE_CACHE', true );
			} catch (\Exception $e) {

			}
		}
	}

	/**
	 * Skip invalidation when the update changed stock-related props only.
	 *
	 * @param int $id Product ID.
	 * @return bool
	 */
	private function should_skip_for_stock_only_update( $id ) {
		$product_id = (int) $id;
		$has_snapshot = array_key_exists( $product_id, $this->updated_product_props );
		$updated_props = isset( $this->updated_product_props[ $product_id ] ) && is_array( $this->updated_product_props[ $product_id ] )
			? $this->updated_product_props[ $product_id ]
			: [];

		unset( $this->updated_product_props[ $product_id ] );

		// Empty snapshots mean WooCommerce fired the update hook without product prop changes.
		if ( $has_snapshot && empty( $updated_props ) ) {
			return true;
		}

		if ( empty( $updated_props ) ) {
			return false;
		}

		$stock_props = [
			'manage_stock',
			'stock_quantity',
			'stock_status',
			'backorders',
			'low_stock_amount',
		];

		foreach ( $updated_props as $prop ) {
			if ( ! in_array( $prop, $stock_props, true ) ) {
				return false;
			}
		}

		return true;
	}
}
