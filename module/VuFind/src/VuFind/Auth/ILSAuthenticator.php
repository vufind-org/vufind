<?php

/**
 * Class for managing ILS-specific authentication.
 *
 * PHP version 7
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

use VuFind\Exception\ILS as ILSException;
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
     * Email authenticator
     *
     * @var EmailAuthenticator
     */
    protected $emailAuthenticator;

    /**
     * Cache for ILS account information (keyed by username)
     *
     * @var array
     */
    protected $ilsAccount = [];

    /**
     * Constructor
     *
     * @param Manager            $auth      Auth manager
     * @param ILSConnection      $catalog   ILS connection
     * @param EmailAuthenticator $emailAuth Email authenticator
     */
    public function __construct(
        Manager $auth,
        ILSConnection $catalog,
        EmailAuthenticator $emailAuth = null
    ) {
        $this->auth = $auth;
        $this->catalog = $catalog;
        $this->emailAuthenticator = $emailAuth;
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
                $user->cat_username,
                $user->getCatPassword()
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
            $this->updateUser($username, $password, $result);
            return $result;
        }
        return false;
    }

    /**
     * Send email authentication link
     *
     * @param string $email Email address
     * @param string $route Route for the login link
     *
     * @return void
     */
    public function sendEmailLoginLink($email, $route)
    {
        if (null === $this->emailAuthenticator) {
            throw new \Exception('Email authenticator not set');
        }

        $patron = $this->catalog->patronLogin($email, '');
        if ($patron) {
            $this->emailAuthenticator->sendAuthenticationLink(
                $patron['email'],
                $patron,
                ['auth_method' => 'ILS'],
                $route
            );
        }
    }

    /**
     * Process email login
     *
     * @param string $hash Login hash
     *
     * @return array|bool
     * @throws ILSException
     */
    public function processEmailLoginHash($hash)
    {
        if (null === $this->emailAuthenticator) {
            throw new \Exception('Email authenticator not set');
        }

        try {
            $patron = $this->emailAuthenticator->authenticate($hash);
        } catch (\VuFind\Exception\Auth $e) {
            return false;
        }
        $this->updateUser($patron['cat_username'], '', $patron);
        return $patron;
    }

    /**
     * Update current user account with the patron information
     *
     * @param string $catUsername Catalog username
     * @param string $catPassword Catalog password
     * @param array  $patron      Patron
     *
     * @return void
     */
    protected function updateUser($catUsername, $catPassword, $patron)
    {
        $user = $this->auth->isLoggedIn();
        if ($user) {
            $user->saveCredentials($catUsername, $catPassword);
            $this->auth->updateSession($user);
            // cache for future use
            $this->ilsAccount[$catUsername] = $patron;
        }
    }
}
