<?php
declare(strict_types=1);

namespace Tests\Feature;

use NitroPack\HttpClient\HttpClient;

class HttpClientTest extends HttpClientTestCase
{
    /**
     * @dataProvider dataProviderContentEncoding
     */
    public function testContentEncoding(string $requestedEncoding, string $actualEncoding, array $callables): void
    {
        foreach ($callables as $callable) {
            if (is_callable($callable)) {
                continue;
            }

            self::markTestSkipped(sprintf('Content encoding "%s" not supported. Callable does not exists %s.', $actualEncoding, $callable));
        }

        $expectedContent = 'Hello world!';
        $query = http_build_query(['encoding' => $actualEncoding, 'content' => $expectedContent]);

        $url = static::mockServerUrl('/content-encoding.php?'. $query);

        $httpClient = new HttpClient($url);
        $httpClient->accept_deflate = false;
        $httpClient->setHeader('Accept-Encoding', $requestedEncoding);
        $httpClient->fetch();

        // make sure decoding works as expecting
        self::assertSame($expectedContent, $httpClient->getBody());

        // check if subsequent calls would fail
        self::assertSame($expectedContent, $httpClient->getBody());

        // check if the request header is set correctly
        self::assertSame($httpClient->request_headers['accept-encoding'], $requestedEncoding);
    }

    public static function dataProviderContentEncoding(): array
    {
        return [
            'null' => ['none', 'none', []],
            'none' => ['none', 'none', []],
            'gzip' => ['gzip', 'gzip', ['gzdecode']],
            'brotli' => ['br', 'br', ['brotli_uncompress']],
            'gzip brotli' => ['gzip, br', 'gzip br', ['gzdecode', 'brotli_uncompress']],
            'brotli gzip' => ['br, gzip', 'br gzip', ['brotli_uncompress', 'gzdecode']],
            'request br, gzip; got gzip' => ['br, gzip', 'gzip', ['gzdecode']],
            'request gzip, br; got br' => ['gzip, br', 'br', ['brotli_uncompress']],
            'request br; got gzip' => ['br', 'gzip', ['gzdecode']],
            'request gzip; got br' => ['gzip', 'br', ['brotli_uncompress']],
        ];
    }

    /**
     * @dataProvider dataProviderContentLength
     */
    public function testContentLength(int $contentLengthValue, int $actualSize): void
    {
        $query = http_build_query(['contentLengthValue' => $contentLengthValue, 'actualSize' => $actualSize]);

        $url = static::mockServerUrl('/content-length.php?'. $query);

        $httpClient = new HttpClient($url);
        $httpClient->fetch();

        self::assertSame($actualSize, strlen($httpClient->getBody()));
    }

    public static function dataProviderContentLength(): array
    {
        return [
            'correct value' => ['contentLengthValue' => 10, 'actualSize' => 10],
            'lower value' => ['contentLengthValue' => 10, 'actualSize' => 20],
        ];
    }
}