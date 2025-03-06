<?php
declare(strict_types=1);

namespace Tests\Feature\StreamFilter;

use NitroPack\HttpClient\StreamFilter\BrotliStreamFilter;
use PHPUnit\Framework\TestCase;

class BrotliStreamFilterTest extends TestCase
{
    public function testBrotliCompressionAndDecompression(): void
    {
        if (! function_exists('brotli_compress')) {
            $this->markTestSkipped('Brotli extension is not installed.');
        }

        BrotliStreamFilter::register();

        $content = bin2hex(random_bytes(8192));

        $compressedStream = fopen('php://memory', 'rb+');

        $chunkSize = 10;
        $contentEncoded = brotli_compress($content);

        $brotliStreamFilter = stream_filter_append($compressedStream, BrotliStreamFilter::STREAM_FILTER_NAME, STREAM_FILTER_WRITE);

        foreach (str_split($contentEncoded, $chunkSize) as $chunk) {
            fwrite($compressedStream, $chunk);
        }

        rewind($compressedStream);
        $contentDecoded = stream_get_contents($compressedStream);

        $this->assertSame($content, $contentDecoded);
    }
}