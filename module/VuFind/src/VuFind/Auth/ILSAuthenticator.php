<?php
/**
 * Class for managing ILS-specific authentication.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Auth;
use VuFind\ILS\Connection as ILSConnection;

/**
 * Class for managing ILS-specific authentication.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ILSAuthenticator
{
    /**
     * Auth manager
     *
     * @var Manager
     */
    protected $auth;

    /**
     * ILS connector
     *
     * @var ILSConnection
     */
    protected $catalog;

    /**
     * Cache for ILS account information (keyed by username)
     *
     * @var array
     */
    protected $ilsAccount = [];

    /**
     * Constructor
     *
     * @param Manager       $auth    Auth manager
     * @param ILSConnection $catalog ILS connection
     */
    public function __construct(Manager $auth, ILSConnection $catalog)
    {
        $this->auth = $auth;
        $this->catalog = $catalog;
    }

    /**
     * Get stored catalog credentials for the current user.
     *
     * Returns associative array of cat_username and cat_password if they are
     * available, false otherwise. This method does not verify that the credentials
     * are valid.
     *
     * @return array|bool
     */
    public function getStoredCatalogCredentials()
    {
        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if (($user = $this->auth->isLoggedIn()) && !empty($user->cat_username)) {
            return [
                'cat_username' => $user->cat_username,
                'cat_password' => $user->cat_password
            ];
        }
        return false;
    }

    /**
     * Log the current user into the catalog using stored credentials; if this
     * fails, clear the user's stored credentials so they can enter new, corrected
     * ones.
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     */
    public function storedCatalogLogin()
    {
        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if (($user = $this->auth->isLoggedIn()) && !empty($user->cat_username)) {
            // Do we have a previously cached ILS account?
            if (isset($this->ilsAccount[$user->cat_username])) {
                return $this->ilsAccount[$user->cat_username];
            }
            $patron = $this->catalog->patronLogin(
                $user->cat_username, $user->getCatPassword()
            );
            if (empty($patron)) {
                // Problem logging in -- clear user credentials so they can be
                // prompted again; perhaps their password has changed in the
                // system!
                $user->clearCredentials();
            } else {
                // cache for future use
                $this->ilsAccount[$user->cat_username] = $patron;
                return $patron;
            }
        }

        return false;
    }

    /**
     * Attempt to log in the user to the ILS, and save credentials if it works.
     *
     * @param string $username Catalog username
     * @param string $password Catalog password
     *
     * Returns associative array of patron data on success, false on failure.
     *
     * @return array|bool
     * @throws ILSException
     */
    public function newCatalogLogin($username, $password)
    {
        $result = $this->catalog->patronLogin($username, $password);
        if ($result) {
            $user = $this->auth->isLoggedIn();
            if ($user) {
                $user->saveCredentials($username, $password);
                $this->auth->updateSession($user);
                // cache for future use
                $this->ilsAccount[$username] = $result;
            }
            return $result;
        }
        return false;
    }
}
