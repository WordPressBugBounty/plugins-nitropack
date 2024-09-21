<?php

namespace NitroPack\Integration\Hosting;

class WPEngine extends Hosting {
    const STAGE = "very_early";

    private $urlPurges = [];
    private $fullPurge = false;
    private $readyToPurge = false;

    public static function detect() {
        return !!getenv('IS_WPE')
            || !!getenv('WPENGINE_ACCOUNT')
            || (\NitroPack\WordPress\NitroPack::isWpCli() && strpos($_SERVER['DOCUMENT_ROOT'], '/nas/content/live/') === 0);
    }

    public function init($stage) {
        if (self::detect()) {
            switch ($stage) {
            case "very_early":
                if (getenv( 'HTTP_GEOIP_COUNTRY_CODE' )) {
                    add_action('np_set_cookie_filter', function() {
                        \NitroPack\SDK\NitroPack::addCookieFilter([$this, 'addGeotCookies']);
                    });
                }

                define("NITROPACK_USE_MICROTIMEOUT", 20000);

                if (isset($_COOKIE["wpengine_no_cache"]) || isset($_SERVER["HTTP_AUTOUPDATER"])) {
                    add_filter("nitropack_passes_cookie_requirements", function() {
                        nitropack_header("X-Nitro-Disabled-Reason: WP Engine SPM bypass");
                        return false;
                    });
                }
                \NitroPack\ModuleHandler::initSemAcquire();
                return true;
            case "early":
                \NitroPack\ModuleHandler::initSemRelease();
                add_action('nitropack_execute_purge_url', [$this, 'purgeUrl']);
                add_action('nitropack_execute_purge_all', [$this, 'purgeAll']);
                break;
            }
        }
    }

    public function purgeUrl($url) {
        try {
            $handler = function($paths) use($url) {
                $wpe_path = parse_url($url, PHP_URL_PATH);
                $wpe_query = parse_url($url, PHP_URL_QUERY);
                $varnish_path = $wpe_path;
                if (!empty($wpe_query)) {
                    $varnish_path .= '?' . $wpe_query;
                }
                if ($url && count($paths) == 1 && $paths[0] == ".*") {
                    return array($varnish_path);
                }
                return $paths;
            };
            add_filter( 'wpe_purge_varnish_cache_paths', $handler );
            if (class_exists("\WpeCommon")) { // We need to have this check for clients that switch hosts
                \WpeCommon::purge_varnish_cache();
            }
            remove_filter( 'wpe_purge_varnish_cache_paths', $handler );
        } catch (\Exception $e) {
            // WPE exception
        }
    }

    public function purgeAll() {
        try {
            if (class_exists("\WpeCommon")) { // We need to have this check for clients that switch hosts
                \WpeCommon::purge_varnish_cache();
            }
        } catch (\Exception $e) {
            // WPE exception
        }
    }

    public function addGeotCookies(&$cookies) {
        $cookies['nitro_geot_country_code']  = getenv( 'HTTP_GEOIP_COUNTRY_CODE' );
        $cookies['nitro_geot_country_code3'] = getenv( 'HTTP_GEOIP_COUNTRY_CODE3' );
        $cookies['nitro_geot_country_name']  = getenv( 'HTTP_GEOIP_COUNTRY_NAME' );
        $cookies['nitro_geot_latitude']      = getenv( 'HTTP_GEOIP_LATITUDE' );
        $cookies['nitro_geot_longitude']     = getenv( 'HTTP_GEOIP_LONGITUDE' );
        $cookies['nitro_geot_area_code']     = getenv( 'HTTP_GEOIP_AREA_CODE' );
        $cookies['nitro_geot_region']        = getenv( 'HTTP_GEOIP_REGION' );
        $cookies['nitro_geot_city']          = getenv( 'HTTP_GEOIP_CITY' );
        $cookies['nitro_geot_postal_code']   = getenv( 'HTTP_GEOIP_POSTAL_CODE' );
    }
}
