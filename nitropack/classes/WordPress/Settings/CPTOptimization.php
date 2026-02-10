<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );
/**
 * Class CPTOptimization
 *
 * This class manages the optimization settings for Custom Post Types (CPTs) and taxonomies (called Page Optimization) in NitroPack.
 *
 * @package NitroPack\WordPress\Settings
 */
class CPTOptimization {
	public static $instance = null;
	public static function getInstance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}
		return $instance;
	}
	public function __construct() {
		add_action( 'admin_init', [ $this, 'autooptimize_new_post_types_and_taxonomies' ] );
		add_action( 'wp_ajax_nitropack_set_cacheable_post_types', [ $this, 'nitropack_set_cacheable_post_types' ] );
	}

	/**
	 * Retrievs all CPTs eligable for optimization and checking if they are optimized
	 */
	public function nitropack_get_CPTs_with_optimization_status() {

		$cacheableObjectTypes = $this->nitropack_get_cacheable_object_types();
		$objectTypes = $this->nitropack_get_object_types();

		$result = [
			"home" => [
				'name' => 'Home',
				'isOptimized' => in_array( 'home', $cacheableObjectTypes ) ? 1 : 0,
			],
			"archive" => [
				'name' => 'Archive',
				'isOptimized' => in_array( 'archive', $cacheableObjectTypes ) ? 1 : 0,
			]
		];

		// Determine new object types by comparing current with stored
		foreach ( $objectTypes as $slug => $objectType ) {
			$isOptimized = in_array( $objectType->name, $cacheableObjectTypes );
			$taxonomies = [];

			if ( ! empty( $objectType->taxonomies ) ) {
				foreach ( $objectType->taxonomies as $tax ) {
					$taxName = $tax->name;
					$taxonomies[ $taxName ] = [
						'isOptimized' => in_array( $taxName, $cacheableObjectTypes ) ? 1 : 0,
						'name' => $tax->labels->name
					];
				}
			}

			$result[ $slug ] = [
				'isOptimized' => $isOptimized ? 1 : 0,
				'name' => $objectType->labels->name,
				'taxonomies' => $taxonomies
			];
		}

		return $result;
	}
	/**
	 * Filters the CPTs which are not optimized.
	 * Show it only once.
	 */
	public function nitropack_filter_non_optimized() {
		$filteredArr = [];
		$objectTypes = $this->nitropack_get_CPTs_with_optimization_status();
		foreach ( $objectTypes as $slug => $objectType ) {
			$includeObjectType = ! $objectType['isOptimized'];
			$filteredTaxonomies = [];
			if ( isset( $objectType['taxonomies'] ) ) {
				foreach ( $objectType['taxonomies'] as $taxSlug => $taxonomy ) {
					if ( ! $taxonomy['isOptimized'] ) {
						$filteredTaxonomies[ $taxSlug ] = $taxonomy;
						$includeObjectType = true; // Include CPT if it has any non-optimized taxonomy
					}
				}
			}
			if ( $includeObjectType ) {
				$filteredArr[ $slug ] = [
					'isOptimized' => $objectType['isOptimized'],
					'name' => $objectType['name'],
					'taxonomies' => $filteredTaxonomies
				];
			}
		}
		return $filteredArr;
	}
	/**
	 * Retrieve the list of optimized Custom Post Types (CPTs).
	 *
	 * This public function fetches the CPTs with their optimization status and returns an array of CPT keys
	 * that are marked as optimized. It excludes 'home' and 'archive' as they are not valid CPTs.
	 *
	 * @return array An array of optimized CPT keys.
	 */
	public function nitropack_get_optimized_CPTs() {
		$get_CPTs_with_optimization_status = $this->nitropack_get_CPTs_with_optimization_status();

		$optimizedKeys = [];

		foreach ( $get_CPTs_with_optimization_status as $key => $value ) {

			if ( $key === 'home' || $key === 'archive' )
				continue; //not valid CPTs

			if ( isset( $value['isOptimized'] ) && $value['isOptimized'] === 1 ) {
				$optimizedKeys[] = $key;
			}
		}

		return $optimizedKeys;
	}

	public function autooptimize_new_post_types_and_taxonomies() {
		//start optimizing after the notice is shown
		$notices = get_option( 'nitropack-dismissed-notices', [] );
		if ( ! $notices || ! in_array( 'OptimizeCPT', $notices ) )
			return;
		//check if the non-optimized CPTs are stored, if not store them
		$nonCacheableObjectTypes = get_option( 'nitropack-nonCacheableObjectTypes' );
		if ( ! $nonCacheableObjectTypes ) {
			$notOptimizedCPTs = $this->nitropack_filter_non_optimized();
			$nonCacheableObjectTypes = [];
			foreach ( $notOptimizedCPTs as $slug => $objectType ) {
				if ( ! $objectType['isOptimized'] ) {
					$nonCacheableObjectTypes[] = $slug;
				}
				foreach ( $objectType['taxonomies'] as $taxSlug => $taxonomy ) {
					if ( ! $taxonomy['isOptimized'] ) {
						$nonCacheableObjectTypes[] = $taxSlug;
					}
				}
			}
			update_option( 'nitropack-nonCacheableObjectTypes', $nonCacheableObjectTypes );
		}

		$postTypes = get_post_types( array( 'public' => true ), 'objects' );
		$cacheableObjectTypes = $this->nitropack_get_cacheable_object_types();
		foreach ( $postTypes as $slug => $postType ) {
			if ( $postType->public ) {
				// Check and add post type to cacheable object types
				if ( ! in_array( $slug, $nonCacheableObjectTypes ) && ! in_array( $slug, $cacheableObjectTypes ) ) {
					$cacheableObjectTypes[] = $slug;
				}

				// Fetch and add connected taxonomies
				$taxonomies = get_object_taxonomies( $slug, 'names' );
				foreach ( $taxonomies as $taxonomy ) {
					if ( ! in_array( $taxonomy, $nonCacheableObjectTypes ) && ! in_array( $taxonomy, $cacheableObjectTypes ) ) {
						$cacheableObjectTypes[] = $taxonomy;
					}
				}
			}
		}

		update_option( 'nitropack-cacheableObjectTypes', $cacheableObjectTypes );
	}
	/**
	 * Gets the cachable post types and taxonomies. 
	 * Allows to filter and modify them via 'nitropack_cacheable_post_types' filter.
	 * @return array
	 */
	public function nitropack_get_cacheable_object_types() {
		return apply_filters( "nitropack_cacheable_post_types", get_option( "nitropack-cacheableObjectTypes", $this->nitropack_get_default_cacheable_object_types() ) );
	}

	/**
	 * Gets the default cacheable post types and taxonomies.
	 * @return array
	 */
	public function nitropack_get_default_cacheable_object_types() {
		$result = array( "home", "archive" );
		$postTypes = get_post_types( array( 'public' => true ), 'names' );
		$result = array_merge( $result, $postTypes );
		foreach ( $postTypes as $postType ) {
			$result = array_merge( $result, get_taxonomies( array( 'object_type' => array( $postType ), 'public' => true ), 'names' ) );
		}
		return $result;
	}
	/**
	 * Gets all public post types and taxonomies from WP.
	 * @return array
	 */
	public function nitropack_get_object_types() {
		$objectTypes = get_post_types( array( 'public' => true ), 'objects' );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		foreach ( $objectTypes as &$objectType ) {
			$objectType->taxonomies = [];
			foreach ( $taxonomies as $tax ) {
				if ( in_array( $objectType->name, $tax->object_type ) ) {
					$objectType->taxonomies[] = $tax;
				}
			}
		}

		return $objectTypes;
	}

	/**
	 * AJAX handler when saving the cachable post types and taxonomies. 
	 * Used when saving CPTs in the modal for Page Optimization in the Dashboard
	 * @return void
	 */
	public function nitropack_set_cacheable_post_types() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$currentCacheableObjectTypes = $this->nitropack_get_cacheable_object_types();
		$cacheableObjectTypes = ! empty( $_POST["cacheableObjectTypes"] ) ? $_POST["cacheableObjectTypes"] : array();
		$noncacheableObjectTypes = ! empty( $_POST["noncacheableObjectTypes"] ) ? $_POST["noncacheableObjectTypes"] : array();

		/* Used for non optimized CPT modal which is shown only once */
		if ( isset( $_POST["append"] ) && $_POST["append"] === "yes" ) {
			$cacheableObjectTypes = array_merge( $currentCacheableObjectTypes, $cacheableObjectTypes );
		}

		update_option( "nitropack-cacheableObjectTypes", $cacheableObjectTypes );
		update_option( "nitropack-nonCacheableObjectTypes", $noncacheableObjectTypes );

		foreach ( $currentCacheableObjectTypes as $objectType ) {
			if ( ! in_array( $objectType, $cacheableObjectTypes ) ) {
				nitropack_purge( NULL, "pageType:" . $objectType, "Optimizing '$objectType' pages was manually disabled" );
			}
		}

		NitroPack::getInstance()->getLogger()->notice( "Optimized CPTs: " . implode( ", ", $cacheableObjectTypes ) );
		NitroPack::getInstance()->getLogger()->notice( "Non-optimized CPTs: " . ( ! empty( $noncacheableObjectTypes ) && is_array( $noncacheableObjectTypes ) ? implode( ", ", $noncacheableObjectTypes ) : 'N/A' ) );

		nitropack_json_and_exit( array(
			"type" => "success",
			"message" => nitropack_admin_toast_msgs( 'success' )
		) );
	}
	public function render() {
		?>
		<div class="nitro-option" id="page-optimization-widget">
			<div class="nitro-option-main">
				<div class="text-box">
					<h6><?php esc_html_e( 'Page optimization', 'nitropack' ); ?></h6>
					<p><?php esc_html_e( 'Select what post/page types get optimized', 'nitropack' ); ?></p>
				</div>
				<a data-modal-target="modal-posttypes" data-modal-toggle="modal-posttypes" class="btn btn-secondary btn-icon">
					<img src="<?php echo plugin_dir_url( NITROPACK_FILE ); ?>view/images/setting-icon.svg">
				</a>
			</div>
			<?php require_once NITROPACK_PLUGIN_DIR . 'view/modals/modal-posttypes.php'; ?>
		</div>
		<?php
	}
}