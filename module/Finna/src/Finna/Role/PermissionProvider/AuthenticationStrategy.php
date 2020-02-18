<?php
/**
 * Authentication strategy permission provider for VuFind.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
use Zend\Session\Container as SessionContainer;

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
     * Session storage
     *
     * @var SessionContainer
     */
    protected $sessionContainer;

    /**
     * Constructor
     *
     * @param AuthManager      $am      Authentication manager
     * @param ILSConnection    $ils     ILS connection
     * @param ILSAuthenticator $ilsAuth ILS authenticator
     * @param SessionContainer $session Session container
     */
    public function __construct(AuthManager $am, ILSConnection $ils,
        ILSAuthenticator $ilsAuth, SessionContainer $session
    ) {
        $this->authManager = $am;
        $this->ils = $ils;
        $this->ilsAuth = $ilsAuth;
        $this->sessionContainer = $session;
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
            if (false === $this->getPatronAuthorizationStatus(false)) {
                return ['loggedin'];
            }
        }

        if (in_array($selected, ['ILS', 'MultiILS'])
            && in_array('ILS-staff', $options)
        ) {
            // Check ILS for staff user
            if ($this->getPatronAuthorizationStatus(true)) {
                return ['loggedin'];
            }
        }

        return [];
    }

    /**
     * Get patron authorization status
     *
     * @param bool $staff Whether to check staff or normal user authorization
     *
     * @return mixed bool or null
     */
    protected function getPatronAuthorizationStatus($staff)
    {
        $func = $staff
            ? 'getPatronStaffAuthorizationStatus' : 'getPatronAuthorizationStatus';
        $code = $staff ? 'staff' : 'patron';

        try {
            if (($user = $this->authManager->isLoggedIn())
                && !empty($user->cat_username)
            ) {
                $key = $user->cat_username;
                if (!isset($this->sessionContainer->{$code})) {
                    $this->sessionContainer->{$code} = [];
                }
                if (!isset($this->sessionContainer->{$code}[$key])) {
                    $patron = $this->ilsAuth->storedCatalogLogin();
                    if ($patron) {
                        $functionConfig = $this->ils->checkFunction(
                            $func,
                            compact('patron')
                        );
                        $this->sessionContainer->{$code}[$key]
                            = $functionConfig && $this->ils->$func($patron);
                    } else {
                        $this->sessionContainer->{$code}[$key] = null;
                    }
                }
                return $this->sessionContainer->{$code}[$key];
            }
        } catch (ILSException $e) {
            $this->sessionContainer->{$code}->{$key} = null;
        }
        return false;
    }
}
