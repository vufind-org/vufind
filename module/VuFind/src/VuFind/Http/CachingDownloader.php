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
use Laminas\Http\Client;
use VuFind\Cache\Manager as CacheManager;

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
     * HTTP client
     *
     * @var Client
     */
    protected $client;

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
     * These result modes indicate whether the responses should be decoded.
     */
    public const RESULT_MODE_RAW = 'RAW';
    public const RESULT_MODE_JSON = 'JSON';

    /**
     * Constructor
     *
     * @param Client $client       HTTP client
     * @param string $cacheManager Base directory for cache
     */
    public function __construct(Client $client, CacheManager $cacheManager)
    {
        $this->client = $client;
        $this->cacheManager = $cacheManager;
        $this->setCacheId('downloader');
    }

    /**
     * Set a different cache.
     *
     * @param string $cacheId Cache ID
     *
     * @return void
     */
    public function setCacheId($cacheId)
    {
        $cacheName = $this->cacheManager->addDownloaderCache($cacheId);
        $this->cache = $this->cacheManager->getCache($cacheName);
    }

    /**
     * Set client options (HTTP headers and so on).
     *
     * @param array $options Client options (e.g. HTTP headers)
     *
     * @return void
     */
    public function setClientOptions($options)
    {
        $this->client->setOptions($options);
    }

    /**
     * Download a resorce using the cache in the background.
     *
     * @param string $url        URL
     * @param string $resultMode See self::RESULT_MODE_... constants
     *
     * @return string
     */
    public function download($url, $resultMode = self::RESULT_MODE_RAW)
    {
        $cacheItemKey = md5($url);

        // Return item if exists in cache
        if ($this->cache->hasItem($cacheItemKey)) {
            $body = $this->cache->getItem($cacheItemKey);
            return $this->decodeBody($body, $resultMode, $url);
        }

        // Add new item to cache if not exists
        $response = $this->client->setUri($url)->send();
        if (!$response->isOk()) {
            throw new \Exception('Could not resolve URL: ' . $url);
        }
        $body = $response->getBody();
        $this->cache->addItem($cacheItemKey, $body);
        return $this->decodeBody($body, $resultMode, $url);
    }

    /**
     * Decode the body according to the given result mode (e.g. RAW or JSON).
     *
     * @param string $body       Body
     * @param string $resultMode See self::RESULT_MODE_... constants
     * @param string $url        URL (for exception message)
     *
     * @return string
     */
    protected function decodeBody($body, $resultMode, $url)
    {
        switch ($resultMode) {
        case static::RESULT_MODE_JSON:
            $decodedJson = json_decode($body);
            if (empty($decodedJson)) {
                throw new \Exception('Unable to decode JSON from URL: ' . $url);
            }
            return $decodedJson;
        case static::RESULT_MODE_RAW:
        default:
            return $body;
        }
    }
}
