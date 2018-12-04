<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\I18n\Translator\TextDomain;
use Zend\Stdlib\Glob;
use Zend\Uri\Uri;

class DirectoryLoader implements LoaderInterface
{
    /**
     * @var string
     */
    protected $dir;

    /**
     * @var string
     */
    protected $ext;

    /**
     * @var LoaderInterface
     */
    protected $loader;

    public function __construct(LoaderInterface $loader, string $dir, string $ext)
    {
        $this->loader = $loader;
        $this->dir = realpath($dir);
        $this->ext = "{{$ext}}";
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
        $dir = $textDomain === 'default' ? "$this->dir" : "$this->dir/$textDomain";
        foreach (Glob::glob("$dir/$locale.$this->ext", Glob::GLOB_BRACE) as $path) {
            yield from $this->loader->load("file://$path");
        }
    }
}