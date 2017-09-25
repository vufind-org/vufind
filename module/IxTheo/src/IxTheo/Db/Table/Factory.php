<?php

namespace IxTheo\Db\Table;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Db\Table\Factory {
    /**
     * Construct a generic table object.
     *
     * This function is inherited but contains the same code as the parent.
     * Required because __NAMESPACE__ overwrites the parent's namespace.
     *
     * @param string         $name    Name of table to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm      Service manager
     * @param string         $rowName Name of custom row prototype object to
     * retrieve (null for none).
     * @param array          $args    Extra constructor arguments for table object
     *
     * @return object
     */
    public static function getGenericTable($name, ServiceManager $sm,
        $rowName = null, $args = []
    ) {
        // Prepend the current namespace unless we receive a FQCN:
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        $adapter = $sm->getServiceLocator()->get('VuFind\DbAdapter');
        $config = $sm->getServiceLocator()->get('config');
        return new $class(
            $adapter, $sm, $config, static::getRowPrototype($sm, $rowName), ...$args
        );
    }
}
