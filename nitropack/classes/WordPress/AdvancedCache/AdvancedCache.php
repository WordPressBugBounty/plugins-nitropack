<?php

namespace Nitropack\WordPress\AdvancedCache;

/**
 * Here we handle the installation and uninstallation of the advanced_cache.php file stored in wp-content folder.
 */
class AdvancedCache {
	public function __construct() {
		if ( $this->has_advanced_cache() ) {
			// Handle automated updates
			if ( ! defined( "NITROPACK_ADVANCED_CACHE_VERSION" ) || NITROPACK_VERSION != NITROPACK_ADVANCED_CACHE_VERSION ) {
				add_action( 'plugins_loaded', [ $this, 'install_advanced_cache' ] );
			}
		}
	}

	/**
	 * @return void|false Returns false if the advanced-cache.php file cannot be created, otherwise returns void.
	 * @description Installs the advanced-cache.php file in the WP_CONTENT_DIR directory. This file is required for NitroPack to work properly. If the file cannot be created, the function returns
	 */
	public function install_advanced_cache() {
		$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
		$nitropack_is_conflicting_plugin_active = $conflictingPlugins->nitropack_is_conflicting_plugin_active();
		if ( $nitropack_is_conflicting_plugin_active || ! nitropack_is_advanced_cache_allowed() ) {
			return false;
		}

		$templatePath = nitropack_trailingslashit( NITROPACK_PLUGIN_DIR ) . "/classes/WordPress/AdvancedCache/advanced-cache.php";
		if ( file_exists( $templatePath ) ) {
			$contents = file_get_contents( $templatePath );
			$contents = str_replace( "/*NITROPACK_FUNCTIONS_FILE*/", NITROPACK_PLUGIN_DIR . 'functions.php', $contents );
			$contents = str_replace( "/*NITROPACK_ABSPATH*/", ABSPATH, $contents );
			$contents = str_replace( "/*LOGIN_COOKIES*/", defined( "LOGGED_IN_COOKIE" ) ? LOGGED_IN_COOKIE : "", $contents );
			$contents = str_replace( "/*NP_VERSION*/", NITROPACK_VERSION, $contents );

			$advancedCacheFile = nitropack_trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
			return WP_DEBUG
				? file_put_contents( $advancedCacheFile, $contents )
				: @file_put_contents( $advancedCacheFile, $contents );
		}
	}

	/**
	 * @return void|false Returns false if the advanced-cache.php file cannot be created, otherwise returns void.
	 * @description Uninstalls the advanced-cache.php file in the WP_CONTENT_DIR directory.
	 */
	public function uninstall_advanced_cache() {
		$advancedCacheFile = nitropack_trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
		if ( file_exists( $advancedCacheFile ) ) {
			if ( WP_DEBUG ) {
				return file_put_contents( $advancedCacheFile, "" );
			} else {
				return @file_put_contents( $advancedCacheFile, "" );
			}
		}
	}

	/**
	 * @return bool
	 * @description Checks whether the constant NITROPACK_ADVANCED_CACHE is defined.
	 */
	public function has_advanced_cache() {
		return defined( 'NITROPACK_ADVANCED_CACHE' );
	}
}