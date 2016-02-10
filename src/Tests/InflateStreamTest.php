<?php

namespace Thruster\Component\HttpMessage\Tests;

use Thruster\Component\HttpMessage\InflateStream;
use function Thruster\Component\HttpMessage\stream_for;

/**
 * Class InflateStreamTest
 *
 * @package Thruster\Component\HttpMessage\Tests
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class InflateStreamTest extends \PHPUnit_Framework_TestCase
{
    public function testInflatesStreams()
    {
        $content = gzencode('test');
        $a = stream_for($content);

        $b = new InflateStream($a);
        $this->assertEquals('test', (string) $b);
    }
}
