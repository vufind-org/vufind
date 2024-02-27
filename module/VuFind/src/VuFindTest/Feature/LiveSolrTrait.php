<?php

/**
 * Mix-in for accessing a real Solr instance during testing.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Laminas\EventManager\SharedEventManager;
use VuFind\Config\SearchSpecsReader;
use VuFind\Search\BackendManager;
use VuFind\Search\Factory\UrlQueryHelperFactory;
use VuFind\Search\Solr\HierarchicalFacetHelper;

/**
 * Mix-in for accessing a real Solr instance during testing.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait LiveSolrTrait
{
    use PathResolverTrait;

    /**
     * Container for services related to live Solr connectivity.
     *
     * @var \VuFindTest\Container\MockContainer
     */
    protected $liveSolrContainer = null;

    /**
     * Create and populate container for services related to live Solr connectivity.
     *
     * @return \VuFindTest\Container\MockContainer
     */
    protected function createLiveSolrContainer()
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $config = include APPLICATION_PATH
            . '/module/VuFind/config/module.config.php';
        $configManager = new \VuFind\Config\PluginManager(
            $container,
            $config['vufind']['config_reader']
        );
        $container->set(\VuFind\Log\Logger::class, $this->createMock(\Laminas\Log\LoggerInterface::class));
        $container->set(\VuFind\Config\PluginManager::class, $configManager);
        $this->addPathResolverToContainer($container);
        $httpFactory = new \VuFind\Service\HttpServiceFactory();
        $container->set(
            \VuFindHttp\HttpService::class,
            $httpFactory($container, \VuFindHttp\HttpService::class)
        );
        $container->set(SearchSpecsReader::class, new SearchSpecsReader());
        $container->set('SharedEventManager', new SharedEventManager());
        $container->set(
            \VuFind\RecordDriver\PluginManager::class,
            new \VuFind\RecordDriver\PluginManager($container)
        );
        $registry = new \VuFind\Search\BackendRegistry($container, $config);
        $backendManager = new BackendManager($registry);
        $container->set(BackendManager::class, $backendManager);
        foreach (['Options', 'Params', 'Results'] as $type) {
            $class = 'VuFind\Search\\' . $type . '\PluginManager';
            $container->set($class, new $class($container));
        }
        $container->set(UrlQueryHelperFactory::class, new UrlQueryHelperFactory());
        $container
            ->set(HierarchicalFacetHelper::class, new HierarchicalFacetHelper());
        $searchService = new \VuFindSearch\Service();
        $container->set(\VuFindSearch\Service::class, $searchService);
        $events = $searchService->getEventManager();
        $events->attach('resolve', [$backendManager, 'onResolve']);
        return $container;
    }

    /**
     * Get container for services related to live Solr connectivity.
     *
     * @return \VuFindTest\Container\MockContainer
     */
    protected function getLiveSolrContainer()
    {
        if (null === $this->liveSolrContainer) {
            $this->liveSolrContainer = $this->createLiveSolrContainer();
        }
        return $this->liveSolrContainer;
    }

    /**
     * Get a search backend
     *
     * @param string $name Backend name
     *
     * @return object
     */
    protected function getBackend($name = 'Solr')
    {
        return $this->getLiveSolrContainer()
            ->get(\VuFind\Search\BackendManager::class)->get($name);
    }

    /**
     * Get a search results object
     *
     * @param string $name Backend name
     *
     * @return \VuFind\Search\Base\Results
     */
    protected function getResultsObject($name = 'Solr')
    {
        return $this->getLiveSolrContainer()
            ->get(\VuFind\Search\Results\PluginManager::class)->get($name);
    }
}
