<?php

/**
 * Factory for Blender search params objects.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2022.
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
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search\Blender;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for Solr search params objects.
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParamsFactory extends \VuFind\Search\Params\ParamsFactory
{
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
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $configLoader = $container->get(\VuFind\Config\PluginManager::class);
        $blenderConfig = $configLoader->get('Blender');
        $backendConfig = $blenderConfig->Backends
            ? $blenderConfig->Backends->toArray() : [];
        if (!$backendConfig) {
            throw new \Exception('No backends enabled in Blender.ini');
        }

        $facetHelper
            = $container->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class);

        $searchParams = [];
        $paramsManager = $container->get(\VuFind\Search\Params\PluginManager::class);
        foreach (array_keys($backendConfig) as $backendId) {
            $searchParams[] = $paramsManager->get($backendId);
        }

        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $blenderMappings = $yamlReader->get('BlenderMappings.yaml');
        return parent::__invoke(
            $container,
            $requestedName,
            [$facetHelper, $searchParams, $blenderConfig, $blenderMappings]
        );
    }
}
