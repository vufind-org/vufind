<?php

/**
 * Cover controller factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2018.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Controller;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\PluginManager;

/**
 * Cover controller factory.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CoverControllerFactory implements FactoryInterface
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
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $dbManager = $container->get(PluginManager::class);
        $config = $container->get(\VuFind\Config\PluginManager::class)->get('config');
        $configArray = $config?->Content?->toArray() ?? [];
        return new $requestedName(
            $container->get(\VuFind\Cover\Loader::class),
            $container->get(\VuFind\Cover\CachingProxy::class),
            $container->get(\VuFind\Session\Settings::class),
            $container->get(\VuFind\Config\PluginManager::class)->get('datasources'),
            $container->get(\VuFind\Record\Loader::class),
            $configArray,
            $container->get(\Finna\File\Loader::class),
            $dbManager->get(\VuFind\Db\Service\AccessTokenService::class)
        );
    }
}
