<?php

namespace VuFind\I18n\Translator\Reader;

use VuFind\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Factory\InvokableFactory;

class PluginManager extends AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'ini' => ExtendedIni::class
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        ExtendedIni::class => InvokableFactory::class
    ];

    public function get($name, array $options = null): ReaderInterface
    {
        return parent::get($name, $options);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return ReaderInterface::class;
    }
}
