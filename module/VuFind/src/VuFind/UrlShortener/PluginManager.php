<?php

namespace VuFind\UrlShortener;

use Zend\ServiceManager\Factory\InvokableFactory;

class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'none' => None::class,
        'database' => Database::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        None::class => InvokableFactory::class,
        Database::class => DatabaseFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return UrlShortenerInterface::class;
    }
}
