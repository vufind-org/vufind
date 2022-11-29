<?php
/**
 * Caching downloader.
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Http;

use Laminas\Cache\Storage\StorageInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Exception\HttpDownloadException;
use VuFindHttp\HttpService;

/**
 * Caching downloader.
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CachingDownloader
{
    /**
     * CacheManager to update caches if necessary.
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Stored client options for cache key generation.
     *
     * @var array
     */
    protected $cacheOptions = [];

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
     * HTTP service
     *
     * @var HttpService
     */
    protected $httpService;

    /**
     * Constructor
     *
     * @param HttpService $httpService  HTTP service
     * @param string      $cacheManager Base directory for cache
     */
    public function __construct(HttpService $httpService, CacheManager $cacheManager)
    {
        $this->httpService = $httpService;
        $this->cacheManager = $cacheManager;
        $this->setUpCache('downloader');
    }

    /**
     * Get cache and initialize it, if necessary.
     *
     * @return StorageInterface
     */
    protected function getCache()
    {
        if ($this->cache == null) {
            $cacheName = $this->cacheManager->addDownloaderCache($this->cacheId);
            $this->cache = $this->cacheManager->getCache($cacheName);
        }
        return $this->cache;
    }

    /**
     * Set up a different cache.
     *
     * @param string $cacheId Cache ID
     *
     * @return void
     */
    public function setUpCache($cacheId)
    {
        $this->cacheId = $cacheId;
        $this->cache = null;
    }

    /**
     * Download a resource using the cache in the background.
     *
     * @param string    $url              URL
     * @param array     $params           Request parameters
     *                                    (e.g. additional headers)
     * @param ?callable $validateCallback Callback for validation
     *                                    before storing to cache
     * @param ?callable $decodeCallback   Callback for decoding
     *
     * @return mixed
     */
    public function download(
        $url,
        $params=[],
        callable $validateCallback=null,
        callable $decodeCallback=null
    ) {
        $cache = $this->getCache();
        $cacheItemKey = md5($url . http_build_query($params));

        // Add new item to cache if not exists
        if (!$cache->hasItem($cacheItemKey)) {
            try {
                $response = $this->httpService->get($url, $params);
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
            if (!$response->isOk()) {
                throw new HttpDownloadException(
                    'HttpService download failed (not ok)',
                    $url,
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody()
                );
            }
            if ($validateCallback !== null
                && ($validateCallback($response) === false)
            ) {
                throw new HttpDownloadException(
                    'Invalid response body',
                    $url,
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody()
                );
            }
            $cache->addItem($cacheItemKey, $response);
        }

        $response = $cache->getItem($cacheItemKey);
        return ($decodeCallback === null) ?
            $response->getBody() : $decodeCallback($response);
    }

    /**
     * Download a resource using the cache in the background,
     * including validation / decoding for JSON.
     *
     * @param string $url    URL
     * @param array  $params Request parameters (e.g. additional headers)
     *
     * @return stdClass
     */
    public function downloadJson($url, $params=[])
    {
        $validateJson = function ($response) {
            $serializer = \Laminas\Serializer\Serializer::factory(
                \Laminas\Serializer\Adapter\Json::class
            );

            try {
                $serializer->unserialize($response->getBody());
                return true;
            } catch (\Laminas\Serializer\Exception\ExceptionInterface $e) {
                return false;
            }
        };

        return $this->download($url, $params, $validateJson);
    }
}
