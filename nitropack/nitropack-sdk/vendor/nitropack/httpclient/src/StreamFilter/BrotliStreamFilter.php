<?php
namespace NitroPack\HttpClient\StreamFilter;

class BrotliStreamFilter extends \php_user_filter
{
    const STREAM_FILTER_NAME = 'brotli.decompress';

    /**
     * Brotli decompress context
     *
     * @var resource
     */
    private $decompressorContext;

    /**
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function onCreate()
    {
        $this->decompressorContext = brotli_uncompress_init();
        return true;
    }

    /**
     * @param resource $in
     * @param resource $out
     * @param int &$consumed
     * @param bool $closing
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $data = $bucket->data;
            $dataLength = $bucket->datalen;

            if ($dataLength === 0) {
                continue;
            }

            $consumed += $dataLength;
            $bucket->data = brotli_uncompress_add($this->decompressorContext, $data, BROTLI_FLUSH);
            stream_bucket_append($out, $bucket);
        }

        if ($closing) {
            $stream = fopen('php://memory', 'rb+');
            $bucket = stream_bucket_new($stream, brotli_uncompress_add($this->decompressorContext, '', BROTLI_FINISH));
            stream_bucket_append($out, $bucket);
            fclose($stream);
        }

        return PSFS_PASS_ON;
    }

    public static function register()
    {
        if (! static::isSatisfied()) {
            return;
        }

        stream_filter_register(static::STREAM_FILTER_NAME, static::class);
    }

    public static function isSatisfied()
    {
        return ! in_array(static::STREAM_FILTER_NAME, stream_get_filters())
            && function_exists('brotli_uncompress_init')
            && function_exists('brotli_uncompress_add');
    }
}