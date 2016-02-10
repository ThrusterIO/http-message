<?php

namespace Thruster\Component\HttpMessage;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class Request
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class Request implements RequestInterface
{
    use MessageTrait {
        withHeader as protected withParentHeader;
    }

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $requestTarget;

    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @param string                     $method          HTTP method for the request.
     * @param string|UriInterface        $uri             URI for the request.
     * @param array                           $headers         Headers for the message.
     * @param string|resource|StreamInterface $body            Message body.
     * @param string                          $protocolVersion HTTP protocol version.
     *
     * @throws InvalidArgumentException for an invalid URI
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1'
    ) {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        } elseif (false === ($uri instanceof UriInterface)) {
            throw new \InvalidArgumentException(
                'URI must be a string or Psr\Http\Message\UriInterface'
            );
        }

        $this->method = strtoupper($method);
        $this->uri    = $uri;
        $this->setHeaders($headers);
        $this->protocol = $protocolVersion;

        $host = $uri->getHost();
        if ($host && false === $this->hasHeader('Host')) {
            $this->updateHostFromUri($host);
        }

        if ($body) {
            $this->stream = stream_for($body);
        }
    }

    public function getRequestTarget() : string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if (null == $target) {
            $target = '/';
        }

        if ($this->uri->getQuery()) {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget($requestTarget) : self
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target provided; cannot contain whitespace'
            );
        }

        $new                = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod() : string
    {
        return $this->method;
    }

    public function withMethod($method) : self
    {
        $new         = clone $this;
        $new->method = strtoupper($method);

        return $new;
    }

    public function getUri() : UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false) : self
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new      = clone $this;
        $new->uri = $uri;

        if (false === $preserveHost) {
            if ($host = $uri->getHost()) {
                $new->updateHostFromUri($host);
            }
        }

        return $new;
    }

    public function withHeader($header, $value) : self
    {
        /** @var Request $newInstance */
        $newInstance = $this->withParentHeader($header, $value);
        return $newInstance;
    }

    private function updateHostFromUri(string $host)
    {
        // Ensure Host is the first header.
        // See: http://tools.ietf.org/html/rfc7230#section-5.4
        if ($port = $this->uri->getPort()) {
            $host .= ':' . $port;
        }

        $this->headerLines = ['Host' => [$host]] + $this->headerLines;
        $this->headers     = ['host' => [$host]] + $this->headers;
    }
}
