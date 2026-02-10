<?php

namespace NitroPack\WordPress;

use WC_Product;

/**
 * Post invalidations on specific events
 */
class Invalidations {
	private static $instance = null;
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_update_product', [ $this, 'invalidate_product_on_update' ], 10, 2 );
		add_action( 'set_object_terms', [ $this, 'nitropack_sot' ], 10, 6 );
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
			$cleanCache = $purgeCache ? "YES" : "NO";
			if ( $purgeCache ) {
				\NitroPack\WordPress\NitroPack::$np_loggedWarmups[] = get_permalink( $post );
				nitropack_clean_post_cache( $post );
				define( 'NITROPACK_PURGE_CACHE', true );
			}
		}
	}
	/**
	 * Invalidate product on update, including REST-API updates.
	 * Mainly used for REST-API, since the nitropack_handle_post_transition() does not cover that.
	 * @param int $id
	 * @param WC_Product $product
	 * @return void
	 */
	public function invalidate_product_on_update( $id, $product ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
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
}