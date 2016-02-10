<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\UriInterface;

/**
 * Class Uri
 *
 * @package Thruster\Component\HttpMessage
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class Uri implements UriInterface
{
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    const REPLACE_QUERY = ['=' => '%3D', '&' => '%26'];
    const SCHEMES = [
        'http'  => 80,
        'https' => 443,
    ];

    /**
     * @var string Uri scheme.
     */
    private $scheme;

    /**
     * @var string Uri user info.
     */
    private $userInfo;

    /**
     * @var string Uri host.
     */
    private $host;

    /**
     * @var int Uri port.
     */
    private $port;

    /**
     * @var string Uri path.
     */
    private $path;

    /**
     * @var string Uri query string.
     */
    private $query;

    /**
     * @var string Uri fragment.
     */
    private $fragment;

    /**
     * @param string $uri URI to parse and wrap.
     */
    public function __construct(string $uri = '')
    {
        $this->scheme   = '';
        $this->userInfo = '';
        $this->host     = '';
        $this->path     = '';
        $this->query    = '';
        $this->fragment = '';

        if (null !== $uri) {
            $parts = parse_url($uri);

            if (false === $parts) {
                throw new \InvalidArgumentException("Unable to parse URI: $uri");
            }

            $this->applyParts($parts);
        }
    }

    public function __toString() : string
    {
        return self::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->getPath(),
            $this->query,
            $this->fragment
        );
    }

    /**
     * Removes dot segments from a path and returns the new path.
     *
     * @param string $path
     *
     * @return string
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    public static function removeDotSegments(string $path) : string
    {
        static $noopPaths = ['' => true, '/' => true, '*' => true];
        static $ignoreSegments = ['.' => true, '..' => true];

        if (isset($noopPaths[$path])) {
            return $path;
        }

        $results  = [];
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment == '..') {
                array_pop($results);
            } elseif (false === isset($ignoreSegments[$segment])) {
                $results[] = $segment;
            }
        }

        $newPath = implode('/', $results);
        // Add the leading slash if necessary
        if (substr($path, 0, 1) === '/' &&
            substr($newPath, 0, 1) !== '/'
        ) {
            $newPath = '/' . $newPath;
        }

        // Add the trailing slash if necessary
        if ($newPath != '/' && isset($ignoreSegments[end($segments)])) {
            $newPath .= '/';
        }

        return $newPath;
    }

    /**
     * Resolve a base URI with a relative URI and return a new URI.
     *
     * @param UriInterface $base Base URI
     * @param string       $rel  Relative URI
     *
     * @return UriInterface
     */
    public static function resolve(UriInterface $base, string $rel) : UriInterface
    {
        if (null === $rel || '' === $rel) {
            return $base;
        }

        if (false === ($rel instanceof UriInterface)) {
            $rel = new self($rel);
        }

        // Return the relative uri as-is if it has a scheme.
        if ($rel->getScheme()) {
            return $rel->withPath(static::removeDotSegments($rel->getPath()));
        }

        $relParts = [
            'scheme'    => $rel->getScheme(),
            'authority' => $rel->getAuthority(),
            'path'      => $rel->getPath(),
            'query'     => $rel->getQuery(),
            'fragment'  => $rel->getFragment()
        ];

        $parts = [
            'scheme'    => $base->getScheme(),
            'authority' => $base->getAuthority(),
            'path'      => $base->getPath(),
            'query'     => $base->getQuery(),
            'fragment'  => $base->getFragment()
        ];

        if (false === empty($relParts['authority'])) {
            $parts['authority'] = $relParts['authority'];
            $parts['path']      = self::removeDotSegments($relParts['path']);
            $parts['query']     = $relParts['query'];
            $parts['fragment']  = $relParts['fragment'];
        } elseif (false === empty($relParts['path'])) {
            if ('/' === substr($relParts['path'], 0, 1)) {
                $parts['path']     = self::removeDotSegments($relParts['path']);
                $parts['query']    = $relParts['query'];
                $parts['fragment'] = $relParts['fragment'];
            } else {
                if (false === empty($parts['authority']) && empty($parts['path'])) {
                    $mergedPath = '/';
                } else {
                    $mergedPath = substr($parts['path'], 0, strrpos($parts['path'], '/') + 1);
                }

                $parts['path']     = self::removeDotSegments($mergedPath . $relParts['path']);
                $parts['query']    = $relParts['query'];
                $parts['fragment'] = $relParts['fragment'];
            }
        } elseif (false === empty($relParts['query'])) {
            $parts['query'] = $relParts['query'];
        } elseif (null != $relParts['fragment']) {
            $parts['fragment'] = $relParts['fragment'];
        }

        return new self(static::createUriString(
            $parts['scheme'],
            $parts['authority'],
            $parts['path'],
            $parts['query'],
            $parts['fragment']
        ));
    }

    /**
     * Create a new URI with a specific query string value removed.
     *
     * Any existing query string values that exactly match the provided key are
     * removed.
     *
     * Note: this function will convert "=" to "%3D" and "&" to "%26".
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string       $key Query string key value pair to remove.
     *
     * @return UriInterface
     */
    public static function withoutQueryValue(UriInterface $uri, string $key) : UriInterface
    {
        $current = $uri->getQuery();
        if (!$current) {
            return $uri;
        }

        $result = [];
        foreach (explode('&', $current) as $part) {
            if ($key !== explode('=', $part)[0]) {
                $result[] = $part;
            };
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Create a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     *
     * Note: this function will convert "=" to "%3D" and "&" to "%26".
     *
     * @param UriInterface $uri   URI to use as a base.
     * @param string       $key   Key to set.
     * @param string       $value Value to set.
     *
     * @return UriInterface
     */
    public static function withQueryValue(UriInterface $uri, string $key, string $value = null) : UriInterface
    {
        $current = $uri->getQuery();
        $key     = strtr($key, static::REPLACE_QUERY);

        if (!$current) {
            $result = [];
        } else {
            $result = [];
            foreach (explode('&', $current) as $part) {
                if (explode('=', $part)[0] !== $key) {
                    $result[] = $part;
                };
            }
        }

        if ($value !== null) {
            $result[] = $key . '=' . strtr($value, static::REPLACE_QUERY);
        } else {
            $result[] = $key;
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Create a URI from a hash of parse_url parts.
     *
     * @param array $parts
     *
     * @return self
     */
    public static function fromParts(array $parts) : self
    {
        $uri = new self();
        $uri->applyParts($parts);

        return $uri;
    }

    public function getScheme() : string
    {
        return $this->scheme;
    }

    public function getAuthority() : string
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;
        if (false === empty($this->userInfo)) {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->isNonStandardPort($this->scheme, $this->host, $this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo() : string
    {
        return $this->userInfo;
    }

    public function getHost() : string
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getPath() : string
    {
        return $this->path ?? '';
    }

    public function getQuery() : string
    {
        return $this->query;
    }

    public function getFragment() : string
    {
        return $this->fragment;
    }

    public function withScheme($scheme) : self
    {
        $scheme = $this->filterScheme($scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        $new         = clone $this;
        $new->scheme = $scheme;
        $new->port   = $new->filterPort($new->scheme, $new->host, $new->port);

        return $new;
    }

    public function withUserInfo($user, $password = null) : self
    {
        $info = $user;
        if (null !== $password) {
            $info .= ':' . $password;
        }

        if ($info === $this->userInfo) {
            return $this;
        }

        $new           = clone $this;
        $new->userInfo = $info;

        return $new;
    }

    public function withHost($host) : self
    {
        if ($host === $this->host) {
            return $this;
        }

        $new       = clone $this;
        $new->host = $host;

        return $new;
    }

    public function withPort($port) : self
    {
        $port = $this->filterPort($this->scheme, $this->host, $port);

        if ($port === $this->port) {
            return $this;
        }

        $new       = clone $this;
        $new->port = $port;

        return $new;
    }

    public function withPath($path) : self
    {
        if (false === is_string($path)) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must be a string'
            );
        }

        $path = $this->filterPath($path);

        if ($path === $this->path) {
            return $this;
        }

        $new       = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withQuery($query) : self
    {
        if (false === is_string($query) && false === method_exists($query, '__toString')) {
            throw new \InvalidArgumentException(
                'Query string must be a string'
            );
        }

        $query = (string) $query;
        if ('?' === substr($query, 0, 1)) {
            $query = substr($query, 1);
        }

        $query = $this->filterQueryAndFragment($query);

        if ($query === $this->query) {
            return $this;
        }

        $new        = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment($fragment) : self
    {
        if ('#' === substr($fragment, 0, 1)) {
            $fragment = substr($fragment, 1);
        }

        $fragment = $this->filterQueryAndFragment($fragment);

        if ($fragment === $this->fragment) {
            return $this;
        }

        $new           = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * Apply parse_url parts to a URI.
     *
     * @param array $parts Array of parse_url parts to apply.
     */
    private function applyParts(array $parts)
    {
        $this->scheme   = isset($parts['scheme'])
            ? $this->filterScheme($parts['scheme'])
            : '';
        $this->userInfo = isset($parts['user']) ? $parts['user'] : '';
        $this->host     = isset($parts['host']) ? $parts['host'] : '';
        $this->port     = !empty($parts['port'])
            ? $this->filterPort($this->scheme, $this->host, $parts['port'])
            : null;
        $this->path     = isset($parts['path'])
            ? $this->filterPath($parts['path'])
            : '';
        $this->query    = isset($parts['query'])
            ? $this->filterQueryAndFragment($parts['query'])
            : '';
        $this->fragment = isset($parts['fragment'])
            ? $this->filterQueryAndFragment($parts['fragment'])
            : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * Create a URI string from its various parts
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     *
     * @return string
     */
    private static function createUriString(
        string $scheme,
        string $authority,
        string $path,
        string $query,
        string $fragment
    ) : string {
        $uri = '';

        if (false === empty($scheme)) {
            $uri .= $scheme . ':';
        }

        $hierPart = '';

        if (false === empty($authority)) {
            if (false === empty($scheme)) {
                $hierPart .= '//';
            }

            $hierPart .= $authority;
        }

        if (null != $path) {
            // Add a leading slash if necessary.
            if ($hierPart && '/' !== substr($path, 0, 1)) {
                $hierPart .= '/';
            }

            $hierPart .= $path;
        }

        $uri .= $hierPart;

        if (null != $query) {
            $uri .= '?' . $query;
        }

        if (null != $fragment) {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Is a given port non-standard for the current scheme?
     *
     * @param string $scheme
     * @param string $host
     * @param int    $port
     *
     * @return bool
     */
    private static function isNonStandardPort(string $scheme = null, string $host = null, int $port = null) : bool
    {
        if (null === $scheme && $port) {
            return true;
        }

        if (null === $host || null === $port) {
            return false;
        }

        return false === isset(static::SCHEMES[$scheme]) || $port !== static::SCHEMES[$scheme];
    }

    /**
     * @param string $scheme
     *
     * @return string
     */
    private function filterScheme(string $scheme) : string
    {
        $scheme = strtolower($scheme);
        $scheme = rtrim($scheme, ':/');

        return $scheme;
    }

    /**
     * @param string $scheme
     * @param string $host
     * @param int    $port
     *
     * @return int|null
     *
     * @throws \InvalidArgumentException If the port is invalid.
     */
    private function filterPort(string $scheme, string $host, int $port = null)
    {
        if (null !== $port) {
            $port = (int) $port;
            if (1 > $port || 0xffff < $port) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid port: %d. Must be between 1 and 65535', $port)
                );
            }
        }

        return $this->isNonStandardPort($scheme, $host, $port) ? $port : null;
    }

    /**
     * Filters the path of a URI
     *
     * @param $path
     *
     * @return string
     */
    private function filterPath(string $path) : string
    {
        return preg_replace_callback(
            '/(?:[^' . static::CHAR_UNRESERVED . static::CHAR_SUB_DELIMS . ':@\/%]+|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawurlencodeMatchZero'],
            $path
        );
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param $str
     *
     * @return string
     */
    private function filterQueryAndFragment(string $str) : string
    {
        return preg_replace_callback(
            '/(?:[^' . static::CHAR_UNRESERVED . static::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawurlencodeMatchZero'],
            $str
        );
    }

    private function rawurlencodeMatchZero(array $match) : string
    {
        return rawurlencode($match[0]);
    }
}
