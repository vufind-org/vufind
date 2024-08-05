<?php

/**
 * ILS authentication module.
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
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;

use function get_class;

/**
 * ILS authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class ILS extends AbstractBase
{
    /**
     * Catalog connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $catalog = null;

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection        $connection         ILS connection to set
     * @param \VuFind\Auth\ILSAuthenticator $authenticator      ILS authenticator
     * @param ?EmailAuthenticator           $emailAuthenticator Email authenticator
     */
    public function __construct(
        \VuFind\ILS\Connection $connection,
        protected \VuFind\Auth\ILSAuthenticator $authenticator,
        protected ?EmailAuthenticator $emailAuthenticator = null
    ) {
        $this->setCatalog($connection);
    }

    /**
     * Get the ILS driver associated with this object (or load the default from
     * the service manager.
     *
     * @return \VuFind\ILS\Connection
     */
    public function getCatalog()
    {
        return $this->catalog;
    }

    /**
     * Set the ILS connection for this object.
     *
     * @param \VuFind\ILS\Connection $connection ILS connection to set
     *
     * @return void
     */
    public function setCatalog(\VuFind\ILS\Connection $connection)
    {
        $this->catalog = $connection;
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
        $username = trim($request->getPost()->get('username', ''));
        $password = trim($request->getPost()->get('password', ''));
        $loginMethod = $this->getILSLoginMethod();
        $rememberMe = (bool)$request->getPost()->get('remember_me', false);

        return $this->handleLogin($username, $password, $loginMethod, $rememberMe);
    }

    /**
     * Does this authentication method support password changing
     *
     * @return bool
     */
    public function supportsPasswordChange()
    {
        try {
            return false !== $this->getCatalog()->checkFunction(
                'changePassword',
                ['patron' => $this->authenticator->getStoredCatalogCredentials()]
            );
        } catch (ILSException $e) {
            return false;
        }
    }

    /**
     * Password policy for a new password (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getPasswordPolicy()
    {
        $policy = $this->getCatalog()->getPasswordPolicy($this->getLoggedInPatron());
        if ($policy === false) {
            return parent::getPasswordPolicy();
        }
        if (isset($policy['pattern']) && empty($policy['hint'])) {
            $policy['hint'] = $this->getCannedPolicyHint(
                'password',
                $policy['pattern']
            );
        }
        return $policy;
    }

    /**
     * Update a user's password from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return UserEntityInterface Updated user entity.
     */
    public function updatePassword($request)
    {
        // Ensure that all expected parameters are populated to avoid notices
        // in the code below.
        $params = [];
        foreach (['oldpwd', 'password', 'password2'] as $param) {
            $params[$param] = $request->getPost()->get($param, '');
        }

        // Connect to catalog:
        if (!($patron = $this->authenticator->storedCatalogLogin())) {
            throw new AuthException('authentication_error_technical');
        }

        // Validate Input
        $this->validatePasswordUpdate($params);

        $result = $this->getCatalog()->changePassword(
            [
                'patron' => $patron,
                'oldPassword' => $params['oldpwd'],
                'newPassword' => $params['password'],
            ]
        );
        if (!$result['success']) {
            throw new AuthException($result['status']);
        }

        // Update the user and send it back to the caller:
        $username = $patron[$this->getUsernameField()];
        $user = $this->getOrCreateUserByUsername($username);
        $this->authenticator->saveUserCatalogCredentials($user, $patron['cat_username'], $params['password']);
        return $user;
    }

    /**
     * What login method does the ILS use (password, email, vufind)
     *
     * @param string $target Login target (MultiILS only)
     *
     * @return string
     */
    public function getILSLoginMethod($target = '')
    {
        $config = $this->getCatalog()->checkFunction(
            'patronLogin',
            ['patron' => ['cat_username' => "$target.login"]]
        );
        return $config['loginMethod'] ?? 'password';
    }

    /**
     * Returns any authentication method this request should be delegated to.
     *
     * @param Request $request Request object.
     *
     * @return string|bool
     */
    public function getDelegateAuthMethod(Request $request)
    {
        return (null !== $this->emailAuthenticator
            && $this->emailAuthenticator->isValidLoginRequest($request))
                ? 'Email' : false;
    }

    /**
     * Handle the actual login with the ILS.
     *
     * @param string $username    User name
     * @param string $password    Password
     * @param string $loginMethod Login method
     * @param bool   $rememberMe  Whether to remember the login
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function handleLogin($username, $password, $loginMethod, $rememberMe)
    {
        if ($username == '' || ('password' === $loginMethod && $password == '')) {
            throw new AuthException('authentication_error_blank');
        }

        // Connect to catalog:
        try {
            $patron = $this->getCatalog()->patronLogin($username, $password);
        } catch (AuthException $e) {
            // Pass Auth exceptions through
            throw $e;
        } catch (\Exception $e) {
            throw new AuthException('authentication_error_technical');
        }

        // Did the patron successfully log in?
        if ('email' === $loginMethod) {
            if (null === $this->emailAuthenticator) {
                throw new \Exception('Email authenticator not set');
            }
            if ($patron) {
                $class = get_class($this);
                if ($p = strrpos($class, '\\')) {
                    $class = substr($class, $p + 1);
                }
                $this->emailAuthenticator->sendAuthenticationLink(
                    $patron['email'],
                    [
                        'userData' => $patron,
                        'rememberMe' => $rememberMe,
                    ],
                    ['auth_method' => $class]
                );
            }
            // Don't reveal the result
            throw new \VuFind\Exception\AuthInProgress('email_login_link_sent');
        }
        if ($patron) {
            return $this->processILSUser($patron);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
    }

    /**
     * Update the database using details from the ILS, then return the User object.
     *
     * @param array $info User details returned by ILS driver.
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function processILSUser($info)
    {
        // Figure out which field of the response to use as an identifier; fail
        // if the expected field is missing or empty:
        $usernameField = $this->getUsernameField();
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new AuthException('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $userService = $this->getUserService();
        if (!empty($info['id'])) {
            $user = $userService->getUserByCatId($info['id']);
            if (empty($user)) {
                $user = $this->getOrCreateUserByUsername($info[$usernameField]);
                $user->setCatId($info['id']);
                $this->getDbService(UserServiceInterface::class)->persistEntity($user);
            }
        } else {
            $user = $this->getOrCreateUserByUsername($info[$usernameField]);
        }

        // No need to store the ILS password in VuFind's main password field:
        $user->setRawPassword('');

        // Update user information based on ILS data:
        $fields = ['firstname', 'lastname', 'major', 'college'];
        foreach ($fields as $field) {
            $this->setUserValueByField($user, $field, $info[$field] ?? ' ');
        }
        $userService->updateUserEmail($user, $info['email'] ?? '');

        // Update the user in the database, then return it to the caller:
        $this->authenticator->saveUserCatalogCredentials(
            $user,
            $info['cat_username'] ?? ' ',
            $info['cat_password'] ?? ' '
        );

        return $user;
    }

    /**
     * Make sure passwords match and fulfill ILS policy
     *
     * @param array $params request parameters
     *
     * @return void
     */
    protected function validatePasswordUpdate($params)
    {
        // Needs a password
        if (trim($params['password']) == '') {
            throw new AuthException('Password cannot be blank');
        }
        // Passwords don't match
        if ($params['password'] != $params['password2']) {
            throw new AuthException('Passwords do not match');
        }

        $this->validatePasswordAgainstPolicy($params['password']);
    }

    /**
     * Get the Currently Logged-In Patron
     *
     * @throws AuthException
     *
     * @return array|null Patron or null if no credentials exist
     */
    protected function getLoggedInPatron()
    {
        $patron = $this->authenticator->storedCatalogLogin();
        return $patron ? $patron : null;
    }

    /**
     * Gets the configured username field.
     *
     * @return string
     */
    protected function getUsernameField()
    {
        $config = $this->getConfig();
        return $config->Authentication->ILS_username_field ?? 'cat_username';
    }
}
