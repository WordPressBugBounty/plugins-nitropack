<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;
use NitroPack\Integration\Plugin\RC as ResidualCache;
use NitroPack\Util\Utils;

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
		//purge/invalidate entire cache when permalink structure or front page is changed
		add_action( 'permalink_structure_changed', [ $this, 'purge_on_change_in_permalink_structure' ], 10, 2 );
		add_action( 'update_option_show_on_front', [ $this, 'purge_on_frontpage_change' ], 10, 2 );
		add_action( 'update_option_page_on_front', [ $this, 'purge_on_frontpage_change' ], 10, 2 );
		add_action( 'update_option_page_for_posts', [ $this, 'purge_on_frontpage_change' ], 10, 2 );
		//purge cache on theme switch
		add_action( 'switch_theme', [ $this, 'purge_on_switch_theme' ] );
		//purge cache on theme update
		add_action( 'upgrader_process_complete', [ $this, 'purge_cache_on_theme_update' ], 10, 2 );
	}

	/**
	 * AJAX handler when clicking Purge Entire Cache in admin topbar NitroPack menu
	 * Triggered in nitropack/assets/js/admin_bar_menu.min.js
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
	 * Triggered in nitropack/assets/js/admin_bar_menu.min.js
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
	 * Triggered in nitropack/assets/js/np_settings.min.js -> clearCacheHandler()
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
	 * Extended capabilities when purging or invalidating single post cache in a metabox.
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
	 * AJAX Handler when purging a single post cache via meta box.
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
						$url = Utils::sanitize_url( $url );
					}
				} else {
					$postUrl = Utils::sanitize_url( $postUrl );
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
	 * AJAX handler when invalidating single post cache via metabox.
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
						$url = Utils::sanitize_url( $url );
					}
				} else {
					$postUrl = Utils::sanitize_url( $postUrl );
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
		$cpt_optimization = CPTOptimization::getInstance();
		$cacheableObjectTypes = $cpt_optimization->nitropack_get_cacheable_object_types();
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
	/**
	 * Purge entire cache when permalink structure is changed.
	 *
	 * @param string $old_permalink_structure The previous permalink structure.
	 * @param string $permalink_structure     The new permalink structure.
	 *
	 * @return void
	 */
	public function purge_on_change_in_permalink_structure( $old_permalink_structure, $permalink_structure ) {

		if ( $old_permalink_structure != $permalink_structure && get_option( "nitropack-autoCachePurge", 1 ) ) {
			$msg = 'The permalink structure is changed. Purging the cache for the home page.';
			$url = get_home_url();

			nitropack_sdk_purge( $url, null, $msg );

			// run warmup
			if ( null !== $nitro = get_nitropack_sdk() ) {
				$nitro->getApi()->runWarmup();
			}
		}
	}
	/**
	 * Purge entire cache when front page is changed.
	 *
	 * @param array $old_value An array of previous settings values.
	 * @param array $value An array of submitted settings values.
	 *
	 * @return void
	 */
	public function purge_on_frontpage_change( $old_value, $value ) {
		if ( $old_value !== $value ) {
			$msg = 'The front page is changed';
			$url = get_home_url();

			nitropack_sdk_purge( $url, null, $msg ); // purge entire cache
		}
	}

	/**
	 * Purge entire cache when theme is switched.
	 * @param mixed $event
	 * @return void
	 */
	public function purge_on_switch_theme( $event ) {
		if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
			return;
		}

		if ( $event ) {
			$msg = $event;
		} else {
			$msg = 'Theme switched to ' . wp_get_theme()->Name;
		}
		nitropack_sdk_purge( null, null, $msg ); // purge entire cache
	}

	/**
	 * Purge cache when the active theme is updated.
	 *
	 * @param \WP_Upgrader $upgrader The upgrader instance.
	 * @param array $options The options for the upgrade.
	 * @return void
	 */
	public function purge_cache_on_theme_update( $upgrader, $options = null ) {
		if ( $options['type'] == 'theme' && $options['action'] == 'update' ) {
			$theme_name = $upgrader->theme_info()->Name;
			if ( $theme_name === wp_get_theme()->Name ) {
				$this->purge_on_switch_theme( 'Theme ' . wp_get_theme()->Name . ' updated' );
			}
		}
	}
}