<?php
declare(strict_types=1);

namespace Tests\Feature;

use NitroPack\HttpClient\HttpClient;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class HttpClientTest extends TestCase
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
            };
        });
    }

    public static function serveContentEncoding(ServerRequestInterface $request): Response
    {
        $queryParams = $request->getQueryParams();
        $encoding = $queryParams['encoding'] ?? null;
        $content = $queryParams['content'] ?? null;

        [$contentEncoding, $contentEncoders] = match ($encoding) {
            'gzip' => ['gzip', ['gzencode']],
            'br' => ['br', ['brotli_compress']],
            'gzip br' => ['gzip, br', ['gzencode', 'brotli_compress',]],
            'br gzip' => ['br, gzip', ['brotli_compress', 'gzencode',]],
            'none' => ['none', [static fn (string $content): string => $content]],
            'null' => [null, [static fn (string $content): string => $content]],
            default => new Response(500, [], 'Unsupported encoding: ' . var_export($encoding, true)),
        };

        foreach ($contentEncoders as $contentEncoder) {
            $content = $contentEncoder($content);
        }

        // OUTPUT GOES BELOW THIS LINE

        $headers = [];

        if ($contentEncoding !== null) {
            $headers['Content-Encoding'] = [$contentEncoding];
        }

        $headers['Content-Length'] = [strlen($content)];

        return new Response(200, $headers, $content);
    }

    public static function tearDownAfterClass(): void
    {
        static::reactServerStop(static::$reactServerPid);

        parent::tearDownAfterClass();
    }

    /**
     * @dataProvider dataProviderContentEncoding
     */
    public function testContentEncoding(string $encoding, array $callables): void
    {
        foreach ($callables as $callable) {
            if (is_callable($callable)) {
                continue;
            }

            self::markTestSkipped(sprintf('Content encoding "%s" not supported', $encoding));
        }

        $expected = 'Hello world!';
        $query = http_build_query(['encoding' => $encoding, 'content' => $expected]);

        $url = static::mockServerUrl('/content-encoding.php?'. $query);

        $httpClient = new HttpClient($url);
        $httpClient->fetch();

        // make sure decoding works as expecting
        self::assertSame($expected, $httpClient->getBody());

        // check if subsequent calls would fail
        self::assertSame($expected, $httpClient->getBody());
    }

    public static function dataProviderContentEncoding(): array
    {
        return [
            'null' => ['none', []],
            'none' => ['none', []],
            'gzip' => ['gzip', ['gzdecode']],
            'brotli' => ['br', ['brotli_uncompress']],
            'gzip brotli' => ['gzip br', ['gzdecode', 'brotli_uncompress']],
            'brotli gzip' => ['br gzip', ['brotli_uncompress', 'gzdecode']],
        ];
    }
}