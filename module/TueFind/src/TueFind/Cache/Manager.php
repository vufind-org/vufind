<?php

namespace TueFind\Cache;

class Manager extends \VuFind\Cache\Manager
{
    public function addWikiCache($name,$dirPath)
    {
        $this->createFileCache(
            $name,
            $dirPath
        );
        return $name;
    }
}
