<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\StreamInterface;

/**
 * Class Stream
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class Stream implements StreamInterface
{
    /** @var array Hash of readable and writable stream types */
    const READ_WRITE_HASH = [
        'read'  => [
            'r'   => true,
            'w+'  => true,
            'r+'  => true,
            'x+'  => true,
            'c+'  => true,
            'rb'  => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'rt'  => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a+'  => true
        ],
        'write' => [
            'w'   => true,
            'w+'  => true,
            'rw'  => true,
            'r+'  => true,
            'x+'  => true,
            'c+'  => true,
            'wb'  => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a'   => true,
            'a+'  => true
        ]
    ];

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var int
     */
    private $size;

    /**
     * @var bool
     */
    private $seekable;

    /**
     * @var bool
     */
    private $readable;

    /**
     * @var bool
     */
    private $writable;

    /**
     * @var array|mixed|null
     */
    private $uri;

    /**
     * @var array
     */
    private $customMetadata;

    /**
     * This constructor accepts an associative array of options.
     *
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknownledge, then you can
     *   provide that size, in bytes.
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     *
     * @param resource $stream  Stream resource to wrap.
     * @param array    $options Associative array of options.
     *
     * @throws \InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct($stream, $options = [])
    {
        if (false === is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        if (isset($options['size'])) {
            $this->size = $options['size'];
        }

        $this->customMetadata = $options['metadata'] ?? [];

        $this->stream   = $stream;
        $meta           = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = isset(static::READ_WRITE_HASH['read'][$meta['mode']]);
        $this->writable = isset(static::READ_WRITE_HASH['write'][$meta['mode']]);
        $this->uri = $this->getMetadata('uri');
    }

    public function __get($name)
    {
        if ('stream' == $name) {
            throw new \RuntimeException('The stream is detached');
        }

        throw new \BadMethodCallException('No value for ' . $name);
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }

    public function __toString() : string
    {
        try {
            $this->seek(0);
            return (string) stream_get_contents($this->stream);
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getContents() : string
    {
        $contents = stream_get_contents($this->stream);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function close()
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }

            $this->detach();
        }
    }

    public function detach()
    {
        if (false === isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        unset($this->stream);
        $this->size     = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    public function getSize()
    {
        if (null !== $this->size) {
            return $this->size;
        }

        if (false === isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }

        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];

            return $this->size;
        }

        return null;
    }

    public function isReadable() : bool
    {
        return $this->readable;
    }

    public function isWritable() : bool
    {
        return $this->writable;
    }

    public function isSeekable() : bool
    {
        return $this->seekable;
    }

    public function eof() : bool
    {
        return !$this->stream || feof($this->stream);
    }

    public function tell() : int
    {
        $result = ftell($this->stream);

        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if (false === $this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        } elseif (-1 === fseek($this->stream, $offset, $whence)) {
            throw new \RuntimeException('Unable to seek to stream position '
                . $offset . ' with whence ' . var_export($whence, true));
        }
    }

    public function read($length)
    {
        if (false === $this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        return fread($this->stream, $length);
    }

    public function write($string)
    {
        if (false === $this->writable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result     = fwrite($this->stream, $string);

        if (false === $result) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    public function getMetadata($key = null)
    {
        if (false === isset($this->stream)) {
            return $key ? null : [];
        } elseif (null === $key) {
            return $this->customMetadata + stream_get_meta_data($this->stream);
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }

        $meta = stream_get_meta_data($this->stream);

        return isset($meta[$key]) ? $meta[$key] : null;
    }
}
