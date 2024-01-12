<?php

/**
 * Solr Hierarchy tree data source plugin factory.
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
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Hierarchy\TreeDataSource;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Solr Hierarchy tree data source plugin factory.
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SolrFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'Solr';

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
        if ($options !== null) {
            throw new \Exception('Unexpected options sent to factory!');
        }
        $cacheDir = $container->get(\VuFind\Cache\Manager::class)
            ->getCacheDir(false);
        $hierarchyFilters = $container->get(\VuFind\Config\PluginManager::class)
            ->get('HierarchyDefault');
        $filters = isset($hierarchyFilters->HierarchyTree->filterQueries)
          ? $hierarchyFilters->HierarchyTree->filterQueries->toArray()
          : [];
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $batchSize = $config->Index->cursor_batch_size ?? 1000;
        $searchService = $container->get(\VuFindSearch\Service::class);
        $formatterManager = $container
            ->get(\VuFind\Hierarchy\TreeDataFormatter\PluginManager::class);
        return new $requestedName(
            $searchService,
            $this->backendId,
            $formatterManager,
            rtrim($cacheDir, '/') . '/hierarchy',
            $filters,
            $batchSize
        );
    }
}
