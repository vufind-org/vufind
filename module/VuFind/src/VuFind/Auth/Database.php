<?php
/**
 * Database authentication class
 *
 * PHP version 7
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

use VuFind\Db\Table\User as UserTable;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\AuthEmailNotVerified as AuthEmailNotVerifiedException;
use Zend\Crypt\Password\Bcrypt;
use Zend\Http\PhpEnvironment\Request;

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
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        // Make sure the credentials are non-blank:
        $this->username = trim($request->getPost()->get('username'));
        $this->password = trim($request->getPost()->get('password'));
        if ($this->username == '' || $this->password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Validate the credentials:
        $user = $this->getUserTable()->getByUsername($this->username, false);
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
        return isset($config->Authentication->hash_passwords)
            ? $config->Authentication->hash_passwords : false;
    }

    /**
     * Create a new user account from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function create($request)
    {
        // Collect POST parameters from request
        $params = $this->collectParamsFromRequest($request);

        // Validate username and password
        $this->validateUsernameAndPassword($params);

        // Get the user table
        $userTable = $this->getUserTable();

        // Make sure parameters are correct
        $this->validateParams($params, $userTable);

        // If we got this far, we're ready to create the account:
        $user = $this->createUserFromParams($params, $userTable);
        $user->save();

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
     * @return \VuFind\Db\Row\User New user row.
     */
    public function updatePassword($request)
    {
        // Ensure that all expected parameters are populated to avoid notices
        // in the code below.
        $params = [
            'username' => '', 'password' => '', 'password2' => ''
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        // Validate Input
        $this->validateUsernameAndPassword($params);

        // Create the row and send it back to the caller:
        $table = $this->getUserTable();
        $user = $table->getByUsername($params['username'], false);
        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $user->pass_hash = $bcrypt->create($params['password']);
        } else {
            $user->password = $params['password'];
        }
        $user->save();
        return $user;
    }

    /**
     * Make sure username and password aren't blank
     * Make sure passwords match
     *
     * @param array $params request parameters
     *
     * @return void
     */
    protected function validateUsernameAndPassword($params)
    {
        // Needs a username
        if (trim($params['username']) == '') {
            throw new AuthException('Username cannot be blank');
        }
        // Needs a password
        if (trim($params['password']) == '') {
            throw new AuthException('Password cannot be blank');
        }
        // Passwords don't match
        if ($params['password'] != $params['password2']) {
            throw new AuthException('Passwords do not match');
        }
        // Password policy
        $this->validatePasswordAgainstPolicy($params['password']);
    }

    /**
     * Check if the user's email address has been verified (if necessary) and
     * throws exception if not.
     *
     * @param \VuFind\Db\Row\User $user User to check
     *
     * @return void
     * @throws AuthEmailNotVerifiedException
     */
    protected function checkEmailVerified($user)
    {
        $config = $this->getConfig();
        $verify_email = $config->Authentication->verify_email ?? false;
        if ($verify_email && !$user->checkEmailVerified()) {
            $exception = new AuthEmailNotVerifiedException(
                'authentication_error_email_not_verified_html'
            );
            $exception->user = $user;
            throw $exception;
        }
    }

    /**
     * Check that the user's password matches the provided value.
     *
     * @param string $password Password to check.
     * @param object $userRow  The user row. We pass this instead of the password
     * because we may need to check different values depending on the password
     * hashing configuration.
     *
     * @return bool
     */
    protected function checkPassword($password, $userRow)
    {
        // Special case: hashing enabled:
        if ($this->passwordHashingEnabled()) {
            if ($userRow->password) {
                throw new \VuFind\Exception\PasswordSecurity(
                    'Unexpected unencrypted password found in database'
                );
            }

            $bcrypt = new Bcrypt();
            return $bcrypt->verify($password, $userRow->pass_hash);
        }

        // Default case: unencrypted passwords:
        return $password == $userRow->password;
    }

    /**
     * Check that an email address is legal based on whitelist (if configured).
     *
     * @param string $email Email address to check (assumed to be valid/well-formed)
     *
     * @return bool
     */
    protected function emailAllowed($email)
    {
        // If no whitelist is configured, all emails are allowed:
        $config = $this->getConfig();
        if (!isset($config->Authentication->domain_whitelist)
            || empty($config->Authentication->domain_whitelist)
        ) {
            return true;
        }

        // Normalize the whitelist:
        $whitelist = array_map(
            'trim',
            array_map(
                'strtolower', $config->Authentication->domain_whitelist->toArray()
            )
        );

        // Extract the domain from the email address:
        $parts = explode('@', $email);
        $domain = strtolower(trim(array_pop($parts)));

        // Match domain against whitelist:
        return in_array($domain, $whitelist);
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
            'password' => '', 'password2' => '', 'email' => ''
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        return $params;
    }

    /**
     * Validate parameters.
     *
     * @param string[]  $params Parameters returned from collectParamsFromRequest()
     * @param UserTable $table  The VuFind user table
     *
     * @throws AuthException
     *
     * @return void
     */
    protected function validateParams($params, $table)
    {
        // Invalid Email Check
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }

        // Check if Email is on whitelist (if applicable)
        if (!$this->emailAllowed($params['email'])) {
            throw new AuthException('authentication_error_creation_blocked');
        }

        // Make sure we have a unique username
        if ($table->getByUsername($params['username'], false)) {
            throw new AuthException('That username is already taken');
        }

        // Make sure we have a unique email
        if ($table->getByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }
    }

    /**
     * Create a user row object from given parametes.
     *
     * @param string[]  $params Parameters returned from collectParamsFromRequest()
     * @param UserTable $table  The VuFind user table
     *
     * @return \VuFind\Db\Row\User A user row object
     */
    protected function createUserFromParams($params, $table)
    {
        $user = $table->createRowForUsername($params['username']);
        $user->firstname = $params['firstname'];
        $user->lastname = $params['lastname'];
        $user->email = $params['email'];
        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $user->pass_hash = $bcrypt->create($params['password']);
        } else {
            $user->password = $params['password'];
        }

        return $user;
    }
}
