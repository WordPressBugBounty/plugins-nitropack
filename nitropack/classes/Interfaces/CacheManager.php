<?php
namespace NitroPack\Interfaces;

interface CacheManager {
    public function isCacheAllowed($key);
    public function purgeCache($key = NULL);
    public function hasCache($key);
    public function getCache($key);
    public function setCache($key, $content);
}
