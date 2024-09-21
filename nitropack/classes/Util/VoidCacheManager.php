<?php
namespace NitroPack\Util;

use NitroPack\Interfaces\CacheManager;

class VoidCacheManager implements CacheManager {
    public function isCacheAllowed($key) {
        return false;
    }

    public function purgeCache($key = NULL) {
    }

    public function hasCache($key) {
        return false;
    }

    public function getCache($key) {
        return NULL;
    }

    public function setCache($key, $content) {
    }
}
