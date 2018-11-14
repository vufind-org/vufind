<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\I18n\Translator\TextDomain;
use Zend\Uri\Uri;

class BaseLocaleLoader implements LoaderInterface
{
    /**
     * @var LoaderInterface
     */
    protected $loader;

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @param string $file
     * @return \Generator|TextDomain[]
     */
    public function load(string $file): \Generator
    {
        $uri = new Uri($file);
        if ('messages' !== $uri->getScheme()) {
            return;
        }

        if ($locale = $this->getBaseLocale($uri->getHost())) {
            $file = $uri->setHost($locale)->toString();
            yield from $this->loader->load($file);
        }
    }

    protected function getBaseLocale(string $locale)
    {
        $parts = array_slice(array_reverse(explode('-', $locale)), 1);
        return implode('-', array_reverse($parts));
    }
}