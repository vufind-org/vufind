<?php

namespace TueFind\Cache;

use VuFind\Cover\CachingProxy;
use Laminas\Config\Config;

class Manager extends \VuFind\Cache\Manager
{
    public function addWikiCache($name)
    {
        $this->createFileCache(
            $name,
            $this->getCacheDir() . 'wiki/' . $name
        );
        return $name;
    }
}
