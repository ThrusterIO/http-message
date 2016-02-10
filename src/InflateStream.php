<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\StreamInterface;

/**
 * Class InflateStream
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class InflateStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public function __construct(StreamInterface $stream)
    {
        // Skip the first 10 bytes
        $stream = new LimitStream($stream, -1, 10);

        $resource = StreamWrapper::getResource($stream);

        stream_filter_append($resource, 'zlib.inflate', STREAM_FILTER_READ);

        $this->stream = new Stream($resource);
    }
}
