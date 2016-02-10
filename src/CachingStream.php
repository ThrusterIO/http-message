<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\StreamInterface;

/**
 * Class CachingStream
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class CachingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     * @var StreamInterface Stream being wrapped
     */
    private $remoteStream;

    /**
     * @var int Number of bytes to skip reading due to a write on the buffer
     */
    private $skipReadBytes;

    /**
     * We will treat the buffer object as the body of the stream
     *
     * @param StreamInterface $stream Stream to cache
     * @param StreamInterface $target Optionally specify where data is cached
     */
    public function __construct(
        StreamInterface $stream,
        StreamInterface $target = null
    ) {
        $this->skipReadBytes = 0;
        $this->remoteStream = $stream;
        $this->stream = $target ?? new Stream(fopen('php://temp', 'r+'));
    }

    public function getSize()
    {
        return max($this->stream->getSize(), $this->remoteStream->getSize());
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if (SEEK_SET === $whence) {
            $byte = $offset;
        } elseif (SEEK_CUR === $whence) {
            $byte = $offset + $this->tell();
        } elseif (SEEK_END === $whence) {
            $size = $this->remoteStream->getSize();
            if (null === $size) {
                $size = $this->cacheEntireStream();
            }
            $byte = $size + $offset;
        } else {
            throw new \InvalidArgumentException('Invalid whence');
        }

        $diff = $byte - $this->stream->getSize();

        if (0 < $diff) {
            // If the seek byte is greater the number of read bytes, then read
            // the difference of bytes to cache the bytes and inherently seek.
            $this->read($diff);
        } else {
            // We can just do a normal seek since we've already seen this byte.
            $this->stream->seek($byte);
        }
    }

    public function read($length)
    {
        // Perform a regular read on any previously read data from the buffer
        $data = $this->stream->read($length);
        $remaining = $length - strlen($data);

        // More data was requested so read from the remote stream
        if ($remaining) {
            // If data was written to the buffer in a position that would have
            // been filled from the remote stream, then we must skip bytes on
            // the remote stream to emulate overwriting bytes from that
            // position. This mimics the behavior of other PHP stream wrappers.
            $remoteData = $this->remoteStream->read(
                $remaining + $this->skipReadBytes
            );

            if ($this->skipReadBytes) {
                $len = strlen($remoteData);
                $remoteData = substr($remoteData, $this->skipReadBytes);
                $this->skipReadBytes = max(0, $this->skipReadBytes - $len);
            }

            $data .= $remoteData;
            $this->stream->write($remoteData);
        }

        return $data;
    }

    public function write($string)
    {
        // When appending to the end of the currently read stream, you'll want
        // to skip bytes from being read from the remote stream to emulate
        // other stream wrappers. Basically replacing bytes of data of a fixed
        // length.
        $overflow = (strlen($string) + $this->tell()) - $this->remoteStream->tell();
        if (0 < $overflow) {
            $this->skipReadBytes += $overflow;
        }

        return $this->stream->write($string);
    }

    public function eof() : bool
    {
        return $this->stream->eof() && $this->remoteStream->eof();
    }

    /**
     * Close both the remote stream and buffer stream
     */
    public function close()
    {
        $this->remoteStream->close() && $this->stream->close();
    }

    private function cacheEntireStream()
    {
        $target = new FnStream(['write' => 'strlen']);
        copy_to_stream($this, $target);

        return $this->tell();
    }
}
