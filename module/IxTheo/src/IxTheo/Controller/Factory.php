<?php

namespace IxTheo\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory
{
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct a generic controller.
     *
     * This function is inherited but contains the same code as the parent.
     * Required because __NAMESPACE__ overwrites the parent's namespace.
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
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        return new $class($sm->getServiceLocator());
    }

    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
}