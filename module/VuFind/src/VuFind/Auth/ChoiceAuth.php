<?php

/**
 * MultiAuth Authentication plugin
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Anna Headley <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

use function call_user_func_array;
use function func_get_args;
use function in_array;
use function is_callable;
use function strlen;

/**
 * ChoiceAuth Authentication plugin
 *
 * This module enables a user to choose between two authentication methods.
 * choices are presented side-by-side and one is manually selected.
 *
 * See config.ini for more details
 *
 * @category VuFind
 * @package  Authentication
 * @author   Anna Headley <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class ChoiceAuth extends AbstractBase
{
    /**
     * Authentication strategies to present
     *
     * @var array
     */
    protected $strategies = [];

    /**
     * Auth strategy selected by user
     *
     * @var string
     */
    protected $strategy;

    /**
     * Plugin manager for obtaining other authentication objects
     *
     * @var PluginManager
     */
    protected $manager;

    /**
     * Session container
     *
     * @var \Laminas\Session\Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param \Laminas\Session\Container $container Session container for retaining
     * user choices.
     */
    public function __construct(\Laminas\Session\Container $container)
    {
        // Set up session container and load cached strategy (if found):
        $this->session = $container;
        $this->strategy = $this->session->auth_method ?? false;
    }

    /**
     * Validate configuration parameters. This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        if (
            !isset($this->config->ChoiceAuth->choice_order)
            || !strlen($this->config->ChoiceAuth->choice_order)
        ) {
            throw new AuthException(
                'One or more ChoiceAuth parameters are missing. ' .
                'Check your config.ini!'
            );
        }
    }

    /**
     * Set configuration; throw an exception if it is invalid.
     *
     * @param \Laminas\Config\Config $config Configuration to set
     *
     * @throws AuthException
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        $this->strategies = array_map(
            'trim',
            explode(',', $this->getConfig()->ChoiceAuth->choice_order)
        );
    }

    /**
     * Inspect the user's request prior to processing a login request; this is
     * essentially an event hook which most auth modules can ignore. See
     * ChoiceAuth for a use case example.
     *
     * @param Request $request Request object.
     *
     * @throws AuthException
     * @return void
     */
    public function preLoginCheck($request)
    {
        $this->setStrategyFromRequest($request);
    }

    /**
     * Reset any internal status; this is essentially an event hook which most auth
     * modules can ignore. See ChoiceAuth for a use case example.
     *
     * @return void
     */
    public function resetState()
    {
        $this->strategy = false;
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request)
    {
        try {
            return $this->proxyUserLoad($request, 'authenticate', func_get_args());
        } catch (\Exception $e) {
            // If an exception was thrown during login, we need to clear the
            // stored strategy to ensure that we display the full ChoiceAuth
            // form rather than the form for only the method that the user
            // attempted to use.
            $this->strategy = false;
            throw $e;
        }
    }

    /**
     * Create a new user account from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return UserEntityInterface New user entity.
     */
    public function create($request)
    {
        return $this->proxyUserLoad($request, 'create', func_get_args());
    }

    /**
     * Set the manager for loading other authentication plugins.
     *
     * @param PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setPluginManager(PluginManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Get the manager for loading other authentication plugins.
     *
     * @throws \Exception
     * @return PluginManager
     */
    public function getPluginManager()
    {
        if (null === $this->manager) {
            throw new \Exception('Plugin manager missing.');
        }
        return $this->manager;
    }

    /**
     * Return an array of authentication options allowed by this class.
     *
     * @return array
     */
    public function getSelectableAuthOptions()
    {
        return $this->strategies;
    }

    /**
     * If an authentication strategy has been selected, return the active option.
     * If not, return false.
     *
     * @return bool|string
     */
    public function getSelectedAuthOption()
    {
        return $this->strategy;
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @throws InvalidArgumentException
     * @return string     Redirect URL (usually same as $url, but modified in
     * some authentication modules).
     */
    public function logout($url)
    {
        // clear user's login choice, if necessary:
        if (isset($this->session->auth_method)) {
            unset($this->session->auth_method);
        }

        // If we have a selected strategy, proxy the appropriate class; otherwise,
        // perform default behavior of returning unmodified URL:
        try {
            return $this->strategy
                ? $this->proxyAuthMethod('logout', func_get_args()) : $url;
        } catch (InvalidArgumentException $e) {
            // If we're in an invalid state (due to an illegal login method),
            // we should just clear everything out so the user can try again.
            $this->strategy = false;
            return false;
        }
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate). Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication strategy should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        return $this->proxyAuthMethod('getSessionInitiator', func_get_args());
    }

    /**
     * Does this authentication method support password changing
     *
     * @return bool
     */
    public function supportsPasswordChange()
    {
        return $this->proxyAuthMethod('supportsPasswordChange', func_get_args());
    }

    /**
     * Does this authentication method support password recovery
     *
     * @return bool
     */
    public function supportsPasswordRecovery()
    {
        return $this->proxyAuthMethod('supportsPasswordRecovery', func_get_args());
    }

    /**
     * Username policy for a new account (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getUsernamePolicy()
    {
        return $this->proxyAuthMethod('getUsernamePolicy', func_get_args()) ?: [];
    }

    /**
     * Password policy for a new password (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getPasswordPolicy()
    {
        return $this->proxyAuthMethod('getPasswordPolicy', func_get_args()) ?: [];
    }

    /**
     * Update a user's password from the request.
     *
     * @param Request $request Request object containing password change details.
     *
     * @throws AuthException
     * @return UserEntityInterface Updated user entity.
     */
    public function updatePassword($request)
    {
        // When a user is recovering a forgotten password, there may be an
        // auth method included in the request since we haven't set an active
        // strategy yet -- thus we should check for it.
        $this->setStrategyFromRequest($request);
        return $this->proxyAuthMethod('updatePassword', func_get_args());
    }

    /**
     * Returns any authentication method this request should be delegated to.
     *
     * @param Request $request Request object.
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDelegateAuthMethod(Request $request)
    {
        return $this->proxyAuthMethod('getDelegateAuthMethod', func_get_args());
    }

    /**
     * Is the configured strategy on the list of legal options?
     *
     * @return bool
     */
    protected function hasLegalStrategy()
    {
        // Do a case-insensitive search of the strategy list:
        return in_array(
            strtolower($this->strategy),
            array_map('strtolower', $this->strategies)
        );
    }

    /**
     * Proxy auth method; a helper function to be called like:
     *   return $this->proxyAuthMethod(METHOD, func_get_args());
     *
     * @param string $method the method to proxy
     * @param array  $params array of params to pass
     *
     * @throws AuthException
     * @return mixed
     */
    protected function proxyAuthMethod($method, $params)
    {
        // If no strategy is found, we can't do anything -- return false.
        if (!$this->strategy) {
            return false;
        }

        if (!$this->hasLegalStrategy()) {
            throw new InvalidArgumentException("Illegal setting: {$this->strategy}");
        }
        $authenticator = $this->getPluginManager()->get($this->strategy);
        $authenticator->setConfig($this->getConfig());
        if (!is_callable([$authenticator, $method])) {
            throw new AuthException($this->strategy . "has no method $method");
        }
        return call_user_func_array([$authenticator, $method], $params);
    }

    /**
     * Proxy auth method that checks the request for an active method and then
     * loads a UserEntityInterface object from the database (e.g. authenticate or create).
     *
     * @param Request $request Request object to check.
     * @param string  $method  the method to proxy
     * @param array   $params  array of params to pass
     *
     * @throws AuthException
     * @return mixed
     */
    protected function proxyUserLoad($request, $method, $params)
    {
        $this->setStrategyFromRequest($request);
        $user = $this->proxyAuthMethod($method, $params);
        if (!$user) {
            throw new AuthException('Unexpected return value');
        }
        $this->session->auth_method = $this->strategy;
        return $user;
    }

    /**
     * Set the active strategy based on the auth_method value in the request,
     * if found.
     *
     * @param Request $request Request object to check.
     *
     * @return void
     */
    protected function setStrategyFromRequest($request)
    {
        // Set new strategy; fall back to old one if there is a problem:
        $defaultStrategy = $this->strategy;
        $this->strategy = trim($request->getPost()->get('auth_method', ''));
        if (!$this->strategy) {
            $this->strategy = trim($request->getQuery()->get('auth_method', ''));
        }
        if (!$this->strategy || !in_array($this->strategy, $this->strategies)) {
            $this->strategy = $defaultStrategy;
            if (empty($this->strategy)) {
                throw new AuthException('authentication_error_technical');
            }
        }
    }

    /**
     * Set the active strategy
     *
     * @param string $strategy New strategy
     *
     * @return void
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
        $this->session->auth_method = $strategy;
    }

    /**
     * Validate the credentials in the provided request, but do not change the state
     * of the current logged-in user. Return true for valid credentials, false
     * otherwise.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return bool
     */
    public function validateCredentials($request)
    {
        try {
            // In this instance we are checking credentials but do not wish to
            // change the state of the current object. Thus, we use proxyAuthMethod()
            // here instead of proxyUserLoad().
            $user = $this->proxyAuthMethod('authenticate', func_get_args());
        } catch (AuthException $e) {
            return false;
        }
        return isset($user) && $user instanceof UserEntityInterface;
    }

    /**
     * Whether this authentication method needs CSRF checking for the request.
     *
     * @param Request $request Request object.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function needsCsrfCheck($request)
    {
        if (!$this->strategy) {
            return true;
        }
        return $this->proxyAuthMethod('needsCsrfCheck', func_get_args());
    }
}
