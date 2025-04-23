<?php

/**
 * Authentication interface.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers
 * Wiki
 */

namespace VuFind\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

/**
 * Authentication interface.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers
 * Wiki
 */
interface AuthInterface
{
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
    public function preLoginCheck($request);

    /**
     * Reset any internal status; this is essentially an event hook which most auth
     * modules can ignore. See ChoiceAuth for a use case example.
     *
     * @return void
     */
    public function clearLoginState();

    /**
     * Set configuration.
     *
     * @param \VuFind\Config\Config $config Configuration to set
     *
     * @return void
     */
    public function setConfig($config);

    /**
     * Whether this authentication method needs CSRF checking for the request.
     *
     * @param Request $request Request object.
     *
     * @return bool
     */
    public function needsCsrfCheck($request);

    /**
     * Returns any authentication method this request should be delegated to.
     *
     * @param Request $request Request object.
     *
     * @return string|bool
     */
    public function getDelegateAuthMethod(Request $request);

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request);

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
    public function validateCredentials($request);

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired();

    /**
     * Create a new user account from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return UserEntityInterface New user entity.
     */
    public function create($request);

    /**
     * Update a user's password from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return UserEntityInterface Updated user entity.
     */
    public function updatePassword($request);

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate). Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target);

    /**
     * Get URL users should be redirected to for logout in external services if necessary.
     *
     * @param string $url Internal URL to redirect user to after logging out.
     *
     * @return string Redirect URL (usually same as $url, but modified in some authentication modules).
     */
    public function getLogoutRedirectUrl(string $url): string;

    /**
     * Does this authentication method support account creation?
     *
     * @return bool
     */
    public function supportsCreation();

    /**
     * Does this authentication method support password changing
     *
     * @return bool
     */
    public function supportsPasswordChange();

    /**
     * Does this authentication method support password recovery
     *
     * @return bool
     */
    public function supportsPasswordRecovery();

    /**
     * Does this authentication method support connecting library card of
     * currently authenticated user?
     *
     * @return bool
     */
    public function supportsConnectingLibraryCard();

    /**
     * Get username policy for a new account (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getUsernamePolicy();

    /**
     * Get password policy for a new password (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getPasswordPolicy();
}
