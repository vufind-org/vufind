<?php

/**
 * Factory for Blender backend.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2019.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use Finna\Search\Solr\DeduplicationListener;
use Finna\Search\Solr\SolrExtensionsListener;

use FinnaSearch\Backend\Blender\Backend;

use Interop\Container\ContainerInterface;

use VuFindSearch\Backend\BackendInterface;

use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for Blender backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class BlenderBackendFactory implements FactoryInterface
{
    /**
     * Superior service manager.
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * VuFind configuration reader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $config;

    /**
     * Search configuration file identifier.
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * Facet configuration file identifier.
     *
     * @var string
     */
    protected $facetConfig;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchConfig = 'Blender';
        $this->facetConfig = 'Blender';
    }

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name (unused)
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $this->serviceLocator = $sm;
        $this->config = $sm->get(\VuFind\Config\PluginManager::class);
        $yamlReader = $sm->get(\VuFind\Config\YamlReader::class);
        $blenderConfig = $this->config->get($this->searchConfig);
        if (!isset($blenderConfig['Primary']['backend'])) {
            throw new \Exception('Primary backend not configured in blender.ini');
        }
        if (!isset($blenderConfig['Secondary']['backend'])) {
            throw new \Exception('Secondary backend not configured in blender.ini');
        }
        $blenderMappings = $yamlReader->get('BlenderMappings.yaml');
        $backendManager = $sm->get(\VuFind\Search\BackendManager::class);
        $backend = new Backend(
            $backendManager->get($blenderConfig['Primary']['backend']),
            $backendManager->get($blenderConfig['Secondary']['backend']),
            $blenderConfig,
            $blenderMappings
        );
        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(Backend $backend)
    {
        // Apply deduplication also if it's not enabled by default (could be enabled
        // by a special filter):
        $search = $this->config->get($this->searchConfig);
        $events = $this->serviceLocator->get('SharedEventManager');
        $this->getDeduplicationListener(
            $backend, $search->Records->deduplication ?? false
        )->attach($events);

        // Finna Solr Extensions
        $solrExtensions = new SolrExtensionsListener(
            $backend,
            $this->serviceLocator,
            $this->searchConfig,
            $this->facetConfig
        );
        $solrExtensions->attach($events);
    }

    /**
     * Get a deduplication listener for the backend
     *
     * @param BackendInterface $backend Search backend
     * @param bool             $enabled Whether deduplication is enabled
     *
     * @return DeduplicationListener
     */
    protected function getDeduplicationListener(BackendInterface $backend, $enabled)
    {
        return new DeduplicationListener(
            $backend,
            $this->serviceLocator,
            $this->searchConfig,
            'datasources',
            $enabled
        );
    }
}
