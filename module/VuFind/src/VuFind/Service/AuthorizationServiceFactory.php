<?php

/**
 * Authorization service factory to inject assertions and permissions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Service;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Lmc\Rbac\Assertion\AssertionPluginManager;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Lmc\Rbac\Mvc\Service\AuthorizationServiceFactory as LmcRbacAuthorizationServiceFactory;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Authorization service factory
 *
 * @category VuFind
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AuthorizationServiceFactory extends LmcRbacAuthorizationServiceFactory
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
        ?array $options = null
    ): AuthorizationService {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $authorizationService = parent::__invoke($container, $requestedName, $options);
        $permissions = $container->get(\VuFind\Config\ConfigManagerInterface::class)->getConfigArray('permissions');
        $assertionPluginManager = $container->get(AssertionPluginManager::class);
        foreach ($permissions as $key => $settings) {
            $sectionPermissions = (array)($settings['permission'] ?? []);
            $assertions = (array)($settings['assertion'] ?? []);
            if ($sectionPermissions && $assertions) {
                foreach ($sectionPermissions as $permission) {
                    foreach ($assertions as $assertion) {
                        $authorizationService->setAssertion($permission, $assertionPluginManager->get($assertion));
                    }
                }
            }
        }
        return $authorizationService;
    }
}
