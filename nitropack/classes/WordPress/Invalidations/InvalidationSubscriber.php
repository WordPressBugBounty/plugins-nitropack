<?php

namespace NitroPack\WordPress\Invalidations;

/**
 * Post invalidations on specific events
 */
class InvalidationSubscriber {
	/**
	 * instance of the class
	 * @var InvalidationSubscriber|null
	 */
	private static $instance = null;
	/**
	 * Post types that never trigger an invalidation.
	 */
	const IGNORED_POST_TYPES = array( 'revision', 'scheduled-action', 'flamingo_contact', 'carts' );
	/**
	 * Post types whose change invalidates the whole site (handled in invalidate_post_on_save()).
	 */
	const SITE_WIDE_POST_TYPES = array( 'nav_menu_item', 'customize_changeset', 'custom_css' );
	/**
	 * Service performing the actual cache invalidation work.
	 * @var InvalidationService
	 */
	private $service;
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
		$this->service = InvalidationService::getInstance();
		//pre post update - capture the post, products, taxonomies, and meta before it is updated
		add_action( 'pre_post_update', [ $this, 'log_post_pre_update' ], 10, 3 );
		add_filter( 'woocommerce_rest_pre_insert_product_object', [ $this, 'log_product_pre_api_update' ], 10, 3 );
		//real time post cache invalidation on save
		add_action( 'clean_post_cache', [ $this, 'invalidate_post_on_save' ] );
		//first publish without wp_update_post() (direct inserts, wp_publish_post())
		add_action( 'transition_post_status', [ $this, 'invalidate_first_publish_without_snapshot' ], 10, 3 );
		//products
		add_action( 'woocommerce_update_product', [ $this, 'invalidate_product_on_update' ], 10, 2 );
		add_action( 'woocommerce_product_object_updated_props', [ $this, 'capture_product_updated_props' ], 10, 2 );
		//taxonomies
		add_action( 'set_object_terms', [ $this, 'invalidate_on_taxonomy_change' ], 10, 6 );
		//Comments
		add_action( 'transition_comment_status', [ $this, 'auto_invalidate_comment_on_save' ], 10, 3 );
		add_action( 'comment_post', [ $this, 'new_comment' ], 10, 3 );

		//execute all queue invalidations on shutdown
		register_shutdown_function( [ $this, 'execute_purges' ] );
		register_shutdown_function( [ $this, 'execute_invalidations' ] );
		register_shutdown_function( [ $this, 'execute_local_purges' ] );
		register_shutdown_function( [ $this, 'execute_warmups' ] );
	}

	/**
	 * Logs the post, taxonomies, and meta before it is updated.
	 * @param int $postID
	 */
	public function log_post_pre_update( int $postID ) {
		if ( InvalidationState::is_ignored( $postID ) ) {
			return;
		}
		$post = get_post( $postID );
		//Is post meta updated at this point? Or maybe this block should be moved to a different action?
		InvalidationState::capture_pre_update( $postID, $post, $this->service->get_taxonomies( $post ), get_post_meta( $postID ) );
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
			if ( InvalidationState::is_ignored( $postID ) ) {
				return $product;
			}

			$post = get_post( $postID );
			//Is post meta updated at this point? Or maybe this block should be moved to a different action?
			InvalidationState::capture_pre_update( $postID, $post, $this->service->get_taxonomies( $post ), get_post_meta( $postID ) );
		}

		return $product;
	}
	/**
	 * Pre-checks if the post is valid for cache clean.
	 * @param int $post_id
	 * @return \WP_Post|null
	 */
	private function is_post_valid_for_cache_clean( int $post_id, ?\WP_Post $post = null ): ?\WP_Post {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return null;
		}
		if ( InvalidationState::is_ignored( $post_id ) ) {
			return null;
		}
		return $post ?: get_post( $post_id );
	}
	/**
	 * Cleans the cache for a given post if it is valid for cache clean.
	 * @param int $post_id
	 * @param \WP_Post|null $post
	 * @return void
	 */
	public function invalidate_post_on_save( int $post_id, ?\WP_Post $post = null ) {

		$post = $this->is_post_valid_for_cache_clean( $post_id, $post );
		if ( ! $post ) {
			return;
		}

		$postType = isset( $post->post_type ) ? $post->post_type : "post";
		$readable_post_type_name = $this->service->readable_post_type_name( $postType );

		if ( in_array( $postType, self::IGNORED_POST_TYPES, true ) ) {
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
				$this->invalidate_post_by_status( $post, $readable_post_type_name );
				break;
		}
	}

	/**
	 * Decides how to invalidate a post based on its current status and the status it had before the update
	 * (taken from the pre-update snapshot). Runs from the clean_post_cache hook.
	 * @param \WP_Post $post
	 * @param string $label Readable post type name.
	 * @return void
	 */
	private function invalidate_post_by_status( \WP_Post $post, string $label ) {
		$post_before = self::get_post_pre_update( $post );
		$old_status = $post_before ? $post_before->post_status : null;
		$new_status = $post->post_status;
		$title = $post->post_title;

		if ( $new_status === 'publish' ) {
			if ( null === $old_status ) {
				// No snapshot: not a wp_update_post() call. A first publish this way (direct insert, wp_publish_post())
				// is handled in invalidate_first_publish_without_snapshot(); everything else (comment counts, stock
				// writes, child cache cleans) is not a post update.
				return;
			} elseif ( $old_status === 'trash' ) {
				$this->service->clean_post_cache( $post, array( 'added' => $this->service->get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to restoring %s '%s'", $label, $title ) );
			} elseif ( $old_status !== 'publish' ) {
				$this->invalidate_first_publish( $post, $label );
			} else {
				$this->detect_changes_and_clean_post_cache( $post );
			}
			return;
		}

		if ( $old_status === 'publish' ) {
			// Published content became unavailable: it must stop being served from cache immediately.
			$actions = array(
				'trash' => 'deleting %s \'%s\'',
				'private' => 'making %s \'%s\' private',
				'draft' => 'making %s \'%s\' a draft',
				'pending' => 'making %s \'%s\' pending',
				'future' => 'scheduling %s \'%s\'',
			);
			$what = isset( $actions[ $new_status ] ) ? $actions[ $new_status ] : 'changing the status of %s \'%s\'';
			$this->service->clean_post_cache( $post, array( 'deleted' => $this->service->get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to " . $what, $label, $title ), true );
		} elseif ( $new_status === 'future' && $old_status !== 'future' && null !== $old_status ) {
			$this->service->clean_post_cache( $post, array( 'added' => $this->service->get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to scheduling %s '%s'", $label, $title ) );
		}
		// Any other non-published status (draft, pending, auto-draft, inherit, private edits) is not served from cache.
	}

	/**
	 * First publish of a post that didn't go through wp_update_post(): created directly as published
	 * (wp_insert_post(), REST, importers, WP-CLI) or published with wp_publish_post() (scheduled posts via cron,
	 * plugins). There's no pre-update snapshot in these cases, so invalidate_post_by_status() can't tell them from
	 * cache cleans that aren't post updates. WordPress fires this hook after clean_post_cache.
	 * @param string $new_status
	 * @param string $old_status
	 * @param \WP_Post $post
	 * @return void
	 */
	public function invalidate_first_publish_without_snapshot( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof \WP_Post ) {
			return;
		}
		// Updates through wp_update_post() have a snapshot and are handled in invalidate_post_by_status().
		if ( wp_is_post_revision( $post ) || self::get_post_pre_update( $post ) ) {
			return;
		}
		$post = $this->is_post_valid_for_cache_clean( $post->ID, $post );
		if ( ! $post
			|| in_array( $post->post_type, self::IGNORED_POST_TYPES, true )
			|| in_array( $post->post_type, self::SITE_WIDE_POST_TYPES, true )
			|| ! is_post_type_viewable( $post->post_type ) // e.g. oembed_cache, created as published on page views
		) {
			return;
		}
		$this->invalidate_first_publish( $post, $this->service->readable_post_type_name( $post->post_type ) );
	}

	/**
	 * First publish (or a scheduled post going live): listings and taxonomy pages must pick the post up.
	 * @param \WP_Post $post
	 * @param string $label Readable post type name.
	 * @return void
	 */
	private function invalidate_first_publish( \WP_Post $post, string $label ) {
		InvalidationState::queue_warmup( get_permalink( $post->ID ) );
		$this->service->clean_post_cache( $post, array( 'added' => $this->service->get_taxonomies( $post ) ), true, sprintf( "Invalidate related pages due to publishing %s '%s'", $label, $post->post_title ), true );
	}
	/**
	 * Detects changes in the post and cleans the post cache if necessary.
	 * Compares the post before update and after update, and if there are any changes,
	 * it cleans the cache for the post and its related taxonomies.
	 * @param \WP_Post $post
	 * @return void
	 */
	public function detect_changes_and_clean_post_cache( \WP_Post $post, bool $assume_changed_if_unknown = false ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		$post_before = self::get_post_pre_update( $post );
		if ( empty( $post_before ) && ! $assume_changed_if_unknown ) {
			// No pre-update snapshot: this was not a regular post update (comment count, stock write,
			// child post cache clean...), so there is nothing to compare against.
			return;
		}
		$ignoredComparisonKeys = array( 'post_modified', 'post_modified_gmt' );

		// 1. Post fields (raw check).
		if ( $this->has_post_changed( $post_before, $post, $ignoredComparisonKeys ) ) {
			$this->clean_post_cache_and_flag( $post );
			return;
		}

		// 2. Post meta.
		$metaCurrent = get_post_meta( $post->ID );
		$metaPreUpdate = $this->get_meta_pre_update( $post );
		if ( ! $this->service->compare_posts( $metaCurrent, $metaPreUpdate ) ) {
			$this->clean_post_cache_and_flag( $post );
		}
	}

	/**
	 * Queue the post for warmup, clean its cache, and flag that a purge happened this request.
	 * @param \WP_Post $post
	 * @return void
	 */
	private function clean_post_cache_and_flag( \WP_Post $post ) {
		InvalidationState::queue_warmup( get_permalink( $post ) );
		$this->service->clean_post_cache( $post );
		if ( ! defined( 'NITROPACK_PURGE_CACHE' ) ) {
			define( 'NITROPACK_PURGE_CACHE', true );
		}
	}

	/**
	 * Determines whether a post changed in a way that requires cache invalidation.
	 * A WP_Post holds only scalar fields, so this compares them directly instead of
	 * running the recursive deep-compare, which is far cheaper on large posts.
	 * @param \WP_Post|null $post_before The post before the update, or null if unknown.
	 * @param \WP_Post $post The post after the update.
	 * @param array $ignoredKeys Post fields to skip when comparing.
	 * @return bool True if the post changed (or the previous state is unknown), false otherwise.
	 */
	private function has_post_changed( $post_before, \WP_Post $post, array $ignoredKeys = array() ) {
		if ( empty( $post_before ) ) {
			return true; // No previous snapshot to compare against, assume it changed.
		}

		// WP_Post lazily materializes these virtual properties, so one snapshot may carry them
		// while the other doesn't, without representing a real change (e.g. page_template).
		$virtualKeys = array( 'page_template', 'post_category', 'tags_input', 'ancestors' );
		$skip = array_merge( $ignoredKeys, $virtualKeys );

		$before = (array) $post_before;
		$after = (array) $post;

		foreach ( array_keys( $before + $after ) as $key ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			$beforeVal = array_key_exists( $key, $before ) ? $before[ $key ] : null;
			$afterVal = array_key_exists( $key, $after ) ? $after[ $key ] : null;

			if ( (string) $beforeVal !== (string) $afterVal ) {
				return true;
			}
		}


		return false;
	}

	/**
	 * Returns the post before it was updated, or null if it wasn't logged.
	 * @param WP_Post $post
	 * @return WP_Post|null
	 */
	public static function get_post_pre_update( \WP_Post $post ) {
		return InvalidationState::get_post_pre_update( $post );
	}

	/**
	 * Returns the taxonomies for a given post before it was updated, or an empty array if they weren't logged.
	 * @param WP_Post $post
	 * @return array of term_taxonomy_ids
	 */
	public static function get_taxonomies_pre_update( \WP_Post $post ) {
		return InvalidationState::get_taxonomies_pre_update( $post );
	}

	/**
	 * Returns the post meta for a given post before it was updated, or an empty array if it wasn't logged.
	 * @param WP_Post $post
	 * @return array of post meta
	 */
	private function get_meta_pre_update( \WP_Post $post ) {
		return InvalidationState::get_meta_pre_update( $post );
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
	public function invalidate_on_taxonomy_change( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		$post = get_post( $object_id );
		$post_status = $post->post_status;

		if ( $post_status === 'auto-draft' || $post_status === 'draft' ) {
			return;
		}

		if ( ! defined( 'NITROPACK_PURGE_CACHE' ) ) {
			$taxes_equal = $this->service->compare_posts( $tt_ids, $old_tt_ids );
			if ( ! $taxes_equal ) {
				InvalidationState::queue_warmup( get_permalink( $post ) );
				$intersection = array_intersect( $tt_ids, $old_tt_ids );
				$prevTaxonomies = array_diff( $old_tt_ids, $intersection );
				$newTaxonomies = array_diff( $tt_ids, $intersection );
				$taxonomies = array(
					'added' => $newTaxonomies,
					'deleted' => $prevTaxonomies,
				);
				$readable_post_type_name = $this->service->readable_post_type_name( $post->post_type );
				$this->service->clean_post_cache( $post, $taxonomies, null, sprintf( "Updated %s '%s' due to taxonomy change", $readable_post_type_name, $post->post_title ) );
				define( 'NITROPACK_PURGE_CACHE', true );
			}
		}
	}
	/**
	 * Invalidate product on update.
	 *
	 * @param int        $id      Product ID.
	 * @param \WC_Product $product Product object.
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
			$post = get_post( $id );
			$this->detect_changes_and_clean_post_cache( $post, true );
			define( 'NITROPACK_PURGE_CACHE', true );
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
			nitropack_invalidate( null, "post:" . $postID, sprintf( "Invalidation of '%s' due to changing related comment status", $post->post_title ) );
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
		nitropack_invalidate( null, "post:" . $post_id, sprintf( "Invalidation of '%s' due to posting a new approved comment", $post_title ) );
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
		$queue = InvalidationState::get_purges();
		if ( ! empty( $queue ) ) {
			uasort( $queue, [ $this, "queue_sort" ] );
			foreach ( $queue as $requestKey => $data ) {
				nitropack_sdk_purge( $data["url"], $data["tag"], $data["reason"] );
			}
		}
	}

	/**
	 * Runs after the purges and invalidations. If NitroPack is unreachable (already known, or found out by the
	 * remote calls above), those calls only reached the backlog and couldn't clear the local cache, so the queued
	 * URLs are purged locally. On a healthy API the purge by tag has already cleared them.
	 * Only queued when NITROPACK_SERVE_CACHE_WHEN_API_DOWN is set; otherwise the SDK purges the whole local cache.
	 * @return void
	 */
	public function execute_local_purges() {
		$urls = InvalidationState::get_local_purges();
		if ( empty( $urls ) || null === ( $nitro = get_nitropack_sdk() ) ) {
			return;
		}
		$nitro->loadHealthStatus();
		if ( $nitro->getHealthStatus() === \NitroPack\SDK\HealthStatus::HEALTHY ) {
			return;
		}
		foreach ( $urls as $url ) {
			nitropack_sdk_purge_local( $url );
		}
	}

	/**
	 * Execute all logged invalidations in the queue.
	 * Used in nitropack_invalidate() only.
	 * Doesn't execute for posts which are manually invalidated by user.
	 * @return void
	 */
	public function execute_invalidations() {
		$queue = InvalidationState::get_invalidations();
		if ( ! empty( $queue ) ) {
			uasort( $queue, [ $this, "queue_sort" ] );
			foreach ( $queue as $requestKey => $data ) {
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

		$warmups = InvalidationState::get_warmups();
		if ( ! empty( $warmups ) && ( null !== $nitro = get_nitropack_sdk() ) ) {
			// Warmup is best-effort; an uncaught exception in a shutdown function is fatal.
			try {
				$warmupStats = $nitro->getApi()->getWarmupStats();
				if ( ! empty( $warmupStats["status"] ) ) {
					foreach ( array_unique( $warmups ) as $url ) {
						$nitro->getApi()->runWarmup( $url );
					}
				}
			} catch ( \Exception $e ) {
				// Service unavailable or request failed - skip warmup.
			}
		}

	}
}
