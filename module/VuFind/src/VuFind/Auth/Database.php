<?php
/**
 * Database authentication class
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException, Zend\Crypt\Password\Bcrypt;

/**
 * Database authentication class
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
class Database extends AbstractBase
{
    protected $username;
    protected $password;

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
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function create($request)
    {
        // Ensure that all expected parameters are populated to avoid notices
        // in the code below.
        $params = array(
            'firstname' => '', 'lastname' => '', 'username' => '',
            'password' => '', 'password2' => '', 'email' => ''
        );
        foreach ($params as $param => $junk) {
            $params[$param] = $request->getPost()->get($param, '');
        }

        // Validate Input
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
        // Invalid Email Check
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }

        // Make sure we have a unique username
        $table = $this->getUserTable();
        if ($table->getByUsername($params['username'], false)) {
            throw new AuthException('That username is already taken');
        }
        // Make sure we have a unique email
        if ($table->getByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }

        // If we got this far, we're ready to create the account:
        $data = array(
            'username'  => $params['username'],
            'firstname' => $params['firstname'],
            'lastname'  => $params['lastname'],
            'email'     => $params['email'],
            'created'   => date('Y-m-d h:i:s')
        );

        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $data['pass_hash'] = $bcrypt->create($params['password']);
        } else {
            $data['password'] = $params['password'];
        }
        // Create the row and send it back to the caller:
        $table->insert($data);
        return $table->getByUsername($params['username'], false);
    }

    /**
     * Check that the user's password matches the provided value.
     *
     * @param string $password Password to check.
     * @param object $userRow  The user row.  We pass this instead of the password
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
     * Does this authentication method support account creation?
     *
     * @return bool
     */
    public function supportsCreation()
    {
        return true;
    }
}