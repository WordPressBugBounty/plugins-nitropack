<?php

namespace NitroPack\Url\Normalizer;

class Regular {
    /**
     * Return a normalized version the provided URL. Path navigations are resolved, query strings and path escaped, etc.
     *
     * @param \NitroPack\Url\Url     $urlObj                    The URL instance to normalize
     * @param boolean                $resolvePathNavigation     If the path navigations should be resolved
     * @param boolean                $includeHash               Whether to include the hash/fragment (if any) in the final URL
     *
     * @return String
     */
    public static function getNormalized($urlObj, $resolvePathNavigation = true, $includeHash = true) {
        $path = $urlObj->getPath();

        $url = "";
        if (strlen($path) > 0 && $path[0] == "/") { // absolute path - use rootUrl
            $url = $urlObj->getRootUrl() ? $urlObj->getRootUrl() : "";
        } else if ($urlObj->getBase()) { // relative path - use relativePath from the base (if set)
            $url = $urlObj->getBase()->getRootUrl() ? $urlObj->getBase()->getRootUrl() : "";
            $path = ($urlObj->getBase()->getRelativePath() ? $urlObj->getBase()->getRelativePath() : "") . "/" . $path;
        }

        if ($resolvePathNavigation) {
            $path = static::resolvePathNavigation($path, $resolvePathNavigation);
        }

        if (strpos($path,'%') !== false) {
            // Based on RFC3986 (https://www.ietf.org/rfc/rfc3986.txt):
            // For consistency, URI producers and normalizers should use uppercase hexadecimal digits for all
            // percent-encodings.
            $path = preg_replace_callback('/%[a-fA-F\d]{2}/', function ($matches) {
                return strtoupper($matches[0]);
            }, $path);
        }

        $path_parts = explode('/', $path);
        $final_parts = array();

        // Be careful when normalizing paths. Special characters should not be converted in the path parts
        // https://en.wikipedia.org/wiki/Percent-encoding
        // Example: https://example.com/a/b/images%2Fcontent2%2F0-1541085431275.jpg must not be converted to https://example.com/a/b/images/content2/0-1541085431275.jpg
        foreach($path_parts as $part) {
            $subparts = explode("+", $part);
            foreach ($subparts as &$subpart) {
                $subpart = implode("%", array_map(array(static::class, "normalizeQueryStr"), array_map("rawurldecode", explode("%", $subpart))));
            }
            unset($subpart);
            $final_parts[] = implode("+", $subparts);
        }
        $path = implode('/', $final_parts);

        if ($url) {
            $url .= "/" . ltrim($path, "/");
        } else {
            $url = $path;
        }

        if ($urlObj->getQuery()) {
            $url .= "?" . static::normalizeQueryStr($urlObj->getQuery());
        }

        if ($includeHash && $urlObj->getHash()) {
            $url .= "#" . $urlObj->getHash();
        }

        return $url;
    }

    private static function normalizeQueryStr($queryStr) {
        $newQueryStr = "";
        $reservedChars = array(":", "/", "?", "#", "[", "]", "@", "!", "$", "&", "'", "(", ")", "*", "+", ",", ";", "=");

        $buf = "";
        for ($i = 0; $i < strlen($queryStr); $i++) {
            $char = $queryStr[$i];

            if (in_array($char, $reservedChars)) {
                $newQueryStr .= rawurlencode(rawurldecode($buf)) . $char;
                $buf = "";
            } else {
                $buf .= $char;
            }
        }

        $newQueryStr .= rawurlencode(rawurldecode($buf));

        return $newQueryStr;
    }

    private static function resolvePathNavigation($path) {
        if (strpos($path, '../') === false) {
            return $path;
        }

        $path_parts = explode('/', $path);
        $final_parts = array();

        foreach($path_parts as $part) {
            if ($part == ".") {
                continue;
            } else if ($part == '..') {
                array_pop($final_parts);
            } else {
                $final_parts[] = $part;
            }
        }

        $path = implode('/', $final_parts);

        return $path;
    }
}