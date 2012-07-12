<?php
/**
 * Wrapper class for handling logged-in user in session.
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Auth;
use VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Auth as AuthException, VuFind\Exception\ILS as ILSException,
    VuFind\Registry, Zend\Session\Container as SessionContainer;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Manager
{
    protected $auth;
    protected $config;
    protected $session;
    protected $ilsAccount = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = ConfigReader::getConfig();
        $this->auth = Factory::getAuth(
            $this->config->Authentication->method, $this->config
        );
        $this->session = new SessionContainer('Account');
    }

    /**
     * Does the current configuration support account creation?
     *
     * @return bool
     */
    public function supportsCreation()
    {
        return $this->auth->supportsCreation();
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @return bool|string
     */
    public function getSessionInitiator()
    {
        return $this->auth->getSessionInitiator();
    }

    /**
     * Get the name of the current authentication class.
     *
     * @return string
     */
    public function getAuthClass()
    {
        return get_class($this->auth);
    }

    /**
     * Is login currently allowed?
     *
     * @return bool
     */
    public function loginEnabled()
    {
        if (isset($this->config->Authentication->hideLogin)
            && $this->config->Authentication->hideLogin
        ) {
            return false;
        }
        try {
            $catalog = ConnectionManager::connectToCatalog();
        } catch (\Exception $e) {
            // If we can't connect to the catalog, assume that no special
            // ILS-related login settings exist -- this prevents ILS errors
            // from triggering an exception early in initialization before
            // VuFind is ready to display error messages.
            return true;
        }
        return !$catalog->loginIsHidden();
    }

    /**
     * Log out the current user.
     *
     * @param string $url     URL to redirect user to after logging out.
     * @param bool   $destroy Should we destroy the session (true) or just reset it
     * (false); destroy is for log out, reset is for expiration.
     *
     * @return string     Redirect URL (usually same as $url, but modified in
     * some authentication modules).
     */
    public function logout($url, $destroy = true)
    {
        // Perform authentication-specific cleanup and modify redirect URL if
        // necessary.
        $url = $this->auth->logout($url);

        // Clear out cached ILS connection.
        $this->ilsAccount = false;

        // Clear out the cached user object.
        unset($this->session->user);

        // Destroy the session for good measure, if requested.
        if ($destroy) {
            Registry::getInstance()->get('Zend_Session')->destroy();
        } else {
            // If we don't want to destroy the session, we still need to empty it.
            // There should be a way to do this through Zend\Session, but there
            // apparently isn't (TODO -- do this better):
            $_SESSION = array();
        }

        return $url;
    }

    /**
     * Checks whether the user is logged in.
     *
     * @return Zend_Db_Table_Row_Abstract|bool Object if user is logged in, false
     * otherwise.
     */
    public function isLoggedIn()
    {
        return isset($this->session->user) ? $this->session->user : false;
    }

    /**
     * Resets the session if the logged in user's credentials have expired.
     *
     * @return bool True if session has expired.
     */
    public function checkForExpiredCredentials()
    {
        if ($this->isLoggedIn() && $this->auth->isExpired()) {
            $this->logout(null, false);
            return true;
        }
        return false;
    }

    /**
     * Updates the user information in the session.
     *
     * @param Zend_Db_Table_Row_Abstract $user User object to store in the session
     *
     * @return void
     */
    public function updateSession($user)
    {
        $this->session->user = $user;
    }

    /**
     * Create a new user account from the request.
     *
     * @param Zend_Controller_Request_Abstract $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return Zend_Db_Table_Row_Abstract New user row.
     */
    public function create($request)
    {
        $user = $this->auth->create($request);
        $this->updateSession($user);
        return $user;
    }

    /**
     * Try to log in the user using current query parameters; return User object
     * on success, throws exception on failure.
     *
     * @param Zend_Controller_Request_Abstract $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return Zend_Db_Table_Row_Abstract Object representing logged-in user.
     */
    public function login($request)
    {
        // Perform authentication:
        try {
            $user = $this->auth->authenticate($request);
        } catch (AuthException $e) {
            // Pass authentication exceptions through unmodified
            throw $e;
        } catch (\Exception $e) {
            // Catch other exceptions and treat them as technical difficulties
            throw new AuthException('authentication_error_technical');
        }

        // Store the user in the session and send it back to the caller:
        $this->updateSession($user);
        return $user;
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
        // Do we have a previously cached ILS account?
        if (is_array($this->ilsAccount)) {
            return $this->ilsAccount;
        }

        try {
            $catalog = ConnectionManager::connectToCatalog();
        } catch (ILSException $e) {
            return false;
        }
        $user = $this->isLoggedIn();

        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if ($user && isset($user->cat_username) && !empty($user->cat_username)) {
            try {
                $patron = $catalog->patronLogin(
                    $user->cat_username,
                    isset($user->cat_password) ? $user->cat_password : null
                );
            } catch (ILSException $e) {
                $patron = null;
            }
            if (empty($patron)) {
                // Problem logging in -- clear user credentials so they can be
                // prompted again; perhaps their password has changed in the
                // system!
                $user->cat_username = null;
                $user->cat_password = null;
            } else {
                $this->ilsAccount = $patron;    // cache for future use
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
     */
    public function newCatalogLogin($username, $password)
    {
        try {
            $catalog = ConnectionManager::connectToCatalog();
            $result = $catalog->patronLogin($username, $password);
        } catch (ILSException $e) {
            return false;
        }
        if ($result) {
            $user = $this->isLoggedIn();
            if ($user) {
                $user->saveCredentials($username, $password);
                $this->updateSession($user);
                $this->ilsAccount = $result;    // cache for future use
            }
            return $result;
        }
        return false;
    }
}