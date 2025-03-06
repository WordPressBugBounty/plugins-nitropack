<?php
declare(strict_types=1);

namespace Tests\Feature;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

abstract class HttpClientTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$reactServerPid = static::reactServerStart(static function (ServerRequestInterface $request) {
            $path = $request->getUri()->getPath();

            return match ($path) {
                default => new Response(404, [], 'Resource Not Found ' . $path),
                '/health' => new Response(200, [], 'OK'),
                '/content-encoding.php' => static::serveContentEncoding($request),
                '/content-length.php' => static::serveContentLength($request),
            };
        });
    }

    public static function tearDownAfterClass(): void
    {
        static::reactServerStop(static::$reactServerPid);

        parent::tearDownAfterClass();
    }

    public static function serveContentEncoding(ServerRequestInterface $request): Response
    {
        $queryParams = $request->getQueryParams();
        $encoding = $queryParams['encoding'] ?? null;

        // Generate a large content to simulate a real-world scenario
        $content = $queryParams['content'] ?? bin2hex(random_bytes(8192));

        if (array_key_exists('prefix', $queryParams)) {
            $content = $queryParams['prefix'] . $content;
        }

        if (array_key_exists('suffix', $queryParams)) {
            $content .= $queryParams['suffix'];
        }

        $isChunked = ($queryParams['chunked'] ?? '0') === '1';

        [$contentEncoding, $contentEncoders] = match ($encoding) {
            'gzip' => ['gzip', ['gzencode']],
            'br' => ['br', ['brotli_compress']],
            'gzip br' => ['gzip, br', ['gzencode', 'brotli_compress']],
            'br gzip' => ['br, gzip', ['brotli_compress', 'gzencode']],
            'none' => ['none', [static fn (string $content): string => $content]],
            'null' => [null, [static fn (string $content): string => $content]],
            default => new Response(500, [], 'Unsupported encoding: ' . var_export($encoding, true)),
        };

        foreach ($contentEncoders as $contentEncoder) {
            $content = $contentEncoder($content);
        }

        $headers = ['Content-Type' => 'text/plain'];

        if ($contentEncoding !== null) {
            $headers['Content-Encoding'] = $contentEncoding;
        }

        if (! $isChunked) {
            $headers['Content-Length'] = (string) strlen($content);
            return new Response(200, $headers, $content);
        }

        // Adding this header makes ReactPHP use chunked transfer encoding
        $headers['Transfer-Encoding'] = 'chunked';
        $headers['Connection'] = 'keep-alive';

        $stream = new ThroughStream();
        $loop = Loop::get();

        // Initialize the content streaming
        $loop->futureTick(function () use ($stream, $content, $isChunked, $loop) {
            $chunks = str_split($content, 10);

            $index = 0;

            // Declare $sendChunk before referencing it
            $sendChunk = null;

            $sendChunk = static function () use (&$index, $chunks, $stream, $isChunked, &$sendChunk, $loop) {
                if (!isset($chunks[$index])) {
                    $stream->end(); // Signal end of stream
                    return;
                }

                $chunk = $chunks[$index];
                $stream->write($chunk);

                ++$index;
                $loop->addTimer(0.01, $sendChunk); // Schedule the next chunk
            };

            // Start sending chunks
            $sendChunk();
        });

        return new Response(200, $headers, $stream);
    }
    public static function serveContentLength(ServerRequestInterface $request): Response
    {
        $queryParams = $request->getQueryParams();
        $contentLengthValue = $queryParams['contentLengthValue'] ?? 64;
        $actualSize = $queryParams['actualSize'] ?? $contentLengthValue;

        $content = str_repeat('a', (int) $actualSize);

        if (array_key_exists('prefix', $queryParams)) {
            $content = $queryParams['prefix'] . $content;
        }

        if (array_key_exists('suffix', $queryParams)) {
            $content .= $queryParams['suffix'];
        }

        // OUTPUT GOES BELOW THIS LINE

        $headers = [];

        $headers['Content-Length'] = [$contentLengthValue];

        return new Response(200, $headers, $content);
    }
}