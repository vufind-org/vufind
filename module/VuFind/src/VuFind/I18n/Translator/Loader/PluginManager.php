<?php

namespace VuFind\I18n\Translator\Loader;

use VuFind\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Factory\InvokableFactory;

class PluginManager extends AbstractPluginManager
{
    protected $factories = [
        BaseLocaleLoader::class => BaseLocaleLoaderFactory::class,
        ClassicLoader::class => ClassicLoaderFactory::class,
        ExtendedIniLoader::class => ExtendedIniLoaderFactory::class,
        YamlLoader::class => YamlLoaderFactory::class,
        LoaderInterface::class => LoaderFactory::class,
    ];

    protected function getExpectedInterface()
    {
        return LoaderInterface::class;
    }
}