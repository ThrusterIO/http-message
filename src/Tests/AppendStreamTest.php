<?php

namespace Thruster\Component\HttpMessage\Tests;

use Thruster\Component\HttpMessage\AppendStream;
use function Thruster\Component\HttpMessage\stream_for;

/**
 * Class AppendStreamTest
 *
 * @package Thruster\Component\HttpMessage\Tests
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class AppendStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Each stream must be readable
     */
    public function testValidatesStreamsAreReadable()
    {
        $appendStream = new AppendStream();

        $stream = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isReadable'])
            ->getMockForAbstractClass();

        $stream->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(false));

        $appendStream->addStream($stream);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The AppendStream can only seek with SEEK_SET
     */
    public function testValidatesSeekType()
    {
        $appendStream = new AppendStream();

        $appendStream->seek(100, SEEK_CUR);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to seek stream 0 of the AppendStream
     */
    public function testTriesToRewindOnSeek()
    {
        $appendStream = new AppendStream();

        $stream = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isReadable', 'rewind', 'isSeekable'])
            ->getMockForAbstractClass();

        $stream->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));

        $stream->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(true));

        $stream->expects($this->once())
            ->method('rewind')
            ->will($this->throwException(new \RuntimeException()));

        $appendStream->addStream($stream);
        $appendStream->seek(10);
    }

    public function testSeeksToPositionByReading()
    {
        $appendStream = new AppendStream([
            stream_for('foo'),
            stream_for('bar'),
            stream_for('baz'),
        ]);

        $appendStream->seek(3);
        $this->assertEquals(3, $appendStream->tell());
        $this->assertEquals('bar', $appendStream->read(3));

        $appendStream->seek(6);
        $this->assertEquals(6, $appendStream->tell());
        $this->assertEquals('baz', $appendStream->read(3));
    }

    public function testDetachesEachStream()
    {
        $streamOne = stream_for('foo');
        $streamTwo = stream_for('bar');

        $appendStream = new AppendStream([$streamOne, $streamTwo]);

        $this->assertSame('foobar', (string) $appendStream);

        $appendStream->detach();

        $this->assertSame('', (string) $appendStream);
        $this->assertSame(0, $appendStream->getSize());
    }

    public function testClosesEachStream()
    {
        $stream = stream_for('foo');

        $appendStream = new AppendStream([$stream]);
        $appendStream->close();

        $this->assertSame('', (string) $appendStream);
    }

    /**
     * @expectedExceptionMessage Cannot write to an AppendStream
     * @expectedException \RuntimeException
     */
    public function testIsNotWritable()
    {
        $appendStream = new AppendStream([stream_for('foo')]);

        $this->assertFalse($appendStream->isWritable());
        $this->assertTrue($appendStream->isSeekable());
        $this->assertTrue($appendStream->isReadable());

        $appendStream->write('foo');
    }

    public function testDoesNotNeedStreams()
    {
        $appendStream = new AppendStream();
        $this->assertEquals('', (string) $appendStream);
    }

    public function testCanReadFromMultipleStreams()
    {
        $appendStream = new AppendStream([
            stream_for('foo'),
            stream_for('bar'),
            stream_for('baz'),
        ]);

        $this->assertFalse($appendStream->eof());
        $this->assertSame(0, $appendStream->tell());
        $this->assertEquals('foo', $appendStream->read(3));
        $this->assertEquals('bar', $appendStream->read(3));
        $this->assertEquals('baz', $appendStream->read(3));
        $this->assertSame('', $appendStream->read(1));
        $this->assertTrue($appendStream->eof());
        $this->assertSame(9, $appendStream->tell());
        $this->assertEquals('foobarbaz', (string) $appendStream);
    }

    public function testCanDetermineSizeFromMultipleStreams()
    {
        $appendStream = new AppendStream([
            stream_for('foo'),
            stream_for('bar')
        ]);

        $this->assertEquals(6, $appendStream->getSize());

        $stream = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'isReadable'])
            ->getMockForAbstractClass();

        $stream->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(null));

        $stream->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));

        $appendStream->addStream($stream);

        $this->assertNull($appendStream->getSize());
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $stream = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'read', 'isReadable', 'eof'])
            ->getMockForAbstractClass();

        $stream->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(true));

        $stream->expects($this->once())
            ->method('read')
            ->will($this->throwException(new \RuntimeException('foo')));

        $stream->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));

        $stream->expects($this->any())
            ->method('eof')
            ->will($this->returnValue(false));

        $appendStream = new AppendStream([$stream]);

        $this->assertFalse($appendStream->eof());
        $this->assertSame('', (string) $appendStream);
    }

    public function testCanDetach()
    {
        $stream = new AppendStream();

        $stream->detach();
    }

    public function testReturnsEmptyMetadata()
    {
        $stream = new AppendStream();

        $this->assertEquals([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('foo'));
    }
}
