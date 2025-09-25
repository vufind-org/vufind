<?php

/**
 * Factory for online payment background monitor.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace VuFindConsole\Command\OnlinePayment;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Mailer\Mailer;
use VuFind\OnlinePayment\OnlinePaymentManager;

/**
 * Factory for online payment background monitor.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class MonitorCommandFactory implements FactoryInterface
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
        $mainConfig = $container->get(\VuFind\Config\ConfigManager::class)->getConfigObject('config');
        $theme = new \VuFindTheme\Initializer($mainConfig->Site, $container);
        $theme->init();

        $dbServiceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        return new $requestedName(
            $dbServiceManager->get(PaymentServiceInterface::class),
            $container->get(OnlinePaymentManager::class),
            $container->get('ViewRenderer'),
            $container->get(Mailer::class),
            $mainConfig->toArray(),
            $dbServiceManager->get(AuditEventServiceInterface::class),
            ...($options ?? [])
        );
    }
}
