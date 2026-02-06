<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

$custom_options = [
	'nitropack_minimumLogLevel',
	'np_warmup_sitemap'
];

if ( defined( 'MULTISITE' ) && MULTISITE ) {
	foreach ( get_sites( [ 'fields' => 'ids' ] ) as $blogId ) {
		switch_to_blog( $blogId );

		// Delete options with 'nitropack-' prefix and transients
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'nitropack-%' OR option_name LIKE '_transient%nitropack-%'" );

		// Delete custom options
		foreach ( $custom_options as $option_name ) {
			delete_option( $option_name );
		}

		restore_current_blog();
	}
} else {
	// Delete options with 'nitropack-' prefix and transients
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'nitropack-%' OR option_name LIKE '_transient%nitropack-%'" );

	// Delete custom options
	foreach ( $custom_options as $option_name ) {
		delete_option( $option_name );
	}
}

wp_cache_flush();

require_once 'nitropack-sdk/autoload.php';
require_once 'constants.php';
NitroPack\SDK\Filesystem::deleteDir( NITROPACK_DATA_DIR );
NitroPack\SDK\Filesystem::deleteDir( NITROPACK_PLUGIN_DATA_DIR );