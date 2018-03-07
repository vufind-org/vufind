<?php

namespace IxTheo\Search\Results;
use Zend\ServiceManager\ServiceLocatorInterface;

class PluginFactory extends \TueFind\Search\Results\PluginFactory
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
     * @param array                   $extraParams    Extra constructor parameters
     * (to follow the Params, Search and RecordLoader objects)
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName, array $extraParams = []
    ) {
        $params = $serviceLocator->getServiceLocator()
            ->get('VuFind\SearchParamsPluginManager')->get($requestedName);
        $searchService = $serviceLocator->getServiceLocator()
            ->get('VuFind\Search');
        $recordLoader = $serviceLocator->getServiceLocator()
            ->get('VuFind\RecordLoader');
        $class = $this->getClassName($name, $requestedName);
        return new $class($params, $searchService, $recordLoader, ...$extraParams);
    }
}
