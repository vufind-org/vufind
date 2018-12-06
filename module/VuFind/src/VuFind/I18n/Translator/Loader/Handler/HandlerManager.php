<?php

namespace VuFind\I18n\Translator\Loader\Handler;

use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Factory\InvokableFactory;

class HandlerManager extends AbstractPluginManager
{
    protected $factories = [
        BaseLocaleHandler::class => InvokableFactory::class,
        DirectoryHandler::class => InvokableFactory::class,
        IniFileHandler::class => InvokableFactory::class,
        ExtendedFilesHandler::class => InvokableFactory::class,
        YamlFileHandler::class => InvokableFactory::class,
    ];

    protected $instanceOf = HandlerInterface::class;
}