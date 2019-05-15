<?php

namespace VuFind\UrlShortener;

class None implements UrlShortenerInterface
{
    /**
     * Dummy to return original URL version.
     *
     * @param string $url URL
     *
     * @return string
     */
    public function shorten($url)
    {
        return $url;
    }

    /**
     * Dummy implementation. Resolving is not necessary because initial URL
     * has not been shortened.
     *
     * @param string $id
     *
     * @throws Exception because this class is not meant to resolve shortlinks.
     */
    public function resolve($id) {
        throw \Exception('UrlShortener None is unable to resolve shortlinks.');
    }
}
