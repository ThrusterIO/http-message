<?php

namespace Thruster\Component\HttpMessage;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Returns a UriInterface for the given value.
 *
 * This function accepts a string or {@see Psr\Http\Message\UriInterface} and
 * returns a UriInterface for the given value. If the value is already a
 * `UriInterface`, it is returned as-is.
 *
 * @param string|UriInterface $uri
 *
 * @return UriInterface
 * @throws \InvalidArgumentException
 */
function uri_for($uri)
{
    if ($uri instanceof UriInterface) {
        return $uri;
    } elseif (is_string($uri)) {
        return new Uri($uri);
    }

    throw new \InvalidArgumentException('URI must be a string or UriInterface');
}

/**
 * Create a new stream based on the input type.
 *
 * Options is an associative array that can contain the following keys:
 * - metadata: Array of custom metadata.
 * - size: Size of the stream.
 *
 * @param resource|string|StreamInterface $resource Entity body data
 * @param array                           $options  Additional options
 *
 * @return Stream
 * @throws \InvalidArgumentException if the $resource arg is not valid.
 */
function stream_for($resource = '', array $options = [])
{
    switch (gettype($resource)) {
        case 'string':
            $stream = fopen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, $resource);
                fseek($stream, 0);
            }
            return new Stream($stream, $options);
        case 'resource':
            return new Stream($resource, $options);
        case 'object':
            if ($resource instanceof StreamInterface) {
                return $resource;
            } elseif ($resource instanceof \Iterator) {
                return new PumpStream(function () use ($resource) {
                    if (!$resource->valid()) {
                        return false;
                    }
                    $result = $resource->current();
                    $resource->next();
                    return $result;
                }, $options);
            } elseif (method_exists($resource, '__toString')) {
                return stream_for((string) $resource, $options);
            }
            break;
        case 'NULL':
            return new Stream(fopen('php://temp', 'r+'), $options);
    }

    if (is_callable($resource)) {
        return new PumpStream($resource, $options);
    }

    throw new \InvalidArgumentException('Invalid resource type: ' . gettype($resource));
}

/**
 * Copy the contents of a stream into a string until the given number of
 * bytes have been read.
 *
 * @param StreamInterface $stream Stream to read
 * @param int             $maxLen Maximum number of bytes to read. Pass -1
 *                                to read the entire stream.
 * @return string
 * @throws \RuntimeException on error.
 */
function copy_to_string(StreamInterface $stream, int $maxLen = -1) : string
{
    $buffer = '';

    if (-1 === $maxLen) {
        while (false === $stream->eof()) {
            $buf = $stream->read(1048576);

            // Using a loose equality here to match on '' and false.
            if ($buf == null) {
                break;
            }

            $buffer .= $buf;
        }
        return $buffer;
    }

    $len = 0;
    while (false === $stream->eof() && $len < $maxLen) {
        $buf = $stream->read($maxLen - $len);
        // Using a loose equality here to match on '' and false.
        if (null === $buf) {
            break;
        }

        $buffer .= $buf;
        $len = strlen($buffer);
    }

    return $buffer;
}



/**
 * Safely opens a PHP stream resource using a filename.
 *
 * When fopen fails, PHP normally raises a warning. This function adds an
 * error handler that checks for errors and throws an exception instead.
 *
 * @param string $filename File to open
 * @param string $mode     Mode used to open the file
 *
 * @return resource
 * @throws \RuntimeException if the file cannot be opened
 */
function try_fopen($filename, $mode)
{
    $ex = null;
    set_error_handler(function () use ($filename, $mode, &$ex) {
        $ex = new \RuntimeException(sprintf(
            'Unable to open %s using mode %s: %s',
            $filename,
            $mode,
            func_get_args()[1]
        ));
    });

    $handle = fopen($filename, $mode);
    restore_error_handler();

    if ($ex) {
        /** @var $ex \RuntimeException */
        throw $ex;
    }

    return $handle;
}

/**
 * Copy the contents of a stream into another stream until the given number
 * of bytes have been read.
 *
 * @param StreamInterface $source Stream to read from
 * @param StreamInterface $dest   Stream to write to
 * @param int             $maxLen Maximum number of bytes to read. Pass -1
 *                                to read the entire stream.
 *
 * @throws \RuntimeException on error.
 */
function copy_to_stream(StreamInterface $source, StreamInterface $dest, $maxLen = -1) {
    if (-1 === $maxLen) {
        while (!$source->eof()) {
            if (!$dest->write($source->read(1048576))) {
                break;
            }
        }
        return;
    }

    $bytes = 0;
    while (false === $source->eof()) {
        $buf = $source->read($maxLen - $bytes);
        if (false === ($len = strlen($buf))) {
            break;
        }

        $bytes += $len;
        $dest->write($buf);

        if ($bytes == $maxLen) {
            break;
        }
    }
}


/**
 * Read a line from the stream up to the maximum allowed buffer length
 *
 * @param StreamInterface $stream    Stream to read from
 * @param int             $maxLength Maximum buffer length
 *
 * @return string|bool
 */
function readline(StreamInterface $stream, $maxLength = null)
{
    $buffer = '';
    $size = 0;

    while (false === $stream->eof()) {
        // Using a loose equality here to match on '' and false.
        if (null === ($byte = $stream->read(1))) {
            return $buffer;
        }

        $buffer .= $byte;
        // Break when a new line is found or the max length - 1 is reached
        if ($byte === PHP_EOL || ++$size === $maxLength - 1) {
            break;
        }
    }

    return $buffer;
}

/**
 * Determines the mimetype of a file by looking at its extension.
 *
 * @param $filename
 *
 * @return null|string
 */
function mimetype_from_filename(string $filename)
{
    return mimetype_from_extension(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Maps a file extensions to a mimetype.
 *
 * @param $extension string The file extension.
 *
 * @return string|null
 * @link http://svn.apache.org/repos/asf/httpd/httpd/branches/1.3.x/conf/mime.types
 */
function mimetype_from_extension(string $extension)
{
    static $mimetypes = [
        '7z' => 'application/x-7z-compressed',
        'aac' => 'audio/x-aac',
        'ai' => 'application/postscript',
        'aif' => 'audio/x-aiff',
        'asc' => 'text/plain',
        'asf' => 'video/x-ms-asf',
        'atom' => 'application/atom+xml',
        'avi' => 'video/x-msvideo',
        'bmp' => 'image/bmp',
        'bz2' => 'application/x-bzip2',
        'cer' => 'application/pkix-cert',
        'crl' => 'application/pkix-crl',
        'crt' => 'application/x-x509-ca-cert',
        'css' => 'text/css',
        'csv' => 'text/csv',
        'cu' => 'application/cu-seeme',
        'deb' => 'application/x-debian-package',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dvi' => 'application/x-dvi',
        'eot' => 'application/vnd.ms-fontobject',
        'eps' => 'application/postscript',
        'epub' => 'application/epub+zip',
        'etx' => 'text/x-setext',
        'flac' => 'audio/flac',
        'flv' => 'video/x-flv',
        'gif' => 'image/gif',
        'gz' => 'application/gzip',
        'htm' => 'text/html',
        'html' => 'text/html',
        'ico' => 'image/x-icon',
        'ics' => 'text/calendar',
        'ini' => 'text/plain',
        'iso' => 'application/x-iso9660-image',
        'jar' => 'application/java-archive',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'latex' => 'application/x-latex',
        'log' => 'text/plain',
        'm4a' => 'audio/mp4',
        'm4v' => 'video/mp4',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mp4a' => 'audio/mp4',
        'mp4v' => 'video/mp4',
        'mpe' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpg4' => 'video/mp4',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
        'ogv' => 'video/ogg',
        'ogx' => 'application/ogg',
        'pbm' => 'image/x-portable-bitmap',
        'pdf' => 'application/pdf',
        'pgm' => 'image/x-portable-graymap',
        'png' => 'image/png',
        'pnm' => 'image/x-portable-anymap',
        'ppm' => 'image/x-portable-pixmap',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ps' => 'application/postscript',
        'qt' => 'video/quicktime',
        'rar' => 'application/x-rar-compressed',
        'ras' => 'image/x-cmu-raster',
        'rss' => 'application/rss+xml',
        'rtf' => 'application/rtf',
        'sgm' => 'text/sgml',
        'sgml' => 'text/sgml',
        'svg' => 'image/svg+xml',
        'swf' => 'application/x-shockwave-flash',
        'tar' => 'application/x-tar',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'torrent' => 'application/x-bittorrent',
        'ttf' => 'application/x-font-ttf',
        'txt' => 'text/plain',
        'wav' => 'audio/x-wav',
        'webm' => 'video/webm',
        'wma' => 'audio/x-ms-wma',
        'wmv' => 'video/x-ms-wmv',
        'woff' => 'application/x-font-woff',
        'wsdl' => 'application/wsdl+xml',
        'xbm' => 'image/x-xbitmap',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xml' => 'application/xml',
        'xpm' => 'image/x-xpixmap',
        'xwd' => 'image/x-xwindowdump',
        'yaml' => 'text/yaml',
        'yml' => 'text/yaml',
        'zip' => 'application/zip',
    ];

    $extension = strtolower($extension);

    return $mimetypes[$extension] ?? null;
}
