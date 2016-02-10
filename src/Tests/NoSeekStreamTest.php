<?php

namespace Thruster\Component\HttpMessage\Tests;

use Thruster\Component\HttpMessage\NoSeekStream;
use function Thruster\Component\HttpMessage\stream_for;

/**
 * Class NoSeekStreamTest
 *
 * @package Thruster\Component\HttpMessage\Tests
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class NoSeekStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot seek a NoSeekStream
     */
    public function testCannotSeek()
    {
        $stream = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'seek'])
            ->getMockForAbstractClass();

        $stream->expects($this->never())
            ->method('seek');

        $stream->expects($this->never())
            ->method('isSeekable');

        $wrapped = new NoSeekStream($stream);

        $this->assertFalse($wrapped->isSeekable());

        $wrapped->seek(2);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot write to a non-writable stream
     */
    public function testHandlesClose()
    {
        $s = stream_for('foo');

        $wrapped = new NoSeekStream($s);
        $wrapped->close();
        $wrapped->write('foo');
    }
}
