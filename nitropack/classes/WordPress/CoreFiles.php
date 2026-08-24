<?php

namespace NitroPack\WordPress;

use NitroPack\SDK\Filesystem;

/**
 * Used for WordPress Core files
 * Modifies .htaccess rules, sets WP_CACHE constant in wp-config.php and batcache if needed
 */
class CoreFiles {

	/**
	 * PHP opening tag constant used throughout file operations
	 */
	const PHP_OPENING_TAG = "<?php";

	private static function get_htaccess_path() {
		$config_file_path = nitropack_trailingslashit( ABSPATH ) . ".htaccess";
		if ( ! file_exists( $config_file_path ) ) {
			return false;
		}

		// Not writable in autoscale environment, but we can still return the path as we have write access to it during plugin installation
		if ( defined( "WPE_PLATFORM_NAME" ) && WPE_PLATFORM_NAME == "autoscale" ) {
			return $config_file_path;
		}

		if ( ! is_writable( $config_file_path ) ) {
			return false;
		}

		return $config_file_path;
	}

	/**
	 * Modifies the .htaccess file to add or remove NitroPack rules based on the provided status.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function set_htaccess_rules( $status ) {
		if ( ! apply_filters( 'nitropack_should_modify_htaccess', false ) ) {
			return true;
		}

		$htaccess_file_path = self::get_htaccess_path();
		if ( ! $htaccess_file_path ) {
			return false;
		}

		$htaccess_backup_file_path = $htaccess_file_path . ".nitrobackup";
		$backup_exists = WP_DEBUG ? Filesystem::fileExists( $htaccess_backup_file_path ) : @Filesystem::fileExists( $htaccess_backup_file_path );
		if ( ! $backup_exists ) {
			$is_backup_success = WP_DEBUG ? copy( $htaccess_file_path, $htaccess_backup_file_path ) : @copy( $htaccess_file_path, $htaccess_backup_file_path );
			if ( ! $is_backup_success ) {
				return false;
			}
		}

		$lines = file( $htaccess_file_path );
		$lines_backup = $lines;

		if ( empty( $lines ) ) {
			return false;
		}

		if ( $status ) {
			$lines = self::remove_lscache_rules( $lines );
		}

		$lines = self::apply_nitropack_rules( $lines, $lines_backup, $status );

		return $lines;
	}

	/**
	 * Removes LiteSpeed cache rules from the .htaccess lines.
	 */
	private static function remove_lscache_rules( $lines ) {
		$nitro_ls_open_line = false;
		$nitro_ls_close_line = false;

		foreach ( $lines as $line_index => $line ) {
			if ( trim( $line ) == "# BEGIN LSCACHE" ) {
				$nitro_ls_open_line = $line_index;
			}

			if ( trim( $line ) == "# END LSCACHE" ) {
				$nitro_ls_close_line = $line_index;
			}
		}

		if ( $nitro_ls_open_line !== false && $nitro_ls_close_line !== false && $nitro_ls_close_line > $nitro_ls_open_line ) {
			array_splice( $lines, $nitro_ls_open_line, $nitro_ls_close_line - $nitro_ls_open_line + 1 );
		}

		return $lines;
	}

	/**
	 * Applies NitroPack rules to the .htaccess lines.
	 *
	 * @return bool|int True if markers are invalid, or the result of the file write operation
	 */
	private static function apply_nitropack_rules( $lines, $lines_backup, $status ) {
		$nitro_open_line = false;
		$nitro_close_line = false;

		foreach ( $lines as $line_index => $line ) {
			if ( trim( $line ) == "# BEGIN NITROPACK" ) {
				$nitro_open_line = $line_index;
			}

			if ( trim( $line ) == "# END NITROPACK" ) {
				$nitro_close_line = $line_index;
			}
		}

		$markers_not_found = ( $nitro_open_line === false && $nitro_close_line === false );
		$markers_in_order = ( $nitro_open_line !== false && $nitro_close_line !== false && $nitro_close_line > $nitro_open_line );

		if ( ! $markers_not_found && ! $markers_in_order ) {
			return true;
		}

		$nitro_lines = self::build_nitro_lines( $status );

		$offset = $nitro_open_line !== false ? $nitro_open_line : 0;
		$length = $nitro_open_line !== false ? $nitro_close_line - $nitro_open_line + 1 : 0;
		array_splice( $lines, $offset, $length, $nitro_lines );

		$htaccess_file_path = self::get_htaccess_path();
		$write_result = WP_DEBUG
			? Filesystem::filePutContents( $htaccess_file_path, implode( "", $lines ) )
			: @Filesystem::filePutContents( $htaccess_file_path, implode( "", $lines ) );

		if ( ! $write_result ) {
			return $write_result;
		}

		return self::verify_htaccess_write( $htaccess_file_path, $lines_backup );
	}

	/**
	 * Builds the NitroPack .htaccess lines block.
	 */
	private static function build_nitro_lines( $status ) {
		$nitro_lines = [ "# BEGIN NITROPACK" ];

		if ( $status ) {
			$rules = apply_filters( "nitropack_htaccess_rules", [] );

			if ( is_string( $rules ) ) {
				$rules = explode( "\n", $rules );
			}

			if ( is_array( $rules ) ) {
				$nitro_lines = array_merge( $nitro_lines, $rules );
			}
		}

		$nitro_lines[] = "# END NITROPACK";

		return array_map( function ( $line ) {
			return trim( $line ) . "\n";
		}, $nitro_lines );
	}

	/**
	 * Verifies the .htaccess write by performing a healthcheck request, restores backup on failure.
	 */
	private static function verify_htaccess_write( $htaccess_file_path, $lines_backup ) {
		$home_url = null;
		$site_config = get_nitropack()->getSiteConfig();

		if ( $site_config && ! empty( $site_config["home_url"] ) ) {
			$home_url = $site_config["home_url"];
		} elseif ( function_exists( 'get_home_url' ) ) {
			$home_url = get_home_url();
		}

		if ( ! $home_url ) {
			return false;
		}

		$home_url .= ( strpos( $home_url, "?" ) === false ? "?" : "&" ) . "nitroHealthcheck=1";

		try {
			$client = new \NitroPack\HttpClient\HttpClient( $home_url );
			$client->timeout = 5;
			$client->setHeader( "Accept", "text/html" );
			$client->fetch();

			if ( $client->getStatusCode() != 200 ) {
				WP_DEBUG
					? Filesystem::filePutContents( $htaccess_file_path, implode( "", $lines_backup ) )
					: @Filesystem::filePutContents( $htaccess_file_path, implode( "", $lines_backup ) );
				return false;
			}
		} catch (\Exception $e) {
			// Can't be certain if the issue is from .htaccess mods or loopback request restrictions
			return false;
		}

		return true;
	}

	/**
	 * Grabs the absolute path of wp-config.php
	 * @return bool|string
	 */
	public static function get_wp_config_path() {
		$config_file_path = nitropack_trailingslashit( ABSPATH ) . "wp-config.php";
		if ( ! file_exists( $config_file_path ) ) {
			$config_file_path = nitropack_trailingslashit( dirname( ABSPATH ) ) . "wp-config.php";
			// Check for wp-settings.php to avoid confusion with nested WP installations (see wp-load.php)
			$settings_file_path = nitropack_trailingslashit( dirname( ABSPATH ) ) . "wp-settings.php";
			if ( ! file_exists( $config_file_path ) || file_exists( $settings_file_path ) ) {
				return false;
			}
		}

		if ( defined( "WPE_PLATFORM_NAME" ) && WPE_PLATFORM_NAME == "autoscale" ) {
			return $config_file_path;
		}

		if ( ! is_writable( $config_file_path ) ) {
			return false;
		}

		return $config_file_path;
	}

	/**
	 * Adds the WP_CACHE constant in wp-config.php
	 * @param mixed $status
	 */
	public static function set_wp_cache_const( $status ) {
		if ( \NitroPack\Integration\Hosting\Flywheel::detect() ) {
			return true;
		}

		if ( \NitroPack\Integration\Hosting\Pressable::detect() ) {
			return self::set_batcache_compat( $status );
		}

		$config_file_path = self::get_wp_config_path();
		if ( ! $config_file_path ) {
			return false;
		}

		$lines = file( $config_file_path );
		if ( empty( $lines ) ) {
			return false;
		}

		$replacement_val = sprintf( " %s /* Modified by NitroPack */ ", ( $status ? "true" : "false" ) );
		$wp_cache_found = self::find_and_update_wp_cache( $lines, $replacement_val );

		if ( ! $wp_cache_found ) {
			$handled = self::handle_missing_wp_cache( $lines, $status );
			if ( ! $handled ) {
				return true;
			}
		}

		return self::write_wp_config_file( $config_file_path, $lines );
	}

	/**
	 * Searches for and updates the WP_CACHE define in config lines.
	 * @param array $lines Reference to config file lines
	 * @param string $replacement_val The replacement value for WP_CACHE
	 * @return bool True if WP_CACHE was found and updated
	 */
	private static function find_and_update_wp_cache( &$lines, $replacement_val ) {
		$wp_cache_found = false;
		$php_opening_tag_line = false;

		foreach ( $lines as $line_index => &$line ) {
			if ( strpos( $line, self::PHP_OPENING_TAG ) !== false && strpos( $line, "?>" ) === false ) {
				$php_opening_tag_line = $line_index;
			}

			if ( ! $wp_cache_found && preg_match( "/define\s*\(\s*['\"](.*?)['\"].?,(.*?)\)/", $line, $matches ) && $matches[1] == "WP_CACHE" ) {
				$line = str_replace( $matches[2], $replacement_val, $line );
				$wp_cache_found = true;
			}

			if ( $php_opening_tag_line !== false && $wp_cache_found ) {
				break;
			}
		}
		unset( $line );

		return $wp_cache_found;
	}

	/**
	 * Handles insertion of WP_CACHE constant when it's not already defined.
	 * @param array $lines Reference to config file lines
	 * @param bool $status The WP_CACHE value to set
	 * @return bool True if constant should be inserted, false if operation should be skipped
	 */
	private static function handle_missing_wp_cache( &$lines, $status ) {
		if ( ! $status ) {
			return false;
		}

		$new_val = sprintf( "define( 'WP_CACHE', %s /* Modified by NitroPack */ );\n", "true" );
		$php_opening_tag_line = self::find_php_opening_tag( $lines );

		if ( $php_opening_tag_line !== false ) {
			array_splice( $lines, $php_opening_tag_line + 1, 0, [ $new_val ] );
		} else {
			array_unshift( $lines, self::PHP_OPENING_TAG . " " . trim( $new_val ) . " ?>\n" );
		}

		return true;
	}

	/**
	 * Finds the line index of the PHP opening tag.
	 * @param array $lines Config file lines
	 * @return bool|int The line index or false if not found
	 */
	private static function find_php_opening_tag( $lines ) {
		foreach ( $lines as $line_index => $line ) {
			if ( strpos( $line, self::PHP_OPENING_TAG ) !== false && strpos( $line, "?>" ) === false ) {
				return $line_index;
			}
		}
		return false;
	}

	/**
	 * Writes the updated config lines back to the wp-config.php file.
	 * @param string $config_file_path Path to wp-config.php
	 * @param array $lines The updated config lines
	 * @return bool|int Result of the file write operation
	 */
	private static function write_wp_config_file( $config_file_path, $lines ) {
		return WP_DEBUG
			? Filesystem::filePutContents( $config_file_path, implode( "", $lines ) )
			: @Filesystem::filePutContents( $config_file_path, implode( "", $lines ) );
	}

	/**
	 * Adds the Batcache compatibility file include in wp-config.php
	 * @param mixed $status
	 */
	private static function set_batcache_compat( $status ) {
		$current_compat_status = defined( "NITROPACK_BATCACHE_COMPAT" ) && NITROPACK_BATCACHE_COMPAT;
		if ( $current_compat_status === $status ) {
			return true;
		}

		$config_file_path = self::get_wp_config_path();
		if ( ! $config_file_path ) {
			return false;
		}

		$bat_cache_file_path = NITROPACK_PLUGIN_DIR . "classes/WordPress/Batcache/batcache.php";
		$compat_include = sprintf( "if (file_exists(\"%s\")) { require_once \"%s\"; } // NitroPack compatibility with Batcache\n", $bat_cache_file_path, $bat_cache_file_path );
		$lines = file( $config_file_path );

		if ( empty( $lines ) ) {
			return false;
		}

		$new_lines = self::remove_batcache_lines( $lines );

		if ( $status ) {
			$new_lines = self::insert_compat_include( $new_lines, $compat_include );
		}

		return self::write_wp_config_file( $config_file_path, $new_lines );
	}

	/**
	 * Removes existing batcache-related lines from config file.
	 * @param array $lines Config file lines
	 * @return array Filtered lines
	 */
	private static function remove_batcache_lines( $lines ) {
		foreach ( $lines as $line_index => &$line ) {
			if ( preg_match( "/nitropack.*?batcache/i", $line ) ) {
				$line = "//REMOVE AT FILTER";
			}
		}
		unset( $line );

		return array_filter( $lines, function ( $line ) {
			return $line != "//REMOVE AT FILTER";
		} );
	}

	/**
	 * Inserts the Batcache compatibility include at the appropriate location.
	 * @param array $new_lines Config file lines
	 * @param string $compat_include The compatibility include code
	 * @return array Updated lines with include inserted
	 */
	private static function insert_compat_include( $new_lines, $compat_include ) {
		$php_opening_tag_line = self::find_php_opening_tag( $new_lines );

		if ( $php_opening_tag_line !== false ) {
			array_splice( $new_lines, $php_opening_tag_line + 1, 0, [ $compat_include ] );
		} else {
			array_unshift( $new_lines, self::PHP_OPENING_TAG . " " . trim( $compat_include ) . " ?>\n" );
		}

		return $new_lines;
	}
}
