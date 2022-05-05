<?php

namespace TueFind\Controller;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Client;
use Laminas\ServiceManager\ServiceLocatorInterface;


/**
 * Abstract proxy controller with functions that allow using a cache
 * and sending additional HTTP headers when resolving URLs.
 */
class AbstractProxyController extends \VuFind\Controller\AbstractBase
{
    /**
     * Cache ID. This can be overridden by child classes if we want to use
     * a separate cache.
     */
    const CACHE_ID = 'shared';

    /**
     * Cache to use for retrieved URLs.
     * @var StorageInterface
     */
    protected $cache;

    /**
     * HTTP client to use for downloads.
     * @var Client
     */
    protected $client;

    /**
     * Overridden constructor which also initializes the cache.
     *
     * @param ServiceLocatorInterface $sm
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
        $this->initializeCache();
        $this->initializeClient();
    }

    /**
     * Initialize the Cache by using the Cache Manager.
     */
    protected function initializeCache()
    {
        $this->cacheManager = $this->serviceLocator->get(\TueFind\Cache\Manager::class);
        $cacheDir = $this->cacheManager->getCacheDir() . static::CACHE_ID;
        $this->cacheManager->addControllerCache(static::CACHE_ID, $cacheDir);
        $this->cache = $this->cacheManager->getCache(static::CACHE_ID);
    }

    /**
     * Initialize the Client by using the HTTP Service.
     */
    protected function initializeClient()
    {
        $this->client = $this->serviceLocator->get(\VuFindHttp\HttpService::class)->createClient();
    }

    /**
     * Resolve URL from cache if possible
     *
     * @param string $url
     * @return json
     */
    protected function getCachedUrlContents($url, $decodeJson=false)
    {
        $cacheItemKey = md5($url);

        // Return item if exists in cache
        if ($this->cache->hasItem($cacheItemKey)) {
            $contents = $this->cache->getItem($cacheItemKey);
            if ($decodeJson) {
                $contents = json_decode($contents);
            }
            return $contents;
        }

        // Add new item to cache if not exists
        $contents = $this->getUrlContents($url);
        if (!$contents) {
            throw new \Exception('Could not resolve URL: ' . $url);
        }
        $contentsString = $contents;
        if ($decodeJson) {
            $contents = json_decode($contents);
            if (!$contents) {
                throw new \Exception('Invalid JSON returned from URL: ' . $url);
            }
        }

        $this->cache->addItem($cacheItemKey, $contentsString);
        return $contents;
    }

    /**
     * This function is meant to be overridden in child classes,
     * e.g. when special headers are needed (like for Wikidata).
     *
     * @param string $url
     *
     * @return string
     */
    protected function getUrlContents($url)
    {
        $response = $this->client->setUri($url)->send();
        return $response->getBody();
    }
}
