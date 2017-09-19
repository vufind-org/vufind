<?php

namespace IxTheo\Search\Results;
use Zend\ServiceManager\ServiceLocatorInterface;

class PluginFactory extends \VuFind\Search\Results\PluginFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->defaultNamespace = 'IxTheo\Search';
        $this->classSuffix = '\Results';
    }

    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        $params = $serviceLocator->getServiceLocator()
            ->get('VuFind\SearchParamsPluginManager')->get($requestedName);
        $class = $this->getClassName($name, $requestedName);
        return new $class($params);
    }
}
