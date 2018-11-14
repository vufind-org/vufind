<?php

namespace VuFind\I18n\Translator\Loader;

use Symfony\Component\Yaml\Yaml as Parser;
use Zend\I18n\Translator\TextDomain;
use Zend\Uri\Uri;

class YamlLoader implements LoaderInterface
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
        if (!$this->canLoad($uri = new Uri($file))) {
            return;
        }
        $data = new TextDomain(Parser::parseFile($file) ?? []);

        foreach ($data['$extends'] ?? [] as $parent) {
            $parentUri = Uri::merge($uri, trim($parent));
            yield from $this->loader->load((string)$parentUri);
        }

        yield $file => $data;
    }

    protected function canLoad(Uri $uri): bool
    {
        $extension = pathinfo($uri->getPath(), PATHINFO_EXTENSION);
        return in_array($extension, ['yml', 'yaml']);
    }
}