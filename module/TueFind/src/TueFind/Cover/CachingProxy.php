<?php

namespace TueFind\Cover;

class CachingProxy extends \VuFind\Cover\CachingProxy
{

    public function setWikiCache($wikiFileName, $cachinData, $cacheManager)
    {
        $dirPath = $cacheManager->getCacheDir() . 'wiki/' . $wikiFileName;
        if (!file_exists($this->cache)) {
            mkdir($this->cache);
        }
        if (!file_exists(dirname($dirPath))) {
            mkdir(dirname($dirPath));
        }
        file_put_contents($dirPath.'/'.$wikiFileName, serialize($cachinData));
    }

    public function checkWikiCache($wikiFileName, $dirPath)
    {
        $wikiData = null;
        $wikiFilePath = $dirPath.'/'.$wikiFileName;
        if (file_exists($wikiFilePath)) {
            $wikiData = $this->fetchWikiCache($wikiFilePath);
        }
        return $wikiData;
    }

    protected function fetchWikiCache($file)
    {
        return file_exists($file)
            ? json_decode(file_get_contents($file))
            : false;
    }

}
