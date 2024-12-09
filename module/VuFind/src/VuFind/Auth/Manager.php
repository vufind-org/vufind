<?php

/**
 * Wrapper class for handling logged-in user in session.
 *
 * PHP version 8
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
use LmcRbacMvc\Identity\IdentityInterface;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\ILS\Connection;
use VuFind\Validator\CsrfInterface;

use function in_array;
use function is_callable;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager implements
    \LmcRbacMvc\Identity\IdentityProviderInterface,
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
     * Cache for current logged in user object
     *
     * @var ?UserEntityInterface
     */
    protected $currentUser = null;

    /**
     * Cache for hideLogin setting
     *
     * @var ?bool
     */
    protected $hideLogin = null;

    /**
     * ILS Authenticator
     *
     * @var ?ILSAuthenticator
     */
    protected $ilsAuthenticator = null;

    /**
     * Constructor
     *
     * @param Config                          $config            VuFind configuration
     * @param UserServiceInterface            $userService       User database service
     * @param UserSessionPersistenceInterface $userSession       User session persistence service
     * @param SessionManager                  $sessionManager    Session manager
     * @param PluginManager                   $pluginManager     Authentication plugin manager
     * @param CookieManager                   $cookieManager     Cookie manager
     * @param CsrfInterface                   $csrf              CSRF validator
     * @param LoginTokenManager               $loginTokenManager Login Token manager
     * @param Connection                      $ils               ILS connection
     */
    public function __construct(
        protected Config $config,
        protected UserServiceInterface $userService,
        protected UserSessionPersistenceInterface $userSession,
        protected SessionManager $sessionManager,
        protected PluginManager $pluginManager,
        protected CookieManager $cookieManager,
        protected CsrfInterface $csrf,
        protected LoginTokenManager $loginTokenManager,
        protected Connection $ils
    ) {
        // Initialize active authentication setting (defaulting to Database
        // if no setting passed in):
        $method = $config->Authentication->method ?? 'Database';
        $this->legalAuthOptions = [$method];   // mark it as legal
        $this->setAuthMethod($method);         // load it
    }

    /**
     * Set ILS Authenticator
     *
     * @param ILSAuthenticator $ilsAuthenticator ILS authenticator
     *
     * @return void
     */
    public function setILSAuthenticator(ILSAuthenticator $ilsAuthenticator): void
    {
        $this->ilsAuthenticator = $ilsAuthenticator;
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
        $legalAuthList = array_map('strtolower', $this->legalAuthOptions);
        // If an illegal option was passed in, don't allow the object to load:
        if (!in_array(strtolower($method), $legalAuthList)) {
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
     * Is persistent login supported by the authentication method?
     *
     * @param string $method Authentication method (overrides currently selected method)
     *
     * @return bool
     */
    public function supportsPersistentLogin(?string $method = null): bool
    {
        if (!empty($this->config->Authentication->persistent_login)) {
            return in_array(
                strtolower($method ?? $this->getSelectedAuthMethod()),
                explode(',', strtolower($this->config->Authentication->persistent_login))
            );
        }
        return false;
    }

    /**
     * Get persistent login lifetime in days
     *
     * @return int
     */
    public function getPersistentLoginLifetime()
    {
        return $this->config->Authentication->persistent_login_lifetime ?? 14;
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
     * form is inadequate). Returns false when no session initiator is needed.
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
            if (!$this->getIdentity()) {
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
        return $auth::class;
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
     * Get the name of the currently selected authentication method (if applicable)
     * or the active authentication method.
     *
     * @return string
     */
    public function getSelectedAuthMethod()
    {
        $auth = $this->getAuth();
        return is_callable([$auth, 'getSelectedAuthOption'])
            ? $auth->getSelectedAuthOption()
            : $this->getAuthMethod();
    }

    /**
     * Is login currently allowed?
     *
     * @return bool
     */
    public function loginEnabled()
    {
        if (null === $this->hideLogin) {
            // Assume login is enabled unless explicitly turned off:
            $this->hideLogin = ($this->config->Authentication->hideLogin ?? false);

            if (!$this->hideLogin) {
                try {
                    // Check if the catalog wants to hide the login link, and override
                    // the configuration if necessary.
                    if ($this->ils->loginIsHidden()) {
                        $this->hideLogin = true;
                    }
                } catch (\Exception $e) {
                    // Ignore exceptions; if the catalog is broken, throwing an exception
                    // here may interfere with UI rendering. If we ignore it now, it will
                    // still get handled appropriately later in processing.
                    $this->logError('Could not check loginIsHidden:' . (string)$e);
                }
            }
        }
        return !$this->hideLogin;
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
        $this->currentUser = null;
        $this->userSession->clearUserFromSession();
        $this->cookieManager->set('loggedOut', 1);
        $this->loginTokenManager->deleteActiveToken();

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
     * @return UserEntityInterface|false Object if user is logged in, false otherwise.
     *
     * @deprecated Use getIdentity() or getUserObject() instead.
     */
    public function isLoggedIn()
    {
        return $this->getUserObject() ?? false;
    }

    /**
     * Checks whether the user is logged in.
     *
     * @return ?UserEntityInterface Object if user is logged in, null otherwise.
     */
    public function getUserObject(): ?UserEntityInterface
    {
        // If user object is not in cache, but user ID is in session,
        // load the object from the database:
        if (!$this->currentUser) {
            if ($this->userSession->hasUserSessionData()) {
                $this->currentUser = $this->userSession->getUserFromSession();
                // End the session if the logged-in user cannot be found:
                if (null === $this->currentUser) {
                    $this->logout('');
                }
            } elseif ($user = $this->loginTokenManager->tokenLogin($this->sessionManager->getId())) {
                if ($this->getAuth() instanceof ChoiceAuth) {
                    $this->getAuth()->setStrategy($user->getAuthMethod());
                }
                if ($this->supportsPersistentLogin()) {
                    $this->updateUser($user, null);
                    $this->updateSession($user);
                } else {
                    $this->currentUser = null;
                }
            } else {
                // not logged in
                $this->currentUser = null;
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
     * Get the logged-in user's identity (null if not logged in)
     *
     * @return ?IdentityInterface
     */
    public function getIdentity()
    {
        return $this->getUserObject();
    }

    /**
     * Resets the session if the logged in user's credentials have expired.
     *
     * @return bool True if session has expired.
     */
    public function checkForExpiredCredentials()
    {
        if ($this->getIdentity() && $this->getAuth()->isExpired()) {
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
     * @param UserEntityInterface $user User object to store in the session
     *
     * @return void
     */
    public function updateSession($user)
    {
        $this->currentUser = $user;
        if ($this->inPrivacyMode()) {
            $this->userSession->addUserDataToSession($user);
        } else {
            $this->userSession->addUserIdToSession($user->getId());
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
     * @return UserEntityInterface New user entity.
     */
    public function create($request)
    {
        $user = $this->getAuth()->create($request);
        $this->updateUser($user, $this->getSelectedAuthMethod());
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
     * @return UserEntityInterface Updated user entity.
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
     * @param UserEntityInterface $user  Object representing user being updated.
     * @param string              $email New email address to set (must be pre-validated!).
     *
     * @throws AuthException
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateEmail(UserEntityInterface $user, $email)
    {
        // Depending on verification setting, either do a direct update or else
        // put the new address into a pending state.
        if ($this->config->Authentication->verify_email ?? false) {
            // If new email address is the current address, just reset any pending
            // email address:
            $user->setPendingEmail($email === $user->getEmail() ? '' : $email);
        } else {
            $this->userService->updateUserEmail($user, $email, true);
            $user->setPendingEmail('');
        }
        $this->userService->persistEntity($user);
        $this->updateSession($user);
    }

    /**
     * Update the verification hash for the provided user.
     *
     * @param UserEntityInterface $user User to update
     *
     * @return void
     */
    public function updateUserVerifyHash(UserEntityInterface $user): void
    {
        $hash = md5($user->getUsername() . $user->getRawCatPassword() . $user->getPasswordHash() . rand());
        // Make totally sure the timestamp is exactly 10 characters:
        $time = str_pad(substr((string)time(), 0, 10), 10, '0', STR_PAD_LEFT);
        $user->setVerifyHash($hash . $time);
        $this->userService->persistEntity($user);
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
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function login($request)
    {
        // Wrap everything in try-catch so that we can reset the state on failure:
        try {
            // Allow the auth module to inspect the request (used by ChoiceAuth,
            // for example):
            $this->getAuth()->preLoginCheck($request);

            // Get the main auth method before switching to any delegate:
            $mainAuthMethod = $this->getSelectedAuthMethod();

            // Check if the current auth method wants to delegate the request to another
            // method:
            if ($delegate = $this->getAuth()->getDelegateAuthMethod($request)) {
                $this->setAuthMethod($delegate, true);
            }

            // Validate CSRF for form-based authentication methods:
            if (
                !$this->getAuth()->getSessionInitiator('')
                && $this->getAuth()->needsCsrfCheck($request)
            ) {
                if (!$this->csrf->isValid($request->getPost()->get('csrf'))) {
                    $this->getAuth()->resetState();
                    $this->logWarning('Invalid CSRF token passed to login');
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

            // Attempt catalog login so that any bad credentials are cleared before further processing
            // (avoids e.g. multiple login attempts by account AJAX checks).
            if (
                ($this->config->Catalog->checkILSCredentialsOnLogin ?? true)
                && $this->ilsAuthenticator
                && $this->allowsUserIlsLogin()
                && ($catUsername = $user->getCatUsername())
                // If ILS authentication was used, catalog username must not be the same as the username just used for
                // authentication:
                && (!in_array($user->getAuthMethod(), ['ils', 'multiils']) || $catUsername !== $user->getUsername())
                && !$this->ils->getOfflineMode()
            ) {
                try {
                    $patron = $this->ils->patronLogin(
                        $catUsername,
                        $this->ilsAuthenticator->getCatPasswordForUser($user)
                    );
                    if (empty($patron)) {
                        // Problem logging in -- clear user credentials so they can be
                        // prompted again; perhaps their password has changed in the
                        // system!
                        $user->setCatUsername(null)->setRawCatPassword(null)->setCatPassEnc(null);
                    }
                } catch (\Exception $e) {
                    // Ignore exceptions here so that the login can continue
                }
            }

            // Update user object
            $this->updateUser($user, $mainAuthMethod);

            if ($request->getPost()->get('remember_me') && $this->supportsPersistentLogin($mainAuthMethod)) {
                try {
                    $this->loginTokenManager->createToken($user, $this->sessionManager->getId());
                } catch (\Exception $e) {
                    $this->logError((string)$e);
                    throw new AuthException('authentication_error_technical', 0, $e);
                }
            }

            // Store the user in the session:
            $this->updateSession($user);

            // Send user back to caller:
            return $user;
        } catch (\Exception $e) {
            $this->getAuth()->resetState();
            throw $e;
        }
    }

    /**
     * Delete a login token
     *
     * @param string $series Series to identify the token
     *
     * @return void
     */
    public function deleteToken(string $series)
    {
        $this->loginTokenManager->deleteTokenSeries($series);
    }

    /**
     * Delete all login tokens for a user
     *
     * @param int $userId User identifier
     *
     * @return void
     */
    public function deleteUserLoginTokens(int $userId)
    {
        $this->loginTokenManager->deleteUserLoginTokens($userId);
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
     * @param UserEntityInterface                  $user    Connect newly created
     * library card to this user.
     *
     * @return void
     * @throws \Exception
     */
    public function connectLibraryCard($request, $user)
    {
        $auth = $this->getAuth();
        if (!$auth->supportsConnectingLibraryCard()) {
            throw new \Exception('Connecting of library cards is not supported');
        }
        $auth->connectLibraryCard($request, $user);
    }

    /**
     * Update common user attributes on login
     *
     * @param UserEntityInterface $user       User object
     * @param ?string             $authMethod Authentication method to user
     *
     * @return void
     */
    protected function updateUser($user, $authMethod)
    {
        if ($authMethod) {
            $user->setAuthMethod(strtolower($authMethod));
        }
        $user->setLastLogin(new \DateTime());
        $this->userService->persistEntity($user);
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
