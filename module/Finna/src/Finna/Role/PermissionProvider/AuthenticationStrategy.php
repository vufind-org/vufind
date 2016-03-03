<?php
/**
 * Authentication strategy permission provider for VuFind.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Authorization
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Role\PermissionProvider;
use Finna\Auth\Manager,
    \VuFind\Role\PermissionProvider\PermissionProviderInterface;

/**
 * Authentication strategy permission provider for VuFind.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AuthenticationStrategy implements PermissionProviderInterface
{
    /**
     * Authentication manager
     *
     * @var Manager
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param ServiceLocator $serviceLocator ServiceLocator object
     */
    public function __construct($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Return an array of roles which may be granted the permission based on
     * the options.
     *
     * @param mixed $options Options provided from configuration.
     *
     * @return array
     */
    public function getPermissions($options)
    {
        $authManager = $this->serviceLocator->get('VuFind\AuthManager');
        $auth = $authManager->getActiveAuth();

        // Check if current authentication strategy is authorizable
        $selected = $auth->getSelectedAuthOption();
        if (in_array($selected, $options)) {
            return ['loggedin'];
        }

        if (in_array($selected, ['ILS', 'MultiILS'])
            && in_array('ILS-statCode', $options)
        ) {
            // Check ILS stat group
            $connection = $this->serviceLocator->get('VuFind\ILSConnection');
            $ilsAuth = $this->serviceLocator->get('VuFind\ILSAuthenticator');
            $patron = $ilsAuth->storedCatalogLogin();
            if ($patron &&
                (!$connection->checkFunction('getPatronAuthorizationStatus', $patron)
                || $connection->getPatronAuthorizationStatus($patron))
            ) {
                return ['loggedin'];
            }
        }
        return [];
    }
}
