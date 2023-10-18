<?php

/**
 * Generic factory for search results objects.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search\Results;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Search\Factory\UrlQueryHelperFactory;

/**
 * Generic factory for search results objects.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ResultsFactory implements FactoryInterface
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
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        // Replace trailing "Results" with "Params" to get the params service:
        $paramsService = preg_replace('/Results$/', 'Params', $requestedName);
        // Replace leading namespace with "VuFind" if service is not available:
        $paramsServiceAvailable = $container
            ->get(\VuFind\Search\Params\PluginManager::class)->has($paramsService);
        if (!$paramsServiceAvailable) {
            $paramsService = preg_replace('/^[^\\\]+/', 'VuFind', $paramsService);
        }
        $params = $container->get(\VuFind\Search\Params\PluginManager::class)
            ->get($paramsService);
        $searchService = $container->get(\VuFindSearch\Service::class);
        $recordLoader = $container->get(\VuFind\Record\Loader::class);
        $results = new $requestedName(
            $params,
            $searchService,
            $recordLoader,
            ...($options ?: [])
        );
        $results->setUrlQueryHelperFactory(
            $container->get(UrlQueryHelperFactory::class)
        );
        return $results;
    }
}
