<?php

namespace TueFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use TueFind\Http\CachedDownloader;


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
    const CACHE_ID = 'downloader';

    /**
     * Cache to use for retrieved URLs.
     * @var CachedDownloader
     */
    protected $cachedDownloader;

    /**
     * Overridden constructor which also initializes the cache.
     *
     * @param ServiceLocatorInterface $sm
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
        $this->cachedDownloader = $this->serviceLocator->get(\TueFind\Http\CachedDownloader::class);
        $this->cachedDownloader->setCacheId(static::CACHE_ID);
    }
}
