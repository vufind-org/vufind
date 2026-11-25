<?php

/**
 * Event manager factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\EventManager;

use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Event manager factory.
 *
 * @category VuFind
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EventManagerFactory implements FactoryInterface
{
    /**
     * Create an EventManager instance.
     *
     * Creates a new EventManager instance, seeding it with a shared instance of SharedEventManager.
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     */
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $shared = $container->has('SharedEventManager') ? $container->get('SharedEventManager') : null;

        $result = new EventManager($shared);
        $result->setEventPrototype(new Event());
        return $result;
    }
}
