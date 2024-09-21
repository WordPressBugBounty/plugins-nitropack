<?php
declare(strict_types=1);

namespace Tests\Feature;

use Generator;
use NitroPack\HttpClient\HttpClient;
use NitroPack\HttpClient\HttpClientMulti;
use NitroPack\HttpClient\HttpConfig;
use SplObjectStorage;

class HttpClientMultiTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::setupExceptionErrorHandler();

        static::$webContainer = static::webContainerStart();
    }

    public static function tearDownAfterClass(): void
    {
        static::webContainerStop(static::$webContainer);

        parent::tearDownAfterClass();
    }

    /**
     * @dataProvider dataProviderContentEncoding
     */
    public function testContentEncoding(string $encoding, array $callables, bool $isChunked): void
    {
        $concurrency = 3;
        $followRedirects = true;
        $httpMethod = 'GET';
        $returnClients = false;

        foreach ($callables as $callable) {
            if (is_callable($callable)) {
                continue;
            }

            self::markTestSkipped(sprintf('Content encoding "%s" not supported', $encoding));
        }

        $successfulHttpClients = [];
        $failedHttpClients = [];

        $httpConfig = new HttpConfig();
        $httpConfig->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');

        $httpClientMulti = new HttpClientMulti();
        $httpClientMulti->returnClients($returnClients);

        $httpClientMulti->onSuccess(static function (HttpClient $httpClient) use (&$successfulHttpClients, &$failedHttpClients): void {
            $successfulHttpClients[] = $httpClient;
        });

        $httpClientMulti->onError(static function (HttpClient $httpClient) use (&$successfulHttpClients, &$failedHttpClients): void {
            $failedHttpClients[] = $httpClient;
        });

        $expectations = new SplObjectStorage();

        for ($i = 0; $i < $concurrency; ++$i) {
            $expectedPrefix = 'Hello world! ' . bin2hex(random_bytes(16));
            $expectedSuffix = 'Hello world! ' . bin2hex(random_bytes(16));
            $query = http_build_query([
                'encoding' => $encoding,
                'prefix' => $expectedPrefix,
                'suffix' => $expectedSuffix,
                'chunked' => (int) $isChunked,
            ]);

            $url = static::mockServerUrl('/content-encoding.php?'. $query);

            $httpClientMulti->push($httpClient = new HttpClient($url, $httpConfig));
            $expectations->attach($httpClient, [$expectedPrefix, $expectedSuffix]);
        }

        [$actualSuccessfulHttpClients, $actualFailedHttpClients] = $httpClientMulti->fetchAll($followRedirects, $httpMethod);

        self::assertCount(0, $failedHttpClients, 'Some HTTP clients failed');
        self::assertCount($concurrency, $successfulHttpClients, 'Not all HTTP clients were successful');

        if ($returnClients) {
            self::assertCount($concurrency, $actualSuccessfulHttpClients);
        } else {
            self::assertCount(0, $actualSuccessfulHttpClients);
        }

        self::assertCount(0, $actualFailedHttpClients);
        self::assertCount(0, $httpClientMulti->getClients());

        foreach ($successfulHttpClients as $httpClient) {
            $actualContent = $httpClient->getBody();
            [$expectedPrefix, $expectedSuffix] = $expectations[$httpClient];

            self::assertStringStartsWith($expectedPrefix, $actualContent);
            self::assertStringEndsWith($expectedSuffix, $actualContent);
        }
    }

    public static function dataProviderContentEncoding(): Generator
    {
        $chunkOptions = [
            true,
            false,
        ];

        $compressionOptions = [
            'null' => ['null', []],
            'none' => ['none', []],
            'gzip' => ['gzip', ['gzdecode']],
            'brotli' => ['br', ['brotli_uncompress']],
        ];

        foreach ($compressionOptions as $compressionName => $option) {
            $name = "compression({$compressionName})";

            foreach ($chunkOptions as $isChunked) {
                yield $name . ' '. ($isChunked ? '✅' : '❌') .'chunked' => [...$option, $isChunked];
            }
        }
    }
}