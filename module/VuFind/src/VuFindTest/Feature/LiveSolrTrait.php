<?php

/**
 * Mix-in for accessing a real Solr instance during testing.
 *
 * PHP version 7
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
    /**
     * Container for services related to live Solr connectivity.
     *
     * @var \VuFindTest\Container\MockContainer
     */
    protected $liveSolrContainer = null;

    /**
     * Get container for services related to live Solr connectivity.
     *
     * @return \VuFindTest\Container\MockContainer
     */
    protected function getLiveSolrContainer()
    {
        if (null === $this->liveSolrContainer) {
            $container = new \VuFindTest\Container\MockContainer($this);
            $config = require(
                APPLICATION_PATH . '/module/VuFind/config/module.config.php'
            );
            $configManager = new \VuFind\Config\PluginManager(
                $container, $config['vufind']['config_reader']
            );
            $container->set(\VuFind\Config\PluginManager::class, $configManager);
            $container->set(
                \VuFind\Config\SearchSpecsReader::class,
                new \VuFind\Config\SearchSpecsReader()
            );
            $container->set(
                'SharedEventManager', new \Laminas\EventManager\SharedEventManager()
            );
            $recordDriverFactory = new \VuFind\RecordDriver\PluginManager($container);
            $container->set(
                \VuFind\RecordDriver\PluginManager::class, $recordDriverFactory
            );
            $registry = new \VuFind\Search\BackendRegistry(
                $container, $config
            );
            $bm = new \VuFind\Search\BackendManager($registry);
            $container->set('VuFind\Search\BackendManager', $bm);
            $this->liveSolrContainer = $container;
        }
        return $this->liveSolrContainer;
    }

    protected function getBackend($name = 'Solr')
    {
        return $this->getLiveSolrContainer()
            ->get(\VuFind\Search\BackendManager::class)->get($name);
    }
}
