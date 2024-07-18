<?php

/**
 * Email authentication module.
 *
 * PHP version 8
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

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserServiceInterface;
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
     * Constructor
     *
     * @param EmailAuthenticator $emailAuthenticator Email authenticator
     * @param ILSAuthenticator   $ilsAuthenticator   ILS authenticator
     */
    public function __construct(
        protected EmailAuthenticator $emailAuthenticator,
        protected ILSAuthenticator $ilsAuthenticator
    ) {
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request)
    {
        // This is a dual-mode method:
        // First, try to find a user account with the provided email address and send
        // a login link.
        // Second, log the user in with the hash from the login link.

        $email = trim($request->getPost()->get('username', ''));
        $hash = $request->getQuery('hash');
        if (!$email && !$hash) {
            throw new AuthException('authentication_error_blank');
        }

        if (!$hash) {
            // Validate the credentials:
            $user = $this->getUserService()->getUserByEmail($email);
            if ($user) {
                $loginData = [
                    'vufind_id' => $user->getId(),
                ];
                $this->emailAuthenticator->sendAuthenticationLink(
                    $user->getEmail(),
                    $loginData,
                    ['auth_method' => 'Email']
                );
            }
            // Don't reveal the result
            throw new \VuFind\Exception\AuthInProgress('email_login_link_sent');
        }

        $loginData = $this->emailAuthenticator->authenticate($hash);
        if (isset($loginData['vufind_id'])) {
            return $this->getUserService()->getUserById($loginData['vufind_id']);
        } else {
            // Check if we have more granular data available:
            if (isset($loginData['userData'])) {
                $userData = $loginData['userData'];
                if ($loginData['rememberMe'] ?? false) {
                    // TODO: This is not a very nice way of carrying this information
                    // over to the authentication manager:
                    $request->getPost()->set('remember_me', '1');
                }
            } else {
                $userData = $loginData;
            }
            return $this->processUser($userData);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
    }

    /**
     * Whether this authentication method needs CSRF checking for the request.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object.
     *
     * @return bool
     */
    public function needsCsrfCheck($request)
    {
        // Disable CSRF if we get a hash in the request
        return $request->getQuery('hash') ? false : true;
    }

    /**
     * Update the database using login user details, then return the User object.
     *
     * @param array $info User details returned by the login initiator like ILS.
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function processUser($info)
    {
        // Check to see if we already have an account for this user:
        if (!empty($info['id'])) {
            $user = $this->getUserService()->getUserByCatId($info['id']);
            if (empty($user)) {
                $user = $this->getOrCreateUserByUsername($info['email']);
                $user->setCatId($info['id']);
                $this->getDbService(UserServiceInterface::class)->persistEntity($user);
            }
        } else {
            $user = $this->getOrCreateUserByUsername($info['email']);
        }

        // No need to store a password in VuFind's main password field:
        $user->setRawPassword('');

        // Update user information based on received data:
        $fields = ['firstname', 'lastname', 'email', 'major', 'college'];
        foreach ($fields as $field) {
            $this->setUserValueByField($user, $field, $info[$field] ?? ' ');
        }

        // Update the user in the database, then return it to the caller:
        $this->ilsAuthenticator->saveUserCatalogCredentials(
            $user,
            $info['cat_username'] ?? ' ',
            $info['cat_password'] ?? ' '
        );

        return $user;
    }
}
