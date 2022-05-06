<?php

namespace TueFind\ServiceManager;

use TueFind\Http\CachedDownloader;

trait CachedDownloaderAwareTrait
{
    /**
     * Cache ID. This can be overridden by child classes if we want to use
     * a separate cache.
     *
     * @var string
     */
    protected $downloaderCacheId = 'downloader';

    /**
     * Client options. This can be overridden, e.g. to set a specific
     * user-agent.
     *
     * @var array
     */
    protected $downloaderClientOptions = [];

    /**
     * Cached downloader
     *
     * @var CachedDownloader
     */
    protected $cachedDownloader = null;

    /**
     * Set cached downloader
     *
     * @param $cachedDownloader CachedDownloader
     */
    public function setCachedDownloader(CachedDownloader $cachedDownloader)
    {
        $this->cachedDownloader = $cachedDownloader;
        $this->cachedDownloader->setCacheId($this->downloaderCacheId);
        $this->cachedDownloader->setClientOptions($this->downloaderClientOptions);
    }
}
