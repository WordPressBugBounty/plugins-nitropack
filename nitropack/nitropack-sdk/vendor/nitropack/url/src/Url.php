<?php

namespace NitroPack\Url;

class Url {
    private $url;
    private $base; // This is the URL found in <base> tag (if any)
    private $scheme;
    private $port;
    private $host;
    private $path;
    private $query;
    private $hash;
    private $rootUrl; // no trailing "/"
    private $relativePath; // no trailing "/"
    private $assumedHost;
    private $assumedPath;
    private $hostUnmodified;

    public function __construct($url) {
        $this->url = $url;
        $parts = parse_url($url ?: '');
        $this->scheme = isset($parts["scheme"]) ? strtolower($parts["scheme"]) : NULL;
        $this->port = isset($parts["port"]) ? $parts["port"] : NULL;
        $this->host = isset($parts["host"]) ? strtolower($parts["host"]) : NULL;
        $this->path = isset($parts["path"]) ? $parts["path"] : "/";
        $this->query = isset($parts["query"]) ? $parts["query"] : NULL;
        $this->hash = isset($parts["fragment"]) ? $parts["fragment"] : NULL;
        $this->rootUrl = NULL;
        $this->relativePath = NULL;
        $this->assumedHost = false;
        $this->assumedPath = false;

        if (!isset($parts["host"]) && isset($parts["path"])) { // This is probably a host written like this: nitropack.io
            if (preg_match("/^[^\s\/]+?\.[^\s\/]+$/", $parts["path"])) {
                $this->host = strtolower($this->path);
                $this->hostUnmodified = $this->path;
                $this->path = "/";
                $this->assumedHost = true;
                $this->assumedPath = true;
            } else if (preg_match("/^([^\s\/]+?\.[^\s\/]+)(\/.*?)$/", $parts["path"], $matches)) {
                $this->host = strtolower($matches[1]);
                $this->hostUnmodified = $matches[1];
                $this->path = $matches[2];
                $this->assumedHost = true;
            }
        }

        $this->buildParts();
    }

    public function __toString() {
        return $this->getNormalized();
    }

    private function buildParts() {
        $this->updateRootUrl();
        $this->updateRelativePath();
    }

    private function suggestScheme() {
        if (!$this->scheme) {
            if ($this->base) {
                $scheme = $this->base->getScheme() ? $this->base->getScheme() : "http";
            } else {
                $scheme = "http";
            }
        } else {
            $scheme = $this->scheme;
        }

        return $scheme;
    }

    private function updateRootUrl() {
        if ($this->host) {
            $scheme = $this->suggestScheme();
            $this->rootUrl = $scheme . "://" . $this->host;

            $port = $this->port ? $this->port : ($scheme == "https" ? 443 : 80);
            if (!in_array($port, [80, 443]) || ($scheme == "http" && $port != 80) || ($scheme == "https" && $port != 443)) {
                $this->rootUrl .= ":" . $port;
            }
        } else if ($this->base) {
            $this->rootUrl = $this->base->getRootUrl();
        }
    }

    private function updateRelativePath() {
        if (substr($this->path, -1) != '/' && $this->path != '/') {
            $this->relativePath = dirname($this->path);
        } else {
            $this->relativePath = $this->path;
        }

        if ($this->relativePath) {
            if ($this->relativePath[0] != "/" && $this->base) {
                $this->relativePath = $this->base->getRelativePath() . "/" . $this->relativePath;
            }

            $this->relativePath = rtrim($this->relativePath, "/");
        }
    }

    public function getUrl() { return $this->url; }
    public function getScheme() { return $this->suggestScheme(); }
    public function getPort() { return $this->port; }
    public function getHost() { return $this->host; }
    public function getPath() { return $this->path; }
    public function getQuery() { return $this->query; }
    public function getHash() { return $this->hash; }
    public function getBase() { return $this->base ? $this->base : $this; }
    public function getBaseUrl() { return $this->base ? $this->base->getNormalized() : NULL; }
    public function getRootUrl() { return $this->rootUrl; }
    public function getRelativePath() { return $this->relativePath; }

    public function setQuery($query) { $this->query = $query; }
    public function setHash($hash) { $this->hash = $hash; }

    public function setPath($path) {
        $this->path = $path;
        $this->updateRelativePath();
    }

    public function setPort($port) {
        $this->port = $port;
        $this->updateRootUrl();
    }

    public function setScheme($scheme) {
        $this->scheme = $scheme;
        $this->updateRootUrl();
    }
    
    public function setHost($host) {
        $this->host = $host;
        $this->updateRootUrl();
    }

    public function setBaseUrl($url) { 
        if ($url instanceof Url) {
            $this->base = $url;
        } else {
            $this->base = new Url($url);
        }

        if ($this->assumedHost) {
            $this->path = $this->assumedPath ? $this->hostUnmodified : $this->host.$this->path;
            $this->host = NULL;
            $this->assumedHost = false;
            $this->assumedPath = false;
        }

        $this->buildParts();
    }

    public function getNormalized($resolvePathNavigation = true, $includeHash = true) {
        return Normalizer\Regular::getNormalized($this, $resolvePathNavigation, $includeHash); 
    }

    public function getNormalizedNaive($resolvePathNavigation = true, $includeHash = true) {
        return Normalizer\Naive::getNormalized($this, $resolvePathNavigation, $includeHash); 
    }

    /**
     * Checks if the URL object produces a valid URL
     * @return boolean
     */
    public function isValid() {
        try {
            $originalHost = $this->getHost();
            // Add more compatibility chars in the array below
            // FILTER_VALIDATE_URL validates against http://www.faqs.org/rfcs/rfc2396.html,
            // which, for example, treats underscore("_") as invalid for hosts.
            $charsToReplace = ['_'];
            $replacementChar = '-';

            if (empty($originalHost)) {
                // probably a relative path
                return false;
            }

            // do we expect to have multibyte string for URL?
            // filter_var will also fail with multibyte string as URL
            if (!empty(array_intersect($charsToReplace, str_split($originalHost)))) {
                $newHost = str_replace($charsToReplace, $replacementChar, $originalHost);
                $this->setHost($newHost);
            }

            if (filter_var($this->getNormalized(), FILTER_VALIDATE_URL) === false) {
                return false;
            }

            return true;
        } finally {
            // Restore the original host
            $this->setHost($originalHost);
        }
    }
}