<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\StreamInterface;

/**
 * Class LazyOpenStream
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class LazyOpenStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     * @var string File to open
     */
    private $filename;

    /**
     * @var string $mode
     */
    private $mode;

    /**
     * @param string $filename File to lazily open
     * @param string $mode     fopen mode to use when opening the stream
     */
    public function __construct(string $filename, string $mode)
    {
        $this->filename = $filename;
        $this->mode = $mode;
    }

    /**
     * Creates the underlying stream lazily when required.
     *
     * @return StreamInterface
     */
    protected function createStream()
    {
        return stream_for(try_fopen($this->filename, $this->mode));
    }
}
