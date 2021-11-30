<?php

namespace TueFind\Cover;

class CachingProxy extends \VuFind\Cover\CachingProxy
{

    public function setWikiCache($wikiFileName, $content)
    {
        file_put_contents($wikiFileName, $content);
    }

    /**
     * Wikidata URLs must be resolved with a special content, else you might get the following error:
     * - HTTP/1.0 429 Too many requests. Please comply with the User-Agent policy: https://meta.wikimedia.org/wiki/User-Agent_policy
     *
     * Since this might be useful for other URLs as well, we generate the user agent for all proxy requests.
     */
    public function resolveUrl($url,$config)
    {
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
