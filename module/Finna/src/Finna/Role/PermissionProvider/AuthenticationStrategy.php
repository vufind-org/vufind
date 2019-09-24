<?php
/**
 * Authentication strategy permission provider for VuFind.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Authorization
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Role\PermissionProvider;

use Finna\Auth\ILSAuthenticator;
use Finna\Auth\Manager as AuthManager;
use Finna\ILS\Connection as ILSConnection;
use VuFind\Exception\ILS as ILSException;
use VuFind\Role\PermissionProvider\PermissionProviderInterface;

/**
 * Authentication strategy permission provider for VuFind.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AuthenticationStrategy implements PermissionProviderInterface
{
    /**
     * Authentication manager
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * ILS authenticator
     *
     * @var ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * ILS connection
     *
     * @var ILSConnection
     */
    protected $ils;

    /**
     * Constructor
     *
     * @param AuthManager      $am      Authentication manager
     * @param ILSConnection    $ils     ILS connection
     * @param ILSAuthenticator $ilsAuth ILS authenticator
     */
    public function __construct(AuthManager $am, ILSConnection $ils,
        ILSAuthenticator $ilsAuth
    ) {
        $this->authManager = $am;
        $this->ils = $ils;
        $this->ilsAuth = $ilsAuth;
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
        $auth = $this->authManager->getActiveAuth();

        // Check if current authentication strategy is authorizable
        $selected = $auth->getSelectedAuthOption();
        if (in_array($selected, $options)) {
            return ['loggedin'];
        }

        if (in_array($selected, ['ILS', 'MultiILS'])
            && in_array('ILS-statCode', $options)
        ) {
            // Check ILS stat group
            try {
                $patron = $this->ilsAuth->storedCatalogLogin();
                if ($patron) {
                    $functionConfig = $this->ils->checkFunction(
                        'getPatronAuthorizationStatus',
                        compact('patron')
                    );
                    if ($functionConfig
                        && !$this->ils->getPatronAuthorizationStatus($patron)
                    ) {
                        return ['loggedin'];
                    }
                }
            } catch (ILSException $e) {
            }
        }

        if (in_array($selected, ['ILS', 'MultiILS'])
            && in_array('ILS-staff', $options)
        ) {
            // Check ILS for staff user
            try {
                $patron = $this->ilsAuth->storedCatalogLogin();
                if ($patron) {
                    $functionConfig = $this->ils->checkFunction(
                        'getPatronStaffAuthorizationStatus',
                        compact('patron')
                    );
                    if ($functionConfig
                        && $this->ils->getPatronStaffAuthorizationStatus($patron)
                    ) {
                        return ['loggedin'];
                    }
                }
            } catch (ILSException $e) {
            }
        }

        return [];
    }
}
