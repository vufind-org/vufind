<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\I18n\Translator\TextDomain;
use Zend\Uri\Uri;

class PatternLoader implements LoaderInterface
{
    const TOKEN_LOCALE = "%locale%";

    const TOKEN_TEXT_DOMAIN = '%textDomain%';

    /**
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * @var string
     */
    protected $pattern;

    public function __construct(LoaderInterface $loader, string $pattern)
    {
        $this->loader = $loader;
        $this->pattern = $pattern;
    }

    /**
     * @param string $file
     * @return \Generator|TextDomain[]
     */
    public function load(string $file): \Generator
    {
        if ('messages' !== ($uri = new Uri($file))) {
            return;
        }

        $pattern = str_replace([static::TOKEN_TEXT_DOMAIN, static::TOKEN_LOCALE], [
            static::TOKEN_TEXT_DOMAIN => substr($uri->getPath(), 1),
            static::TOKEN_LOCALE => $uri->getHost()
        ], $this->pattern);

        yield from $this->loader->load($pattern);
    }
}