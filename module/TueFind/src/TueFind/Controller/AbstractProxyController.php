<?php

namespace TueFind\Controller;
/**
 * Abstract proxy controller with functions that allow using a cache
 * and sending additional HTTP headers when resolving URLs.
 */
class AbstractProxyController extends \VuFind\Controller\AbstractBase
{
    /**
     * Resolve URL from cache if possible
     *
     * @param string $url
     * @return json
     */
    protected function getCachedUrlContents($url, $decodeJson=false)
    {
        $cacheManager = $this->serviceLocator->get(\TueFind\Cache\Manager::class);
        $cachinProxi = $this->serviceLocator->get(\TueFind\Cover\CachingProxy::class);
        $cachedFile = md5($url);
        $dirPath = $cacheManager->getCacheDir() . 'wiki/' . $cachedFile;
        $wikiData = $cachinProxi->checkWikiCache($cachedFile, $dirPath);
        $cachedFilenew = $dirPath.'/'.$cachedFile;
        if($wikiData == null){
            $cacheManager->addWikiCache(
                $cachedFile,
                $dirPath
            );
            $contents = $this->resolveUrl($url);
            if (!$contents) {
                throw new \Exception('Could not resolve URL: ' + $url);
            }
            $contentsString = $contents;
            if ($decodeJson) {
                $contents = json_decode($contents);
                if (!$contents) {
                  throw new \Exception('Invalid JSON returned from URL: ' + $url);
                }
            }
            $cachinProxi->setWikiCache($cachedFile, $dirPath, $contentsString);
            return $contents;
        }else{
            return $wikiData;
        }
    }

    /**
     * Wikidata URLs must be resolved with a special content, else you might get the following error:
     * - HTTP/1.0 429 Too many requests. Please comply with the User-Agent policy: https://meta.wikimedia.org/wiki/User-Agent_policy
     *
     * Since this might be useful for other URLs as well, we generate the user agent for all proxy requests.
     */
    protected function resolveUrl($url)
    {
        $config = $this->getConfig();
        $siteTitle = $config->Site->title;
        $siteUrl = $config->Site->url;
        $siteEmail = $config->Site->email;

        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: " . $siteTitle . "/1.0 (" . $siteUrl . "; " . $siteEmail . ")\r\n"
            ]
        ];

        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }
}
