<?php
/**
 * VuFind dynamic role provider factory.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * VuFind dynamic role provider factory.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DynamicRoleProviderFactory implements FactoryInterface
{
    /**
     * Create the service.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     *
     * @return DynamicRoleProvider
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->getServiceLocator()->get('config');
        $rbacConfig = $config['zfc_rbac'];
        return new DynamicRoleProvider(
            $this->getPermissionProviderPluginManager($serviceLocator, $rbacConfig),
            $rbacConfig['role_provider']['VuFind\Role\DynamicRoleProvider']
        );
    }

    /**
     * Create the supporting plugin manager.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param array $rbacConfig ZfcRbac configuration
     *
     * @return PermissionProviderPluginManager
     */
    protected function getPermissionProviderPluginManager(
        ServiceLocatorInterface $serviceLocator, array $rbacConfig
    ) {
        $pm = new PermissionProviderPluginManager(
            new Config($rbacConfig['vufind_permission_provider_manager'])
        );
        $pm->setServiceLocator($serviceLocator->getServiceLocator());
        return $pm;
    }
}
