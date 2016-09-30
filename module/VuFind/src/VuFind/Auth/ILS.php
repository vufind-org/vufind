<?php
/**
 * ILS authentication module.
 *
 * PHP version 5
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

use VuFind\Exception\Auth as AuthException,
    VuFind\Exception\ILS as ILSException;

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
     * ILS Authenticator
     *
     * @var object
     */
    protected $authenticator;

    /**
     * Catalog connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $catalog = null;

    /**
     * Set the ILS connection for this object.
     *
     * @param \VuFind\ILS\Connection    $connection    ILS connection to set
     * @param \VuFind\ILS\Authenticator $authenticator ILS authenticator
     */
    public function __construct(
        \VuFind\ILS\Connection $connection,
        \VuFind\Auth\ILSAuthenticator $authenticator
    ) {
        $this->setCatalog($connection);
        $this->authenticator = $authenticator;
    }

    /**
     * Get the ILS driver associated with this object (or load the default from
     * the service manager.
     *
     * @return \VuFind\ILS\Driver\DriverInterface
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
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $username = trim($request->getPost()->get('username'));
        $password = trim($request->getPost()->get('password'));
        if ($username == '' || $password == '') {
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
        if ($patron) {
            return $this->processILSUser($patron);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
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
            $policy['hint'] = $this->getCannedPasswordPolicyHint($policy['pattern']);
        }
        return $policy;
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
                'newPassword' => $params['password']
            ]
        );
        if (!$result['success']) {
            throw new AuthException($result['status']);
        }

        // Update the user and send it back to the caller:
        $user = $this->getUserTable()->getByUsername($patron['cat_username']);
        $user->saveCredentials($patron['cat_username'], $params['password']);
        return $user;
    }

    /**
     * Update the database using details from the ILS, then return the User object.
     *
     * @param array $info User details returned by ILS driver.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function processILSUser($info)
    {
        // Figure out which field of the response to use as an identifier; fail
        // if the expected field is missing or empty:
        $config = $this->getConfig();
        $usernameField = isset($config->Authentication->ILS_username_field)
            ? $config->Authentication->ILS_username_field : 'cat_username';
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new AuthException('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $user = $this->getUserTable()->getByUsername($info[$usernameField]);

        // No need to store the ILS password in VuFind's main password field:
        $user->password = '';

        // Update user information based on ILS data:
        $fields = ['firstname', 'lastname', 'email', 'major', 'college'];
        foreach ($fields as $field) {
            $user->$field = isset($info[$field]) ? $info[$field] : ' ';
        }

        // Update the user in the database, then return it to the caller:
        $user->saveCredentials(
            isset($info['cat_username']) ? $info['cat_username'] : ' ',
            isset($info['cat_password']) ? $info['cat_password'] : ' '
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
}
