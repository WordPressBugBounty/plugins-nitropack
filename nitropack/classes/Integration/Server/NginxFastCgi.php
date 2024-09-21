<?php

namespace NitroPack\Integration\Server;

use NitroPack\SDK\StorageDriver\Disk;
use NitroPack\Url\Url;

class NginxFastCgi {
    const STAGE = "very_early";

    public function isActive() {
        if (
            defined('NITROPACK_NGINX_FAST_CGI_CACHE_PATH') && 
            is_dir(NITROPACK_NGINX_FAST_CGI_CACHE_PATH) && 
            is_writable(NITROPACK_NGINX_FAST_CGI_CACHE_PATH)
        ) {
            return true;
        }

        return false;
    }

    public function init($stage) {
        if ($stage === 'very_early' && $this->isActive()) {
            add_action('nitropack_execute_purge_url', [$this, 'purgeUrl']);
            add_action('nitropack_execute_purge_all', [$this, 'purgeAll']);
        }
    }

    public function purgeUrl($url) {
        $cacheFile = $this->getCachePathFromUrl($url);
        $diskStorage = new Disk();
        if (!$diskStorage->exists($cacheFile)) {
            return false;
        }

        return $diskStorage->deleteFile($cacheFile);
    }

    public function purgeAll() {
        $diskStorage = new Disk();
        if (!$diskStorage->exists(NITROPACK_NGINX_FAST_CGI_CACHE_PATH)) {
            return false;
        }

        return $diskStorage->trunkDir(NITROPACK_NGINX_FAST_CGI_CACHE_PATH);
    }

    private function getCachePathFromUrl($url) {
        if (empty($url)) {
            return false;
        }

        $urlObj = new Url($url);

        if (
            empty($urlObj->getScheme()) ||
            empty($urlObj->getHost()) ||
            empty($urlObj->getPath())
        ) {
            return false;
        }

        $cacheKeyBase = sprintf(
            '%sGET%s%s%s', 
            $urlObj->getScheme(), 
            $urlObj->getHost(), 
            $urlObj->getPath(), 
            $urlObj->getQuery() ? '?' . $urlObj->getQuery() : ''
        );

        $cacheKey = md5($cacheKeyBase);

        return sprintf(
            '%s%s/%s/%s', 
            nitropack_trailingslashit(NITROPACK_NGINX_FAST_CGI_CACHE_PATH), 
            substr($cacheKey, -1), 
            substr($cacheKey, -3, 2), 
            $cacheKey
        );
    }
}
