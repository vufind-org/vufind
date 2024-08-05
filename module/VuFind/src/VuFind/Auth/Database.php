<?php

/**
 * Database authentication class
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use Laminas\Crypt\Password\Bcrypt;
use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\AuthEmailNotVerified as AuthEmailNotVerifiedException;

use function in_array;
use function is_object;

/**
 * Database authentication class
 *
 * @category VuFind
 * @package  Authentication
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class Database extends AbstractBase
{
    /**
     * Username
     *
     * @var string
     */
    protected $username;

    /**
     * Password
     *
     * @var string
     */
    protected $password;

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
        // Make sure the credentials are non-blank:
        $this->username = trim($request->getPost()->get('username', ''));
        $this->password = trim($request->getPost()->get('password', ''));
        if ($this->username == '' || $this->password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Validate the credentials:
        $userService = $this->getUserService();
        $user = $userService->getUserByUsername($this->username);
        if (!is_object($user) || !$this->checkPassword($this->password, $user)) {
            throw new AuthException('authentication_error_invalid');
        }

        // Verify email address:
        $this->checkEmailVerified($user);

        // If we got this far, the login was successful:
        return $user;
    }

    /**
     * Is password hashing enabled?
     *
     * @return bool
     */
    protected function passwordHashingEnabled()
    {
        $config = $this->getConfig();
        return $config->Authentication->hash_passwords ?? false;
    }

    /**
     * Set the password in a UserEntityInterface object.
     *
     * @param UserEntityInterface $user User to update
     * @param string              $pass Password to store
     *
     * @return void
     */
    protected function setUserPassword(UserEntityInterface $user, string $pass): void
    {
        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $user->setPasswordHash($bcrypt->create($pass));
        } else {
            $user->setRawPassword($pass);
        }
    }

    /**
     * Does the provided exception indicate that a duplicate key value has been
     * created?
     *
     * @param \Exception $e Exception to check
     *
     * @return bool
     */
    protected function exceptionIndicatesDuplicateKey(\Exception $e): bool
    {
        return strstr($e->getMessage(), 'Duplicate entry') !== false;
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
        // Collect POST parameters from request
        $params = $this->collectParamsFromRequest($request);

        // Validate username and password
        $this->validateUsername($params);
        $this->validatePassword($params);

        // Get the user table
        $userService = $this->getUserService();

        // Make sure parameters are correct
        $this->validateParams($params, $userService);

        // If we got this far, we're ready to create the account:
        $user = $this->createUserFromParams($params, $userService);
        try {
            $userService->persistEntity($user);
        } catch (\Laminas\Db\Adapter\Exception\RuntimeException $e) {
            // In a scenario where the unique key of the user table is
            // shorter than the username field length, it is possible that
            // a user will pass validation but still get rejected due to
            // the inability to generate a unique key. This is a very
            // unlikely scenario, but if it occurs, we will treat it the
            // same as a duplicate username. Other unexpected database
            // errors will be passed through unmodified.
            throw $this->exceptionIndicatesDuplicateKey($e)
                ? new AuthException('That username is already taken') : $e;
        }

        // Verify email address:
        $this->checkEmailVerified($user);

        return $user;
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
        $params = [
            'username' => '', 'password' => '', 'password2' => '',
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        // Validate username and password, but skip validation of username policy
        // since the account already exists):
        $this->validateUsername($params, false);
        $this->validatePassword($params);

        // Create the row and send it back to the caller:
        $user = $this->getUserService()->getUserByUsername($params['username']);
        $this->setUserPassword($user, $params['password']);
        $this->getUserService()->persistEntity($user);
        return $user;
    }

    /**
     * Make sure username isn't blank and matches the policy.
     *
     * @param array $params      Request parameters
     * @param bool  $checkPolicy Whether to check the policy as well (default is
     * true)
     *
     * @return void
     */
    protected function validateUsername($params, $checkPolicy = true)
    {
        // Needs a username
        if (trim($params['username']) == '') {
            throw new AuthException('Username cannot be blank');
        }
        if ($checkPolicy) {
            // Check username policy
            $this->validateUsernameAgainstPolicy($params['username']);
        }
    }

    /**
     * Make sure password isn't blank, matches the policy and passwords match.
     *
     * @param array $params Request parameters
     *
     * @return void
     */
    protected function validatePassword($params)
    {
        // Needs a password
        if (trim($params['password']) == '') {
            throw new AuthException('Password cannot be blank');
        }
        // Passwords don't match
        if ($params['password'] != $params['password2']) {
            throw new AuthException('Passwords do not match');
        }
        // Check password policy
        $this->validatePasswordAgainstPolicy($params['password']);
    }

    /**
     * Check if the user's email address has been verified (if necessary) and
     * throws exception if not.
     *
     * @param UserEntityInterface $user User to check
     *
     * @return void
     * @throws AuthEmailNotVerifiedException
     */
    protected function checkEmailVerified($user)
    {
        $config = $this->getConfig();
        $verify_email = $config->Authentication->verify_email ?? false;
        if ($verify_email && !$user->getEmailVerified()) {
            throw new AuthEmailNotVerifiedException(
                $user,
                'authentication_error_email_not_verified_html'
            );
        }
    }

    /**
     * Check that the user's password matches the provided value.
     *
     * @param string              $password Password to check.
     * @param UserEntityInterface $userRow  The user row. We pass this instead of the password
     * because we may need to check different values depending on the password
     * hashing configuration.
     *
     * @return bool
     */
    protected function checkPassword($password, $userRow)
    {
        // Special case: hashing enabled:
        if ($this->passwordHashingEnabled()) {
            if ($userRow->getRawPassword()) {
                throw new \VuFind\Exception\PasswordSecurity(
                    'Unexpected unencrypted password found in database'
                );
            }

            $bcrypt = new Bcrypt();
            return $bcrypt->verify($password, $userRow->getPasswordHash() ?? '');
        }

        // Default case: unencrypted passwords:
        return $password == $userRow->getRawPassword();
    }

    /**
     * Check that an email address is legal based on inclusion list (if configured).
     *
     * @param string $email Email address to check (assumed to be valid/well-formed)
     *
     * @return bool
     */
    protected function emailAllowed($email)
    {
        // If no inclusion list is configured, all emails are allowed:
        $fullConfig = $this->getConfig();
        $config = isset($fullConfig->Authentication)
            ? $fullConfig->Authentication->toArray() : [];
        $rawIncludeList = $config['legal_domains']
            ?? $config['domain_whitelist']  // deprecated configuration
            ?? null;
        if (empty($rawIncludeList)) {
            return true;
        }

        // Normalize the allowed list:
        $includeList = array_map(
            'trim',
            array_map('strtolower', $rawIncludeList)
        );

        // Extract the domain from the email address:
        $parts = explode('@', $email);
        $domain = strtolower(trim(array_pop($parts)));

        // Match domain against allowed list:
        return in_array($domain, $includeList);
    }

    /**
     * Does this authentication method support account creation?
     *
     * @return bool
     */
    public function supportsCreation()
    {
        return true;
    }

    /**
     * Does this authentication method support password changing
     *
     * @return bool
     */
    public function supportsPasswordChange()
    {
        return true;
    }

    /**
     * Does this authentication method support password recovery
     *
     * @return bool
     */
    public function supportsPasswordRecovery()
    {
        return true;
    }

    /**
     * Username policy for a new account (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getUsernamePolicy()
    {
        $policy = parent::getUsernamePolicy();
        // Limit maxLength to the database limit
        if (!isset($policy['maxLength']) || $policy['maxLength'] > 255) {
            $policy['maxLength'] = 255;
        }
        return $policy;
    }

    /**
     * Password policy for a new password (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getPasswordPolicy()
    {
        $policy = parent::getPasswordPolicy();
        // Limit maxLength to the database limit
        if (!isset($policy['maxLength']) || $policy['maxLength'] > 32) {
            $policy['maxLength'] = 32;
        }
        return $policy;
    }

    /**
     * Collect parameters from request and populate them.
     *
     * @param Request $request Request object containing new account details.
     *
     * @return string[]
     */
    protected function collectParamsFromRequest($request)
    {
        // Ensure that all expected parameters are populated to avoid notices
        // in the code below.
        $params = [
            'firstname' => '', 'lastname' => '', 'username' => '',
            'password' => '', 'password2' => '', 'email' => '',
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        return $params;
    }

    /**
     * Validate parameters.
     *
     * @param string[]             $params      Parameters returned from collectParamsFromRequest()
     * @param UserServiceInterface $userService User service
     *
     * @throws AuthException
     *
     * @return void
     */
    protected function validateParams(array $params, UserServiceInterface $userService): void
    {
        // Invalid Email Check
        $validator = new \Laminas\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }

        // Check if Email is on allowed list (if applicable)
        if (!$this->emailAllowed($params['email'])) {
            throw new AuthException('authentication_error_creation_blocked');
        }

        // Make sure we have a unique username
        if ($userService->getUserByUsername($params['username'])) {
            throw new AuthException('That username is already taken');
        }

        // Make sure we have a unique email
        if ($userService->getUserByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }
    }

    /**
     * Create a user entity object from given parameters.
     *
     * @param string[]             $params      Parameters returned from collectParamsFromRequest()
     * @param UserServiceInterface $userService User service
     *
     * @return UserEntityInterface A user entity
     */
    protected function createUserFromParams(array $params, UserServiceInterface $userService)
    {
        $user = $userService->createEntityForUsername($params['username']);
        $user->setFirstname($params['firstname']);
        $user->setLastname($params['lastname']);
        $this->getUserService()->updateUserEmail($user, $params['email'], true);
        $this->setUserPassword($user, $params['password']);
        return $user;
    }
}
