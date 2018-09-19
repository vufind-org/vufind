<?php

/**
 * Abstract base class for PHPUnit test cases.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Unit;

/**
 * Abstract base class for PHPUnit test cases.
 *
 * @category VuFind
 * @package  Tests
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * The service manager instance
     *
     * @var \Zend\ServiceManager\ServiceManager
     */
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
    protected function callMethod($object, $method, array $arguments = [])
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
    protected function getProperty($object, $property)
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
    protected function setProperty($object, $property, $value)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->setValue($object, $value);
    }

    /**
     * Support method for getServiceManager()
     *
     * @return void
     */
    protected function setupSearchService()
    {
        $config = [
            'factories' => [
                'Solr' => 'VuFind\Search\Factory\SolrDefaultBackendFactory',
                'SolrAuth' => 'VuFind\Search\Factory\SolrAuthBackendFactory',
            ]
        ];
        $registry = new \VuFind\Search\BackendRegistry(
            $this->serviceManager, $config
        );
        $bm = new \VuFind\Search\BackendManager($registry);
        $this->serviceManager->setService('VuFind\Search\BackendManager', $bm);
        $ss = new \VuFindSearch\Service();
        $this->serviceManager->setService('VuFindSearch\Service', $ss);
        $fh = new \VuFind\Search\Solr\HierarchicalFacetHelper();
        $this->serviceManager
            ->setService('VuFind\Search\Solr\HierarchicalFacetHelper', $fh);
        $events = $ss->getEventManager();
        $events->attach('resolve', [$bm, 'onResolve']);
    }

    /**
     * Get a service manager.
     *
     * @return \Zend\ServiceManager\ServiceManager
     */
    public function getServiceManager()
    {
        if (!$this->serviceManager) {
            $this->serviceManager = new \Zend\ServiceManager\ServiceManager();
            $optionsFactory = new \VuFind\Search\Options\PluginManager(
                $this->serviceManager,
                [
                    'abstract_factories' =>
                        ['VuFind\Search\Options\PluginFactory'],
                ]
            );
            $this->serviceManager->setService(
                'VuFind\Search\Options\PluginManager', $optionsFactory
            );
            $paramsFactory = new \VuFind\Search\Params\PluginManager(
                $this->serviceManager,
                [
                    'abstract_factories' =>
                        ['VuFind\Search\Params\PluginFactory'],
                ]
            );
            $this->serviceManager->setService(
                'VuFind\Search\Params\PluginManager', $paramsFactory
            );
            $resultsFactory = new \VuFind\Search\Results\PluginManager(
                $this->serviceManager,
                [
                    'abstract_factories' =>
                        ['VuFind\Search\Results\PluginFactory'],
                ]
            );
            $this->serviceManager->setService(
                'VuFind\Search\Results\PluginManager', $resultsFactory
            );
            $recordDriverFactory = new \VuFind\RecordDriver\PluginManager(
                $this->serviceManager,
                [
                    'abstract_factories' =>
                        ['VuFind\RecordDriver\PluginFactory']
                ]
            );
            $this->serviceManager->setService(
                'VuFind\RecordDriver\PluginManager', $recordDriverFactory
            );
            $this->serviceManager->setService(
                'VuFind\Config\SearchSpecsReader',
                new \VuFind\Config\SearchSpecsReader()
            );
            $this->serviceManager->setService(
                'VuFind\Log\Logger', $this->createMock('VuFind\Log\Logger')
            );
            $this->serviceManager->setService(
                'VuFindHttp\HttpService', new \VuFindHttp\HttpService()
            );
            $this->setupSearchService();
            $cfg = ['abstract_factories' => ['VuFind\Config\PluginFactory']];
            $this->serviceManager->setService(
                'VuFind\Config\PluginManager',
                new \VuFind\Config\PluginManager($this->serviceManager, $cfg)
            );
            $this->serviceManager->setService(
                'SharedEventManager', new \Zend\EventManager\SharedEventManager()
            );
            $this->serviceManager->setService(
                'VuFind\Record\Loader', new \VuFind\Record\Loader(
                    $this->serviceManager->get('VuFindSearch\Service'),
                    $this->serviceManager->get('VuFind\RecordDriver\PluginManager')
                )
            );
            $this->serviceManager->setService('Config', []);
            $factory = new \Zend\Mvc\I18n\TranslatorFactory();
            $this->serviceManager->setService(
                'Zend\Mvc\I18n\Translator',
                $factory->createService($this->serviceManager)
            );
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
        if (!$sm->has('VuFind\Auth\PluginManager')) {
            $authManager = new \VuFind\Auth\PluginManager($sm);
            $sm->setService('VuFind\Auth\PluginManager', $authManager);
        }
        return $sm->get('VuFind\Auth\PluginManager');
    }

    /**
     * Is this test running in a continuous integration context?
     *
     * @return bool
     */
    public function continuousIntegrationRunning()
    {
        // We'll assume that if the CI Solr PID is present, then CI is active:
        return file_exists(__DIR__ . '/../../../../../local/vufindtest.pid');
    }
}
