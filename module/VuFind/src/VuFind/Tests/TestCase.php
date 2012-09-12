<?php

/**
 * Abstract base class for PHPUnit test cases.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */

namespace VuFind\Tests;

/**
 * Abstract base class for PHPUnit test cases.
 *
 * @category VuFind2
 * @package  Tests
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $serviceManager = false;

    /**
     * Call protected or private method for side-effect and result.
     *
     * Uses PHP's reflection API in order to modify method accessibility.
     *
     * @param object|string $object    Object or class name
     * @param string        $method    Method name
     * @param array         $arguments Method arguments
     *
     * @throws \ReflectionException Method does not exist
     *
     * @return mixed
     */
    protected function callMethod ($object, $method, array $arguments = array())
    {
        $reflectionMethod = new \ReflectionMethod($object, $method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($object, $arguments);
    }

    /**
     * Return protected or private property.
     *
     * Uses PHP's reflection API in order to modify property accessibility.
     *
     * @param object|string $object   Object or class name
     * @param string        $property Property name
     *
     * @throws \ReflectionException Property does not exist
     *
     * @return mixed
     */
    protected function getProperty ($object, $property)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    }

    /**
     * Set protected or private property.
     *
     * Uses PHP's reflection API in order to modify property accessibility.
     *
     * @param object|string $object   Object or class name
     * @param string        $property Property name
     * @param mixed         $value    Property value
     *
     * @throws \ReflectionException Property does not exist
     *
     * @return void
     */
    protected function setProperty ($object, $property, $value)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->setValue($object, $value);
    }

    /**
     * Get a service manager.
     *
     * @return \Zend\ServiceManager\ServiceManager
     */
    public function getServiceManager()
    {
        if (!$this->serviceManager) {
            $recordDriverFactory = new \VuFind\RecordDriver\PluginManager(
                new \Zend\ServiceManager\Config(
                    array(
                        'abstract_factories' =>
                            array('VuFind\RecordDriver\PluginFactory')
                    )
                )
            );
            $this->serviceManager = new \Zend\ServiceManager\ServiceManager();
            $this->serviceManager->setService(
                'RecordDriverPluginManager', $recordDriverFactory
            );
            $this->serviceManager->setService(
                'SearchSpecsReader', new \VuFind\Config\SearchSpecsReader()
            );
            \VuFind\Connection\Manager::setServiceLocator($this->serviceManager);
        }
        return $this->serviceManager;
    }

    /**
     * Get an auth manager instance.
     *
     * @return \VuFind\Auth\PluginManager
     */
    public function getAuthManager()
    {
        $sm = $this->getServiceManager();
        if (!$sm->has('AuthPluginManager')) {
            $authManager = new \VuFind\Auth\PluginManager(
                new \Zend\ServiceManager\Config(
                    array(
                        'abstract_factories' =>
                            array('VuFind\Auth\PluginFactory')
                    )
                )
            );
            $authManager->setServiceLocator($sm);
            $sm->setService('AuthPluginManager', $authManager);
        }
        return $sm->get('AuthPluginManager');
    }

    /**
     * Get a search manager instance for testing search objects.
     *
     * @return \VuFind\Search\Manager
     */
    public function getSearchManager()
    {
        $sm = $this->getServiceManager();
        if (!$sm->has('SearchManager')) {
            $searchManager = new \VuFind\Search\Manager(
                array('default_namespace' => 'VuFind\Search')
            );
            $searchManager->setServiceLocator($sm);
            $sm->setService('SearchManager', $searchManager);
        }
        return $sm->get('SearchManager');
    }
}
