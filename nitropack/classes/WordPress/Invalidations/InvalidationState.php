<?php

namespace NitroPack\WordPress\Invalidations;

/**
 * Request-scoped store for invalidation state: pre-update snapshots, the
 * ignore list, and the warmup / invalidation / purge queues.
 *
 * Both InvalidationSubscriber and InvalidationService depend on this store
 * instead of on each other, which keeps the dependency graph acyclic.
 */
class InvalidationState {
	/** @var array<int,\WP_Post|null> Post snapshots captured before an update, keyed by post ID. */
	private static $pre_update_posts = [];
	/** @var array<int,array> Taxonomy term_taxonomy_ids captured before an update, keyed by post ID. */
	private static $pre_update_taxonomies = [];
	/** @var array<int,array> Post meta captured before an update, keyed by post ID. */
	private static $pre_update_meta = [];
	/** @var int[] Post IDs whose changes should not trigger invalidation. */
	private static $ignore_post_ids = [];
	/** @var string[] URLs queued for warmup this request. */
	private static $warmups = [];
	/** @var array<string,array> Invalidation queue keyed by request hash. */
	private static $invalidations = [];
	/** @var array<string,array> Purge queue keyed by request hash. */
	private static $purges = [];
	/** @var array<string,string> URLs whose local cache is purged on shutdown if NitroPack is unreachable, keyed by URL. */
	private static $local_purges = [];

	/* ---- Pre-update snapshots ---- */

	/**
	 * Capture the state of a post before it is updated.
	 * @param int $post_id
	 * @param \WP_Post|null $post
	 * @param array $taxonomies
	 * @param array $meta
	 * @return void
	 */
	public static function capture_pre_update( int $post_id, $post, array $taxonomies, array $meta ) {
		self::$pre_update_posts[ $post_id ] = $post;
		self::$pre_update_taxonomies[ $post_id ] = $taxonomies;
		self::$pre_update_meta[ $post_id ] = $meta;
	}

	/**
	 * Returns the post before it was updated, or null if it wasn't logged.
	 * @param \WP_Post $post
	 * @return \WP_Post|null
	 */
	public static function get_post_pre_update( \WP_Post $post ) {
		return ! empty( self::$pre_update_posts[ $post->ID ] ) ? self::$pre_update_posts[ $post->ID ] : null;
	}

	/**
	 * Returns the taxonomies for a post before it was updated, or an empty array if none were logged.
	 * @param \WP_Post $post
	 * @return array of term_taxonomy_ids
	 */
	public static function get_taxonomies_pre_update( \WP_Post $post ) {
		return ! empty( self::$pre_update_taxonomies[ $post->ID ] ) ? self::$pre_update_taxonomies[ $post->ID ] : array();
	}

	/**
	 * Returns the post meta for a post before it was updated, or an empty array if none was logged.
	 * @param \WP_Post $post
	 * @return array of post meta
	 */
	public static function get_meta_pre_update( \WP_Post $post ) {
		return ! empty( self::$pre_update_meta[ $post->ID ] ) ? self::$pre_update_meta[ $post->ID ] : array();
	}

	/* ---- Ignore list ---- */

	/**
	 * Mark a post ID so its changes will not trigger invalidation this request.
	 * @param int $post_id
	 * @return void
	 */
	public static function ignore( int $post_id ) {
		self::$ignore_post_ids[] = $post_id;
	}

	/**
	 * Whether a post ID is on the ignore list.
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_ignored( int $post_id ) {
		return in_array( $post_id, self::$ignore_post_ids, true );
	}

	/* ---- Warmups ---- */

	/**
	 * Queue a URL for warmup.
	 * @param string $url
	 * @return void
	 */
	public static function queue_warmup( $url ) {
		self::$warmups[] = $url;
	}

	/**
	 * @return string[]
	 */
	public static function get_warmups() {
		return self::$warmups;
	}

	/* ---- Invalidation / purge queues ---- */

	/**
	 * Add (or bump the priority of) an entry in the invalidation queue.
	 * @param string $key
	 * @param string|null $url
	 * @param string|null $tag
	 * @param string|null $reason
	 * @return void
	 */
	public static function enqueue_invalidation( $key, $url, $tag, $reason ) {
		self::enqueue( self::$invalidations, $key, $url, $tag, $reason );
	}

	/**
	 * Add (or bump the priority of) an entry in the purge queue.
	 * @param string $key
	 * @param string|null $url
	 * @param string|null $tag
	 * @param string|null $reason
	 * @return void
	 */
	public static function enqueue_purge( $key, $url, $tag, $reason ) {
		self::enqueue( self::$purges, $key, $url, $tag, $reason );
	}

	/**
	 * Queue a URL whose local cache should be purged on shutdown if NitroPack turns out to be unreachable.
	 * @param string $url
	 * @return void
	 */
	public static function enqueue_local_purge( string $url ) {
		self::$local_purges[ $url ] = $url;
	}

	/**
	 * @return string[]
	 */
	public static function get_local_purges() {
		return array_values( self::$local_purges );
	}

	/**
	 * @return array<string,array>
	 */
	public static function get_invalidations() {
		return self::$invalidations;
	}

	/**
	 * @return array<string,array>
	 */
	public static function get_purges() {
		return self::$purges;
	}

	/**
	 * Remove an entry from the purge queue.
	 * @param string $key
	 * @return void
	 */
	public static function remove_purge( $key ) {
		unset( self::$purges[ $key ] );
	}

	/**
	 * Shared queue logic: dedupe by key and bump priority on repeats.
	 * @param array $queue
	 * @param string $key
	 * @param string|null $url
	 * @param string|null $tag
	 * @param string|null $reason
	 * @return void
	 */
	private static function enqueue( array &$queue, $key, $url, $tag, $reason ) {
		if ( array_key_exists( $key, $queue ) ) {
			$queue[ $key ]["reason"] = $reason;
			$queue[ $key ]["priority"]++;
		} else {
			$queue[ $key ] = array(
				"url" => $url,
				"tag" => $tag,
				"reason" => $reason,
				"priority" => 1,
			);
		}
	}
}
