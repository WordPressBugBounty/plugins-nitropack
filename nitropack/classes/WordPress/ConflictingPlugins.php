<?php
namespace NitroPack\WordPress;

/**
 * Class ConflictingPlugins
 *
 * This class is responsible for managing conflicting plugins in WordPress.
 * It provides methods to retrieve a list of known conflicting plugins and check if any are active.
 */
class ConflictingPlugins {
	private static $instance = NULL;
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new ConflictingPlugins();
		}

		return self::$instance;
	}
	/**
	 * Returns an array of conflicting plugins with their names and plugin paths.
	 *
	 * @return array<string, string>
	 */

	public function nitropack_get_conflicting_plugins() {
		$clashingPlugins = array();

		if ( defined( 'BREEZE_PLUGIN_DIR' ) ) {
			$clashingPlugins[] = array( 'name' => 'Breeze', 'plugin' => 'breeze/breeze.php' );
		}

		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$clashingPlugins[] = array( 'name' => 'WP-Rocket', 'plugin' => 'wp-rocket/wp-rocket.php' );
		}

		if ( defined( 'W3TC' ) ) {
			$clashingPlugins[] = array( 'name' => 'W3 Total Cache', 'plugin' => 'w3-total-cache/w3-total-cache.php' );
		}

		if ( defined( 'WPFC_MAIN_PATH' ) ) {
			$clashingPlugins[] = array( 'name' => 'WP Fastest Cache', 'plugin' => 'wp-fastest-cache/wpFastestCache.php' );
		}

		if ( defined( 'PHASTPRESS_VERSION' ) ) {
			$clashingPlugins[] = array( 'name' => 'PhastPress', 'plugin' => 'phastpress/phastpress.php' );
		}

		if ( defined( 'WPCACHEHOME' ) && function_exists( "wp_cache_phase2" ) ) {
			$clashingPlugins[] = array( 'name' => 'WP Super Cache', 'plugin' => 'wp-super-cache/wp-cache.php' );
		}

		if ( defined( 'LSCACHE_ADV_CACHE' ) || defined( 'LSCWP_DIR' ) ) {
			$clashingPlugins[] = array( 'name' => 'LiteSpeed Cache', 'plugin' => 'litespeed-cache/litespeed-cache.php' );
		}

		if ( class_exists( 'Swift_Performance' ) || class_exists( 'Swift_Performance_Lite' ) ) {
			$clashingPlugins[] = array( 'name' => 'Swift Performance Lite', 'plugin' => 'swift-performance-lite/performance.php' );
		}

		if ( class_exists( 'PagespeedNinja' ) ) {
			$clashingPlugins[] = array( 'name' => 'PageSpeed Ninja', 'plugin' => 'psn-pagespeed-ninja/pagespeedninja.php' );
		}

		if ( defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
			$clashingPlugins[] = array( 'name' => 'Autoptimize', 'plugin' => 'autoptimize/autoptimize.php' );
		}

		if ( class_exists( 'WP_Hummingbird' ) || class_exists( 'Hummingbird\\WP_Hummingbird' ) ) {
			$clashingPlugins[] = array( 'name' => 'Hummingbird', 'plugin' => 'hummingbird-performance/wp-hummingbird.php' );
		}

		if ( defined( 'WP_SMUSH_VERSION' ) ) {
			if ( class_exists( 'Smush\\Core\\Settings' ) && defined( 'WP_SMUSH_PREFIX' ) ) {
				$smushLazy = \Smush\Core\Settings::get_instance()->get( 'lazy_load' );
				if ( $smushLazy ) {
					$clashingPlugins[] = array( 'name' => 'Smush - Lazy Load', 'plugin' => 'wp-smushit/wp-smush.php' );
				}
			} else {
				$clashingPlugins[] = array( 'name' => 'Smush', 'plugin' => 'wp-smushit/wp-smush.php' );
			}
		}

		if ( defined( 'COMET_CACHE_PLUGIN_FILE' ) ) {
			$clashingPlugins[] = array( 'name' => 'Comet Cache', 'plugin' => 'comet-cache/comet-cache.php' );
		}

		if ( defined( 'WPO_VERSION' ) && class_exists( 'WPO_Cache_Config' ) ) {
			$wpo_cache_config = \WPO_Cache_Config::instance();
			if ( $wpo_cache_config->get_option( 'enable_page_caching', false ) ) {
				$clashingPlugins[] = array( 'name' => 'WP Optimize page caching', 'plugin' => 'wp-optimize/wp-optimize.php' );
			}
		}

		if ( class_exists( 'BJLL' ) ) {
			$clashingPlugins[] = array( 'name' => 'BJ Lazy Load', 'plugin' => 'bj-lazy-load/bj-lazy-load.php' );
		}

		if ( defined( 'SHORTPIXEL_IMAGE_OPTIMISER_VERSION' ) && class_exists( '\ShortPixel\ShortPixelPlugin' ) ) {
			$sp_config = \ShortPixel\ShortPixelPlugin::getInstance();
			if ( $sp_config->settings()->createWebp ) {
				$clashingPlugins[] = array( 'name' => 'ShortPixel Image Optimizer', 'plugin' => 'shortpixel-image-optimiser/shortpixel-plugin.php' );
			}
		}
		if ( defined( 'RAPIDLOAD_PLUGIN_URL' ) ) {
			$clashingPlugins[] = array( 'name' => 'RapidLoad AI', 'plugin' => 'unusedcss/unusedcss.php' );
		}
		if ( defined( 'JETPACK_BOOST_VERSION' ) ) {
			$clashingPlugins[] = array( 'name' => 'Jetpack Boost', 'plugin' => 'jetpack-boost/jetpack-boost.php' );
		}
		if ( defined( 'SiteGround_Optimizer\VERSION' ) ) {
			$clashingPlugins[] = array( 'name' => 'SiteGround Optimizer', 'plugin' => 'sg-cachepress/sg-cachepress.php' );
		}
		if ( defined( 'A3_LAZY_VERSION' ) ) {
			$clashingPlugins[] = array( 'name' => 'a3 Lazy Load', 'plugin' => 'a3-lazy-load/a3-lazy-load.php' );
		}

		return $clashingPlugins;
	}

	/**
	 * Checks if any conflicting plugin is active.
	 *
	 * @return bool
	 */
	public function nitropack_is_conflicting_plugin_active() {
		$conflictingPlugins = $this->nitropack_get_conflicting_plugins();
		return ! empty( $conflictingPlugins );
	}
}