<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\StreamInterface;

/**
 * Class NoSeekStream
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class NoSeekStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException('Cannot seek a NoSeekStream');
    }

    public function isSeekable() : bool
    {
        return false;
    }
}
