<?php

/**
 * Factory for the "verify record links" task.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use Finna\Db\Service\CommentsServiceInterface;
use Finna\Db\Service\FinnaCommentsRecordServiceInterface;
use Finna\Db\Service\RatingsServiceInterface;
use Finna\Db\Service\RecordServiceInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\ResourceServiceInterface;

/**
 * Factory for the "verify record links" task.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class VerifyRecordLinksFactory implements FactoryInterface
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
        $dbServiceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        return new $requestedName(
            $container->get('doctrine.entitymanager.orm_vufind'),
            $dbServiceManager->get(RecordServiceInterface::class),
            $dbServiceManager->get(CommentsServiceInterface::class),
            $dbServiceManager->get(FinnaCommentsRecordServiceInterface::class),
            $dbServiceManager->get(RatingsServiceInterface::class),
            $dbServiceManager->get(ResourceServiceInterface::class),
            $container->get(\VuFind\Search\BackendManager::class)->get('Solr'),
            $container->get(\VuFind\Record\Loader::class),
            $container->get(\VuFind\Config\ConfigManager::class)->getConfigArray('searches'),
            ...($options ?? [])
        );
    }
}
