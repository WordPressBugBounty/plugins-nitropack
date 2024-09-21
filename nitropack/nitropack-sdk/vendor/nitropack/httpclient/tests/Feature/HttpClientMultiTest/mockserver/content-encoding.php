<?php
declare(strict_types=1);

$encoding = $_GET['encoding'];

// Generate a large content to simulate a real-world scenario and make race conditions more likely
$content = array_key_exists('content', $_GET)
    ? $_GET['content']
    : bin2hex(random_bytes(8192));

if (array_key_exists('prefix', $_GET)) {
    $content = $_GET['prefix'] . $content;
}

if (array_key_exists('suffix', $_GET)) {
    $content .= $_GET['suffix'];
}

$isChunked = ($_GET['chunked'] ?? 0) === '1';

[$contentEncoding, $contentEncoders] = match ($encoding) {
    'gzip' => ['gzip', ['gzencode']],
    'br' => ['br', ['brotli_compress']],
    'gzip br' => ['gzip, br', ['gzencode', 'brotli_compress',]],
    'br gzip' => ['br, gzip', ['brotli_compress', 'gzencode',]],
    'none' => ['none', [static fn (string $content): string => $content]],
    'null' => [null, [static fn (string $content): string => $content]],
    default => throw new LogicException('Unsupported encoding'),
};

foreach ($contentEncoders as $contentEncoder) {
    $content = $contentEncoder($content);
}

// OUTPUT GOES BELOW THIS LINE

header('Content-Type: text/plain');

if ($contentEncoding !== null) {
    header('Content-Encoding: ' . $contentEncoding);
}

$fh = fopen('php://output', 'wb');

if (! $fh) {
    throw new LogicException('Could not open php://output');
}

if ($isChunked) {
    header('Transfer-Encoding: chunked');
    header('Connection: keep-alive');

    $output = static function ($fh, $chunk) {
        // Write chunk size in hexadecimal followed by \r\n
        fwrite($fh, dechex(strlen($chunk)) . "\r\n");
        // Write the actual chunk followed by \r\n
        return fwrite($fh, $chunk . "\r\n");
    };
} else {
    header('Content-Length: ' . strlen($content));

    $output = static function ($fh, $chunk) {
        return fwrite($fh, $chunk);
    };
}

// This piece simulates rate limited stream of data
foreach (str_split($content, 10) as $chunk) {
    usleep(10);

    if ($output($fh, $chunk) === false) {
        throw new LogicException('Could not write to php://output');
    }

    flush();
}

if ($isChunked) {
    // Send the final zero-length chunk to indicate the end of the response
    fwrite($fh, "0\r\n\r\n");
}

fflush($fh);
flush();
fclose($fh);