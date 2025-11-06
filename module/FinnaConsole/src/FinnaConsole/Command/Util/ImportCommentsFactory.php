<?php

/**
 * Factory for the "import comments" task.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\Search\SearchRunner;

/**
 * Factory for the "import comments" task.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ImportCommentsFactory implements FactoryInterface
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
        // We need to initialize the theme so that the view renderer works:
        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $mainConfig = $configManager->getConfigArray('config');
        $theme = new \VuFindTheme\Initializer($mainConfig['Site'], $container);
        $theme->init();

        $dbServiceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        return new $requestedName(
            $dbServiceManager->get(UserServiceInterface::class),
            $dbServiceManager->get(CommentsServiceInterface::class),
            $dbServiceManager->get(RatingsServiceInterface::class),
            $container->get(ResourcePopulator::class),
            $container->get(RecordLoader::class),
            $container->get(SearchRunner::class),
            ...($options ?? [])
        );
    }
}
