<?php

namespace TueFind\Controller;

/**
 * Abstract proxy controller with functions that allow using a cache
 * and sending additional HTTP headers when resolving URLs.
 */
class AbstractProxyController extends \VuFind\Controller\AbstractBase
{
    const CACHE_DIR = '/tmp/proxycache/shared';
    const CACHE_LIFETIME = 3600;

    /**
     * Resolve URL from cache if possible
     *
     * @param string $url
     * @return json
     */
    protected function getCachedUrlContents($url, $decodeJson=false)
    {
        if (!is_dir(static::CACHE_DIR)) mkdir(static::CACHE_DIR, 0777, true);
        $cachedFile = static::CACHE_DIR . '/' . md5($url);

        if (is_file($cachedFile)) {
            if (filemtime($cachedFile) + static::CACHE_LIFETIME > time()) {
                $contents = file_get_contents($cachedFile);
                if ($decodeJson)
                    $contents = json_decode($contents);
                return $contents;
            }
        }

        $contents = $this->resolveUrl($url);
        if (!$contents)
            throw new \Exception('Could not resolve URL: ' + $url);

        $contentsString = $contents;
        if ($decodeJson) {
            $contents = json_decode($contents);
            if (!$contents)
                throw new \Exception('Invalid JSON returned from URL: ' + $url);
        }

        file_put_contents($cachedFile, $contentsString);
        return $contents;
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
