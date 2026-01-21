<?php

/**
 * Factory for Blender backend.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org   Main Site
 */

namespace VuFind\Search\Factory;

use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFindSearch\Backend\Blender\Backend;

/**
 * Factory for Blender backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org   Main Site
 */
class BlenderBackendFactory implements FactoryInterface
{
    /**
     * Service manager.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * VuFind configuration reader
     *
     * @var ConfigManagerInterface
     */
    protected ConfigManagerInterface $configManager;

    /**
     * Search configuration file identifier.
     *
     * @var string
     */
    protected string $searchConfig = 'Blender';

    /**
     * Facet configuration file identifier.
     *
     * @var string
     */
    protected string $facetConfig = 'Blender';

    /**
     * Mappings YAML configuration file identifier.
     *
     * @var string
     */
    protected string $mappingsConfig = 'BlenderMappings';

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $this->container = $container;
        $this->configManager = $container->get(ConfigManagerInterface::class);
        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $blenderConfig = $this->configManager->getConfigObject($this->searchConfig);
        $backendConfig = $blenderConfig->Backends
            ? $blenderConfig->Backends->toArray() : [];
        if (!$backendConfig) {
            throw new \Exception("No backends enabled in {$this->searchConfig}.ini");
        }
        $backends = [];
        $backendManager = $container->get(\VuFind\Search\BackendManager::class);
        foreach (array_keys($backendConfig) as $backendId) {
            $backends[$backendId] = $backendManager->get($backendId);
        }
        // Legacy code may already include the '.yaml' extension; ignore it for safety:
        $blenderMappings = $yamlReader->get(str_ends_with($this->mappingsConfig, '.yaml')
            ? $this->mappingsConfig
            : $this->mappingsConfig . '.yaml');
        $backend = new Backend(
            $backends,
            $blenderConfig,
            $blenderMappings,
            new EventManager($container->get('SharedEventManager'))
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
    protected function attachEvents(Backend $backend): void
    {
        $manager = $this->container->get('SharedEventManager');

        $manager->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_PRE,
            [$backend, 'onSearchPre']
        );
        $manager->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_POST,
            [$backend, 'onSearchPost']
        );
    }
}
