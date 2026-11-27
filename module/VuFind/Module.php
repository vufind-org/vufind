<?php

/**
 * Code module for the core of the VuFind application.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Module
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind;

use Laminas\EventManager\EventManagerInterface;
use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFind\EventManager\Event;
use VuFind\EventManager\EventInterface;
use VuFind\ModuleManager\ModuleInterface;

/**
 * Code module for the core of the VuFind application.
 *
 * @category VuFind
 * @package  Module
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Module implements ModuleInterface
{
    /**
     * Get module configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Initialize the module.
     *
     * @param ContainerInterface $container Service manager
     *
     * @return void
     */
    public function initialize(ContainerInterface $container): void
    {
        $eventManager = $container->get(EventManagerInterface::class);
        $eventManager->attach(Event::EVENT_BOOTSTRAP, fn ($e) => $this->onBootstrap($e, $container, $eventManager));
    }

    /**
     * Bootstrap the module.
     *
     * @param EventInterface        $event        Event
     * @param ContainerInterface    $container    Service manager
     * @param EventManagerInterface $eventManager Event manager
     *
     * @return void
     */
    public function onBootstrap(
        EventInterface $event,
        ContainerInterface $container,
        EventManagerInterface $eventManager
    ): void {
        $config = $container->get(ConfigManagerInterface::class)->getConfigArray('config');
        $bootstrapper = new Bootstrapper($event, $container, $eventManager, $config);
        $bootstrapper->bootstrap();
    }
}
