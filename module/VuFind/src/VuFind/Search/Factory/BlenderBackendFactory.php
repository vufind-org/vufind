<?php

/**
 * Factory for Blender backend.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2013-2022.
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
namespace VuFind\Search\Factory;

use Interop\Container\ContainerInterface;
use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VuFindSearch\Backend\Blender\Backend;

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
     * Service manager.
     *
     * @var ContainerInterface
     */
    protected $container;

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
        $this->container = $sm;
        $this->config = $sm->get(\VuFind\Config\PluginManager::class);
        $yamlReader = $sm->get(\VuFind\Config\YamlReader::class);
        $blenderConfig = $this->config->get($this->searchConfig);

        if ($blenderConfig->Backends->count() === 0) {
            throw new \Exception('No backends enabled in blender.ini');
        }
        $backends = [];
        $backendManager = $sm->get(\VuFind\Search\BackendManager::class);
        foreach (array_keys($blenderConfig->Backends->toArray()) as $backendId) {
            $backends[$backendId] = $backendManager->get($backendId);
        }
        $blenderMappings = $yamlReader->get('BlenderMappings.yaml');
        $backend = new Backend(
            $backends,
            $blenderConfig,
            $blenderMappings,
            new EventManager($sm->get('SharedEventManager'))
        );
        $this->attachEvents($backend);
        return $backend;
    }

    /**
     * Create Blender listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function attachEvents(Backend $backend)
    {
        $manager = $this->container->get('SharedEventManager');

        $manager->attach(
            'VuFind\Search',
            \VuFindSearch\Service::EVENT_PRE,
            [$backend, 'onSearchPre']
        );
        $manager->attach(
            'VuFind\Search',
            \VuFindSearch\Service::EVENT_POST,
            [$backend, 'onSearchPost']
        );
    }
}
