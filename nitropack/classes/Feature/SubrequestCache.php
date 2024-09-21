<?php
namespace NitroPack\Feature;

use NitroPack\Util\CacheStreamWrapper;
use NitroPack\Interfaces\CacheManager;
use NitroPack\SDK\NitroPack as NitroPackSDK;

class SubrequestCache implements CacheManager {
    const STAGE = "very_early";

    private $allowList = array();
    private $blockList = array();

    private $cache = array();
    private $cacheDir = NULL;
    private $cacheTtl = 3600;

    public function init($stage) {
        if ($stage != self::STAGE) {
            return;
        }

        if (!defined("NITROPACK_SUBREQUEST_CACHE_ALLOW_LIST") || !NITROPACK_SUBREQUEST_CACHE_ALLOW_LIST) {
            return;
        }

        if (null === $nitro = get_nitropack_sdk()) {
            return;
        }

        $this->allowList = explode(";", NITROPACK_SUBREQUEST_CACHE_ALLOW_LIST);
        $this->blockList = array();

        if (defined("NITROPACK_SUBREQUEST_CACHE_BLOCK_LIST") && NITROPACK_SUBREQUEST_CACHE_BLOCK_LIST) {
            $this->blockList = explode(";", NITROPACK_SUBREQUEST_CACHE_BLOCK_LIST);
        }

        if (defined("NITROPACK_SUBREQUEST_CACHE_TTL") && NITROPACK_SUBREQUEST_CACHE_TTL >= 0) {
            $this->cacheTtl = NITROPACK_SUBREQUEST_CACHE_TTL;
        }

        add_action('nitropack_execute_purge_all', [$this, 'purgeCache']);

        $this->cacheDir = nitropack_trailingslashit(NITROPACK_DATA_DIR) . nitropack_trailingslashit("subrequest-cache");

        CacheStreamWrapper::setCacheManager($this);
        CacheStreamWrapper::init();

        add_filter('pre_http_request', array($this, "interceptRequest"), 10, 3);
        add_action('http_api_debug', array($this, "interceptResponse"), 10, 5);
    }

    public function interceptRequest($resp, $args, $url) {
        if (!empty($args["method"]) && $args["method"] != "GET") {
            return $resp;
        }

        if (!$this->isCacheAllowed($url)) {
            return $resp;
        }

        $key = "wpremote-" . $url;
        if ($this->hasCache($key)) {
            return unserialize($this->getCache($key));
        }

        return $resp;
    }

    public function interceptResponse($response, $context, $class, $parsed_args, $url) {
        if (is_wp_error($response)) {
            return;
        }

        if (!empty($parsed_args["method"]) && $parsed_args["method"] != "GET") {
            return;
        }

        if (!$this->isCacheAllowed($url)) {
            return;
        }

        $key = "wpremote-" . $url;
        $this->setCache($key, serialize($response));
    }

    public function purgeCache($key = NULL) {
        if (!$this->cacheDir || !is_dir($this->cacheDir)) return;

        if ($key) {
            $cacheFile = $this->getCacheFile($key);
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }

            $cacheFileWp = $this->getCacheFile("wpremote-" . $key);
            if (file_exists($cacheFileWp)) {
                unlink($cacheFileWp);
            }
            return;
        }

        $dh = opendir($this->cacheDir);
        while (($entry = readdir($dh)) !== false) {
            if ($entry == "." || $entry == "..") continue;

            $cacheFile = $this->cacheDir . $entry;
            if (!is_file($cacheFile)) continue;
            unlink($cacheFile);
        }
        closedir($dh);
        rmdir($this->cacheDir);
    }

    public function hasCache($key) {
        if(!empty($this->cache[$key])) {
            return true;
        }

        $cacheFile = $this->getCacheFile($key);
        if (file_exists($cacheFile) && time() - filemtime($cacheFile) <= $this->cacheTtl) {
            return true;
        }

        return false;
    }

    public function getCache($key) {
        if(empty($this->cache[$key])) {
            $cacheFile = $this->getCacheFile($key);
            if (file_exists($cacheFile) && time() - filemtime($cacheFile) <= $this->cacheTtl) {
                $this->cache[$key] = file_get_contents($cacheFile);
            }
        }

        if(!empty($this->cache[$key])) {
            return $this->cache[$key];
        }

        return NULL;
    }

    public function setCache($key, $content) {
        $this->cache[$key] = $content;

        if (!$this->cacheDir) {
            return;
        }

        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            return;
        }

        file_put_contents($this->getCacheFile($key), $content);
    }

    private function getCacheFile($key) {
        if (!$this->cacheDir) {
            return;
        }

        return $this->cacheDir . md5($key);
    }

    public function isCacheAllowed($key) {
        // Exact match search. Exact matches have higher priority than wildcard matches.
        foreach ($this->allowList as $pattern) {
            if ($pattern == $key) {
                return true;
            }
        }

        // Block list patterns have higher priority than allow list ones
        foreach ($this->blockList as $pattern) {
            if (preg_match("/" . NitroPackSDK::wildcardToRegex($pattern) . "/", $key)) {
                return false;
            }
        }

        foreach ($this->allowList as $pattern) {
            if (preg_match("/" . NitroPackSDK::wildcardToRegex($pattern) . "/", $key)) {
                return true;
            }
        }

        return false;
    }
}
