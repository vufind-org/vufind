<?php

/**
 * Online payment factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2025.
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;

use function in_array;

/**
 * Online payment factory.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OnlinePaymentManagerFactory implements FactoryInterface
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
            throw new \Exception('Unexpected options passed to factory.');
        }
        $dbServiceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        $devToolsAvailable = in_array('VuFindDevTools', $container->get('ModuleManager')->getModules());
        return new $requestedName(
            $container->get(\VuFind\OnlinePayment\Handler\PluginManager::class),
            $container->get(\VuFind\ILS\Connection::class),
            $container->get(\VuFind\Auth\ILSAuthenticator::class),
            $dbServiceManager->get(PaymentServiceInterface::class),
            $dbServiceManager->get(PaymentFeeServiceInterface::class),
            $dbServiceManager->get(UserCardServiceInterface::class),
            $dbServiceManager->get(AuditEventServiceInterface::class),
            $container->get(\VuFind\OnlinePayment\Receipt::class),
            $container->get(\Laminas\Session\SessionManager::class),
            $devToolsAvailable
        );
    }
}
