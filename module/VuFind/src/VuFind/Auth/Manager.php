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
use VuFind\Db\Row\User as UserRow, VuFind\Db\Table\User as UserTable,
    VuFind\Exception\Auth as AuthException,
    Zend\Config\Config, Zend\Session\SessionManager;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Manager
{
    /**
     * Authentication modules
     *
     * @var \VuFind\Auth\AbstractBase[]
     */
    protected $auth = array();

    /**
     * Currently selected authentication module
     *
     * @var string
     */
    protected $activeAuth;

    /**
     * Whitelist of values allowed to be set into $activeAuth
     *
     * @var array
     */
    protected $legalAuthOptions;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Session container
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * Gateway to user table in database
     *
     * @var UserTable
     */
    protected $userTable;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Authentication plugin manager
     *
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * Cache for current logged in user object
     *
     * @var UserRow
     */
    protected $currentUser = false;

    /**
     * Constructor
     *
     * @param Config         $config         VuFind configuration
     * @param UserTable      $userTable      User table gateway
     * @param SessionManager $sessionManager Session manager
     * @param PluginManager  $pm             Authentication plugin manager
     */
    public function __construct(Config $config, UserTable $userTable,
        SessionManager $sessionManager, PluginManager $pm
    ) {
        // Store dependencies:
        $this->config = $config;
        $this->userTable = $userTable;
        $this->sessionManager = $sessionManager;
        $this->pluginManager = $pm;

        // Set up session:
        $this->session = new \Zend\Session\Container('Account');

        // Initialize active authentication setting (defaulting to Database
        // if no setting passed in):
        $method = isset($config->Authentication->method)
            ? $config->Authentication->method : 'Database';
        $this->legalAuthOptions = array($method);   // mark it as legal
        $this->setAuthMethod($method);              // load it
    }

    /**
     * Get the authentication handler.
     *
     * @param string $name Auth module to load (null for currently active one)
     *
     * @return AbstractBase
     */
    protected function getAuth($name = null)
    {
        $name = empty($name) ? $this->activeAuth : $name;
        if (!isset($this->auth[$name])) {
            $this->auth[$name] = $this->makeAuth($name);
        }
        return $this->auth[$name];
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
        // If an illegal option was passed in, don't allow the object to load:
        if (!in_array($method, $this->legalAuthOptions)) {
            throw new \Exception("Illegal authentication method: $method");
        }
        $auth = $this->pluginManager->get($method);
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
        return $this->getAuth($authMethod)->supportsCreation();
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
        if ($this->getAuth($authMethod)->supportsPasswordChange()) {
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
        if ($this->getAuth($authMethod)->supportsPasswordChange()) {
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
     * In VuFind, views are tied to the name of the active authentication class.
     * This method returns that name so that an appropriate template can be
     * selected. It supports authentication methods that proxy other authentication
     * methods (see ChoiceAuth for an example).
     *
     * @return string
     */
    public function getAuthClassForTemplateRendering()
    {
        $auth = $this->getAuth();
        if (is_callable(array($auth, 'getSelectedAuthOption'))) {
            $selected = $auth->getSelectedAuthOption();
            if ($selected) {
                $auth = $this->getAuth($selected);
            }
        }
        return get_class($auth);
    }

    /**
     * Return an array of all of the authentication options supported by the
     * current auth class. In most cases (except for ChoiceAuth), this will
     * just contain a single value.
     *
     * @return array
     */
    public function getSelectableAuthOptions()
    {
        $auth = $this->getAuth();
        if (is_callable(array($auth, 'getSelectableAuthOptions'))) {
            if ($methods = $auth->getSelectableAuthOptions()) {
                return $methods;
            }
        }
        return array($this->getAuthMethod());
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
        return $this->activeAuth;
    }

    /**
     * Is login currently allowed?
     *
     * @return bool
     */
    public function loginEnabled()
    {
        // Assume login is enabled unless explicitly turned off:
        return isset($this->config->Authentication->hideLogin)
            ? !$this->config->Authentication->hideLogin
            : true;
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

        // Clear out the cached user object and session entry.
        $this->currentUser = false;
        unset($this->session->userId);
        setcookie('loggedOut', 1, null, '/');

        // Destroy the session for good measure, if requested.
        if ($destroy) {
            $this->sessionManager->destroy();
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
     * @return UserRow|bool Object if user is logged in, false otherwise.
     */
    public function isLoggedIn()
    {
        // If user object is not in cache, but user ID is in session,
        // load the object from the database:
        if (!$this->currentUser && isset($this->session->userId)) {
            $results = $this->userTable
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
     * @param UserRow $user User object to store in the session
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
     * @return UserRow New user row.
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
     * password change details.
     *
     * @throws AuthException
     * @return UserRow New user row.
     */
    public function updatePassword($request)
    {
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
     * @return UserRow Object representing logged-in user.
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
     * Setter
     *
     * @param string $method The auth class to proxy
     *
     * @return void
     */
    public function setAuthMethod($method)
    {
        // Change the setting:
        $this->activeAuth = $method;

        // If this method supports switching to a different method and we haven't
        // already initialized it, add those options to the whitelist. If the object
        // is already initialized, that means we've already gone through this step
        // and can save ourselves the trouble.

        // This code also has the side effect of validating $method, since if an
        // invalid value was passed in, the call to getSelectableAuthOptions will
        // throw an exception.
        if (!isset($this->auth[$method])) {
            $this->legalAuthOptions = array_unique(
                array_merge(
                    $this->legalAuthOptions, $this->getSelectableAuthOptions()
                )
            );
        }
    }
}
