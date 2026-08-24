<?php

namespace NitroPack\Util;

final class Utils {
	/**
	 * Checks if the current request is a WP-CLI request
	 * @return bool
	 */
	public static function is_wp_cli() {
		return defined( "WP_CLI" ) && WP_CLI;
	}

	/**
	 * Checks if the current request is a WP-Cron
	 * @return bool
	 */
	public static function is_wp_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}

	/**
	 * Checks if the current request is AJAX
	 * @return bool
	 */
	public static function is_ajax() {
		return ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) ||
			( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			( ! empty( $_SERVER["HTTP_X_REQUESTED_WITH"] ) && $_SERVER["HTTP_X_REQUESTED_WITH"] == "XMLHttpRequest" ) ||
			( ! empty( $_SERVER["REQUEST_URI"] ) && basename( $_SERVER["REQUEST_URI"] ) == "admin-ajax.php" ) ||
			! empty( $_GET["wc-ajax"] );
	}
	/**
	 * Checks if the current request is a REST API request
	 * @return bool
	 */
	public static function is_rest() {
		// Source: https://wordpress.stackexchange.com/a/317041
		$prefix = rest_get_url_prefix();
		if (
			defined( 'REST_REQUEST' ) && REST_REQUEST // (#1)
			|| isset( $_GET['rest_route'] ) // (#2)
			&& strpos( trim( $_GET['rest_route'], '\\/' ), $prefix, 0 ) === 0
		) {
			return true;
		}
		// (#3)
		global $wp_rewrite;
		if ( $wp_rewrite === null ) {
			$wp_rewrite = new \WP_Rewrite();
		}

		// (#4)
		$rest_url = wp_parse_url( trailingslashit( rest_url() ) );
		$current_url = wp_parse_url( add_query_arg( array() ) );
		return strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;
	}
	public static function is_post_request() {
		return ( ! empty( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) || ( empty( $_SERVER['REQUEST_METHOD'] ) && ! empty( $_POST ) );
	}

	/**
	 * Checks if the current request is an XML-RPC request
	 * @return bool
	 */
	public static function is_xmlrpc() {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	/**
	 * Checks if the current request is from robots.txt
	 * @return bool
	 */
	public static function is_robots_request() {
		return is_robots() || ( ! empty( $_SERVER["REQUEST_URI"] ) && basename( parse_url( $_SERVER["REQUEST_URI"], PHP_URL_PATH ) ) === "robots.txt" );
	}

	/**
	 * Checks if the current request is a Cache Warmup from NitroPack
	 * @return bool
	 */
	public static function is_warmup_nitropack_request() {
		return ! empty( $_SERVER["HTTP_X_NITRO_WARMUP"] );
	}

	/**
	 * Checks if the current request is an NitroPack optimizer request
	 * @return bool
	 */
	public static function is_optimizer_nitropack_request() {
		return isset( $_SERVER["HTTP_X_NITROPACK_REQUEST"] );
	}

	/**
	 * Checks if the current request is a Lighthouse request
	 * @return bool
	 */
	public static function is_lighthouse_request() {
		return ! empty( $_SERVER["HTTP_USER_AGENT"] ) && stripos( $_SERVER["HTTP_USER_AGENT"], "lighthouse" ) !== false;
	}

	/**
	 * Checks if the current request is a GTmetrix request
	 * @return bool
	 */
	public static function is_gtmetrix_request() {
		return ! empty( $_SERVER["HTTP_USER_AGENT"] ) && stripos( $_SERVER["HTTP_USER_AGENT"], "gtmetrix" ) !== false;
	}

	/**
	 * Checks if the current request is a Pingdom request
	 * @return bool
	 */
	public static function is_pingdom_request() {
		return ! empty( $_SERVER["HTTP_USER_AGENT"] ) && stripos( $_SERVER["HTTP_USER_AGENT"], "pingdom" ) !== false;
	}

	/**
	 * We have a lot of requests with these GET parameters that we want to ignore
	 */
	public static function get_ignored_get_params() {
		return [ 'shop_order', 'shop_order_refund', 'revisionPreview' ];
	}
	public static function nitropack_health_check() {
		if ( null !== $nitro = get_nitropack_sdk() ) {
			return $nitro->getHealthStatus() == \NitroPack\SDK\HealthStatus::HEALTHY || $nitro->checkHealthStatus() == \NitroPack\SDK\HealthStatus::HEALTHY;
		}
		return true;
	}
	//Pages
	/**
	 * Checks if the current request is the homepage
	 * @return bool
	 */
	public static function nitropack_is_home() {
		if ( 'posts' == get_option( 'show_on_front' ) ) {
			return is_front_page() || is_home();
		} else {
			return is_front_page();
		}
	}
	/**
	 * Checks if the current request is a Blog page
	 * @return bool
	 */
	public static function nitropack_is_blogindex() {
		return is_home();
	}

	/**
	 * Checks if the current request is an archive page
	 * @return bool
	 */
	public static function nitropack_is_archive() {
		return apply_filters( "nitropack_is_archive_page", is_author() || is_archive() );
	}

	/**
	 * Checks if the current request is an AMP page
	 * @return bool
	 */
	public static function is_amp_page() {
		return ( function_exists( 'amp_is_request' ) && amp_is_request() && ! get_nitropack()->setDisabledReason( "amp page" ) ) ||
			( function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint() && ! get_nitropack()->setDisabledReason( "amp page" ) );
	}
	public static function is_logged_in() {
		$nitro = get_nitropack_sdk();
		$useAccountOverride = $nitro !== null && $nitro->isStatefulCacheSatisfied( "account" );
		if ( $useAccountOverride ) {
			return false;
		}

		//used for previewing the site while logged in
		if ( ! empty( $_GET['previewmode'] ) ) {
			return false;
		}

		$loginCookies = array( defined( 'NITROPACK_LOGGED_IN_COOKIE' ) ? NITROPACK_LOGGED_IN_COOKIE : ( defined( 'LOGGED_IN_COOKIE' ) ? LOGGED_IN_COOKIE : '' ) );
		foreach ( $loginCookies as $loginCookie ) {
			if ( ! empty( $_COOKIE[ $loginCookie ] ) ) {
				$parts = explode( '|', urldecode( $_COOKIE[ $loginCookie ] ) );
				if ( count( $parts ) < 3 ) {
					continue; // Invalid cookie
				}

				return time() <= (int) $parts[1];
			}
		}
		$cookieStr = implode( "|", array_keys( $_COOKIE ) );

		return strpos( $cookieStr, "wordpress_logged_in_" ) !== false;
	}
	/**
	 * Get the layout type of the current page for internal use as Page Type.
	 *
	 * @return string The layout type, which can be one of the following: "default", "home", "blogindex", "page", "attachment", "author", "search", "tag", "taxonomy", "category", "archive", "feed", or the post type for single posts.
	 */
	public static function get_page_type() {
		$page_type_map = [
			[ Utils::nitropack_is_home(), "home" ],
			[ Utils::nitropack_is_blogindex(), "blogindex" ],
			[ is_page(), "page" ],
			[ is_attachment(), "attachment" ],
			[ is_author(), "author" ],
			[ is_search(), "search" ],
			[ is_tag(), "tag" ],
			[ is_tax(), "taxonomy" ],
			[ is_category(), "category" ],
			[ Utils::nitropack_is_archive(), "archive" ],
			[ is_feed(), "feed" ],
		];

		foreach ( $page_type_map as [ $condition, $type ] ) {
			if ( $condition ) {
				return $type;
			}
		}

		return is_single() ? get_post_type() : "default";
	}
	/**
	 * Sanitizes a URL by validating and escaping it for safe use.
	 *
	 * @param mixed $url The URL to sanitize
	 * @return string|null The sanitized URL, or null if validation fails
	 */
	public static function sanitize_url( $url ) {
		$result = null;
		if ( ! function_exists( "esc_url" ) ) {
			$sanitizedUrl = filter_var( $url, FILTER_SANITIZE_URL );
			if ( $sanitizedUrl !== false && filter_var( $sanitizedUrl, FILTER_VALIDATE_URL ) !== false ) {
				$result = $sanitizedUrl;
			}
		} else if ( $validatedUrl = esc_url( $url, array( "http", "https" ), "notdisplay" ) ) {
			$result = $validatedUrl;
		}

		return $result;
	}
	/**
	 * Simple method to detect on which hosting is the user
	 * @return string
	 */
	public static function detect_hosting() {
		$hostingDetectors = array(
			'Flywheel' => 'flywheel',
			'Cloudways' => 'cloudways',
			'WPEngine' => 'wpengine',
			'SiteGround' => 'siteground',
			'GoDaddyWPaaS' => 'godaddy_wpaas',
			'GridPane' => 'gridpane',
			'Kinsta' => 'kinsta',
			'Closte' => 'closte',
			'Pagely' => 'pagely',
			'WPX' => 'wpx',
			'Vimexx' => 'vimexx',
			'Pressable' => 'pressable',
			'RocketNet' => 'rocketnet',
			'Savvii' => 'savvii',
			'DreamHost' => 'dreamhost',
			'Raidboxes' => 'raidboxes',
		);

		foreach ( $hostingDetectors as $class => $hostingName ) {
			$className = "\\NitroPack\\Integration\\Hosting\\$class";
			if ( $className::detect() ) {
				return $hostingName;
			}
		}

		return "unknown";
	}
}