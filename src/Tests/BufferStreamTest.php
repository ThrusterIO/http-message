<?php

namespace Thruster\Component\HttpMessage\Tests;

use Thruster\Component\HttpMessage\BufferStream;

/**
 * Class BufferStreamTest
 *
 * @package Thruster\Component\HttpMessage\Tests
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class BufferStreamTest extends \PHPUnit_Framework_TestCase
{
    public function testHasMetadata()
    {
        $bufferStream = new BufferStream(10);
        $this->assertTrue($bufferStream->isReadable());
        $this->assertTrue($bufferStream->isWritable());
        $this->assertFalse($bufferStream->isSeekable());
        $this->assertEquals(null, $bufferStream->getMetadata('foo'));
        $this->assertEquals(10, $bufferStream->getMetadata('hwm'));
        $this->assertEquals([], $bufferStream->getMetadata());
    }

    public function testRemovesReadDataFromBuffer()
    {
        $bufferStream = new BufferStream();
        $this->assertEquals(3, $bufferStream->write('foo'));
        $this->assertEquals(3, $bufferStream->getSize());
        $this->assertFalse($bufferStream->eof());
        $this->assertEquals('foo', $bufferStream->read(10));
        $this->assertTrue($bufferStream->eof());
        $this->assertEquals('', $bufferStream->read(10));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot determine the position of a BufferStream
     */
    public function testCanCastToStringOrGetContents()
    {
        $bufferStream = new BufferStream();
        $bufferStream->write('foo');
        $bufferStream->write('baz');

        $this->assertEquals('foo', $bufferStream->read(3));

        $bufferStream->write('bar');

        $this->assertEquals('bazbar', (string) $bufferStream);

        $bufferStream->tell();
    }

    public function testDetachClearsBuffer()
    {
        $bufferStream = new BufferStream();
        $bufferStream->write('foo');
        $bufferStream->detach();

        $this->assertTrue($bufferStream->eof());
        $this->assertEquals(3, $bufferStream->write('abc'));
        $this->assertEquals('abc', $bufferStream->read(10));
    }

    public function testExceedingHighwaterMarkReturnsFalseButStillBuffers()
    {
        $b = new BufferStream(5);

        $this->assertEquals(3, $b->write('hi '));
        $this->assertFalse($b->write('hello'));
        $this->assertEquals('hi hello', (string) $b);
        $this->assertEquals(4, $b->write('test'));
    }
}
