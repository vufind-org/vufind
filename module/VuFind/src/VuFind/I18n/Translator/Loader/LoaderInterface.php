<?php

namespace VuFind\I18n\Translator\Loader;

use Zend\I18n\Translator\TextDomain;

interface LoaderInterface
{
    /**
     * Loads a resource as well as possible dependencies and yields
     * the loaded {@see Zend\I18n\Translator\TextDomain} indexed by
     * the corresponding resource for each loaded resource.
     *
     * @param string $file
     * @return \Generator|TextDomain[]
     */
    public function load(string $file): \Generator;
}