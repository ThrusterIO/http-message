<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Trait MessageTrait
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
trait MessageTrait
{
    /**
     * @var array Cached HTTP header collection with lowercase key to values
     */
    private $headers = [];

    /**
     * @var array Actual key to list of values per header.
     */
    private $headerLines = [];

    /**
     * @var string
     */
    private $protocol = '1.1';

    /**
     * @var StreamInterface
     */
    private $stream;

    public function getProtocolVersion() : string
    {
        return $this->protocol;
    }

    public function withProtocolVersion($version) : MessageInterface
    {
        if ($version === $this->protocol) {
            return $this;
        }

        $new           = clone $this;
        $new->protocol = $version;

        return $new;
    }

    public function getHeaders() : array
    {
        return $this->headerLines;
    }

    public function hasHeader($header) : bool
    {
        return isset($this->headers[strtolower($header)]);
    }

    public function getHeader($header) : array
    {
        $name = strtolower($header);

        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine($header) : string
    {
        return implode(', ', $this->getHeader($header));
    }

    public function withHeader($header, $value) : MessageInterface
    {
        $new = clone $this;

        $header = trim($header);
        $name   = strtolower($header);

        if (false === is_array($value)) {
            $new->headers[$name] = [trim($value)];
        } else {
            $new->headers[$name] = $value;

            foreach ($new->headers[$name] as &$v) {
                $v = trim($v);
            }
        }

        // Remove the header lines.
        foreach (array_keys($new->headerLines) as $key) {
            if ($name === strtolower($key)) {
                unset($new->headerLines[$key]);
            }
        }

        // Add the header line.
        $new->headerLines[$header] = $new->headers[$name];

        return $new;
    }

    public function withAddedHeader($header, $value) : MessageInterface
    {
        if (false === $this->hasHeader($header)) {
            return $this->withHeader($header, $value);
        }

        $new = clone $this;

        $new->headers[strtolower($header)][] = $value;
        $new->headerLines[$header][]         = $value;

        return $new;
    }

    public function withoutHeader($header) : MessageInterface
    {
        if (false === $this->hasHeader($header)) {
            return $this;
        }

        $new = clone $this;

        $name = strtolower($header);
        unset($new->headers[$name]);

        foreach (array_keys($new->headerLines) as $key) {
            if ($name === strtolower($key)) {
                unset($new->headerLines[$key]);
            }
        }

        return $new;
    }

    public function getBody() : StreamInterface
    {
        if (!$this->stream) {
            $this->stream = stream_for('');
        }

        return $this->stream;
    }

    public function withBody(StreamInterface $body) : MessageInterface
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new         = clone $this;
        $new->stream = $body;

        return $new;
    }

    private function setHeaders(array $headers)
    {
        $this->headerLines = [];
        $this->headers     = [];

        foreach ($headers as $header => $value) {
            $header = trim($header);
            $name   = strtolower($header);
            if (false === is_array($value)) {
                $value                        = trim($value);
                $this->headers[$name][]       = $value;
                $this->headerLines[$header][] = $value;
            } else {
                foreach ($value as $v) {
                    $v                            = trim($v);
                    $this->headers[$name][]       = $v;
                    $this->headerLines[$header][] = $v;
                }
            }
        }
    }
}
