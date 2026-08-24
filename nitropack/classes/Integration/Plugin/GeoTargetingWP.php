<?php

namespace NitroPack\Integration\Plugin;

use \NitroPack\SDK\Filesystem;

class GeoTargetingWP {
	const STAGE = "very_early";
	const allGeoWpCookies = [ 'geot_rocket_country', 'geot_rocket_state', 'geot_rocket_city', 'STYXKEY_geot_country' ];
	const defaultVariationCookies = [ 'nitro_geot_country_code' ];
	const cookieMap = [
		'geot_rocket_country' => 'nitro_geot_country_code',
		'geot_rocket_state' => 'nitro_geot_region',
		'geot_rocket_city' => 'nitro_geot_city_code',
		'STYXKEY_geot_country' => 'nitro_geot_country_code',
	];
	private $printedCookies = [];
	private $cache = array();
	private $cacheDir = NULL;

	public static function isActive() {
		return defined( "GEOWP_VERSION" );
	}

	public function init( $stage ) {
		$siteConfig = get_nitropack()->getSiteConfig();
		$geotSettings = null;

		if ( empty( $siteConfig["isGeoTargetingWPActive"] ) ) {
			return true;
		}

		// no need for variation cookies with GEOWP if using Ajax mode
		if ( function_exists( 'geot_settings' ) ) {
			$geotSettings = geot_settings();
		} elseif ( function_exists( 'get_option' ) ) {
			$geotSettings = apply_filters( 'geot/settings_page/opts', get_option( 'geot_settings' ) );
		}

		if ( ! empty( $geotSettings ) && ! empty( $geotSettings['ajax_mode'] ) ) {
			return true;
		}

		$this->cacheDir = nitropack_trailingslashit( NITROPACK_DATA_DIR ) . nitropack_trailingslashit( "geotwp-cache" );

		// !!! IMPORTANT !!!
		// We should not purge the response data cache because cache warmup will not work properly then
		// The add_action line below is left here as a place to attach this comment on
		// If response data cache is purged then the overrides for cache warmup requests will not happen
		// and we will always get the default response data or wherever the IP of the optimization server is
		//add_action('nitropack_execute_purge_all', [$this, 'purgeCache']);

		add_filter( 'geot/response_data', function ( $value ) {
			// TODO: Add more parametrs to the key. Example region and city. Base the parameters on the variation cookie settings
			$key = $value->country->iso_code;
			if ( \NitroPack\Util\Utils::is_optimizer_nitropack_request() && ! empty( $_COOKIE["nitro_geot_country_code"] ) ) {
				// Look for a response override
				$key = $_COOKIE["nitro_geot_country_code"];

				if ( $this->hasCache( $key ) ) {
					// Override based on the request cookie
					return unserialize( $this->getCache( $key ) );
				}
			} else {
				$this->setCache( $key, serialize( $value ) );
			}

			return $value;
		}, 10, 1 );

		// enable geot cookies
		add_filter( 'geot/enable_rocket_cookies', '__return_true' );
		add_filter( 'geot/disable_cookies', '__return_false' );

		// require geot cookies for serving cache
		add_filter( "nitropack_passes_cookie_requirements", [ $this, "hasGeoTargetingWpCookies" ] );

		// serve cache after geowp has added geot cookies
		add_action( 'init', function () {
			nitropack_handle_request( 'geotargetingwp' );
		}, 16 );

		add_action( 'np_set_cookie_filter', function () {
			\NitroPack\SDK\NitroPack::addCookieFilter( [ $this, "filterCookies" ] );
		} );

		if ( \NitroPack\Util\Utils::is_optimizer_nitropack_request() ) {
			add_filter( 'geot/enable_crawl_detection', '__return_false' );
		}

		return true;
	}

	public static function getCustomVariationCookies() {
		$enabledCookies = self::defaultVariationCookies;
		// apply_filter() is unavailable at stage 'very_early'
		// $enabledCookies = apply_filter("nitropack_geotargetingwp_enabled_cookies", self::defaultVariationCookies);
		return array_intersect( self::allGeoWpCookies, $enabledCookies );
	}

	public static function configureVariationCookies() {
		return; // TODO: Reimplement to work with nitro_geot_* cookies
		$siteConfig = get_nitropack()->getSiteConfig();

		if ( empty( $siteConfig["isGeoTargetingWPActive"] ) ) {
			self::removeVariationCookies( self::allGeoWpCookies );
			return true;
		}

		// standard cookie integration
		self::initVariationCookies( self::getCustomVariationCookies() );
	}

	/**
	 * Removes the specified variation cookies.
	 * @param array $cookiesToRemove Array of cookie names to remove.
	 * @return void
	 */
	private static function removeVariationCookies( array $cookiesToRemove ) {
		$api = get_nitropack_sdk()->getApi();
		try {
			$variationCookies = $api->getVariationCookies();
			foreach ( $variationCookies as $cookie ) {
				if ( in_array( $cookie["name"], $cookiesToRemove ) ) {
					$api->unsetVariationCookie( $cookie["name"] );
				}
			}
		} catch (\Exception $e) {
			// what to do here? possible reason for exception is the API not responding
			return false;
		}
	}
	/**
	 * Initializes variation cookies by removing existing ones and setting new ones.
	 * @param array $customVariationCookies Array of custom variation cookie names to set.
	 * @return bool Returns false if an exception occurs, true otherwise.
	 */
	private static function initVariationCookies( array $customVariationCookies ) {
		$api = get_nitropack_sdk()->getApi();
		try {
			$variationCookies = $api->getVariationCookies();
			foreach ( $variationCookies as $cookie ) {
				$index = array_search( $cookie["name"], $customVariationCookies );
				if ( $index !== false ) {
					array_splice( $customVariationCookies, $index, 1 );
				}
			}

			foreach ( $customVariationCookies as $cookieName ) {
				$api->setVariationCookie( $cookieName );
			}
		} catch (\Exception $e) {
			// what to do here? possible reason for exception is the API not responding
			return false;
		}
	}
	public function hasGeoTargetingWpCookies( $currentState ) {
		$allCookies = array_merge( $_COOKIE, $this->getNewCookies() );
		$neededCookies = self::getCustomVariationCookies();

		foreach ( $neededCookies as $c ) {
			if ( ! empty( $allCookies[ $c ] ) ) {
				// Needed so reverse proxies don't end up caching these pages.
				if ( ! in_array( $c, $this->printedCookies ) ) {
					$val = $allCookies[ $c ];
					if ( is_array( $val ) ) {
						$val = end( $val );
					}
					nitropack_setcookie( $c, $val, time() + 86000 );
					$this->printedCookies[] = $c;
				}
				$neededCookies = array_diff( $neededCookies, [ $c ] );
			}
		}

		if ( ! empty( $neededCookies ) ) {
			return false;
		}

		return $currentState;
	}

	/**
	 * Returns the value of a newly set cookie (not in $_COOKIE), that will be sent along with the headers of the current response.
	 * @param string $name The name of the cookie.
	 * @return string|null The value of the cookie, or null if not found.
	 */
	private function getNewCookie( string $name ) {
		$cookies = $this->getNewCookies();
		return ! empty( $cookies[ $name ] ) ? $cookies[ $name ] : null;
	}

	/**
	 * Returns an array of newly set cookies (not in $_COOKIE), that will be sent along with the headers of the current response.
	 */
	private function getNewCookies() {
		$cookies = [];
		$headers = headers_list();
		foreach ( $headers as $header ) {
			if ( strpos( $header, 'Set-Cookie: ' ) === 0 ) {
				$value = str_replace( '&', urlencode( '&' ), substr( $header, 12 ) );
				parse_str( current( explode( ';', $value ) ), $pair );
				$cookies = array_merge_recursive( $cookies, $pair );
			}
		}

		return array_filter( array_map( function ( $val ) {
			if ( is_array( $val ) ) {
				$lastEl = end( $val );
				if ( is_array( $lastEl ) ) {
					return null;
				}
				return $lastEl;
			}

			return $val;
		}, $cookies ) );
	}
	public function filterCookies( &$cookies ) {
		foreach ( self::cookieMap as $geotCookie => $nitroCookie ) {
			if ( ! empty( $_COOKIE[ $geotCookie ] ) ) {
				$cookies[ $nitroCookie ] = $_COOKIE[ $geotCookie ];
			} else {
				$newlySetCookie = $this->getNewCookie( $geotCookie );
				if ( ! empty( $newlySetCookie ) ) {
					$cookies[ $nitroCookie ] = $newlySetCookie;
				}
			}
		}

		//foreach (self::getCustomVariationCookies() as $cookieName) {
		//    $newlySetCookie = getNewCookie($cookieName);
		//    if (!empty($_COOKIE[$cookieName])) {
		//        $cookies[]
		//    }
		//    if (empty($_COOKIE[$cookieName]) && !empty($newlySetCookie)) {
		//        $cookies[$cookieName] = $newlySetCookie;
		//    }
		//}
	}

	public function purgeCache( $key = NULL ) {
		if ( ! $this->cacheDir || ! is_dir( $this->cacheDir ) )
			return;

		if ( $key ) {
			$cacheFile = $this->getCacheFile( $key );
			if ( Filesystem::fileExists( $cacheFile ) ) {
				Filesystem::deleteFile( $cacheFile );
			}

			$cacheFileWp = $this->getCacheFile( "wpremote-" . $key );
			if ( Filesystem::fileExists( $cacheFileWp ) ) {
				Filesystem::deleteFile( $cacheFileWp );
			}
			return;
		}

		$dh = opendir( $this->cacheDir );
		while ( ( $entry = readdir( $dh ) ) !== false ) {
			if ( $entry == "." || $entry == ".." )
				continue;

			$cacheFile = $this->cacheDir . $entry;
			if ( ! is_file( $cacheFile ) )
				continue;
			Filesystem::deleteFile( $cacheFile );
		}
		closedir( $dh );
		rmdir( $this->cacheDir );
	}

	public function hasCache( $key ) {
		if ( ! empty( $this->cache[ $key ] ) ) {
			return true;
		}

		$cacheFile = $this->getCacheFile( $key );
		if ( Filesystem::fileExists( $cacheFile ) ) {
			return true;
		}

		return false;
	}

	public function getCache( $key ) {
		if ( empty( $this->cache[ $key ] ) ) {
			$cacheFile = $this->getCacheFile( $key );
			if ( Filesystem::fileExists( $cacheFile ) ) {
				$this->cache[ $key ] = file_get_contents( $cacheFile );
			}
		}

		if ( ! empty( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		return NULL;
	}

	public function setCache( $key, $content ) {
		$this->cache[ $key ] = $content;

		if ( ! $this->cacheDir ) {
			return;
		}

		if ( ! is_dir( $this->cacheDir ) && ! mkdir( $this->cacheDir, 0755, true ) ) {
			return;
		}

		Filesystem::filePutContents( $this->getCacheFile( $key ), $content );
	}

	private function getCacheFile( $key ) {
		if ( ! $this->cacheDir ) {
			return;
		}

		return $this->cacheDir . md5( $key );
	}

	public function isCacheAllowed( $key ) {
		return true;
	}
}
