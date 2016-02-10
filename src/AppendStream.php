<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\StreamInterface;

/**
 * Class AppendStream
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class AppendStream implements StreamInterface
{
    /**
     * @var StreamInterface[] Streams being decorated
     */
    private $streams;

    /**
     * @var bool
     */
    private $seekable;

    /**
     * @var int
     */
    private $current;

    /**
     * @var int
     */
    private $pos;

    /**
     * @var bool
     */
    private $detached;

    /**
     * @param StreamInterface[] $streams Streams to decorate. Each stream must
     *                                   be readable.
     */
    public function __construct(array $streams = [])
    {
        $this->streams = [];
        $this->seekable = true;
        $this->current = 0;
        $this->pos = 0;
        $this->detached = false;

        foreach ($streams as $stream) {
            $this->addStream($stream);
        }
    }

    public function __toString()
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Add a stream to the AppendStream
     *
     * @param StreamInterface $stream Stream to append. Must be readable.
     *
     * @return $this
     * @throws \InvalidArgumentException if the stream is not readable
     */
    public function addStream(StreamInterface $stream) : self
    {
        if (false === $stream->isReadable()) {
            throw new \InvalidArgumentException('Each stream must be readable');
        }

        // The stream is only seekable if all streams are seekable
        if (false === $stream->isSeekable()) {
            $this->seekable = false;
        }

        $this->streams[] = $stream;

        return $this;
    }

    public function getContents()
    {
        return copy_to_string($this);
    }

    /**
     * Closes each attached stream.
     *
     * {@inheritdoc}
     */
    public function close()
    {
        $this->pos = $this->current = 0;

        foreach ($this->streams as $stream) {
            $stream->close();
        }

        $this->streams = [];
    }

    /**
     * Detaches each attached stream
     *
     * {@inheritdoc}
     */
    public function detach()
    {
        $this->close();

        $this->detached = true;
    }

    public function tell()
    {
        return $this->pos;
    }

    /**
     * Tries to calculate the size by adding the size of each stream.
     *
     * If any of the streams do not return a valid number, then the size of the
     * append stream cannot be determined and null is returned.
     *
     * {@inheritdoc}
     */
    public function getSize()
    {
        $size = 0;

        foreach ($this->streams as $stream) {
            $s = $stream->getSize();

            if (null === $s) {
                return null;
            }

            $size += $s;
        }

        return $size;
    }

    public function eof() : bool
    {
        return !$this->streams || ($this->current >= count($this->streams) - 1 &&
            $this->streams[$this->current]->eof());
    }

    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Attempts to seek to the given position. Only supports SEEK_SET.
     *
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (false === $this->seekable) {
            throw new \RuntimeException('This AppendStream is not seekable');
        } elseif ($whence !== SEEK_SET) {
            throw new \RuntimeException('The AppendStream can only seek with SEEK_SET');
        }

        $this->pos = $this->current = 0;

        // Rewind each stream
        foreach ($this->streams as $i => $stream) {
            try {
                $stream->rewind();
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    'Unable to seek stream ' . $i . ' of the AppendStream',
                    0,
                    $e
                );
            }
        }

        // Seek to the actual position by reading from each stream
        while ($this->pos < $offset && false === $this->eof()) {
            $result = $this->read(min(8096, $offset - $this->pos));

            if ('' === $result) {
                break;
            }
        }
    }

    /**
     * Reads from all of the appended streams until the length is met or EOF.
     *
     * {@inheritdoc}
     */
    public function read($length)
    {
        $buffer = '';
        $total = count($this->streams) - 1;
        $remaining = $length;
        $progressToNext = false;

        while (0 < $remaining) {
            // Progress to the next stream if needed.
            if ($progressToNext || $this->streams[$this->current]->eof()) {
                $progressToNext = false;

                if ($total === $this->current) {
                    break;
                }

                $this->current++;
            }

            $result = $this->streams[$this->current]->read($remaining);

            // Using a loose comparison here to match on '', false, and null
            if (null === $result) {
                $progressToNext = true;

                continue;
            }

            $buffer .= $result;
            $remaining = $length - strlen($buffer);
        }

        $this->pos += strlen($buffer);

        return $buffer;
    }

    public function isReadable() : bool
    {
        return true;
    }

    public function isWritable() : bool
    {
        return false;
    }

    public function isSeekable() : bool
    {
        return $this->seekable;
    }

    public function write($string)
    {
        throw new \RuntimeException('Cannot write to an AppendStream');
    }

    public function getMetadata($key = null)
    {
        return $key ? null : [];
    }
}
