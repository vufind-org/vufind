<?php

namespace TueFind\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory
{
    /**
     * Construct a generic controller.
     *
     * This function replaces the __NAMESPACE__ logic of VuFind's default
     * with a static logic to improve inheritance.
     *
     * @param string         $name Name of table to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm   Service manager
     *
     * @return object
     */
    public static function getGenericController($name, ServiceManager $sm)
    {
        // Prepend the current namespace unless we receive a FQCN:
        if (strpos($name, '\\') === false) {
            $reflection = new \ReflectionClass(static::class);
            $namespace = $reflection->getNamespaceName();
            $class = $namespace . '\\' . $name;
        } else {
            $class = $name;
        }

        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        return new $class($sm->getServiceLocator());
    }

    public static function getPDAProxyController(ServiceManager $sm) {
        return new PDAProxyController($sm->getServiceLocator());
    }

    public static function getProxyController(ServiceManager $sm) {
        return new ProxyController($sm->getServiceLocator());
    }
}
