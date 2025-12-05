<?php

/**
 * Caching downloader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Http;

use Laminas\Cache\Storage\StorageInterface;
use Psr\Http\Message\ResponseInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Exception\HttpDownloadException;

/**
 * Caching downloader.
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CachingDownloader implements GuzzleServiceAwareInterface
{
    use GuzzleServiceAwareTrait;

    /**
     * Cache to use for downloads
     *
     * @var StorageInterface
     */
    protected $cache;

    /**
     * Cache ID to use for downloads
     *
     * @var string
     */
    protected $cacheId;

    /**
     * Stored client options for cache key generation.
     *
     * @var array
     */
    protected $cacheOptions = [];

    /**
     * Constructor
     *
     * @param CacheManager           $cacheManager  VuFind Cache Manager
     * @param ConfigManagerInterface $configManager VuFind Config Manager
     * @param bool                   $cacheEnabled  Main toggle for enabling caching
     */
    public function __construct(
        protected CacheManager $cacheManager,
        protected ConfigManagerInterface $configManager,
        protected bool $cacheEnabled = true
    ) {
        $this->setUpCache('default');
    }

    /**
     * Get cache and initialize it, if necessary.
     *
     * @return ?StorageInterface Cache storage interface or null if disabled
     */
    protected function getDownloaderCache(): ?StorageInterface
    {
        if (!$this->cacheEnabled) {
            return null;
        }
        if ($this->cache == null) {
            $cacheName = $this->cacheManager->addDownloaderCache(
                $this->cacheId,
                $this->cacheOptions
            );
            $this->cache = $this->cacheManager->getCache($cacheName);
        }
        return $this->cache;
    }

    /**
     * Set up a different cache.
     *
     * @param string  $cacheId             Cache ID
     * @param ?string $cacheOptionsSection Cache Options Section
     * @param ?string $cacheOptionsFile    Config file defining the cache options
     *
     * @return void
     */
    public function setUpCache(string $cacheId, ?string $cacheOptionsSection = null, ?string $cacheOptionsFile = null)
    {
        $this->cache = null;
        $this->cacheId = $cacheId;

        if (!empty($cacheOptionsSection)) {
            $fullCacheOptionsSection = 'Cache_' . $cacheOptionsSection;
            $this->cacheOptions = $this->configManager
                ->getConfigArray($cacheOptionsFile ?? 'config')[$fullCacheOptionsSection] ?? [];
        }
    }

    /**
     * Download a resource using the cache in the background.
     *
     * @param string    $url            URL
     * @param array     $params         Request parameters
     *                                  (e.g. additional headers)
     * @param ?callable $decodeCallback Callback for decoding
     *
     * @return mixed
     */
    public function download(
        $url,
        $params = [],
        ?callable $decodeCallback = null
    ) {
        $cache = $this->getDownloaderCache();
        $cacheItemKey = md5($url . http_build_query($params));

        if ($cache && $cache->hasItem($cacheItemKey)) {
            return $cache->getItem($cacheItemKey);
        }

        // Add new item to cache if not exists
        try {
            $response = $this->guzzleService->get($url, $params);
        } catch (\Exception $e) {
            throw new HttpDownloadException(
                'HttpService download failed (error)',
                $url,
                null,
                null,
                null,
                $e
            );
        }

        $body = $response->getBody()->getContents();
        $response->getBody()->rewind(); // later code might need to read the body again
        if ($response->getStatusCode() != 200) {
            throw new HttpDownloadException(
                'HttpService download failed (not ok)',
                $url,
                $response->getStatusCode(),
                $response->getHeaders(),
                $body
            );
        }

        $finalValue = $decodeCallback !== null
            ? $decodeCallback($response, $url) : $body;
        if ($cache) {
            $cache->addItem($cacheItemKey, $finalValue);
        }
        return $finalValue;
    }

    /**
     * Download a resource using the cache in the background,
     * including decoding for JSON.
     *
     * @param string    $url         URL
     * @param array     $params      Request parameters (e.g. additional headers)
     * @param bool|null $associative Sent to json_decode
     *
     * @return \stdClass|array
     */
    public function downloadJson($url, $params = [], $associative = null)
    {
        $decodeJson = function (ResponseInterface $response, string $url) use ($associative) {
            $body = $response->getBody()->getContents();
            $decodedJson = json_decode($body, $associative);
            if ($decodedJson === null) {
                throw new HttpDownloadException(
                    'Invalid response body',
                    $url,
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $body
                );
            } else {
                return $decodedJson;
            }
        };
        return $this->download($url, $params, $decodeJson);
    }
}
