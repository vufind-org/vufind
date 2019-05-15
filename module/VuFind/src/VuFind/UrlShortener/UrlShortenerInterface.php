<?php

namespace VuFind\UrlShortener;

interface UrlShortenerInterface
{
    /**
     * Generate and return shortened version of a URL.
     *
     * @param string $url URL
     *
     * @return string
     */
    public function shorten($url);

    /**
     * Resolve a shortened URL by its id.
     *
     * @param string $id
     *
     * @return string
     */
    public function resolve($id);
}
