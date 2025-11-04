<?php

/**
 * Reservation list service factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\ReservationList;

use Finna\ReservationList\Handler\PluginManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Reservation list service factory.
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReservationListServiceFactory implements FactoryInterface
{
    /**
     * Create a FinnaResourceListService object.
     *
     * @param ContainerInterface $container Service manager
     * @param string             $name      Service being created
     * @param array              $options   Extra options (optional)
     *
     * @return ReservationListService
     */
    public function __invoke(ContainerInterface $container, $name, ?array $options = null)
    {
        $serviceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        $sessionManager = $container->get(\Laminas\Session\SessionManager::class);
        $session = new \Laminas\Session\Container('ReservationList', $sessionManager);
        $reservationListYaml = $container->get(\Finna\Config\YamlReader::class)
            ->getFinna('ReservationList.yaml', 'config/finna');
        return new ReservationListService(
            $serviceManager->get(\Finna\Db\Service\FinnaResourceListServiceInterface::class),
            $serviceManager->get(\Finna\Db\Service\FinnaResourceListResourceServiceInterface::class),
            $serviceManager->get(\VuFind\Db\Service\ResourceServiceInterface::class),
            $serviceManager->get(\VuFind\Db\Service\UserServiceInterface::class),
            $container->get(\VuFind\Record\ResourcePopulator::class),
            $container->get(\Finna\Record\Loader::class),
            $container->get(\VuFind\Record\Cache::class),
            $session,
            $container->get(\VuFindHttp\HttpService::class),
            $container->get(\VuFind\Auth\ILSAuthenticator::class),
            $container->get(\VuFind\Cache\Manager::class),
            $container->get(PluginManager::class),
            $reservationListYaml ?: []
        );
    }
}
