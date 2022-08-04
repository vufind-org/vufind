<?php

namespace TueFind\ServiceManager;

use TueFind\Http\CachedDownloader;

interface CachedDownloaderAwareInterface
{
    public function setCachedDownloader(CachedDownloader $cachedDownloader);
}
