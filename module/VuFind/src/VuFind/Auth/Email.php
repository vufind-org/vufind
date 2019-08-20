<?php
/**
 * Email authentication module.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
namespace VuFind\Auth;

use VuFind\Exception\Auth as AuthException;

/**
 * Email authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class Email extends AbstractBase
{
    /**
     * Email Authenticator
     *
     * @var EmailAuthenticator
     */
    protected $emailAuthenticator;

    /**
     * Constructor
     *
     * @param EmailAuthenticator $emailAuth Email authenticator
     */
    public function __construct(EmailAuthenticator $emailAuth)
    {
        $this->emailAuthenticator = $emailAuth;
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
        $patron
            = $this->emailAuthenticator->authenticate($request->getQuery('hash'));

        if ($patron) {
            return $this->processUser($patron);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed or
     * true if session initiator is built-in and form processing needs to be
     * bypassed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        return true;
    }

    /**
     * Update the database using login user details, then return the User object.
     *
     * @param array $info User details returned by the login initiator like ILS.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function processUser($info)
    {
        // Check to see if we already have an account for this user:
        $userTable = $this->getUserTable();
        if (!empty($info['id'])) {
            $user = $userTable->getByCatalogId($info['id']);
            if (empty($user)) {
                $user = $userTable->getByUsername($info['email']);
                $user->saveCatalogId($info['id']);
            }
        } else {
            $user = $userTable->getByUsername($info['email']);
        }

        // No need to store the ILS password in VuFind's main password field:
        $user->password = '';

        // Update user information based on received data:
        $fields = ['firstname', 'lastname', 'email', 'major', 'college'];
        foreach ($fields as $field) {
            $user->$field = $info[$field] ?? ' ';
        }

        // Update the user in the database, then return it to the caller:
        $user->saveCredentials(
            $info['cat_username'] ?? ' ',
            $info['cat_password'] ?? ' '
        );

        return $user;
    }
}
