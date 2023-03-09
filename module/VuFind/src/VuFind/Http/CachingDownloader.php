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

/**
 * Caching downloader.
 *
 * @category VuFind
 * @package  Http
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CachingDownloader implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * CacheManager to update caches if necessary.
     *
     * @var CacheManager
     */
    protected $cacheManager;

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
     * @param string $cacheManager Base directory for cache
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
        $this->setUpCache('default');
    }

    /**
     * Get cache and initialize it, if necessary.
     *
     * @return StorageInterface
     */
    protected function getDownloaderCache()
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
     * @param string $cacheId      Cache ID
     * @param array  $cacheOptions Cache Options
     *
     * @return void
     */
    public function setUpCache(string $cacheId, array $cacheOptions=[])
    {
        $this->cache = null;
        $this->cacheId = $cacheId;
        $this->cacheOptions = $cacheOptions;
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
        $params=[],
        callable $decodeCallback=null
    ) {
        $cache = $this->getDownloaderCache();
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

            if ($decodeCallback !== null) {
                $cache->addItem($cacheItemKey, $decodeCallback($response, $url));
            } else {
                $cache->addItem($cacheItemKey, $response->getBody());
            }
        }

        return $cache->getItem($cacheItemKey);
    }

    /**
     * Download a resource using the cache in the background,
     * including decoding for JSON.
     *
     * @param string $url    URL
     * @param array  $params Request parameters (e.g. additional headers)
     *
     * @return stdClass
     */
    public function downloadJson($url, $params=[])
    {
        $decodeJson = function (\Laminas\Http\Response $response, $url) {
            $decodedJson = json_decode($response->getBody());
            if ($decodedJson === null) {
                throw new HttpDownloadException(
                    'Invalid response body',
                    $url,
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody()
                );
            } else {
                return $decodedJson;
            }
        };

        return $this->download($url, $params, $decodeJson);
    }
}
