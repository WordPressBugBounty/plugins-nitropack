<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use NitroPack\WordPress\Settings\TestMode;
use NitroPack\SDK\Filesystem;
use NitroPack\Util\Utils;

$np_basePath = dirname( __FILE__ ) . '/';
require_once $np_basePath . 'nitropack-sdk/autoload.php';
require_once $np_basePath . 'constants.php';

$np_originalRequestCookies = $_COOKIE;
$np_customExpirationTimes = array();
$np_queriedObj = null;
$np_loggedPurges = array();
$np_loggedInvalidations = array();
$np_integrationSetupEvent = "muplugins_loaded";

/**
 * Checks if the current request which has cookies passes the requirements to be cached by NitroPack.
 * If not sets the disabled reason and returns false -> checks if user is logged in, or has items in cart.
 * @return bool
 */
function nitropack_passes_cookie_requirements() {
	$isUserLoggedIn = Utils::is_logged_in();
	$cookieStr = implode( "|", array_keys( $_COOKIE ) );
	$safeCookie = (
		( strpos( $cookieStr, "comment_author" ) === false || ! ! get_nitropack()->setDisabledReason( "comment author" ) )
		&& ( strpos( $cookieStr, "wp-postpass_" ) === false || ! ! get_nitropack()->setDisabledReason( "password protected page" ) )
	);

	$isItemsInCart = ! empty( $_COOKIE["woocommerce_items_in_cart"] );
	$useCartOverride = nitropack_is_cart_cache_active();

	if ( $isUserLoggedIn ) {
		get_nitropack()->setDisabledReason( "logged in" );
	}

	if ( $isItemsInCart && ! $useCartOverride ) {
		get_nitropack()->setDisabledReason( "items in cart" );
	}

	// allow registering filters to "nitropack_passes_cookie_requirements"
	return apply_filters( "nitropack_passes_cookie_requirements", $safeCookie && ( ! $isItemsInCart || $useCartOverride ) && ( ! $isUserLoggedIn ) );
}

/**
 * Sets a cookie with the given name and value, along with optional expiration time and additional options.
 * @param string $name Cookie Name
 * @param string $value Cookie Value
 * @param ?int $expires Cookie Expiration Time (Unix Timestamp). If null, the cookie will be a session cookie.
 * @param array $options Additional cookie options (e.g., "SameSite", "Secure", etc.)
 * @return void
 */
function nitropack_setcookie( string $name, string $value, ?int $expires = null, array $options = [] ) {
	if ( headers_sent() )
		return;
	$cookie_options = '';
	$cookie_path = nitropack_cookiepath();

	if ( $expires && is_numeric( $expires ) ) {
		$options["Expires"] = date( "D, d M Y H:i:s", (int) $expires ) . ' GMT';
	}

	if ( empty( $options["SameSite"] ) ) {
		$options["SameSite"] = "Lax";
	}

	foreach ( $options as $optName => $optValue ) {
		$cookie_options .= "$optName=$optValue; ";
	}
	nitropack_header( "set-cookie: $name=$value; Path=$cookie_path; " . $cookie_options, false );
}

/**
 * Returns the cookie path for the current site.
 * 
 * @return string The cookie path.
 */
function nitropack_cookiepath() {
	$siteConfig = nitropack_get_site_config();
	$homeUrl = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();
	$url = new \NitroPack\Url\Url( $homeUrl );
	return $url ? $url->getPath() : "/";
}

/**
 * Returns true if the current request passes all requirements to be cached by NitroPack.
 *
 * @param bool $no_cache Whether to detect the result if it hasn't been cached yet. Default is true.
 * @return bool
 */
function nitropack_passes_page_requirements( bool $no_cache = true ) {
	static $cachedResult = null;
	$reduceCheckoutChecks = defined( "NITROPACK_REDUCE_CHECKOUT_CHECKS" ) && NITROPACK_REDUCE_CHECKOUT_CHECKS;
	$reduceCartChecks = defined( "NITROPACK_REDUCE_CART_CHECKS" ) && NITROPACK_REDUCE_CART_CHECKS;

	if ( $cachedResult === null && $no_cache ) {

		$cachedResult = ! (
			( is_404() && ! get_nitropack()->setDisabledReason( "404" ) ) ||
			( is_preview() && ! get_nitropack()->setDisabledReason( "preview page" ) ) ||
			( is_feed() && ! get_nitropack()->setDisabledReason( "feed" ) ) ||
			( is_comment_feed() && ! get_nitropack()->setDisabledReason( "comment feed" ) ) ||
			( is_trackback() && ! get_nitropack()->setDisabledReason( "trackback" ) ) ||
			( Utils::is_logged_in() && ! get_nitropack()->setDisabledReason( "logged in" ) ) ||
			( is_search() && ! get_nitropack()->setDisabledReason( "search" ) ) ||
			( Utils::is_ajax() && ! get_nitropack()->setDisabledReason( "ajax" ) ) ||
			( Utils::is_post_request() && ! get_nitropack()->setDisabledReason( "post request" ) ) ||
			( Utils::is_xmlrpc() && ! get_nitropack()->setDisabledReason( "xmlrpc" ) ) ||
			( Utils::is_robots_request() && ! get_nitropack()->setDisabledReason( "robots" ) ) ||
			Utils::is_amp_page() ||
			! nitropack_is_allowed_request() ||
			( Utils::is_wp_cron() && ! get_nitropack()->setDisabledReason( "doing cron" ) ) || // CRON request
			( Utils::is_wp_cli() ) || // CLI request
			( defined( 'WC_PLUGIN_FILE' ) && ( is_page( 'cart' ) || ( ! $reduceCartChecks && is_cart() ) ) && ! get_nitropack()->setDisabledReason( "cart page" ) ) || // WooCommerce
			( defined( 'WC_PLUGIN_FILE' ) && ( is_page( 'checkout' ) || ( ! $reduceCheckoutChecks && is_checkout() ) ) && ! get_nitropack()->setDisabledReason( "checkout page" ) ) || // WooCommerce
			( defined( 'WC_PLUGIN_FILE' ) && is_account_page() && ! get_nitropack()->setDisabledReason( "account page" ) ) // WooCommerce
		);
	}

	return $cachedResult;
}

/* BEACON */
/**
 * Checks if the current request is a valid NitroPack beacon request.
 *
 * @return bool True if the request is valid, false otherwise.
 */
function is_valid_nitropack_beacon() {
	if ( ! isset( $_POST["nitroBeaconUrl"] ) || ! isset( $_POST["nitroBeaconHash"] ) ) {
		return false;
	}

	$siteConfig = nitropack_get_site_config();
	if ( ! $siteConfig || empty( $siteConfig["siteSecret"] ) ) {
		return false;
	}


	if ( function_exists( "hash_hmac" ) && function_exists( "hash_equals" ) ) {
		$url = base64_decode( $_POST["nitroBeaconUrl"] );
		$cookiesJson = ! empty( $_POST["nitroBeaconCookies"] ) ? base64_decode( $_POST["nitroBeaconCookies"] ) : ""; // We need to fall back to empty string to remain backwards compatible. Otherwise cache files invalidated before an upgrade will never get updated :(
		$layout = ! empty( $_POST["layout"] ) ? $_POST["layout"] : "";
		$localHash = hash_hmac( "sha512", $url . $cookiesJson . $layout, $siteConfig["siteSecret"] );
		return hash_equals( $_POST["nitroBeaconHash"], $localHash );
	} else {
		return ! empty( $_POST["nitroBeaconUrl"] );
	}
}

/**
 * Beacon - trigger optimizations on organic visit
 * @return void
 */
function nitropack_handle_beacon() {
	global $np_originalRequestCookies;
	if ( ! defined( "NITROPACK_BEACON_HANDLED" ) ) {
		define( "NITROPACK_BEACON_HANDLED", 1 );
	} else {
		return;
	}

	$siteConfig = nitropack_get_site_config();
	if ( $siteConfig && ! empty( $siteConfig["siteId"] ) && ! empty( $siteConfig["siteSecret"] ) && ! empty( $_POST["nitroBeaconUrl"] ) ) {
		$url = base64_decode( $_POST["nitroBeaconUrl"] );

		if ( ! empty( $_POST["nitroBeaconCookies"] ) ) {
			$np_originalRequestCookies = json_decode( base64_decode( $_POST["nitroBeaconCookies"] ), true );
		}

		NitroPack\WordPress\NitroPack::getInstance()->getLogger()->notice( 'Beacon request received for URL: ' . $url );

		if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"], $url ) ) {
			try {

				$hasLocalCache = $nitro->hasLocalCache( false );
				$proxyPurgeOnly = ! empty( $_POST["proxyPurgeOnly"] );
				$layout = ! empty( $_POST["layout"] ) ? $_POST["layout"] : "default";
				$output = "";

				if ( ! $proxyPurgeOnly ) {
					if ( ! $hasLocalCache ) {
						nitropack_header( "X-Nitro-Beacon: FORWARD" );
						try {
							$hasCache = $nitro->hasRemoteCache( $layout, false ); // Download the new cache file
							$hasLocalCache = $hasCache;
							$output = sprintf( "Cache %s", $hasCache ? "fetched" : "requested" );
						} catch (\Exception $e) {
							// not a critical error, do nothing
						}
					} else {
						nitropack_header( "X-Nitro-Beacon: SKIP" );
						$output = sprintf( "Cache exists already" );
					}
				}

				if ( $hasLocalCache || $proxyPurgeOnly ) { // proxyPurgeOnly is set for unsupported browsers, in which case we need to purge the cache regardless of the existence of local NP cache
					nitropack_header( "X-Nitro-Proxy-Purge: true" );
					$nitro->purgeProxyCache( $url );
					do_action( 'nitropack_integration_purge_url', $url );
				}

				\NitroPack\ModuleHandler::onShutdown( function () use ($output) {
					echo $output;
				} );
			} catch (Exception $e) {
				// not a critical error, do nothing
			}
		}
	}
	\NitroPack\ModuleHandler::onCriticalInit( function () {
		exit;
	} );
}

function nitropack_is_allowed_request() {
	global $np_queriedObj;
	$CPTOptimization = NitroPack\WordPress\Settings\CPTOptimization::getInstance();
	$cacheableObjectTypes = $CPTOptimization->nitropack_get_cacheable_object_types();
	if ( is_array( $cacheableObjectTypes ) ) {
		if ( Utils::nitropack_is_home() ) {
			if ( ! in_array( 'home', $cacheableObjectTypes ) ) {
				get_nitropack()->setDisabledReason( "page type not allowed (home)" );
				return false;
			}
		} else {
			if ( is_tax() || is_category() || is_tag() ) {
				$np_queriedObj = get_queried_object();
				if ( ! empty( $np_queriedObj ) && ! in_array( $np_queriedObj->taxonomy, $cacheableObjectTypes ) ) {
					get_nitropack()->setDisabledReason( "page type not allowed ({$np_queriedObj->taxonomy})" );
					return false;
				}
			} else {
				if ( Utils::nitropack_is_archive() ) {
					if ( ! in_array( 'archive', $cacheableObjectTypes ) ) {
						get_nitropack()->setDisabledReason( "page type not allowed (archive)" );
						return false;
					}
				} else {
					$postType = get_post_type();
					if ( ! empty( $postType ) && ! in_array( $postType, $cacheableObjectTypes ) ) {
						get_nitropack()->setDisabledReason( "page type not allowed ($postType)" );
						return false;
					}
				}
			}
		}
	}

	$ignoredGETParams = Utils::get_ignored_get_params();
	foreach ( $ignoredGETParams as $ignoredGETParam ) {
		if ( ! empty( $_GET[ $ignoredGETParam ] ) ) {
			get_nitropack()->setDisabledReason( "ignored url parameter detected ($ignoredGETParam)" );
			return false;
		}
	}
	//add test mode as disabled reason but not when the testnitro parameter is set
	if ( empty( $_GET['testnitro'] ) && TestMode::getInstance()->is_test_mode_enabled() ) {
		get_nitropack()->setDisabledReason( "Test Mode" );
		return false;
	}

	if ( null !== $nitro = get_nitropack_sdk() ) {
		return ( $nitro->isAllowedUrl( $nitro->getUrl() ) || get_nitropack()->setDisabledReason( "url not allowed" ) ) &&
			( $nitro->isAllowedRequest( true ) || get_nitropack()->setDisabledReason( "request type not allowed" ) );
	}

	get_nitropack()->setDisabledReason( "site not connected" );
	return false;
}

function nitropack_print_beacon_script() {
	if ( defined( "NITROPACK_BEACON_PRINTED" ) || ! nitropack_passes_page_requirements() ) {
		return;
	}
	define( "NITROPACK_BEACON_PRINTED", true );
	echo apply_filters( "nitro_script_output", nitropack_get_beacon_script() );
}

function nitropack_get_beacon_script() {
	$siteConfig = nitropack_get_site_config();
	if ( $siteConfig && ! empty( $siteConfig["siteId"] ) && ! empty( $siteConfig["siteSecret"] ) ) {
		if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
			$url = $nitro->getUrl();
			$cookiesJson = json_encode( $nitro->supportedCookiesFilter( NitroPack\SDK\NitroPack::getCookies() ) );
			$layout = Utils::get_page_type();

			if ( function_exists( "hash_hmac" ) && function_exists( "hash_equals" ) ) {
				$hash = hash_hmac( "sha512", $url . $cookiesJson . $layout, $siteConfig["siteSecret"] );
			} else {
				$hash = "";
			}
			$url = base64_encode( $url ); // We want only ASCII
			$cookiesb64 = base64_encode( $cookiesJson );
			$proxyPurgeOnly = ! $nitro->isAllowedBrowser();

			return "
<script nitro-exclude>
    if (!window.NITROPACK_STATE || window.NITROPACK_STATE != 'FRESH') {
        var proxyPurgeOnly = " . ( $proxyPurgeOnly ? 1 : 0 ) . ";
        if (typeof navigator.sendBeacon !== 'undefined') {
            var nitroData = new FormData(); nitroData.append('nitroBeaconUrl', '$url'); nitroData.append('nitroBeaconCookies', '$cookiesb64'); nitroData.append('nitroBeaconHash', '$hash'); nitroData.append('proxyPurgeOnly', '$proxyPurgeOnly'); nitroData.append('layout', '$layout'); navigator.sendBeacon(location.href, nitroData);
        } else {
            var xhr = new XMLHttpRequest(); xhr.open('POST', location.href, true); xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); xhr.send('nitroBeaconUrl={$url}&nitroBeaconCookies={$cookiesb64}&nitroBeaconHash={$hash}&proxyPurgeOnly={$proxyPurgeOnly}&layout={$layout}');
        }
    }
</script>";
		}
	}
}

function nitropack_print_generic_nitro_script() {
	if ( defined( "NITROPACK_GENERIC_NITRO_SCRIPT_PRINTED" ) ) {
		return;
	}
	define( "NITROPACK_GENERIC_NITRO_SCRIPT_PRINTED", true );
	//echo apply_filters( "nitro_script_output", nitropack_get_telemetry_meta() );
	echo apply_filters( "nitro_script_output", nitropack_get_generic_nitro_script() );
}

/**
 * Generic NitroPack Script - pageview counting and related connection details
 * @return string
 */
function nitropack_get_generic_nitro_script() {
	$siteConfig = nitropack_get_site_config();
	if ( $siteConfig && ! empty( $siteConfig["siteId"] ) && ! empty( $siteConfig["siteSecret"] ) ) {
		if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
			$config = $nitro->getConfig();
			if ( ! empty( $config->GenericNitroScript->Script ) ) {
				return "<script id='nitro-generic' nitro-exclude>" . $config->GenericNitroScript->Script . "</script>";
			}
		}
	}

	return "";
}

function nitropack_print_web_vitals_telemetry_script() {
	if ( defined( "NITROPACK_WEB_VITALS_TELEMETRY_SCRIPT_PRINTED" ) ) {
		return;
	}
	define( "NITROPACK_WEB_VITALS_TELEMETRY_SCRIPT_PRINTED", true );
	echo apply_filters( "nitro_script_output", nitropack_get_web_vitals_telemetry_script() );
}

/**
 * Web Vitals - RUM (Real User Monitoring) data gathering
 * @return string
 */
function nitropack_get_web_vitals_telemetry_script() {
	$siteConfig = nitropack_get_site_config();
	if ( $siteConfig && ! empty( $siteConfig["siteId"] ) && ! empty( $siteConfig["siteSecret"] ) ) {
		if ( null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
			$config = $nitro->getConfig();
			if ( ! empty( $config->WebVitalsTelemetry->Status ) && ! empty( $config->WebVitalsTelemetry->Script ) ) {
				return "<script id='nitro-web-vitals-telemetry' nitro-exclude>" . $config->WebVitalsTelemetry->Script . "</script>";
			}
		}
	}

	return "";
}

/**
 * Not used at the moment
 * @return string
 */
function nitropack_get_telemetry_meta() {
	$disabledReason = get_nitropack()->getDisabledReason();
	$missReason = $disabledReason !== null ? $disabledReason : "cache not found";
	$pageType = get_nitropack()->getPageType();
	$isEligibleForOptimization = nitropack_passes_page_requirements();
	$metaObj = "window.NPTelemetryMetadata={";

	if ( $missReason ) {
		$metaObj .= "missReason: (!window.NITROPACK_STATE ? '$missReason' : 'hit'),";
	}

	if ( $pageType ) {
		$metaObj .= "pageType: '$pageType',";
	}

	$metaObj .= "isEligibleForOptimization: " . ( $isEligibleForOptimization ? "true" : "false" ) . ",";

	$metaObj .= "}";

	return "<script id='nitro-telemetry-meta' nitro-exclude>$metaObj</script>";
}

function nitropack_print_element_override() {
	if ( defined( "NITROPACK_ELEMENT_OVERRIDE_PRINTED" ) ) {
		return;
	}
	define( "NITROPACK_ELEMENT_OVERRIDE_PRINTED", true );
	echo apply_filters( "nitro_script_output", nitropack_get_element_override_script() );
}

function nitropack_get_element_override_script() {
	$nitro = get_nitropack_sdk();
	return $nitro !== null ? $nitro->getStatefulCacheHandlerScript() : "";
}
function nitropack_init() {
	global $np_queriedObj;
	nitropack_header( 'X-Nitro-Cache: MISS' );
	$GLOBALS["NitroPack.tags"] = array();
	$webhooks = new \NitroPack\WordPress\Webhooks();
	if ( $webhooks->is_valid_webhook() ) {
		$webhooks->handle_webhook();
	} else {
		if ( is_valid_nitropack_beacon() ) {
			nitropack_handle_beacon();
		} else {
			/* The following if statement should stay as it is written.
			 * is_archive() can return true if visiting a tax, category or tag page, so is_acrchive must be checked last
			 */
			if ( is_tax() || is_category() || is_tag() ) {
				$np_queriedObj = get_queried_object();
				get_nitropack()->setPageType( $np_queriedObj->taxonomy );
			} else {
				$layout = Utils::get_page_type();
				get_nitropack()->setPageType( $layout );
			}

			add_action( 'wp_footer', 'nitropack_print_element_override', 9999999 );
			if ( ! isset( $_GET["wpf_action"] ) && nitropack_passes_cookie_requirements() && nitropack_passes_page_requirements() ) {
				add_action( 'wp_footer', 'nitropack_print_beacon_script' );
				add_action( 'get_footer', 'nitropack_print_beacon_script' );

				if ( Utils::is_optimizer_nitropack_request() ) { // Only care about tags for requests coming from our service. There is no need to do an API request when handling a standard client request.
					if ( defined( 'FUSION_BUILDER_VERSION' ) ) {
						add_filter( 'do_shortcode_tag', 'nitropack_handle_fusion_builder_conatainer_expiration', 10, 3 );
						add_action( 'wp_footer', 'nitropack_set_custom_expiration' );
					} else {
						nitropack_set_custom_expiration();
					}

					$GLOBALS["NitroPack.tags"][ "pageType:" . get_nitropack()->getPageType()] = 1;

					/* The following if statement should stay as it is written.
					 * is_archive() can return true if visiting a tax, category or tag page, so is_acrchive must be checked last
					 */
					if ( is_tax() || is_category() || is_tag() ) {
						$np_queriedObj = get_queried_object();
						$GLOBALS["NitroPack.tags"][ "tax:" . $np_queriedObj->term_taxonomy_id ] = 1;
					} else {
						if ( is_single() || is_page() || is_attachment() ) {
							$singlePost = get_post();
							if ( $singlePost ) {
								$GLOBALS["NitroPack.tags"][ "single:" . $singlePost->ID ] = 1;
							}
						}
					}

					// Uncomment the code below in case object cache interferes with correct URL taggig
					// The code below will attempt to temporarily disable using the object cache only for the requests coming from NitroPack
					//wp_using_ext_object_cache(false);
					//add_action("pre_get_posts", function($query) {
					//    $query->query_vars["cache_results"] = false;
					//});
					//
					//add_filter("all", function() {
					//    $args = func_get_args();
					//    if (count($args) > 1) {
					//        list($filterName, $value) = func_get_args();
					//        if (preg_match("/^transient_(.*)/", $filterName, $matches) && $value) {
					//            return false;
					//        }
					//    }
					//}, 10, 2);

					add_filter( 'post_link', 'nitropack_post_link_listener', 10, 2 );
					add_action( 'the_post', 'nitropack_handle_the_post' );
					add_action( 'wp_footer', 'nitropack_log_tags' );
				}
			} else {
				nitropack_header( "X-Nitro-Disabled: 1" );
				if ( ( null !== $nitro = get_nitropack_sdk() ) && ! $nitro->isAllowedBrowser() ) { // This clears any proxy cache when a proxy cached non-optimized request due to unsupported browser
					add_action( 'wp_footer', 'nitropack_print_beacon_script' );
					add_action( 'get_footer', 'nitropack_print_beacon_script' );
				}
			}

			if ( ! Utils::is_optimizer_nitropack_request() ) {
				add_action( 'wp_head', 'nitropack_print_generic_nitro_script' );
				add_action( 'wp_head', 'nitropack_print_web_vitals_telemetry_script' );
			}
		}
	}
}

/**
 * Handle Fusion Builder container expiration by checking the publish date and status attributes.
 *
 * @param string $output The output of the shortcode.
 * @param string $tag The shortcode tag.
 * @param array $attr The shortcode attributes.
 * @return string The output of the shortcode.
 */
function nitropack_handle_fusion_builder_conatainer_expiration( string $output, string $tag, array $attr ) {
	global $np_customExpirationTimes;
	if ( $tag == "fusion_builder_container" ) {
		if ( ! empty( $attr["publish_date"] ) && ! empty( $attr["status"] ) && in_array( $attr["status"], array( "published_until", "publish_after" ) ) ) {
			$timezone = get_option( 'timezone_string' );
			$offset = get_option( 'gmt_offset' );
			$dt = new DateTime( $attr["publish_date"] );
			if ( $timezone ) {
				$timeZone = new DateTimeZone( $timezone );
				$timeZoneOffset = $timeZone->getOffset( $dt );
			} else if ( $offset ) {
				$timeZoneOffset = (int) $offset * 3600;
			}
			$time = $dt->getTimestamp() - $timeZoneOffset;
			if ( $time > time() ) { // We only need to look at future dates
				$np_customExpirationTimes[] = $time;
			}
		}
	}
	return $output;
}

/**
 * Set custom expiration times for optimized posts based on their scheduled publish dates.
 * This function checks for optimized custom post types (CPTs) and adds their scheduled publish dates to the expiration times.
 * @return void
 */
function nitropack_set_custom_expiration() {
	//check which CPTs are marked for optimization
	$CPTOptimization = NitroPack\WordPress\Settings\CPTOptimization::getInstance();
	$get_optimized_CPTs = $CPTOptimization->nitropack_get_optimized_CPTs();
	if ( empty( $get_optimized_CPTs ) ) {
		return;
	}

	global $np_customExpirationTimes, $wpdb;

	$placeholders = implode( ',', array_fill( 0, count( $get_optimized_CPTs ), '%s' ) );
	$currentDate = date( "Y-m-d H:i:s" );

	//For better security, we use prepared statements
	$unmodifiedPosts_query = $wpdb->prepare(
		"SELECT ID, post_date 
        FROM {$wpdb->prefix}posts 
        WHERE {$wpdb->prefix}posts.post_date > %s
        AND {$wpdb->prefix}posts.post_type IN ($placeholders)
        AND ({$wpdb->prefix}posts.post_status = 'future')
        ORDER BY {$wpdb->prefix}posts.post_date ASC 
        LIMIT 0, 1",
		array_merge( [ $currentDate ], $get_optimized_CPTs )
	);
	$unmodifiedPosts = $wpdb->get_results( $unmodifiedPosts_query );

	if ( ! empty( $unmodifiedPosts ) && strtotime( $unmodifiedPosts[0]->post_date ) > time() ) {
		$scheduled_post = get_post( $unmodifiedPosts[0]->ID );

		// We will check relatedness only if a proper option is set in wp-config.php
		$check_relatedness = defined( "NITROPACK_SCHEDULED_POST_EXPIRATION_CHECK_RELATEDNESS" ) && NITROPACK_SCHEDULED_POST_EXPIRATION_CHECK_RELATEDNESS;

		// We'll add the expiration time only if the current page is related to the scheduled post or if we don't need to check relatedness
		if ( ! $check_relatedness || nitropack_is_page_related_to_scheduled_post( $scheduled_post ) ) {
			$np_customExpirationTimes[] = strtotime( $unmodifiedPosts[0]->post_date );
		}
	}

	if ( ! empty( $np_customExpirationTimes ) ) {
		sort( $np_customExpirationTimes, SORT_NUMERIC );
		nitropack_header( "X-Nitro-Expires: " . $np_customExpirationTimes[0] );
	}
}


/**
 * Determine if the current page is related to the scheduled post
 * This is a best-effort approach and may not cover all cases
 * @param \WP_Post $scheduled_post The scheduled post object.
 * @return bool True if the current page is related to the scheduled post, false otherwise.
 */
function nitropack_is_page_related_to_scheduled_post( \WP_Post $scheduled_post ) {
	$current_layout = Utils::get_page_type();
	$postType = $scheduled_post->post_type;
	$CPTOptimization = NitroPack\WordPress\Settings\CPTOptimization::getInstance();
	$cacheableObjectTypes = $CPTOptimization->nitropack_get_cacheable_object_types();

	// Only proceed if post type is cacheable 
	if ( ! in_array( $postType, $cacheableObjectTypes ) ) {
		return false;
	}

	// Check if current home page would be invalidated
	if ( $current_layout === 'home' ) {
		return true;
	}

	// Check if current blog index page would be invalidated
	if ( $postType === 'post' && $current_layout === 'blogindex' ) {
		return true;
	}

	if ( is_post_type_archive( $postType ) ) {
		return true;
	}

	// Check if current single post/page would be invalidated
	if ( is_singular( $postType ) ) {
		return true;
	}

	// Default to false for other page types
	return false;
}

/**
 * Checks if the config.json is up to date.
 * @return bool True if the configuration is up to date, false otherwise.
 */
function nitropack_is_config_up_to_date() {
	$siteConfig = nitropack_get_site_config();
	return ! empty( $siteConfig ) && ! empty( $siteConfig["pluginVersion"] ) && $siteConfig["pluginVersion"] == NITROPACK_VERSION;
}

/**
 * Filters out cookies that were not present in the original request.
 *
 * @param array $cookies The array of cookies to filter.
 */
function nitropack_filter_non_original_cookies( &$cookies ) {
	global $np_originalRequestCookies;
	$ogNames = is_array( $np_originalRequestCookies ) ? array_keys( $np_originalRequestCookies ) : array();
	foreach ( $cookies as $name => $val ) {
		if ( ! in_array( $name, $ogNames ) ) {
			unset( $cookies[ $name ] );
		}
	}
}

/**
 * Get the NitroPack SDK instance.
 *
 * @param string|null $siteId Optional. The site ID for the NitroPack SDK. If not provided, it will be retrieved from the site configuration.
 * @param string|null $siteSecret Optional. The site secret for the NitroPack SDK. If not provided, it will be retrieved from the site configuration.
 * @param string|null $urlOverride Optional. A URL to override the default URL used by the NitroPack SDK.
 * @param bool $forwardExceptions Optional. Whether to forward exceptions thrown by the NitroPack SDK. Default is false.
 * @return \NitroPack\SDK\NitroPack|null The NitroPack SDK instance or null if it cannot be created.
 */
function get_nitropack_sdk( $siteId = null, $siteSecret = null, $urlOverride = null, $forwardExceptions = false ) {
	return get_nitropack()->getSdk( $siteId, $siteSecret, $urlOverride, $forwardExceptions );
}

/**
 * Get the integration URL for a specific integration.
 *
 * @param string $integration The name of the integration.
 * @param \NitroPack\SDK\NitroPack|null $nitro Optional. The NitroPack SDK instance. If not provided, it will be retrieved using get_nitropack_sdk().
 * @return string The integration URL or "#" if the NitroPack SDK is not available.
 */
function get_nitropack_integration_url( string $integration, $nitro = null ) {
	if ( $nitro || ( null !== $nitro = get_nitropack_sdk() ) ) {
		return $nitro->integrationUrl( $integration );
	}

	return "#";
}

/**
 * Invalidate the NitroPack cache for a specific URL or tag.
 *
 * @param string|null $url Optional. The URL to invalidate. If not provided, all URLs will be invalidated.
 * @param string|array|null $tag Optional. The tag(s) to invalidate. If not provided, all tags will be invalidated.
 * @param string|null $reason Optional. The reason for the invalidation.
 * @return bool True if the invalidation was successful, false otherwise.
 */
function nitropack_sdk_invalidate( ?string $url = null, $tag = null, ?string $reason = null ) {

	$status = false;

	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			$siteConfig = nitropack_get_site_config();
			$homeUrl = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();

			if ( $tag ) {
				if ( is_array( $tag ) ) {
					$tag = array_map( 'nitropack_filter_tag', $tag );
				} else {
					$tag = nitropack_filter_tag( $tag );
				}
			}

			$nitro->invalidateCache( $url, $tag, $reason );

			try {

				if ( defined( 'NITROPACK_DEBUG_MODE' ) ) {
					do_action( 'nitropack_debug_invalidate', $url, $tag, $reason );
				}

				do_action( 'nitropack_integration_purge_url', $homeUrl );

				if ( $tag ) {
					do_action( 'nitropack_integration_purge_all' );
				} else if ( $url ) {
					do_action( 'nitropack_integration_purge_url', $url );
				} else {
					do_action( 'nitropack_integration_purge_all' );
				}
			} catch (\Exception $e) {
				// Exception while signaling 3rd party integration addons to purge their cache
			}
		} catch (\Exception $e) {
			$status = false;
		}

		$status = true;
	}

	return $status;
}

/* Start Heartbeat Related Functions */
function is_valid_nitropack_heartbeat() {
	return ! empty( $_POST['nitroHeartbeat'] );
}

function nitropack_get_heartbeat_file() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		return nitropack_trailingslashit( $nitro->getCacheDir() ) . "heartbeat";
	} else {
		return nitropack_trailingslashit( NITROPACK_DATA_DIR ) . "heartbeat";
	}
}

function nitropack_last_heartbeat() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			return \NitroPack\SDK\Filesystem::fileMTime( nitropack_get_heartbeat_file() );
		} catch (\Exception $e) {
			return 0;
		}
	}
}

function nitropack_is_heartbeat_running() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			$heartbeatContent = \NitroPack\SDK\Filesystem::fileGetContents( nitropack_get_heartbeat_file() );
			if ( $heartbeatContent == "1" ) {
				return time() - nitropack_last_heartbeat() < NITROPACK_HEARTBEAT_INTERVAL;
			}
		} catch (\Exception $e) {
			return false;
		}
	}
}

function nitropack_is_heartbeat_completed() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			$heartbeatContent = \NitroPack\SDK\Filesystem::fileGetContents( nitropack_get_heartbeat_file() );
			return $heartbeatContent == "0"; // 0 - Job Done, 1 - Job Running, 2 - Job Needs Repeat
		} catch (\Exception $e) {
			return true;
		}
	}
}
/**
 * Heartbeat - trigger service and house keeping tasks
 * @return bool
 */
function nitropack_handle_heartbeat() {

	if ( nitropack_is_heartbeat_running() ) {
		return;
	}

	session_write_close();
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			$success = true;
			\NitroPack\SDK\Filesystem::filePutContents( nitropack_get_heartbeat_file(), 1 );
			if ( Utils::nitropack_health_check() ) {
				$success &= nitropack_flush_backlog();
			}
			$success &= nitropack_cache_cleanup();

			if ( $success ) {
				\NitroPack\SDK\Filesystem::filePutContents( nitropack_get_heartbeat_file(), 0 );
			} else {
				\NitroPack\SDK\Filesystem::filePutContents( nitropack_get_heartbeat_file(), 2 );
			}
		} catch (\Exception $e) {
			return false;
		}
	}
	exit;
}

function nitropack_flush_backlog() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			if ( $nitro->backlog->exists() ) {
				return $nitro->backlog->replay( 30 );
			}
		} catch (\NitroPack\SDK\BacklogReplayTimeoutException $e) {
			$nitro->backlog->delete();
			return nitropack_sdk_purge( null, null, "Full purge after backlog timeout" );
		} catch (\Exception $e) {
			return false;
		}
	}
	return true;
}

function nitropack_cache_cleanup() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		$cacheDirParent = dirname( $nitro->getCacheDir() );
		$entries = scandir( $cacheDirParent );
		foreach ( $entries as $entry ) {
			if ( strpos( $entry, ".stale." ) !== false ) {
				$cacheDir = nitropack_trailingslashit( $cacheDirParent ) . $entry;
				try {
					Filesystem::deleteDir( $cacheDir );
				} catch (\Exception $e) {
					return false;
				}
			}
		}
	}
	return true;
}
/* End Heartbeat Related Functions */

/* Start NitroPack SDK Purges and Invalidations */

/**
 * @param string|null $url URL to purge. If null, all URLs will be purged.
 * @param string|array|null $tag Related pages (tags) to purge. If null, all tags will be purged.
 * @param string|null $reason Reason for the purge, for logging purposes.
 * @param int $type Type of purge, using \NitroPack\SDK\PurgeType constants.
 * @return bool
 */
function nitropack_sdk_purge( $url = null, $tag = null, $reason = null, $type = \NitroPack\SDK\PurgeType::COMPLETE ) {

	$status = false;

	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			$siteConfig = nitropack_get_site_config();
			$homeUrl = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();

			if ( $tag ) {
				if ( is_array( $tag ) ) {
					$tag = array_map( 'nitropack_filter_tag', $tag );
				} else {
					$tag = nitropack_filter_tag( $tag );
				}
			}

			if ( ! $url && ! $tag ) {
				$nitro->purgeLocalCache( true );
			}

			$nitro->purgeCache( $url, $tag, $type, $reason );

			if ( defined( 'NITROPACK_DEBUG_MODE' ) ) {
				do_action( 'nitropack_debug_purge', $url, $tag, $reason );
			}

			try {
				do_action( 'nitropack_integration_purge_url', $homeUrl );

				if ( $tag ) {
					do_action( 'nitropack_integration_purge_all' );
				} else if ( $url ) {
					do_action( 'nitropack_integration_purge_url', $url );
				} else {
					do_action( 'nitropack_integration_purge_all' );
				}
			} catch (\Exception $e) {
				// Exception while signaling 3rd party integration addons to purge their cache
			}
		} catch (\Exception $e) {
			$status = false;
		}

		$status = true;
	}

	return $status;
}

/**
 * @param string|null $url
 * @return bool
 */
function nitropack_sdk_purge_local( $url = null ) {
	if ( null === $nitro = get_nitropack_sdk() ) {
		return false;
	}

	try {
		if ( $url ) {
			$nitro->purgeLocalUrlCache( $url );
			do_action( 'nitropack_integration_purge_url', $url );
			return true;
		}

		$nitro->purgeLocalCache( true );

		try {
			do_action( 'nitropack_integration_purge_all' );
		} catch (\Exception $e) {
			// Exception while signaling our 3rd party integration addons to purge their cache
		}

		return true;
	} catch (\Exception $e) {
		return false;
	}
}

/**
 * @param string|null $url
 * @return bool
 */
function nitropack_sdk_invalidate_local( $url = null ) {
	if ( null === $nitro = get_nitropack_sdk() ) {
		return false;
	}

	try {
		if ( $url ) {
			$nitro->invalidateLocalUrlCache( $url );
			do_action( 'nitropack_integration_purge_url', $url );
			return true;
		}

		$nitro->invalidateLocalCache( true );

		try {
			do_action( 'nitropack_integration_purge_all' );
		} catch (\Exception $e) {
			// Exception while signaling our 3rd party integration addons to purge their cache
		}

		return true;
	} catch (\Exception $e) {
		return false;
	}
}

function nitropack_sdk_delete_backlog() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			if ( $nitro->backlog->exists() ) {
				$nitro->backlog->delete();
			}
		} catch (\Exception $e) {
			return false;
		}

		return true;
	}

	return false;
}

function nitropack_purge( $url = null, $tag = null, $reason = null ) {
	if ( $tag != "pageType:home" ) {
		$siteConfig = nitropack_get_site_config();
		$homeUrl = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();
		nitropack_log_invalidate( $homeUrl, "pageType:home", $reason );
	}

	if ( $tag != "pageType:archive" ) {
		nitropack_log_invalidate( null, "pageType:archive", $reason );
	}

	nitropack_log_purge( $url, $tag, $reason );
}

function nitropack_log_purge( $url = null, $tag = null, $reason = null ) {
	global $np_loggedPurges;
	if ( $tag && is_array( $tag ) ) {
		foreach ( $tag as $tagSingle ) {
			nitropack_log_purge( $url, $tagSingle, $reason );
		}
		return;
	}

	$keyBase = "";
	if ( $url ) {
		$keyBase .= $url;
	}

	if ( $tag ) {
		$tag = nitropack_filter_tag( $tag );
		$keyBase .= $tag;
	}

	$purgeRequestKey = md5( $keyBase );
	if ( is_array( $np_loggedPurges ) && array_key_exists( $purgeRequestKey, $np_loggedPurges ) ) {
		$np_loggedPurges[ $purgeRequestKey ]["reason"] = $reason;
		$np_loggedPurges[ $purgeRequestKey ]["priority"]++;
	} else {
		$np_loggedPurges[ $purgeRequestKey ] = array(
			"url" => $url,
			"tag" => $tag,
			"reason" => $reason,
			"priority" => 1
		);
	}
}
/**
 * Simple global function to invalidate a page
 * @param string|null $url
 * @param string|null $tag
 * @param string|null $reason
 * @return void
 */
function nitropack_invalidate( ?string $url = null, ?string $tag = null, ?string $reason = null ) {
	if ( $tag != "pageType:home" ) {
		$siteConfig = nitropack_get_site_config();
		$homeUrl = $siteConfig && ! empty( $siteConfig["home_url"] ) ? $siteConfig["home_url"] : get_home_url();
		nitropack_log_invalidate( $homeUrl, "pageType:home", $reason );
	}

	if ( $tag != "pageType:archive" ) {
		nitropack_log_invalidate( null, "pageType:archive", $reason );
	}

	nitropack_log_invalidate( $url, $tag, $reason );
}

function nitropack_log_invalidate( ?string $url = null, ?string $tag = null, ?string $reason = null ) {
	global $np_loggedInvalidations;
	if ( $tag && is_array( $tag ) ) {
		foreach ( $tag as $tagSingle ) {
			nitropack_log_invalidate( $url, $tagSingle, $reason );
		}
		return;
	}

	$keyBase = "";
	if ( $url ) {
		$keyBase .= $url;
	}

	if ( $tag ) {
		$tag = nitropack_filter_tag( $tag );
		$keyBase .= $tag;
	}

	$invalidateRequestKey = md5( $keyBase );
	if ( is_array( $np_loggedInvalidations ) && array_key_exists( $invalidateRequestKey, $np_loggedInvalidations ) ) {
		$np_loggedInvalidations[ $invalidateRequestKey ]["reason"] = $reason;
		$np_loggedInvalidations[ $invalidateRequestKey ]["priority"]++;
	} else {
		$np_loggedInvalidations[ $invalidateRequestKey ] = array(
			"url" => $url,
			"tag" => $tag,
			"reason" => $reason,
			"priority" => 1
		);
	}
}

/* End NitroPack SDK Purges and Invalidations */

/**
 * Fetch the latest config stored in the cache folder from the NitroPack.
 * @return void
 */
function nitropack_fetch_config() {
	if ( null !== $nitro = get_nitropack_sdk() ) {
		try {
			$nitro->fetchConfig();
		} catch (\Exception $e) {
		}
	}
}

/**
 * This function is used to send a JSON response and terminate an AJAX request.
 * @param array $array
 * @return never
 */
function nitropack_json_and_exit( array $array ) {
	if ( Utils::is_wp_cli() ) {
		$type = null;
		if ( array_key_exists( "status", $array ) ) {
			$type = $array["status"];
		} else if ( array_key_exists( "type", $array ) ) {
			$type = $array["type"];
		}

		if ( $type && array_key_exists( "message", $array ) ) {
			if ( $type == "success" ) {
				WP_CLI::success( $array["message"] );
			} else {
				WP_CLI::error( $array["message"] );
			}
		}
	} else {
		echo json_encode( $array );
	}
	exit;
}

/**
 * Returns the toast message for the admin panel based on the type
 * @param string $type The type of the message (success or error)
 * @return string The toast message
 */
function nitropack_admin_toast_msgs( string $type ) {
	if ( $type === 'success' ) {
		$msg = esc_html__( 'Settings updated.', 'nitropack' );
	} else {
		$msg = esc_html__( 'Something went wrong.', 'nitropack' );
	}
	return $msg;
}

/**
 * General verification for AJAX requests
 * @param array $request_data The request data
 * @param array|null $allowed_roles The allowed user roles
 */
function nitropack_verify_ajax_nonce( $request_data, $allowed_roles = null ) {
	// If not an ajax request
	if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
		return;
	}

	// Check if WordPress functions are available
	if ( ! function_exists( 'wp_verify_nonce' ) || ! function_exists( 'wp_die' ) || ! function_exists( 'current_user_can' ) ) {
		return;
	}

	// If nonce fails verification
	if ( empty( $request_data['nonce'] ) || ! wp_verify_nonce( $request_data['nonce'], NITROPACK_NONCE ) ) {
		wp_die( 'Unauthorized request' );
	}

	// Check user permissions
	if ( $allowed_roles ) {
		$has_permission = false;
		foreach ( $allowed_roles as $role ) {
			if ( current_user_can( $role ) ) {
				$has_permission = true;
				break;
			}
		}
		if ( ! $has_permission ) {
			wp_die( 'Unauthorized request' );
		}
	} else {
		//fallback to admin rights
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized request' );
		}
	}
}

/**
 * Summary of nitropack_has_post_important_change
 * @param \WP_Post $post
 * @return bool
 **/
function nitropack_has_post_important_change( $post ) {
	$prevPost = nitropack_get_post_pre_update( $post );
	return $prevPost && ( $prevPost->post_title != $post->post_title || $prevPost->post_name != $post->post_name || $prevPost->post_excerpt != $post->post_excerpt );
}

/**
 * Main logic of invalidating the cache for a post and its related taxonomies.
 * Checks if the post type is cacheable, and if so, invalidates the cache for the post and its related taxonomies.
 * @param \WP_Post $post
 * @param array | null $taxonomies
 * @param bool | null $hasImportantChangeInPost
 * @param string | null $reason
 * @param bool $usePurge
 * @return void
 */
function nitropack_clean_post_cache( \WP_Post $post, $taxonomies = null, $hasImportantChangeInPost = null, $reason = null, $usePurge = false ) {
	try {
		$postID = $post->ID;
		$postType = isset( $post->post_type ) ? $post->post_type : "post";
		$nicePostTypeLabel = nitropack_get_nice_post_type_label( $postType );
		$reason = $reason ? $reason : sprintf( "Updated %s '%s'", $nicePostTypeLabel, $post->post_title );
		$CPTOptimization = NitroPack\WordPress\Settings\CPTOptimization::getInstance();
		$cacheableObjectTypes = $CPTOptimization->nitropack_get_cacheable_object_types();

		if ( in_array( $postType, $cacheableObjectTypes ) ) {
			if ( $usePurge ) {
				// We only purge the single pages because they have to immediately stop serving cache
				// These pages no longer exists and if their URL is requested we must not server cache
				nitropack_purge( null, "single:$postID", $reason );
			} else {
				nitropack_invalidate( null, "single:$postID", $reason );
			}

			nitropack_invalidate( null, "post:$postID", $reason );

			if ( $hasImportantChangeInPost === null ) {
				$hasImportantChangeInPost = nitropack_has_post_important_change( $post );
			}
			if ( $taxonomies === null ) {
				if ( $hasImportantChangeInPost ) { // This change should be reflected in all taxonomy pages
					$taxonomies = array( 'related' => nitropack_get_taxonomies( $post ) );
				} else { // No important change, so only update taxonomy pages which have been added or removed from the post
					$taxonomies = nitropack_get_taxonomies_for_update( $post );
				}
			}
			if ( $taxonomies ) {
				if ( ! empty( $taxonomies['added'] ) ) { // taxonomies that the post was just added to, must purge all pages for these taxonomies
					foreach ( $taxonomies['added'] as $term_taxonomy_id ) {
						nitropack_invalidate( null, "tax:$term_taxonomy_id", $reason );
					}
				}
				if ( ! empty( $taxonomies['deleted'] ) ) { // taxonomy pages that the post was just removed from (also accounts for paginations via the taxpost: tag instead of only tax:)
					foreach ( $taxonomies['deleted'] as $term_taxonomy_id ) {
						nitropack_invalidate( null, "taxpost:$term_taxonomy_id:$postID", $reason );
					}
				}
				if ( ! empty( $taxonomies['related'] ) ) { // taxonomy pages that the post is linked to (also accounts for paginations via the taxpost: tag instead of only tax:)
					foreach ( $taxonomies['related'] as $term_taxonomy_id ) {
						nitropack_invalidate( null, "taxpost:$term_taxonomy_id:$postID", $reason );
					}
				}
			}
		} else {
			if ( $post->public ) {
				nitropack_invalidate( null, "post:$postID", $reason );
			}

			$posts = get_post_ancestors( $postID );
			foreach ( $posts as $parentID ) {
				$parent = get_post( $parentID );
				nitropack_clean_post_cache( $parent, false, false, $reason );
			}
		}
	} catch (\Exception $e) {
	}
}

/**
 * Formats the post type into a nice label
 * @param string $postType
 */
function nitropack_get_nice_post_type_label( string $postType ) {
	$postTypes = get_post_types( array(
		"name" => $postType
	), "objects" );

	return ! empty( $postTypes[ $postType ] ) && ! empty( $postTypes[ $postType ]->labels ) ? $postTypes[ $postType ]->labels->singular_name : $postType;
}

/**
 * Detects changes in the post and cleans the post cache if necessary.
 * Compares the post before update and after update, and if there are any changes,
 * it cleans the cache for the post and its related taxonomies.
 * @param \WP_Post $post
 * @return void
 */
function nitropack_detect_changes_and_clean_post_cache( \WP_Post $post ) {
	if ( ! get_option( "nitropack-autoCachePurge", 1 ) ) {
		return;
	}

	$post_before = nitropack_get_post_pre_update( $post );
	$ignoredComparisonKeys = array( 'post_modified', 'post_modified_gmt' );
	$canCleanPostCache = false;
	$postStatesEqual = nitropack_compare_posts( (array) $post_before, (array) $post, $ignoredComparisonKeys );

	if ( $postStatesEqual ) {
		$taxCurrent = nitropack_get_taxonomies( $post );
		$taxPreUpdate = nitropack_get_taxonomies_pre_update( $post );
		$taxAreEqual = nitropack_compare_posts( $taxCurrent, $taxPreUpdate );
		if ( $taxAreEqual ) {
			$metaCurrent = get_post_meta( $post->ID );
			$metaPreUpdate = nitropack_get_meta_pre_update( $post );
			$metaIsEqual = nitropack_compare_posts( $metaCurrent, $metaPreUpdate );
			if ( ! $metaIsEqual ) {
				$canCleanPostCache = true;
			}
		} else {
			$canCleanPostCache = true;
		}
	} else {
		$canCleanPostCache = true;
	}

	if ( $canCleanPostCache ) {
		\NitroPack\WordPress\NitroPack::$np_loggedWarmups[] = get_permalink( $post );
		nitropack_clean_post_cache( $post );
		define( 'NITROPACK_PURGE_CACHE', true );
	}
}

/**
 * Used only in is_optimizer_nitropack_request(), we handle the post and add the tags for the post and its author.
 * @param string $permalink
 * @param \WP_Post $post
 * @return string
 */
function nitropack_post_link_listener( string $permalink, \WP_Post $post ) {
	if ( is_object( $post ) ) {
		nitropack_handle_the_post( $post );
	}

	return $permalink;
}

/**
 * Used only in is_optimizer_nitropack_request()
 * @param \WP_Post $post
 * @return void
 */
function nitropack_handle_the_post( \WP_Post $post ) {
	global $np_customExpirationTimes, $np_queriedObj;
	if ( defined( 'POSTEXPIRATOR_VERSION' ) ) {
		$postExpiryDate = get_post_meta( $post->ID, "_expiration-date", true );
		if ( ! empty( $postExpiryDate ) && $postExpiryDate > time() ) { // We only need to look at future dates
			$np_customExpirationTimes[] = $postExpiryDate;
		}
	}

	if ( function_exists( "sort_portfolio" ) ) { // Portfolio Sorting plugin
		$portfolioStartDate = get_post_meta( $post->ID, "start_date", true );
		$portfolioEndDate = get_post_meta( $post->ID, "end_date", true );
		if ( ! empty( $portfolioStartDate ) && strtotime( $portfolioStartDate ) > time() ) { // We only need to look at future dates
			$np_customExpirationTimes[] = strtotime( $portfolioStartDate );
		} elseif ( ! empty( $portfolioEndDate ) && strtotime( $portfolioEndDate ) > time() ) { // We only need to look at future dates
			$np_customExpirationTimes[] = strtotime( $portfolioEndDate );
		}
	}

	$GLOBALS["NitroPack.tags"][ "post:" . $post->ID ] = 1;
	$GLOBALS["NitroPack.tags"][ "author:" . $post->post_author ] = 1;
	if ( $np_queriedObj ) {
		$GLOBALS["NitroPack.tags"][ "taxpost:" . $np_queriedObj->term_taxonomy_id . ":" . $post->ID ] = 1;
	}
}

/**
 * Grabs the taxonomies for a given post and returns an array of term_taxonomy_ids
 * @param WP_Post $post
 * @return array of term_taxonomy_ids
 */
function nitropack_get_taxonomies( \WP_Post $post ) {
	$term_taxonomy_ids = array();
	$taxonomies = get_object_taxonomies( $post->post_type );
	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_the_terms( $post->ID, $taxonomy );
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_taxonomy_ids[] = $term->term_taxonomy_id;
			}
		}
	}
	return $term_taxonomy_ids;
}

/**
 * Grabs the taxonomies for a given post and returns an array of term_taxonomy_ids that have been added or deleted since the last update
 * @param WP_Post $post
 * @return array{added: array, deleted: array}
 */
function nitropack_get_taxonomies_for_update( \WP_Post $post ) {
	$prevTaxonomies = nitropack_get_taxonomies_pre_update( $post );
	$newTaxonomies = nitropack_get_taxonomies( $post );
	$intersection = array_intersect( $newTaxonomies, $prevTaxonomies );
	$prevTaxonomies = array_diff( $prevTaxonomies, $intersection );
	$newTaxonomies = array_diff( $newTaxonomies, $intersection );
	return array(
		"added" => array_diff( $newTaxonomies, $prevTaxonomies ),
		"deleted" => array_diff( $prevTaxonomies, $newTaxonomies )
	);
}

/**
 * Returns the post before it was updated, or null if it wasn't logged.
 * @param WP_Post $post
 * @return WP_Post|null
 */
function nitropack_get_post_pre_update( \WP_Post $post ) {
	return ! empty( \NitroPack\WordPress\NitroPack::$preUpdatePosts[ $post->ID ] ) ? \NitroPack\WordPress\NitroPack::$preUpdatePosts[ $post->ID ] : null;
}

/**
 * Returns the taxonomies for a given post before it was updated, or an empty array if they weren't logged.
 * @param WP_Post $post
 * @return array of term_taxonomy_ids
 */
function nitropack_get_taxonomies_pre_update( \WP_Post $post ) {
	return ! empty( \NitroPack\WordPress\NitroPack::$preUpdateTaxonomies[ $post->ID ] ) ? \NitroPack\WordPress\NitroPack::$preUpdateTaxonomies[ $post->ID ] : array();
}

/**
 * Returns the post meta for a given post before it was updated, or an empty array if it wasn't logged.
 * @param WP_Post $post
 * @return array of post meta
 */
function nitropack_get_meta_pre_update( \WP_Post $post ) {
	return ! empty( \NitroPack\WordPress\NitroPack::$preUpdateMeta[ $post->ID ] ) ? \NitroPack\WordPress\NitroPack::$preUpdateMeta[ $post->ID ] : array();
}

/**
 * Compare two posts or one post and its associated data - post_meta or taxonomies
 * Return true if they are equal, false otherwise.
 * @param array $p1
 * @param array $p2
 * @param array|null $ignoredKeys
 * @return bool
 */
function nitropack_compare_posts( array $p1, array $p2, ?array $ignoredKeys = null ) {
	$p1keys = array_keys( $p1 );
	$p2keys = array_keys( $p2 );
	if ( count( $p1keys ) !== count( $p2keys ) ) {
		return false;
	}
	if ( array_diff( $p1keys, $p2keys ) ) {
		return false;
	}

	$isP1assoc = false;
	$expectedKey = 0;
	foreach ( $p2 as $i => $_ ) {
		if ( $i !== $expectedKey ) {
			$isP1assoc = true;
		}
		$expectedKey++;
	}

	$isP2assoc = false;
	$expectedKey = 0;
	foreach ( $p2 as $i => $_ ) {
		if ( $i !== $expectedKey ) {
			$isP2assoc = true;
		}
		$expectedKey++;
	}

	if ( $isP1assoc !== $isP2assoc ) {
		return false;
	}

	if ( ! $isP1assoc && ! $isP2assoc ) {
		sort( $p1 );
		sort( $p2 );
	}

	foreach ( $p1 as $poKey => $poVal ) {
		if ( $ignoredKeys && in_array( $poKey, $ignoredKeys, true ) ) {
			continue;
		}
		$checkpoint01 = is_array( $poVal );
		$checkpoint02 = is_array( $p2[ $poKey ] );
		if ( $checkpoint01 && $checkpoint02 ) {
			if ( ! nitropack_compare_posts( $poVal, $p2[ $poKey ], $ignoredKeys ) ) {
				return false;
			}
		} elseif ( ! $checkpoint01 && ! $checkpoint02 ) {
			if ( $poVal != $p2[ $poKey ] ) {
				return false;
			}
		} else {
			return false;
		}
	}
	return true;
}

/**
 * Filters a NitroPack tag.
 * @param string $tag
 * @return array|string|null
 */
function nitropack_filter_tag( string $tag ) {
	return preg_replace( "/[^a-zA-Z0-9:]/", ":", $tag );
}

/**
 * Logs the NitroPack tags for the current request.
 * @return void
 */
function nitropack_log_tags() {
	if ( ! empty( $GLOBALS["NitroPack.instance"] ) && ! empty( $GLOBALS["NitroPack.tags"] ) ) {
		$nitro = $GLOBALS["NitroPack.instance"];
		$layout = Utils::get_page_type();
		try {
			$config = $nitro->getConfig();
			$useHeader = ! empty( $config->TagsViaHeader );

			if ( $layout == "home" ) {
				if ( $useHeader ) {
					nitropack_header( "x-nitro-tags:pageType:home" );
				} else {
					$nitro->getApi()->tagUrl( $nitro->getUrl(), "pageType:home" );
				}
			} elseif ( $layout == "archive" ) {
				if ( $useHeader ) {
					nitropack_header( "x-nitro-tags:pageType:archive" );
				} else {
					$nitro->getApi()->tagUrl( $nitro->getUrl(), "pageType:archive" );
				}
			} else {
				if ( $useHeader && count( $GLOBALS["NitroPack.tags"] ) <= 100 ) {
					nitropack_header( "x-nitro-tags:" . implode( "|", array_map( "nitropack_filter_tag", array_keys( $GLOBALS["NitroPack.tags"] ) ) ) );
				} else {
					$nitro->getApi()->tagUrl( $nitro->getUrl(), array_map( "nitropack_filter_tag", array_keys( $GLOBALS["NitroPack.tags"] ) ) );
				}
			}
		} catch (\Exception $e) {
		}
	}
}

/**
 * Extends the life of a nonce based on NitroPack setting -> Page Cache -> Expire Time.
 * Special exception for REST requests that are not cacheable, in which case we do not extend the nonce life.
 * @param int $life The current nonce life.
 * @return int The extended nonce life.
 */
function nitropack_extend_nonce_life( $life ) {
	if ( Utils::is_rest() && isset( $_COOKIE["nitroCachedPage"] ) && $_COOKIE["nitroCachedPage"] == "0" ) {
		return $life;
	}

	// Nonce life should be extended only:
	//  - if NitroPack is connected for this site
	//  - if the current value is shorter than the life time of a cache file
	//  - if no user is logged in
	//  - for cacheable requests
	//
	// Reasons why we might need to extend the nonce life time even for requests that are not cacheable:
	//  - a request may be cachable at first, but become uncachable during changes at runtime or user actions on the page (example: log in via AJAX on a category page. Once logged in the page will not redirect, but if there is an infinite scroll it will stop working if we stop extending the nonce life time)
	//  - a request may seem cachable at first, but be determined uncachable during runtime (example: visit to a URL of a page whose post type does not match the enabled cacheable post types, or a cart, checkout page, etc.)

	if ( ( null !== $nitro = get_nitropack_sdk() ) ) {
		$siteConfig = nitropack_get_site_config();
		if ( $siteConfig && ! empty( $siteConfig["isDlmActive"] ) && ! empty( $siteConfig["dlm_downloading_url"] ) && ! empty( $siteConfig["dlm_download_endpoint"] ) ) {
			$currentUrl = $nitro->getUrl();
			if ( strpos( $currentUrl, $siteConfig["dlm_downloading_url"] ) !== false || strpos( $currentUrl, $siteConfig["dlm_download_endpoint"] ) !== false ) {
				// Do not modify the nonce times on pages of Download Monitor
				return $life;
			}
		}
		$cacheExpiration = $nitro->getConfig()->PageCache->ExpireTime;
		return $cacheExpiration > $life ? $cacheExpiration : $life; // Extend the life of cacheable nonces up to the cache expiration time if needed
	}
	return $life;
}

/**
 * Checks if the cart cache is active and available.
 * @return bool True if the cart cache is active and available, false otherwise.
 */
function nitropack_is_cart_cache_active() {
	$nitro = get_nitropack()->getSdk();
	if ( $nitro ) {
		$config = $nitro->getConfig();
		if ( ! empty( $config->StatefulCache->Status ) && ! empty( $config->StatefulCache->CartCache ) ) {
			return nitropack_is_cart_cache_available();
		}
	}
	return false;
}

/**
 * Checks if the cart cache is available.
 * @return bool True if the cart cache is available, false otherwise.
 */
function nitropack_is_cart_cache_available() {
	$nitro = get_nitropack()->getSdk();
	if ( $nitro ) {
		$config = $nitro->getConfig();
		if ( ! empty( $config->StatefulCache->isCartCacheAvailable ) ) {
			return true;
		}
	}
	return false;
}

function nitropack_get_site_config() {
	return get_nitropack()->getSiteConfig();
}

function nitropack_get_current_site_id() {

	$site_config = nitropack_get_site_config();

	if ( $site_config && isset( $site_config['siteId'] ) ) {
		return $site_config['siteId'];
	}
}

function get_nitropack() {
	return \NitroPack\WordPress\NitroPack::getInstance();
}

/**
 * Sends an event to the NitroPack integration server.
 * @param string $event The event name.
 * @param \NitroPack\SDK\NitroPack|null $nitro The NitroPack SDK instance.
 * @param array|null $additional_meta_data Additional metadata to send with the event.
 */
function nitropack_event( string $event, $nitro = null, $additional_meta_data = null ) {
	global $wp_version;

	try {
		$eventUrl = get_nitropack_integration_url( "extensionEvent", $nitro );
		$domain = ! empty( $_SERVER["HTTP_HOST"] ) ? $_SERVER["HTTP_HOST"] : "Unknown";


		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$platform = 'WooCommerce';
		} else {
			$platform = 'WordPress';
		}

		$query_data = array(
			'event' => $event,
			'platform' => $platform,
			'platform_version' => $wp_version,
			'nitropack_extension_version' => NITROPACK_VERSION,
			'additional_meta_data' => $additional_meta_data ? json_encode( $additional_meta_data ) : "{}",
			'domain' => $domain
		);

		$client = new NitroPack\HttpClient\HttpClient( $eventUrl . '&' . http_build_query( $query_data ) );
		$client->doNotDownload = true;
		$client->fetch();
	} catch (\Exception $e) {
	}
}

/**
 * Removes the NitroPack cache-busting parameter from the given content $_SERVER['REQUEST_URI'].
 * @param string $content The content from which to remove the cache-busting parameter.
 * @return string The content with the cache-busting parameter removed.
 */
function nitropack_removeCacheBustParam( string $content ) {
	$content = preg_replace( "/(\?|%26|&#0?38;|&#x0?26;|&(amp;)?)ignorenitro(%3D|=)[a-fA-F0-9]{32}(?!%26|&#0?38;|&#x0?26;|&(amp;)?)\/?/mu", "", $content );
	return preg_replace( "/(\?|%26|&#0?38;|&#x0?26;|&(amp;)?)ignorenitro(%3D|=)[a-fA-F0-9]{32}(%26|&#0?38;|&#x0?26;|&(amp;)?)/mu", "$1", $content );
}

function nitropack_handle_request( $servedFrom = "unknown" ) {

	global $np_integrationSetupEvent;

	if ( isset( $_GET["ignorenitro"] ) ) {
		unset( $_GET["ignorenitro"] );
	}

	if ( defined( "NITROPACK_STRIP_IGNORENITRO" ) && NITROPACK_STRIP_IGNORENITRO && $_SERVER['REQUEST_URI'] != '' ) {
		$_SERVER['REQUEST_URI'] = nitropack_removeCacheBustParam( $_SERVER['REQUEST_URI'] );
	}

	nitropack_header( 'Cache-Control: no-cache' );
	do_action( "nitropack_early_cache_headers" ); // Overrides the Cache-Control header on supported platforms
	$isManageWpRequest = ! empty( $_GET["mwprid"] );
	$isWpCli = Utils::is_wp_cli();

	if ( Filesystem::fileExists( NITROPACK_CONFIG_FILE ) && ! empty( $_SERVER["HTTP_HOST"] ) && ! empty( $_SERVER["REQUEST_URI"] ) && ! $isManageWpRequest && ! $isWpCli ) {
		try {
			$siteConfig = nitropack_get_site_config();
			if ( $siteConfig && null !== $nitro = get_nitropack_sdk( $siteConfig["siteId"], $siteConfig["siteSecret"] ) ) {
				$webhooks = new \NitroPack\WordPress\Webhooks();
				if ( $webhooks->is_valid_webhook() ) {
					$webhooks->handle_webhook();
				} elseif ( is_valid_nitropack_beacon() ) {
					nitropack_handle_beacon();
				} else if ( is_valid_nitropack_heartbeat() ) {
					nitropack_handle_heartbeat();
				} else {
					$GLOBALS["NitroPack.instance"] = $nitro;

					if ( nitropack_passes_cookie_requirements() || ( Utils::is_ajax() && ! empty( $_COOKIE["nitroCachedPage"] ) ) ) {
						// Check whether the current URL is cacheable
						// If this is an AJAX request, check whether the referer is cachable - this is needed for cases when NitroPack's "Enabled URLs" option is being used to whitelist certain URLs. 
						// If we are not checking the referer, the AJAX requests on these pages can fail.
						$urlToCheck = Utils::is_ajax() && ! empty( $_SERVER["HTTP_REFERER"] ) ? $_SERVER["HTTP_REFERER"] : $nitro->getUrl();
						if ( $nitro->isAllowedUrl( $urlToCheck ) ) {
							add_filter( 'nonce_life', 'nitropack_extend_nonce_life' );
						}
					}

					if ( nitropack_passes_cookie_requirements() && apply_filters( "nitropack_can_serve_cache", true ) ) {
						if ( $nitro->isCacheAllowed() ) {
							if ( ! Utils::is_ajax() ) {
								do_action( "nitropack_cacheable_cache_headers" );
							}

							// Handle corner cases where the URL contains multiple slashes
							if ( ! empty( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '//' ) !== false ) {
								$redirect_url = preg_replace( '#(?<!:)/{2,}#', '/', $nitro->getUrl() );
								header( 'HTTP/1.1 301 Moved Permanently' );
								header( 'Location: ' . $redirect_url );
								exit;
							}

							if ( ! empty( $siteConfig["compression"] ) ) {
								$nitro->enableCompression();
							}

							if ( $nitro->hasLocalCache() ) {
								// TODO: Make this work so we can provide the reverse proxies with this information $remainingTtl = $nitr->pageCache->getRemainingTtl();
								do_action( "nitropack_cachehit_cache_headers" ); // TODO: Pass the remaining TTL here
								$cacheControlOverride = defined( "NITROPACK_CACHE_CONTROL_OVERRIDE" ) ? NITROPACK_CACHE_CONTROL_OVERRIDE : null;
								if ( $cacheControlOverride ) {
									nitropack_header( 'Cache-Control: ' . $cacheControlOverride );
								}

								nitropack_header( 'X-Nitro-Cache: HIT' );
								nitropack_header( 'X-Nitro-Cache-From: ' . $servedFrom );
								$cjHandler = new \NitroPack\SDK\Utils\CjHandler( $nitro );
								$cjHandler->handleQueryParams();
								$nitro->pageCache->readfile();
								exit;
							} else {
								// We need the following if..else block to handle bot requests which will not be firing our beacon
								if ( Utils::is_warmup_nitropack_request() ) {
									if ( ! empty( $_SERVER["HTTP_ACCEPT_LANGUAGE"] ) ) {
										add_action( "init", function () use ($nitro) {
											$nitro->hasRemoteCache( "default" ); // Only ping the API letting our service know that this page must be cached.
											exit;
										}, 9999 );
										return; // We need to wait for a language plugin (if present) to redirect
									} else {
										$nitro->hasRemoteCache( "default" ); // Only ping the API letting our service know that this page must be cached.
										exit; // No need to continue handling this request. The response is not important.
									}
								} else if ( Utils::is_lighthouse_request() || Utils::is_gtmetrix_request() || Utils::is_pingdom_request() ) {
									$nitro->hasRemoteCache( "default" ); // Ping the API letting our service know that this page must be cached.
								}

								$nitro->pageCache->useInvalidated( true );
								if ( $nitro->hasLocalCache() ) {
									nitropack_header( 'X-Nitro-Cache: STALE' );
									nitropack_header( 'X-Nitro-Cache-From: ' . $servedFrom );
									$cjHandler = new \NitroPack\SDK\Utils\CjHandler( $nitro );
									$cjHandler->handleQueryParams();
									$nitro->pageCache->readfile();
									exit;
								} else {
									$nitro->pageCache->useInvalidated( false );
								}
							}
						}
					}
				}
			}
		} catch (\Exception $e) {
			// Do nothing, cache serving will be handled by nitropack_init
		}
	}
}

function nitropack_is_dropin_cache_allowed() {
	$siteConfig = nitropack_get_site_config();
	return $siteConfig && empty( $siteConfig["isEzoicActive"] );
}

/**
 * Sends an HTTP header if not running in WP Cron or WP CLI.
 * @param string $header The header string to send.
 * @param bool $replace Whether to replace a previous similar header.
 * @param int $response_code The HTTP response code.
 * @return void
 */
function nitropack_header( string $header, bool $replace = true, int $response_code = 0 ) {
	if ( ! Utils::is_wp_cron() && ! Utils::is_wp_cli() ) {
		header( $header, $replace, $response_code );
	}
}

function nitropack_is_late_integration_init_required() {
	return \NitroPack\Integration\Plugin\NginxHelper::isActive() || \NitroPack\Integration\Plugin\Cloudflare::isApoActive();
}

//to be removed when the mu-file is removed
function nitropack_verify_connect( string $siteId, string $siteSecret ) {
	$nitropack_connect = new \NitroPack\WordPress\Connect();
	$nitropack_connect->nitropack_verify_connect( $siteId, $siteSecret );
}
// Init integration action handlers
$modHandler = NitroPack\ModuleHandler::getInstance();
$modHandler->init();