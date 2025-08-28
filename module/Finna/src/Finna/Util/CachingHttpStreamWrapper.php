<?php

/**
 * HTTP stream wrapper with caching support
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Util
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Util;

use Laminas\Cache\Storage\StorageInterface;
use VuFindHttp\HttpServiceInterface;

/**
 * HTTP stream wrapper with caching support
 *
 * @category VuFind
 * @package  Util
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CachingHttpStreamWrapper
{
    /**
     * Context
     *
     * @var resource
     */
    public $context;

    /**
     * Wrapper enabled?
     *
     * @var bool
     */
    protected static $enabled = false;

    /**
     * HTTP response
     *
     * @var ?resource
     */
    protected static $response = null;

    /**
     * HTTP Service
     *
     * @var ?HttpServiceInterface
     */
    protected static $httpService = null;

    /**
     * Cache storage
     *
     * @var ?StorageInterface
     */
    protected static $cacheStorage = null;

    /**
     * Cache life time (two weeks)
     *
     * @var int
     */
    protected static $cacheLifetime = 60 * 60 * 24 * 14;

    /**
     * Enable wrapper
     *
     * @param HttpServiceInterface $httpService  HTTP service
     * @param StorageInterface     $cacheStorage Cache storage
     *
     * @return void
     */
    public static function enable(HttpServiceInterface $httpService, StorageInterface $cacheStorage)
    {
        if (self::$enabled) {
            self::disable();
        }

        stream_wrapper_unregister('http');
        // Note: STREAM_IS_URL would cause the stream to be blocked by PHP's allow_url_fopen, but we need to allow it
        // (the remote files will never get executed, so this does not affect security):
        stream_wrapper_register('http', __CLASS__/*, STREAM_IS_URL*/);

        stream_wrapper_unregister('https');
        // Note: STREAM_IS_URL would cause the stream to be blocked by PHP's allow_url_fopen, but we need to allow it
        // (the remote files will never get executed, so this does not affect security):
        stream_wrapper_register('https', __CLASS__/*, STREAM_IS_URL*/);

        self::$enabled = true;
        self::$httpService = $httpService;
        self::$cacheStorage = $cacheStorage;
    }

    /**
     * Disable the stream wrapper.
     *
     * @return void
     */
    public static function disable()
    {
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');

        self::$enabled = false;
    }

    /**
     * Is the wrapper enabled?
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return self::$enabled;
    }

    /**
     * This method is called immediately after the wrapper is initialized (f.e. by fopen() and file_get_contents()).
     *
     * @param string $path        Specifies the URL that was passed to the original function.
     * @param string $mode        The mode used to open the file, as detailed for fopen().
     * @param int    $options     Holds additional flags set by the streams API.
     * @param string $opened_path If the path is opened successfully, and STREAM_USE_PATH is set.
     *
     * @return bool
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_open($path, $mode, $options, &$opened_path)
    {
        $cacheKey = md5(self::class . "/$path");
        if (null === ($data = self::getCachedData($cacheKey))) {
            $response = self::$httpService->get($path);
            if (!$response->isSuccess()) {
                throw new \Exception("Could not load $path");
            }
            $data = $response->getBody();
            // Cache items for two weeks:
            self::putCachedData($cacheKey, $data);
        }
        self::$response = fopen('php://memory', 'r+');
        fwrite(self::$response, $data);
        rewind(self::$response);
        return true;
    }

    /**
     * Read from stream.
     *
     * @param int $count How many bytes of data from the current position should be returned.
     *
     * @return string
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_read($count)
    {
        return fread(self::$response, $count);
    }

    /**
     * Write to stream.
     *
     * @param string $data Should be stored into the underlying stream.
     *
     * @return int
     *
     * @throws \BadMethodCallException
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_write($data)
    {
        throw new \BadMethodCallException('Writes not supported!');
    }

    /**
     * Retrieve the current position of a stream.
     *
     * This method is called in response to fseek() to determine the current position.
     *
     * @return int
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_tell()
    {
        return ftell(self::$response);
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @return bool
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_eof()
    {
        return feof(self::$response);
    }

    /**
     * Retrieve information about a file resource.
     *
     * @return array See stat().
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_stat()
    {
        return [];
    }

    /**
     * Retrieve information about a file resource.
     *
     * @param string $path  Path
     * @param int    $flags Flags
     *
     * @return array|false
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function url_stat($path, $flags)
    {
        return [];
    }

    /**
     * Seeks to specific location in a stream.
     *
     * @param int $offset The stream offset to seek to.
     * @param int $whence Possible values:
     *                    SEEK_SET - Set position equal to offset bytes.
     *                    SEEK_CUR - Set position to current location plus offset.
     *                    SEEK_END - Set position to end-of-file plus offset.
     *
     * @return bool
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_seek($offset, $whence)
    {
        return fseek(self::$response, $offset, $whence);
    }

    /**
     * Change stream options.
     *
     * @param string $path   The file path or URL to set metadata.
     * @param int    $option One of the stream options.
     * @param mixed  $var    Value depending on the option.
     *
     * @return bool
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function stream_metadata($path, $option, $var)
    {
        return false;
    }

    /**
     * Helper function for fetching cached data.
     *
     * Data is cached for up to self::$cacheLifetime.
     *
     * @param string $key Cache entry key
     *
     * @return ?mixed Cached entry or null if not cached or expired
     */
    protected static function getCachedData($key)
    {
        // No cache object, no cached results!
        if (null === self::$cacheStorage) {
            return null;
        }

        $item = self::$cacheStorage->getItem($key);
        if (null !== $item) {
            // Return value if still valid:
            $lifetime = $item['lifetime'] ?? self::$cacheLifetime;
            if (time() - $item['time'] <= $lifetime) {
                return $item['entry'];
            }
            // Clear expired item from cache:
            self::$cacheStorage->removeItem($key);
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     *
     * Data is cached for up to $this->cacheLifetime seconds.
     *
     * @param string $key      Cache entry key
     * @param mixed  $entry    Entry to be cached
     * @param int    $lifetime Optional lifetime for the entry in seconds
     *
     * @return void
     */
    protected static function putCachedData($key, $entry, $lifetime = null)
    {
        // Don't write to cache if we don't have a cache!
        if (null === self::$cacheStorage) {
            return;
        }
        $item = [
            'time' => time(),
            'lifetime' => $lifetime,
            'entry' => $entry,
        ];
        self::$cacheStorage->setItem($key, $item);
    }
}
