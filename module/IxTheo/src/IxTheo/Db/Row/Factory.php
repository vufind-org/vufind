<?php

namespace IxTheo\Db\Row;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Db\Row\Factory {
    /**
     * Construct a generic row object.
     *
     * This function is inherited but contains the same code as the parent.
     * Required because __NAMESPACE__ overwrites the parent's namespace.
     *
     * @param string         $name Name of row to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm   Service manager
     * @param array          $args Extra constructor arguments for row object
     *
     * @return object
     */
    public static function getGenericRow($name, ServiceManager $sm, $args = [])
    {
        // Prepend the current namespace unless we receive a FQCN:
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        $adapter = $sm->getServiceLocator()->get('VuFind\DbAdapter');
        return new $class($adapter, ...$args);
    }
}