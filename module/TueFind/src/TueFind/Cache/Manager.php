<?php

namespace TueFind\Cache;

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
