<?php

/**
 * Factory for ScheduledSearch/Notify command.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\ScheduledSearch;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\SearchServiceInterface;

/**
 * Factory for ScheduledSearch/Notify command.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class NotifyCommandFactory implements FactoryInterface
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
        $scheduleOptions = $container
            ->get(\VuFind\Search\History::class)
            ->getScheduleOptions();
        $mainConfig = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        // We need to initialize the theme so that the view renderer works:
        $theme = new \VuFindTheme\Initializer($mainConfig->Site, $container);
        $theme->init();

        // Now build the object:
        $command = new $requestedName(
            $container->get(\VuFind\Crypt\SecretCalculator::class),
            $container->get('ViewRenderer'),
            $container->get(\VuFind\Search\Results\PluginManager::class),
            $scheduleOptions,
            $mainConfig,
            $container->get(\VuFind\Mailer\Mailer::class),
            $container->get(\VuFind\Db\Service\PluginManager::class)->get(SearchServiceInterface::class),
            $container->get(\VuFind\I18n\Locale\LocaleSettings::class),
            ...($options ?? [])
        );
        $command->setPathResolver($container->get(PathResolver::class));
        return $command;
    }
}
