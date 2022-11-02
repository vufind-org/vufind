<?php
/**
 * Wrapper class for handling logged-in user in session.
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

use Laminas\Config\Config;
use Laminas\Session\SessionManager;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Table\User as UserTable;
use VuFind\Exception\Auth as AuthException;
use VuFind\Validator\CsrfInterface;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager implements \LmcRbacMvc\Identity\IdentityProviderInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * List of values allowed to be set into $activeAuth
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
     * @var \Laminas\Session\Container
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
     * CSRF validator
     *
     * @var CsrfInterface
     */
    protected $csrf;

    /**
     * Constructor
     *
     * @param Config         $config         VuFind configuration
     * @param UserTable      $userTable      User table gateway
     * @param SessionManager $sessionManager Session manager
     * @param PluginManager  $pm             Authentication plugin manager
     * @param CookieManager  $cookieManager  Cookie manager
     * @param CsrfInterface  $csrf           CSRF validator
     */
    public function __construct(
        Config $config,
        UserTable $userTable,
        SessionManager $sessionManager,
        PluginManager $pm,
        CookieManager $cookieManager,
        CsrfInterface $csrf
    ) {
        // Store dependencies:
        $this->config = $config;
        $this->userTable = $userTable;
        $this->sessionManager = $sessionManager;
        $this->pluginManager = $pm;
        $this->cookieManager = $cookieManager;
        $this->csrf = $csrf;

        // Set up session:
        $this->session = new \Laminas\Session\Container('Account', $sessionManager);

        // Initialize active authentication setting (defaulting to Database
        // if no setting passed in):
        $method = $config->Authentication->method ?? 'Database';
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
        return ($this->config->Authentication->recover_password ?? false)
            && $this->getAuth($authMethod)->supportsPasswordRecovery();
    }

    /**
     * Is email changing currently allowed?
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return bool
     */
    public function supportsEmailChange($authMethod = null)
    {
        return $this->config->Authentication->change_email ?? false;
    }

    /**
     * Is new passwords currently allowed?
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return bool
     */
    public function supportsPasswordChange($authMethod = null)
    {
        return ($this->config->Authentication->change_password ?? false)
            && $this->getAuth($authMethod)->supportsPasswordChange();
    }

    /**
     * Is connecting library card allowed and supported?
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return bool
     */
    public function supportsConnectingLibraryCard($authMethod = null)
    {
        return ($this->config->Catalog->auth_based_library_cards ?? false)
            && $this->getAuth($authMethod)->supportsConnectingLibraryCard();
    }

    /**
     * Username policy for a new account (e.g. minLength, maxLength)
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return array
     */
    public function getUsernamePolicy($authMethod = null)
    {
        return $this->processPolicyConfig(
            $this->getAuth($authMethod)->getUsernamePolicy()
        );
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
        return $this->processPolicyConfig(
            $this->getAuth($authMethod)->getPasswordPolicy()
        );
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
        return !($this->config->Authentication->hideLogin ?? false);
    }

    /**
     * Is login currently allowed?
     *
     * @return bool
     */
    public function ajaxEnabled()
    {
        // Assume ajax is enabled unless explicitly turned off:
        return $this->config->Authentication->enableAjax ?? true;
    }

    /**
     * Is login currently allowed?
     *
     * @return bool
     */
    public function dropdownEnabled()
    {
        // Assume dropdown is disabled unless explicitly turned on:
        return $this->config->Authentication->enableDropdown ?? false;
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

        // Reset authentication state
        $this->getAuth()->resetState();

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
            // There should be a way to do this through Laminas\Session, but there
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
     * @return UserRow|false Object if user is logged in, false otherwise.
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
                // End the session since the logged-in user cannot be found:
                if (false === $this->currentUser) {
                    $this->logout('');
                }
            } elseif (isset($this->session->userDetails)) {
                // privacy mode
                $results = $this->userTable->createRow();
                $results->exchangeArray($this->session->userDetails);
                $this->currentUser = $results;
            } else {
                // not logged in
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
     * @param int  $maxTokens  The maximum number of tokens to store in the
     * session.
     *
     * @return string
     */
    public function getCsrfHash($regenerate = false, $maxTokens = 5)
    {
        // Reset token store if we've overflowed the limit:
        $this->csrf->trimTokenList($maxTokens);
        return $this->csrf->getHash($regenerate);
    }

    /**
     * Get the identity
     *
     * @return \LmcRbacMvc\Identity\IdentityInterface|null
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
        return $this->config->Authentication->privacy ?? false;
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
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return UserRow New user row.
     */
    public function create($request)
    {
        $user = $this->getAuth()->create($request);
        $this->updateUser($user);
        $this->updateSession($user);
        return $user;
    }

    /**
     * Update a user's password from the request.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
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
     * Update a user's email from the request.
     *
     * @param UserRow $user  Object representing user being updated.
     * @param string  $email New email address to set (must be pre-validated!).
     *
     * @throws AuthException
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateEmail(UserRow $user, $email)
    {
        // Depending on verification setting, either do a direct update or else
        // put the new address into a pending state.
        if ($this->config->Authentication->verify_email ?? false) {
            // If new email address is the current address, just reset any pending
            // email address:
            $user->pending_email = ($email === $user->email) ? '' : $email;
        } else {
            $user->updateEmail($email, true);
            $user->pending_email = '';
        }
        $user->save();
        $this->updateSession($user);
    }

    /**
     * Try to log in the user using current query parameters; return User object
     * on success, throws exception on failure.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @throws \VuFind\Exception\PasswordSecurity
     * @throws \VuFind\Exception\AuthInProgress
     * @return UserRow Object representing logged-in user.
     */
    public function login($request)
    {
        // Allow the auth module to inspect the request (used by ChoiceAuth,
        // for example):
        $this->getAuth()->preLoginCheck($request);

        // Check if the current auth method wants to delegate the request to another
        // method:
        if ($delegate = $this->getAuth()->getDelegateAuthMethod($request)) {
            $this->setAuthMethod($delegate, true);
        }

        // Validate CSRF for form-based authentication methods:
        if (!$this->getAuth()->getSessionInitiator(null)
            && $this->getAuth()->needsCsrfCheck($request)
        ) {
            if (!$this->csrf->isValid($request->getPost()->get('csrf'))) {
                $this->getAuth()->resetState();
                $this->logWarning("Invalid CSRF token passed to login");
                throw new AuthException('authentication_error_technical');
            } else {
                // After successful token verification, clear list to shrink session:
                $this->csrf->trimTokenList(0);
            }
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
            $this->logError((string)$e);
            throw new AuthException('authentication_error_technical', 0, $e);
        }

        // Update user object
        $this->updateUser($user);

        // Store the user in the session and send it back to the caller:
        $this->updateSession($user);
        return $user;
    }

    /**
     * Setter
     *
     * @param string $method     The auth class to proxy
     * @param bool   $forceLegal Whether to force the new method legal
     *
     * @return void
     */
    public function setAuthMethod($method, $forceLegal = false)
    {
        // Change the setting:
        $this->activeAuth = $method;

        if ($forceLegal) {
            if (!in_array($method, $this->legalAuthOptions)) {
                $this->legalAuthOptions[] = $method;
            }
        }

        // If this method supports switching to a different method and we haven't
        // already initialized it, add those options to the legal list. If the object
        // is already initialized, that means we've already gone through this step
        // and can save ourselves the trouble.

        // This code also has the side effect of validating $method, since if an
        // invalid value was passed in, the call to getSelectableAuthOptions will
        // throw an exception.
        if (!isset($this->auth[$method])) {
            $this->legalAuthOptions = array_unique(
                array_merge(
                    $this->legalAuthOptions,
                    $this->getSelectableAuthOptions()
                )
            );
        }
    }

    /**
     * Validate the credentials in the provided request, but do not change the state
     * of the current logged-in user. Return true for valid credentials, false
     * otherwise.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return bool
     */
    public function validateCredentials($request)
    {
        return $this->getAuth()->validateCredentials($request);
    }

    /**
     * What login method does the ILS use (password, email, vufind)
     *
     * @param string $target Login target (MultiILS only)
     *
     * @return array|false
     */
    public function getILSLoginMethod($target = '')
    {
        $auth = $this->getAuth();
        if (is_callable([$auth, 'getILSLoginMethod'])) {
            return $auth->getILSLoginMethod($target);
        }
        return false;
    }

    /**
     * Connect authenticated user as library card to his account.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object
     * containing account credentials.
     * @param \VuFind\Db\Row\User                  $user    Connect newly created
     * library card to this user.
     *
     * @return void
     * @throws \Exception
     */
    public function connectLibraryCard($request, $user)
    {
        $auth = $this->getAuth();
        if (!$auth->supportsConnectingLibraryCard()) {
            throw new \Exception("Connecting of library cards is not supported");
        }
        $auth->connectLibraryCard($request, $user);
    }

    /**
     * Update common user attributes on login
     *
     * @param \VuFind\Db\Row\User $user User object
     *
     * @return void
     */
    protected function updateUser($user)
    {
        if ($this->getAuth() instanceof ChoiceAuth) {
            $method = $this->getAuth()->getSelectedAuthOption();
        } else {
            $method = $this->activeAuth;
        }
        $user->auth_method = strtolower($method);
        $user->last_login = date('Y-m-d H:i:s');
        $user->save();
    }

    /**
     * Is the user allowed to log directly into the ILS?
     *
     * @return bool
     */
    public function allowsUserIlsLogin(): bool
    {
        return $this->config->Catalog->allowUserLogin ?? true;
    }

    /**
     * Process a raw policy configuration
     *
     * @param array $policy Policy configuration
     *
     * @return array
     */
    protected function processPolicyConfig(array $policy): array
    {
        // Convert 'numeric' or 'alphanumeric' pattern to a regular expression:
        switch ($policy['pattern'] ?? '') {
        case 'numeric':
            $policy['pattern'] = '\d+';
            break;
        case 'alphanumeric':
            $policy['pattern'] = '[\da-zA-Z]+';
        }

        // Map settings to attributes for a text input field:
        $inputMap = [
            'minLength' => 'data-minlength',
            'maxLength' => 'maxlength',
            'pattern' => 'pattern',
        ];
        $policy['inputAttrs'] = [];
        foreach ($inputMap as $from => $to) {
            if (isset($policy[$from])) {
                $policy['inputAttrs'][$to] = $policy[$from];
            }
        }
        return $policy;
    }
}
