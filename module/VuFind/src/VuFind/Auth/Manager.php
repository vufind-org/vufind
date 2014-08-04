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
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException, VuFind\Exception\ILS as ILSException,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Manager implements ServiceLocatorAwareInterface
{
    /**
     * Authentication module (false if uninitialized)
     *
     * @var \VuFind\Auth\AbstractBase|bool
     */
    protected $auth = false;

    /**
     * Authentication module currently proxied (false if uninitialized)
     *
     * @var \VuFind\Auth\AbstractBase|bool
     */
    protected $authProxied = false;

    /**
     * Authentication module to proxy (false if uninitialized)
     *
     * @var string|bool
     */
    protected $authToProxy = false;

    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Session container
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * ILS account information (false if uninitialized)
     *
     * @var array|bool
     */
    protected $ilsAccount = false;

    /**
     * Cache for current logged in user object
     *
     * @var \VuFind\Db\Row\User
     */
    protected $currentUser = false;

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;
        $this->session = new \Zend\Session\Container('Account');
    }

    /**
     * Get the ILS connection.
     *
     * @return \VuFind\ILS\Connection
     */
    protected function getILS()
    {
        return $this->getServiceLocator()->get('VuFind\ILSConnection');
    }

    /**
     * Get the authentication handler.
     *
     * @return AbstractBase
     */
    protected function getAuth()
    {
        if ($this->authToProxy == false) {
            if (!$this->auth) {
                $this->auth = $this->makeAuth($this->config->Authentication->method);
            }
            return $this->auth;
        } else {
            if (!$this->authProxied) {
                $this->authProxied = $this->makeAuth($this->authToProxy);
            }
            return $this->authProxied;
        }
    }

    /**
     * Helper
     *
     * @param string $method auth method to instantiate
     *
     * @return AbstractBase
     */
    protected function makeAuth($method)
    {
        $manager = $this->getServiceLocator()->get('VuFind\AuthPluginManager');
        $auth = $manager->get($method);
        $auth->setConfig($this->config);
        return $auth;
    }

    /**
     * Does the current configuration support account creation?
     *
     * @param string $authMethod optional; check this auth method rather than
     *  the one in config file
     *
     * @return bool
     */
    public function supportsCreation($authMethod=null)
    {
        if ($authMethod != null) {
            $this->setActiveAuthClass($authMethod);
        }
        return $this->getAuth()->supportsCreation();
    }

    /**
     * Does the current configuration support password recovery?
     *
     * @param string $authMethod optional; check this auth method rather than
     *  the one in config file
     *
     * @return bool
     */
    public function supportsRecovery($authMethod=null)
    {
        if ($authMethod != null) {
            $this->setActiveAuthClass($authMethod);
        }
        if ($this->getAuth()->supportsPasswordChange()) {
            return isset($this->config->Authentication->recover_password)
                && $this->config->Authentication->recover_password;
        }
        return false;
    }

    /**
     * Is new passwords currently allowed?
     *
     * @param string $authMethod optional; check this auth method rather than
     *  the one in config file
     *
     * @return bool
     */
    public function supportsPasswordChange($authMethod=null)
    {
        if ($authMethod != null) {
            $this->setActiveAuthClass($authMethod);
        }
        if ($this->getAuth()->supportsPasswordChange()) {
            return isset($this->config->Authentication->change_password)
                && $this->config->Authentication->change_password;
        }
        return false;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        return $this->getAuth()->getSessionInitiator($target);
    }

    /**
     * Get the name of the current authentication class.
     *
     * @return string
     */
    public function getAuthClass()
    {
        return get_class($this->getAuth());
    }

    /**
     * Does the current auth class allow for authentication from more than
     * one auth method? (e.g. choiceauth)
     * If so return an array that lists the classes for the methods allowed.
     *
     * @return array
     */
    public function getAuthClasses()
    {
        $classes = $this->getAuth()->getClasses();
        if ($classes) {
            return $classes;
        } else {
            return array($this->getAuthClass());
        }
    }

    /**
     * Does the current auth class allow for authentication from more than
     * one target? (e.g. MultiILS)
     * If so return an array that lists the targets.
     *
     * @return array
     */
    public function getLoginTargets()
    {
        $auth = $this->getAuth();
        return is_callable(array($auth, 'getLoginTargets'))
            ? $auth->getLoginTargets() : array();
    }

    /**
     * Does the current auth class allow for authentication from more than
     * one target? (e.g. MultiILS)
     * If so return the default target.
     *
     * @return string
     */
    public function getDefaultLoginTarget()
    {
        $auth = $this->getAuth();
        return is_callable(array($auth, 'getDefaultLoginTarget'))
            ? $auth->getDefaultLoginTarget() : null;
    }

    /**
     * Get the name of the current authentication method.
     *
     * @return string
     */
    public function getAuthMethod()
    {
        $className = $this->getAuthClass();
        $classParts = explode('\\', $className);
        return array_pop($classParts);
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
            $catalog = $this->getILS();
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
        $url = $this->getAuth()->logout($url);

        // Clear out cached ILS connection.
        $this->ilsAccount = false;

        // Clear out the cached user object and session entry.
        $this->currentUser = false;
        unset($this->session->userId);
        setcookie('loggedOut', 1, null, '/');

        // Destroy the session for good measure, if requested.
        if ($destroy) {
            $this->getServiceLocator()->get('VuFind\SessionManager')->destroy();
        } else {
            // If we don't want to destroy the session, we still need to empty it.
            // There should be a way to do this through Zend\Session, but there
            // apparently isn't (TODO -- do this better):
            $_SESSION = array();
        }

        return $url;
    }

    /**
     * Checks whether the user has recently logged out.
     *
     * @return bool
     */
    public function userHasLoggedOut()
    {
        return isset($_COOKIE['loggedOut']) && $_COOKIE['loggedOut'];
    }

    /**
     * Checks whether the user is logged in.
     *
     * @return \VuFind\Db\Row\User|bool Object if user is logged in, false
     * otherwise.
     */
    public function isLoggedIn()
    {
        // If user object is not in cache, but user ID is in session,
        // load the object from the database:
        if (!$this->currentUser && isset($this->session->userId)) {
            $results = $this->getServiceLocator()
                ->get('VuFind\DbTablePluginManager')->get('user')
                ->select(array('id' => $this->session->userId));
            $this->currentUser = count($results) < 1
                ? false : $results->current();
        }
        return $this->currentUser;
    }

    /**
     * Resets the session if the logged in user's credentials have expired.
     *
     * @return bool True if session has expired.
     */
    public function checkForExpiredCredentials()
    {
        if ($this->isLoggedIn() && $this->getAuth()->isExpired()) {
            $this->logout(null, false);
            return true;
        }
        return false;
    }

    /**
     * Updates the user information in the session.
     *
     * @param \VuFind\Db\Row\User $user User object to store in the session
     *
     * @return void
     */
    public function updateSession($user)
    {
        $this->currentUser = $user;
        $this->session->userId = $user->id;
        setcookie('loggedOut', '', time() - 3600, '/'); // clear logged out cookie
    }

    /**
     * Create a new user account from the request.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function create($request)
    {
        $user = $this->getAuth()->create($request);
        $this->updateSession($user);
        return $user;
    }

    /**
     * Update a user's password from the request.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function updatePassword($request)
    {
        if (($authMethod = $request->getPost()->get('auth_method')) != null) {
            $this->setActiveAuthClass($authMethod);
        }
        $user = $this->getAuth()->updatePassword($request);
        $this->updateSession($user);
        return $user;
    }

    /**
     * Try to log in the user using current query parameters; return User object
     * on success, throws exception on failure.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function login($request)
    {
        // Perform authentication:
        try {
            $user = $this->getAuth()->authenticate($request);
        } catch (AuthException $e) {
            // Pass authentication exceptions through unmodified
            throw $e;
        } catch (\VuFind\Exception\PasswordSecurity $e) {
            // Pass password security exceptions through unmodified
            throw $e;
        } catch (\Exception $e) {
            // Catch other exceptions, log verbosely, and treat them as technical
            // difficulties
            error_log(
                "Exception in " . get_class($this) . "::login: " . $e->getMessage()
            );
            error_log($e);
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
            $catalog = $this->getILS();
        } catch (ILSException $e) {
            return false;
        }
        $user = $this->isLoggedIn();

        // Fail if no username is found, but allow a missing password (not every ILS
        // requires a password to connect).
        if ($user && isset($user->cat_username) && !empty($user->cat_username)) {
            try {
                $patron = $catalog->patronLogin(
                    $user->cat_username, $user->getCatPassword()
                );
            } catch (ILSException $e) {
                $patron = null;
            }
            if (empty($patron)) {
                // Problem logging in -- clear user credentials so they can be
                // prompted again; perhaps their password has changed in the
                // system!
                $user->clearCredentials();
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
            $catalog = $this->getILS();
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

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Manager
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Setter
     *
     * @param string $method The auth class to proxy
     *
     * @return void
     */
    public function setActiveAuthClass($method)
    {
        $this->authToProxy = $method;
        $this->authProxied = false;
    }
}
