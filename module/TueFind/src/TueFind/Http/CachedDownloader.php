<?php

namespace TueFind\Http;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Client;
use VuFind\Cache\Manager as CacheManager;

class CachedDownloader
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
     * @param string $cacheId
     */
    public function setCacheId($cacheId)
    {
        $cacheDir = $this->cacheManager->getCacheDir() . $cacheId;
        $this->cacheManager->addControllerCache($cacheId, $cacheDir);
        $this->cache = $this->cacheManager->getCache($cacheId);
    }

    /**
     * Set client options (HTTP headers and so on).
     *
     * @param array $options
     */
    public function setClientOptions($options)
    {
        $this->client->setOptions($options);
    }

    /**
     * Download a resorce using the cache in the background.
     *
     * @param string $url
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
     * @param string $body
     * @param string $resultMode
     * @param string $url
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
