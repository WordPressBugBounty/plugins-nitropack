#!/usr/bin/env php
<?php
declare(strict_types=1);

$url = $argv[1] ?? 'https://www.google.com';
$domainName = parse_url($url, PHP_URL_HOST);
$filepath = __DIR__ . '/data/' . $domainName .'.html';

if (!is_dir(__DIR__ . '/data') && !mkdir($concurrentDirectory = __DIR__ . '/data') && !is_dir($concurrentDirectory)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
}

if (file_exists($filepath)) {
    echo "File already exists: $filepath\n";
    exit(1);
}

$html = file_get_contents($url);
file_put_contents($filepath, $html);

echo "File created: $filepath\n";

shell_exec("exec brotli \"$filepath\"");
shell_exec("exec gzip -k \"$filepath\"");
