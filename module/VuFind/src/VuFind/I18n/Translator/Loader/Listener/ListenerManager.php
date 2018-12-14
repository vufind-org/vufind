<?php

namespace VuFind\I18n\Translator\Loader\Listener;

use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Factory\InvokableFactory;

class ListenerManager extends AbstractPluginManager
{
    protected $factories = [
        BaseLocaleListener::class => InvokableFactory::class,
        DirectoryListener::class => InvokableFactory::class,
        IniFileListener::class => InvokableFactory::class,
        ExtensionListener::class => InvokableFactory::class,
        YamlFileListener::class => InvokableFactory::class,
    ];

    protected $instanceOf = ListenerInterface::class;
}