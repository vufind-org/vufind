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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Auth;
use VuFind\Cookie\CookieManager,
    VuFind\Db\Row\User as UserRow, VuFind\Db\Table\User as UserTable,
    VuFind\Exception\Auth as AuthException,
    Zend\Config\Config, Zend\Session\SessionManager, Zend\Validator\Csrf;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager implements \ZfcRbac\Identity\IdentityProviderInterface
{
    /**
     * Authentication modules
     *
     * @var \VuFind\Auth\AbstractBase[]
     */
    protected $auth = [];

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
     * Cookie Manager
     *
     * @var CookieManager
     */
    protected $cookieManager;

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
     * @param CookieManager  $cookieManager  Cookie manager
     */
    public function __construct(Config $config, UserTable $userTable,
        SessionManager $sessionManager, PluginManager $pm,
        CookieManager $cookieManager
    ) {
        // Store dependencies:
        $this->config = $config;
        $this->userTable = $userTable;
        $this->sessionManager = $sessionManager;
        $this->pluginManager = $pm;
        $this->cookieManager = $cookieManager;

        // Set up session:
        $this->session = new \Zend\Session\Container('Account', $sessionManager);

        // Set up CSRF:
        $this->csrf = new Csrf(
            [
                'session' => new \Zend\Session\Container('csrf', $sessionManager),
                'salt' => isset($this->config->Security->HMACkey)
                    ? $this->config->Security->HMACkey : 'VuFindCsrfSalt',
            ]
        );

        // Initialize active authentication setting (defaulting to Database
        // if no setting passed in):
        $method = isset($config->Authentication->method)
            ? $config->Authentication->method : 'Database';
        $this->legalAuthOptions = [$method];   // mark it as legal
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
    public function supportsCreation($authMethod = null)
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
    public function supportsRecovery($authMethod = null)
    {
        if ($this->getAuth($authMethod)->supportsPasswordRecovery()) {
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
    public function supportsPasswordChange($authMethod = null)
    {
        if (isset($this->config->Authentication->change_password)
            && $this->config->Authentication->change_password
        ) {
            return $this->getAuth($authMethod)->supportsPasswordChange();
        }
        return false;
    }

    /**
     * Password policy for a new password (e.g. minLength, maxLength)
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return array
     */
    public function getPasswordPolicy($authMethod = null)
    {
        return $this->getAuth($authMethod)->getPasswordPolicy();
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
        try {
            return $this->getAuth()->getSessionInitiator($target);
        } catch (InvalidArgumentException $e) {
            // If the authentication is in an illegal state but there is an
            // active user session, we should clear everything out so the user
            // can try again. This is useful, for example, if a user is logged
            // in at the same time that an administrator changes the [ChoiceAuth]
            // settings in config.ini. However, if the user is not logged in,
            // they are probably attempting something nasty and should be given
            // an error message.
            if (!$this->isLoggedIn()) {
                throw $e;
            }
            $this->logout('');
            return $this->getAuth()->getSessionInitiator($target);
        }
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
        if (is_callable([$auth, 'getSelectedAuthOption'])) {
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
        if (is_callable([$auth, 'getSelectableAuthOptions'])) {
            if ($methods = $auth->getSelectableAuthOptions()) {
                return $methods;
            }
        }
        return [$this->getAuthMethod()];
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
        return is_callable([$auth, 'getLoginTargets'])
            ? $auth->getLoginTargets() : [];
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
        return is_callable([$auth, 'getDefaultLoginTarget'])
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
        unset($this->session->userDetails);
        $this->cookieManager->set('loggedOut', 1);

        // Destroy the session for good measure, if requested.
        if ($destroy) {
            $this->sessionManager->destroy();
        } else {
            // If we don't want to destroy the session, we still need to empty it.
            // There should be a way to do this through Zend\Session, but there
            // apparently isn't (TODO -- do this better):
            $_SESSION = [];
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
        return (bool)$this->cookieManager->get('loggedOut');
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
        if (!$this->currentUser) {
            if (isset($this->session->userId)) {
                // normal mode
                $results = $this->userTable
                    ->select(['id' => $this->session->userId]);
                $this->currentUser = count($results) < 1
                    ? false : $results->current();
            } else if (isset($this->session->userDetails)) {
                // privacy mode
                $results = $this->userTable->createRow();
                $results->exchangeArray($this->session->userDetails);
                $this->currentUser = $results;
            } else {
                // unexpected state
                $this->currentUser = false;
            }
        }
        return $this->currentUser;
    }

    /**
     * Retrieve CSRF token
     *
     * If no CSRF token currently exists, or should be regenerated, generates one.
     *
     * @param bool $regenerate Should we regenerate token? (default false)
     *
     * @return string
     */
    public function getCsrfHash($regenerate = false)
    {
        return $this->csrf->getHash($regenerate);
    }

    /**
     * Get the identity
     *
     * @return \ZfcRbac\Identity\IdentityInterface|null
     */
    public function getIdentity()
    {
        return $this->isLoggedIn() ?: null;
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
     * Are we in privacy mode?
     *
     * @return bool
     */
    public function inPrivacyMode()
    {
        return isset($this->config->Authentication->privacy)
            && $this->config->Authentication->privacy;
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
        if ($this->inPrivacyMode()) {
            $this->session->userDetails = $user->toArray();
        } else {
            $this->session->userId = $user->id;
        }
        $this->cookieManager->clear('loggedOut');
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
        // Allow the auth module to inspect the request (used by ChoiceAuth,
        // for example):
        $this->getAuth()->preLoginCheck($request);

        // Validate CSRF for form-based authentication methods:
        if (!$this->getAuth()->getSessionInitiator(null)
            && !$this->csrf->isValid($request->getPost()->get('csrf'))
        ) {
            $this->getAuth()->resetState();
            throw new AuthException('authentication_error_technical');
        }

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
    /**
     * Validate the credentials in the provided request, but do not change the state
     * of the current logged-in user. Return true for valid credentials, false
     * otherwise.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return bool
     */
    public function validateCredentials($request)
    {
        return $this->getAuth()->validateCredentials($request);
    }
}
