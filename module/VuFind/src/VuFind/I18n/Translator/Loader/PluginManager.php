<?php

namespace VuFind\I18n\Translator\Loader;

use VuFind\ServiceManager\AbstractPluginManager;

class PluginManager extends AbstractPluginManager
{
    protected $factories = [
        BaseLocaleLoader::class => BaseLocaleLoaderFactory::class,
        DirectoryLoader::class => DirectoryLoaderFactory::class,
        ExtendedIniLoader::class => ExtendedIniLoaderFactory::class,
        YamlLoader::class => YamlLoaderFactory::class,
        LoaderInterface::class => LoaderFactory::class,
    ];

    protected function getExpectedInterface()
    {
        return LoaderInterface::class;
    }
}