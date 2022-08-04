<?php

namespace TueFind\Cache;

class Manager extends \VuFind\Cache\Manager
{
    public function addControllerCache($name, $dirPath)
    {
        $this->createFileCache(
            $name,
            $dirPath
        );
        return $name;
    }
}
