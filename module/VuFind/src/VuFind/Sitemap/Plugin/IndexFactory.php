<?php

/**
 * Index-based generator plugin factory
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
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Sitemap\Plugin;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function is_callable;

/**
 * Index-based generator plugin factory
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class IndexFactory implements FactoryInterface
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
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $configLoader = $container->get(\VuFind\Config\PluginManager::class);
        $sitemapConfig = $configLoader->get('sitemap');
        $retrievalMode = $sitemapConfig->Sitemap->retrievalMode ?? 'search';
        return new $requestedName(
            $this->getBackendSettings($sitemapConfig),
            $this->getIdFetcher($container, $retrievalMode),
            $sitemapConfig->Sitemap->countPerPage ?? 10000,
            (array)($sitemapConfig->Sitemap->extraFilters ?? [])
        );
    }

    /**
     * Process backend configuration into a convenient array.
     *
     * @param Config $config Sitemap config
     *
     * @return array
     */
    protected function getBackendSettings($config): array
    {
        // Process backend configuration:
        $backendConfig = $config->Sitemap->index ?? ['Solr,/Record/'];
        if (!$backendConfig) {
            return [];
        }
        $backendConfig = is_callable([$backendConfig, 'toArray'])
            ? $backendConfig->toArray() : (array)$backendConfig;
        $callback = function ($n) {
            $parts = array_map('trim', explode(',', $n));
            return ['id' => $parts[0], 'url' => $parts[1]];
        };
        return array_map($callback, $backendConfig);
    }

    /**
     * Get the helper object for generating sitemaps through the search service.
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $retrievalMode Retrieval mode ('terms' or 'search')
     *
     * @return Index\AbstractIdFetcher
     */
    protected function getIdFetcher(
        ContainerInterface $container,
        $retrievalMode
    ): Index\AbstractIdFetcher {
        $class = $retrievalMode === 'terms'
            ? Index\TermsIdFetcher::class : Index\CursorMarkIdFetcher::class;
        return new $class($container->get(\VuFindSearch\Service::class));
    }
}
