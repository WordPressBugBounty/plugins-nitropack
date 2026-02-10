<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;
use NitroPack\Integration\Plugin\RC as ResidualCache;

/**
 * Ajax handlers when purging or invalidating the NitroPack cache
 */
class PurgeCache {
	public function __construct() {
		//admin topbar menu
		add_action( 'wp_ajax_nitropack_purge_entire_cache', [ $this, 'nitropack_purge_entire_cache' ] );
		add_action( 'wp_ajax_nitropack_invalidate_entire_cache', [ $this, 'nitropack_invalidate_entire_cache' ] );
		//dashboard
		add_action( 'wp_ajax_nitropack_purge_cache', [ $this, 'nitropack_purge_cache' ] );
		add_action( 'wp_ajax_nitropack_clear_residual_cache', [ $this, 'nitropack_clear_residual_cache' ] );
		//metaboxes
		add_action( 'wp_ajax_nitropack_purge_single_cache', [ $this, 'nitropack_purge_single_cache' ] );
		add_action( 'wp_ajax_nitropack_invalidate_single_cache', [ $this, 'nitropack_invalidate_single_cache' ] );

		/* Action Links to Purge/Invalidate cache */
		//add links under page and post for purging and invalidating cache
		add_filter( 'post_row_actions', [ $this, 'purge_invalidate_post_links' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'purge_invalidate_post_links' ], 10, 2 );
		//metaboxes
		add_action( 'add_meta_boxes', [ $this, 'nitropack_meta_box' ] );
	}

	/**
	 * AJAX handler when clicking Purge Entire Cache in admin topbar NitroPack menu
	 * Triggered in nitropack/view/javascript/admin_bar_menu.js
	 * @return void
	 */
	public function nitropack_purge_entire_cache() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		try {
			if ( nitropack_sdk_purge( null, null, 'Manual purge of all pages' ) ) {
				NitroPack::getInstance()->getLogger()->notice( 'Manual purge of all pages' );
				nitropack_json_and_exit( [
					"type" => "success",
					"message" => __( 'Success! Cache has been purged successfully!', 'nitropack' )
				] );
			}
		} catch (\Exception $e) {
			NitroPack::getInstance()->getLogger()->error( 'Manual purge of all pages. Error: ' . $e );
		}

		nitropack_json_and_exit( [
			"type" => "error",
			"message" => __( 'Error! There was an error and the cache was not purged!', 'nitropack' )
		] );
	}

	/**
	 * AJAX handler when clicking Invalidate Entire Cache in admin topbar NitroPack menu
	 * Triggered in nitropack/view/javascript/admin_bar_menu.js
	 * @return void
	 */
	public function nitropack_invalidate_entire_cache() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		try {
			if ( nitropack_sdk_invalidate( NULL, NULL, 'Manual invalidation of all pages' ) ) {
				NitroPack::getInstance()->getLogger()->notice( 'Manual invalidation of all pages' );
				nitropack_json_and_exit( array(
					"type" => "success",
					"message" => __( 'Cache has been invalidated successfully!', 'nitropack' )

				) );
			}
		} catch (\Exception $e) {
			NitroPack::getInstance()->getLogger()->error( 'Manual invalidation of all pages. Error: ' . $e );
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'There was an error and the cache was not invalidated!', 'nitropack' )
		) );
	}
	/**
	 * AJAX handler when clicking Purge Cache in Dashboard > NitroPack. Performs light purge (excludes images).
	 * Triggered in nitropack/view/javascript/np_settings.js -> clearCacheHandler()
	 * @return void
	 */
	public function nitropack_purge_cache() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		try {
			if ( nitropack_sdk_purge( NULL, NULL, 'Light purge of all caches', \NitroPack\SDK\PurgeType::LIGHT_PURGE ) ) {
				NitroPack::getInstance()->getLogger()->notice( 'Light purge of all caches' );
				nitropack_json_and_exit( array(
					"type" => "success",
					"message" => __( 'Cache has been purged successfully!', 'nitropack' )
				) );
			}
		} catch (\Exception $e) {
			NitroPack::getInstance()->getLogger()->error( 'Light purge of all caches. Error: ' . $e );
		}
		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Error! There was an error and the cache was not purged!', 'nitropack' )
		) );
	}
	/**
	 * Extended capabilities when purging or invalidating single post cache in a metabox
	 * Triggered in nitropack/view/javascript/metabox.js
	 * @return string[]
	 */
	private function capabilities_prior_purge() {
		$canEditorPurge = get_option( 'nitropack-canEditorClearCache' );
		if ( $canEditorPurge ) {
			return [ 'editor', 'manage_options' ];
		} else {
			return [ 'manage_options' ];
		}
	}
	/**
	 * AJAX Handler when purging a single post cache via meta box
	 * Triggered in nitropack/view/javascript/metabox.js
	 * @return void
	 */
	public function nitropack_purge_single_cache() {

		$capabilities = $this->capabilities_prior_purge();
		nitropack_verify_ajax_nonce( $_REQUEST, $capabilities );

		if ( ! empty( $_POST["postId"] ) && is_numeric( $_POST["postId"] ) ) {
			$postId = $_POST["postId"];
			$postUrl = ! empty( $_POST["postUrl"] ) ? $_POST["postUrl"] : NULL;
			$reason = sprintf( "Manual purge of post %s via the WordPress admin panel", $postId );
			$tag = $postId > 0 ? "single:$postId" : NULL;

			if ( $postUrl ) {
				if ( is_array( $postUrl ) ) {
					foreach ( $postUrl as &$url ) {
						$url = nitropack_sanitize_url_input( $url );
					}
				} else {
					$postUrl = nitropack_sanitize_url_input( $postUrl );
					$reason = "Manual purge of " . $postUrl;
				}
			}

			try {
				if ( nitropack_sdk_purge( $postUrl, $tag, $reason ) ) {
					NitroPack::getInstance()->getLogger()->notice( 'Manual purge of post ' . $postId . ' via WordPress.' );
					nitropack_json_and_exit( array(
						"type" => "success",
						"message" => __( 'Success! Cache has been purged successfully!', 'nitropack' )
					) );
				}
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Manual purge of post ' . $postId . ' via WordPress. Error: ' . $e );
			}
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Error! There was an error and the cache was not purged!', 'nitropack' )
		) );
	}

	/**
	 * AJAX handler when invalidating single post cache via metabox
	 * Triggered in nitropack/view/javascript/metabox.js
	 * @return void
	 */
	public function nitropack_invalidate_single_cache() {

		$capabilities = $this->capabilities_prior_purge();
		nitropack_verify_ajax_nonce( $_REQUEST, $capabilities );

		if ( ! empty( $_POST["postId"] ) && is_numeric( $_POST["postId"] ) ) {
			$postId = $_POST["postId"];
			$postUrl = ! empty( $_POST["postUrl"] ) ? $_POST["postUrl"] : NULL;
			$reason = sprintf( "Manual invalidation of post %s via the WordPress admin panel", $postId );
			$tag = $postId > 0 ? "single:$postId" : NULL;

			if ( $postUrl ) {
				if ( is_array( $postUrl ) ) {
					foreach ( $postUrl as &$url ) {
						$url = nitropack_sanitize_url_input( $url );
					}
				} else {
					$postUrl = nitropack_sanitize_url_input( $postUrl );
					$reason = "Manual invalidation of " . $postUrl;
				}
			}

			try {
				if ( nitropack_sdk_invalidate( $postUrl, $tag, $reason ) ) {
					NitroPack::getInstance()->getLogger()->notice( 'Manual invalidation of post ' . $postId . ' via WordPress.' );
					nitropack_json_and_exit( array(
						"type" => "success",
						"message" => __( 'Success! Cache has been invalidated successfully!', 'nitropack' )
					) );
				}
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Manual invalidation of post ' . $postId . ' via WordPress. Error: ' . $e );
			}
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Error! There was an error and the cache was not invalidated!', 'nitropack' )
		) );
	}

	/**
	 * AJAX handler when clicking "Delete now" in residual cache message in Dashboard. Deletes 3rd party cache files.
	 * Notification => "We found residual cache files from %s. These files can interfere with the caching process and must be deleted."
	 * @return void
	 */
	public function nitropack_clear_residual_cache() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$gde = ! empty( $_POST["gde"] ) ? $_POST["gde"] : NULL;
		if ( $gde && array_key_exists( $gde, ResidualCache::$modules ) ) {
			$result = call_user_func( array( ResidualCache::$modules[ $gde ], "clearCache" ) ); // This needs to be like this because of compatibility with PHP 5.6
			if ( ! in_array( false, $result ) ) {
				NitroPack::getInstance()->getLogger()->notice( 'Manual clearing of residual cache via WordPress.' );
				nitropack_json_and_exit( array(
					"type" => "success",
					"message" => __( 'Success! The residual cache has been cleared successfully!', 'nitropack' )
				) );
			}
		}
		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => __( 'Error! There was an error clearing the residual cache!', 'nitropack' )
		) );
	}

	/**
	 * Capabilities of cleaning single post cache
	 * @return string[]
	 */
	public function clean_cache_capabilities() {
		$canEditorPurge = get_option( 'nitropack-canEditorClearCache' );
		if ( $canEditorPurge ) {
			return [ 'editor', 'manage_options' ];
		} else {
			return [ 'manage_options' ];
		}
	}
	/** Checks capabilities and adds meta box to post types that can have "single" pages
	 */
	public function nitropack_meta_box() {
		$editor = get_option( "nitropack-canEditorClearCache" );
		$allowed_capabilities = current_user_can( 'manage_options' ) || current_user_can( 'nitropack_meta_box' ) || ( $editor && current_user_can( 'editor' ) );
		if ( $allowed_capabilities ) {
			$cptOptimization = CPTOptimization::getInstance();
			foreach ( $cptOptimization->nitropack_get_cacheable_object_types() as $objectType ) {
				add_meta_box( 'nitropack_manage_cache_box', 'NitroPack', [ $this, 'nitropack_print_meta_box' ], $objectType, 'side' );
			}
		}
	}

	/** HTML rendered meta boxes. Used for post types that can have "single" pages
	 */
	public function nitropack_print_meta_box( $post ) {
		$html = '<p><a class="button nitropack-invalidate-single" data-post_id="' . $post->ID . '" data-post_url="' . get_permalink( $post ) . '" style="width:100%;text-align:center;padding: 3px 0;">Invalidate cache</a></p>';
		$html .= '<p><a class="button nitropack-purge-single" data-post_id="' . $post->ID . '" data-post_url="' . get_permalink( $post ) . '" style="width:100%;text-align:center;padding: 3px 0;">Purge cache</a></p>';
		$html .= '<p id="nitropack-status-msg" style="display:none;"></p>';
		echo $html;
	}

	/**
	 * Add 2 extra links in wp-admin post listing, under each post on hover
	 * @param array $actions
	 * @param mixed $post
	 */
	public function purge_invalidate_post_links( $actions, $post ) {
		//chgeck if the CPT is cacheable
		$CPTOptimization = CPTOptimization::getInstance();
		$cacheableObjectTypes = $CPTOptimization->nitropack_get_cacheable_object_types();
		if ( ! in_array( $post->post_type, $cacheableObjectTypes ) ) {
			return $actions;
		}

		//check if the user has permissions
		$editor = get_option( "nitropack-canEditorClearCache" );
		$allowed_capabilities = current_user_can( 'manage_options' ) || ( $editor && current_user_can( 'editor' ) );
		if ( ! $allowed_capabilities ) {
			return $actions;
		}

		$permalink = get_permalink( $post->ID );
		if ( ! empty( $permalink ) ) {
			$actions['nitropack_purge'] = '<a href="#" class="nitropack-purge-single" data-post_id="' . $post->ID . '" data-post_url="' . get_permalink( $post ) . '" ">' . __( 'Purge Cache', 'nitropack' ) . '</a>';
			$actions['nitropack_invalidate'] = '<a href="#" class="nitropack-invalidate-single" data-post_id="' . $post->ID . '" data-post_url="' . get_permalink( $post ) . '" ">' . __( 'Invalidate Cache', 'nitropack' ) . '</a>';
		}

		return $actions;
	}
}