<?php

/**
 * BrowZine identifier linker factory
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018-2025.
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
 * @package  IdentifierLinker
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:identifier_linkers Wiki
 */

namespace VuFind\IdentifierLinker;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * BrowZine identifier linker factory
 *
 * @category VuFind
 * @package  IdentifierLinker
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:identifier_linkers Wiki
 */
class BrowZineFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Default DOI services to return if no configuration is provided.
     *
     * @var array
     */
    protected array $defaultDoiServices;

    /**
     * Default ISSN services to return if no configuration is provided.
     *
     * @var array
     */
    protected array $defaultIssnServices;

    /**
     * Constructor
     */
    public function __construct()
    {
        $baseIconUrl = 'https://assets.thirdiron.com/images/integrations/';
        $this->defaultDoiServices = [
            'browzineWebLink' => "View Complete Issue|browzine-issue|{$baseIconUrl}browzine-open-book-icon.svg",
            'fullTextFile' => "PDF Full Text|browzine-pdf|{$baseIconUrl}browzine-pdf-download-icon.svg",
        ];
        $this->defaultIssnServices = [
            'browzineWebLink' => "Browse Available Issues|browzine-issue|{$baseIconUrl}browzine-open-book-icon.svg",
        ];
    }

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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $search = $container->get(\VuFindSearch\Service::class);
        $fullConfig = $container->get(\VuFind\Config\PluginManager::class)->get('BrowZine')->toArray();
        // DOI config section is supported as a fallback for back-compatibility:
        $config = $fullConfig['IdentifierLinks'] ?? $fullConfig['DOI'] ?? [];

        return new $requestedName(
            $search,
            $config,
            $fullConfig['DOIServices'] ?? $this->defaultDoiServices,
            $fullConfig['ISSNServices'] ?? $this->defaultIssnServices
        );
    }
}
