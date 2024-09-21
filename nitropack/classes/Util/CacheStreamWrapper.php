<?php

namespace NitroPack\Util;

use NitroPack\Util\VoidCacheManager;

class CacheStreamWrapper {
    public $context;
    private $startTime;
    private $endTime;
    private $stream;
    private $path;

    public static $timeTaken = [];
    private static $cacheManager = NULL;

    private $cacheLoaded = false;
    private $content = "";
    private $readPos = 0;
    private $ctxOpts = [];

    public static function setCacheManager($cacheManager) {
        self::$cacheManager = $cacheManager;
    }

    public static function init() {
        if (!self::$cacheManager) {
            self::$cacheManager = new VoidCacheManager();
        }

        self::register();
    }

    private static function register() {
        stream_wrapper_unregister('https');
        stream_wrapper_unregister('http');
        stream_wrapper_register('https', __CLASS__);
        stream_wrapper_register('http', __CLASS__);
    }

    private static function restore() {
        stream_wrapper_restore('https');
        stream_wrapper_restore('http');
    }

    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($this->context && is_resource($this->context)) {
            $this->ctxOpts = stream_context_get_options($this->context);
        }

        $this->path = $path;
        $this->startTime = microtime(true);
        if ($this->hasCache()) {
            $this->content = self::$cacheManager->getCache($this->path);
            $this->cacheLoaded = true;
            return true;
        }

        self::restore();
        $this->stream = fopen($path, $mode, $options, $this->context);
        self::register();

        return $this->stream !== false;
    }

    public function stream_read($count) {
        if ($this->cacheLoaded) {
            $chunk = substr($this->content, $this->readPos, $count);
            $this->readPos += $count;
            return $chunk;
        }
        $chunk = fread($this->stream, $count);
        $this->content .= $chunk;
        return $chunk;
    }

    public function stream_write($data) {
        if ($this->cacheLoaded) {
            return strlen($data);
        }
        return fwrite($this->stream, $data);
    }

    public function stream_close() {
        $this->endTime = microtime(true);
        self::$timeTaken[$this->path] = $this->endTime - $this->startTime;

        if ($this->cacheLoaded) return;

        fclose($this->stream);
        $this->saveCache();
    }

    public function stream_eof() {
        if ($this->cacheLoaded) {
            return $this->readPos >= strlen($this->content);
        }
        return feof($this->stream);
    }

    public function stream_stat() {
        return false;
    }

    public function stream_seek($offset, $whence = SEEK_SET) {
        return 0;
    }

    public function stream_tell() {
        return 0;
    }

    public function stream_metadata($path, $option, $value) {
        return false;
    }

    public function url_stat($path, $flags) {
        self::restore();
        $stat = @stat($path);
        self::register();
        return $stat;
    }

    public function stream_set_option($option, $arg1, $arg2) {
        return false;
    }

    public function dir_closedir() {
        return false;
    }

    public function dir_opendir($path, $options) {
        return false;
    }

    public function dir_readdir() {
        return false;
    }

    public function dir_rewinddir() {
        return false;
    }

    public function mkdir($path, $mode, $options) {
        return false;
    }

    public function rename($path_from, $path_to) {
        return false;
    }

    public function rmdir($path, $options) {
        return false;
    }

    public function stream_cast($cast_as) {
        return $this->stream;
    }

    public function unlink($path) {
        return false;
    }

    private function hasCache() {
        if (!$this->canCache()) return false;
        return self::$cacheManager->hasCache($this->path);
    }

    private function saveCache() {
        if (!$this->canCache()) return;
        self::$cacheManager->setCache($this->path, $this->content);
    }

    private function canCache() {
        if (!empty($this->ctxOpts["http"])) {
            if (!empty($this->ctxOpts["http"]["method"]) && $this->ctxOpts["http"]["method"] !== "GET") {
                return false;
            }
        }

        return self::$cacheManager->isCacheAllowed($this->path);
    }
}
