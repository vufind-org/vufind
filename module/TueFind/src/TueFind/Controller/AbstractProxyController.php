<?php

namespace TueFind\Controller;
/**
 * Abstract proxy controller with functions that allow using a cache
 * and sending additional HTTP headers when resolving URLs.
 */
class AbstractProxyController extends \VuFind\Controller\AbstractBase
{
    const CACHE_DIR = 'shared';

    /**
     * Resolve URL from cache if possible
     *
     * @param string $url
     * @return json
     */
    protected function getCachedUrlContents($url, $decodeJson=false)
    {
        $cacheManager = $this->serviceLocator->get(\TueFind\Cache\Manager::class);
        $cachingProxy = $this->serviceLocator->get(\TueFind\Cover\CachingProxy::class);

        $md5Url = md5($url);

        $dirPath = $cacheManager->getCacheDir() . static::CACHE_DIR . '/' . $md5Url;

        $cachedFile = $dirPath . '/' . $md5Url;

        if (is_file($cachedFile)) {
            $contents = file_get_contents($cachedFile);
            if ($decodeJson)
                $contents = json_decode($contents);
            return $contents;
        }

        $cacheManager->addWikiCache(
            $md5Url,
            $dirPath
        );

        $config = $this->getConfig();

        $contents = $cachingProxy->resolveUrl($url, $config);
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

        $cachingProxy->setWikiCache($cachedFile, $contentsString);

        return $contents;

    }
}
