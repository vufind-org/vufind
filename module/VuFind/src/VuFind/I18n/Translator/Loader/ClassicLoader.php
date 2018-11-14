<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\I18n\Translator\TextDomain;
use Zend\Stdlib\Glob;
use Zend\Uri\Uri;

class ClassicLoader implements LoaderInterface
{
    /**
     * @var string
     */
    protected $dir;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    public function __construct(LoaderInterface $loader, string $dir)
    {
        $this->loader = $loader;
        $this->dir = realpath($dir);
    }

    /**
     * @param string $file
     * @return \Generator|TextDomain[]
     */
    public function load(string $file): \Generator
    {
        if ('messages' !== ($uri = new Uri($file))->getScheme()) {
            return;
        }

        list ($locale, $textDomain) = [$uri->getHost(), substr($uri->getPath(), 1)];
        $pattern = $textDomain === 'default' ? "$this->dir/$locale.ini"
            : "$this->dir/$textDomain/$locale.ini";

        foreach (Glob::glob($pattern) as $file) {
            yield from $this->loader->load("file://$file");
        }
    }
}