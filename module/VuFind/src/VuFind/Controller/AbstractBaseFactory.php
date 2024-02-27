<?php

/**
 * Generic controller factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Generic controller factory.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AbstractBaseFactory implements FactoryInterface
{
    /**
     * Apply permission settings to the controller.
     *
     * @param ContainerInterface $container  Service manager
     * @param AbstractBase       $controller Controller to configure
     *
     * @return AbstractBase
     */
    protected function applyPermissions($container, $controller)
    {
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('permissionBehavior');
        $permissions = $config->global->controllerAccess ?? [];

        if (!empty($permissions) && $controller instanceof Feature\AccessPermissionInterface) {
            // Iterate through parent classes until we find the most specific
            // class access permission defined (if any):
            $class = $controller::class;
            do {
                if (isset($permissions[$class])) {
                    $controller->setAccessPermission($permissions[$class]);
                    break;
                }
                $class = get_parent_class($class);
            } while ($class);

            // If the controller's current permission is null (as opposed to false
            // or a string), that means it has no internally configured default, and
            // setAccessPermission was not called above; thus, we should apply the
            // default value:
            if (
                isset($permissions['*'])
                && $controller->getAccessPermission() === null
            ) {
                $controller->setAccessPermission($permissions['*']);
            }
        }

        return $controller;
    }

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
        return $this->applyPermissions(
            $container,
            new $requestedName($container, ...($options ?: []))
        );
    }
}
