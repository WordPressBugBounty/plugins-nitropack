<?php

namespace NitroPack\WordPress;

use WC_Product;

/**
 * Post invalidations on specific events
 */
class Invalidations {
	/**
	 * instance of the class
	 * @var Invalidations|null
	 */
	private static $instance = null;
	/**
	 * New updated product props for the current request.
	 * @var array $updated_product_props
	 */
	private $updated_product_props = [];

	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		//pre post update - capture the post, products, taxonomies, and meta before it is updated
		add_action( 'pre_post_update', [ $this, 'log_post_pre_update' ], 10, 3 );
		add_filter( 'woocommerce_rest_pre_insert_product_object', [ $this, 'log_product_pre_api_update' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'auto_invalidate_post_on_save' ], 10, 3 );
		//products
		add_action( 'woocommerce_update_product', [ $this, 'invalidate_product_on_update' ], 10, 2 );
		add_action( 'woocommerce_product_object_updated_props', [ $this, 'capture_product_updated_props' ], 10, 2 );
		//taxonomies
		add_action( 'set_object_terms', [ $this, 'nitropack_sot' ], 10, 6 );
		//Comments
		add_action( 'transition_comment_status', [ $this, 'auto_invalidate_comment_on_save' ], 10, 3 );
		add_action( 'comment_post', [ $this, 'new_comment' ], 10, 3 );

		//execute all queue invalidations on shutdown
		register_shutdown_function( [ $this, 'execute_purges' ] );
		register_shutdown_function( [ $this, 'execute_invalidations' ] );
		register_shutdown_function( [ $this, 'execute_warmups' ] );
	}

	/**
	 * Logs the post, taxonomies, and meta before it is updated.
	 * @param int $postID
	 */
	public function log_post_pre_update( int $postID ) {
		if ( in_array( $postID, \NitroPack\WordPress\NitroPack::$ignoreUpdatePostIDs ) ) {
			return;
		}
		$post = get_post( $postID );
		\NitroPack\WordPress\NitroPack::$preUpdatePosts[ $postID ] = $post;
		\NitroPack\WordPress\NitroPack::$preUpdateTaxonomies[ $postID ] = nitropack_get_taxonomies( $post );
		//Is post meta updated at this point? Or maybe this block should be moved to a different action?
		\NitroPack\WordPress\NitroPack::$preUpdateMeta[ $postID ] = get_post_meta( $postID );
	}

	/**
	 * If we are not adding/creating a new product, we log the product's current state before it is updated.
	 * 
	 * @param WC_Data         $product  Object object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating If is creating a new object.
	 */
	public function log_product_pre_api_update( \WC_Data $product, \WP_REST_Request $request, bool $creating ) {

		if ( ! $creating ) {

			$postID = $product->get_id();
			if ( in_array( $postID, \NitroPack\WordPress\NitroPack::$ignoreUpdatePostIDs ) ) {
				return;
			}


			$post = get_post( $postID );
			\NitroPack\WordPress\NitroPack::$preUpdatePosts[ $postID ] = $post;
			\NitroPack\WordPress\NitroPack::$preUpdateTaxonomies[ $postID ] = nitropack_get_taxonomies( $post );
			//Is post meta updated at this point? Or maybe this block should be moved to a different action?
			\NitroPack\WordPress\NitroPack::$preUpdateMeta[ $postID ] = get_post_meta( $postID );
		}

		return $product;
	}
	/**
	 * Automatically invalidate a post and its related pages (taxonomies) based on the post status transition.
	 * Relies on Purge cache option to be enabled.
	 * @param string $new_status
	 * @param string $old_status
	 * @param \WP_Post $post
	 * @return void
	 */
	public function auto_invalidate_post_on_save( string $new_status, string $old_status, \WP_Post $post ) {
		if ( wp_is_post_revision( $post ) ) {
			return;
		}
		if ( ! empty( $post->ID ) && in_array( $post->ID, \NitroPack\WordPress\NitroPack::$ignoreUpdatePostIDs ) ) {
			return;
		}
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		try {
			if ( $new_status === "auto-draft" || ( $new_status === "draft" && $old_status === "auto-draft" ) || ( $new_status === "draft" && $old_status != "publish" ) || $new_status === "inherit" ) { // Creating a new post or draft, don't do anything for now. 
				return;
			}

			$ignoredPostTypes = array(
				"revision",
				"scheduled-action",
				"flamingo_contact",
				"carts"/*WooCommerce Cart Reports*/
			);

			$postType = isset( $post->post_type ) ? $post->post_type : "post";
			$nicePostTypeLabel = nitropack_get_nice_post_type_label( $postType );

			if ( in_array( $postType, $ignoredPostTypes ) ) {
				return;
			}

			switch ( $postType ) {
				case "nav_menu_item":
					nitropack_invalidate( null, null, sprintf( "Invalidation of all pages due to modifying menu entries" ) );
					break;
				case "customize_changeset":
					nitropack_invalidate( null, null, sprintf( "Invalidation of all pages due to applying appearance customization" ) );
					break;
				case "custom_css":
					nitropack_invalidate( null, null, sprintf( "Invalidation of all pages due to modifying custom CSS" ) );
					break;
				default:
					if ( $new_status == "future" ) {
						//scheduled posts
						nitropack_clean_post_cache( $post, array( 'added' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to scheduling %s '%s'", $nicePostTypeLabel, $post->post_title ) );
					} else if ( $new_status === 'publish' && $old_status === 'trash' ) {
						//untrashed posts
						nitropack_clean_post_cache( $post, array( 'added' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to restoring %s '%s'", $nicePostTypeLabel, $post->post_title ) );
					} else if ( $new_status == "publish" && $old_status != "publish" ) {
						/* Handle first publish */
						\NitroPack\WordPress\NitroPack::$np_loggedWarmups[] = get_permalink( $post->ID );
						if ( ! defined( 'NITROPACK_PURGE_CACHE' ) ) {
							nitropack_clean_post_cache( $post, array( 'added' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to publishing %s '%s'", $nicePostTypeLabel, $post->post_title ), true );
						}
						if ( $post->post_type === 'post' ) {
							nitropack_invalidate( null, "pageType:blogindex", 'Invalidation of blog page due to changing related post status' );
						}
					} else if ( $new_status == "trash" && $old_status == "publish" ) {
						//Trashed Posts
						nitropack_clean_post_cache( $post, array( 'deleted' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to deleting %s '%s'", $nicePostTypeLabel, $post->post_title ), true );
					} else if ( $new_status == "private" && $old_status == "publish" ) {
						nitropack_clean_post_cache( $post, array( 'deleted' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to making %s '%s' private", $nicePostTypeLabel, $post->post_title ), true );
					} else if ( $new_status == "draft" && $old_status == "publish" ) {
						nitropack_clean_post_cache( $post, array( 'deleted' => nitropack_get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to making %s '%s' a draft", $nicePostTypeLabel, $post->post_title ), true );
					} else if ( $new_status != "trash" ) {
						// regular post update, first we need to check if the post has changed, and if it has, we invalidate the post and its related pages (taxonomies)
						if ( ! defined( 'NITROPACK_PURGE_CACHE' ) ) {
							nitropack_detect_changes_and_clean_post_cache( $post );
						}
						if ( $new_status == 'publish' ) {
							\NitroPack\WordPress\NitroPack::$np_loggedWarmups[] = get_permalink( $post->ID );
						}
					}
					break;
			}
		} catch (\Exception $e) {
			// TODO: Log the error
		}
	}
	/**
	 * Capture updated WooCommerce product props for the current request.
	 *
	 * @param WC_Product $product       Product object.
	 * @param array      $updated_props Updated props.
	 * @return void
	 */
	public function capture_product_updated_props( \WC_Product $product, array $updated_props ) {
		if ( ! $product instanceof \WC_Product || ! is_array( $updated_props ) ) {
			return;
		}

		$this->updated_product_props[ (int) $product->get_id()] = $updated_props;
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

		// Stock-related props that we want to ignore for invalidation.
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

	/**
	 * Invalidate single post due to comment status change.
	 * @param string $new_status
	 * @param string $old_status
	 * @param \WP_Comment $comment
	 * @return void
	 */
	public function auto_invalidate_comment_on_save( string $new_status, string $old_status, \WP_Comment $comment ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		$postID = $comment->comment_post_ID;
		$post = get_post( $postID );
		$postType = isset( $post->post_type ) ? $post->post_type : "post";
		$cpt_optimization = \NitroPack\WordPress\Settings\CPTOptimization::getInstance();
		$cacheableObjectTypes = $cpt_optimization->nitropack_get_cacheable_object_types();

		if ( in_array( $postType, $cacheableObjectTypes ) ) {
			nitropack_invalidate( null, "single:" . $postID, sprintf( "Invalidation of '%s' due to changing related comment status", $post->post_title ) );
		}

	}

	/**
	 * Invalidate single post when a new and approved comment is posted.
	 * @param int $comment_id
	 * @param int $comment_approved 1 if the comment is approved, 0 if not.
	 * @param array $comment_data
	 * @return void
	 */
	public function new_comment( int $comment_id, int $comment_approved, array $comment_data ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) || $comment_approved !== 1 ) {
			return;
		}

		$post_id = $comment_data['comment_post_ID'];
		$post_title = get_the_title( $post_id );
		nitropack_invalidate( null, "single:" . $post_id, sprintf( "Invalidation of '%s' due to posting a new approved comment", $post_title ) );
	}
	public function queue_sort( $a, $b ) {
		if ( $a["priority"] == $b["priority"] ) {
			return 0;
		}
		return ( $a["priority"] < $b["priority"] ) ? -1 : 1;
	}

	/**
	 * Execute all logged purges in the queue.
	 * Used in nitropack_purge() only.
	 * @return void
	 */
	public function execute_purges() {
		global $np_loggedPurges;
		if ( ! empty( $np_loggedPurges ) ) {
			uasort( $np_loggedPurges, [ $this, "queue_sort" ] );
			foreach ( $np_loggedPurges as $requestKey => $data ) {
				nitropack_sdk_purge( $data["url"], $data["tag"], $data["reason"] );
			}
		}
	}

	/**
	 * Execute all logged invalidations in the queue.
	 * Used in nitropack_invalidate() only.
	 * Doesn't execute for posts which are manually invalidated by user.
	 * @return void
	 */
	public function execute_invalidations() {
		global $np_loggedInvalidations;
		if ( ! empty( $np_loggedInvalidations ) ) {
			uasort( $np_loggedInvalidations, [ $this, "queue_sort" ] );
			foreach ( $np_loggedInvalidations as $requestKey => $data ) {
				nitropack_sdk_invalidate( $data["url"], $data["tag"], $data["reason"] );
			}
		}
	}

	/**
	 * Execute all logged warmups in the queue.
	 * @return void
	 */
	public function execute_warmups() {
		if ( ! empty( $_GET["action"] ) && ( $_GET["action"] === "edit" ) && ! empty( $_GET["meta-box-loader"] ) ) {
			return;
		}

		try {
			if ( ! empty( \NitroPack\WordPress\NitroPack::$np_loggedWarmups ) && ( null !== $nitro = get_nitropack_sdk() ) ) {
				$warmupStats = $nitro->getApi()->getWarmupStats();
				if ( ! empty( $warmupStats["status"] ) ) {
					foreach ( array_unique( \NitroPack\WordPress\NitroPack::$np_loggedWarmups ) as $url ) {
						$nitro->getApi()->runWarmup( $url );
					}
				}
			}
		} catch (\Exception $e) {
		}
	}
}
