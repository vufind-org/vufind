<?php

/**
 * Facebook authentication module.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

/**
 * Facebook authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Facebook extends AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Session container
     *
     * @var \Laminas\Session\Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param \Laminas\Session\Container $container Session container for persisting
     * state information.
     */
    public function __construct(\Laminas\Session\Container $container)
    {
        $this->session = $container;
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
        // Throw an exception if the required username setting is missing.
        $fb = $this->config->Facebook;
        if (!isset($fb->appId) || empty($fb->appId)) {
            throw new AuthException(
                'Facebook app ID is missing in your configuration file.'
            );
        }

        if (!isset($fb->secret) || empty($fb->secret)) {
            throw new AuthException(
                'Facebook app secret is missing in your configuration file.'
            );
        }
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
        $code = $request->getQuery()->get('code');
        if (empty($code)) {
            throw new AuthException('authentication_error_admin');
        }
        $accessToken = $this->getAccessTokenFromCode($code);
        if (empty($accessToken)) {
            throw new AuthException('authentication_error_admin');
        }
        $details = $this->getDetailsFromAccessToken($accessToken);
        if (empty($details->id)) {
            throw new AuthException('authentication_error_admin');
        }

        // If we made it this far, we should log in the user!
        $userService = $this->getUserService();
        $user = $this->getOrCreateUserByUsername($details->id);
        if (isset($details->first_name)) {
            $user->setFirstname($details->first_name);
        }
        if (isset($details->last_name)) {
            $user->setLastname($details->last_name);
        }
        if (isset($details->email)) {
            $userService->updateUserEmail($user, $details->email);
        }

        // Save and return the user object:
        $userService->persistEntity($user);
        return $user;
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
        $base = 'https://www.facebook.com/dialog/oauth';
        // Adding the auth_method setting makes it possible to handle logins when
        // using an auth method that proxies others (e.g. ChoiceAuth)
        $target .= ((str_contains($target, '?')) ? '&' : '?')
            . 'auth_method=Facebook';
        $this->session->lastUri = $target;
        return $base . '?client_id='
            . urlencode($this->getConfig()->Facebook->appId)
            . '&redirect_uri=' . urlencode($target)
            . '&scope=public_profile,email';
    }

    /**
     * Obtain an access token from a code.
     *
     * @param string $code Code to look up.
     *
     * @return string
     */
    protected function getAccessTokenFromCode($code)
    {
        $requestUrl = 'https://graph.facebook.com/oauth/access_token?'
            . 'client_id=' . urlencode($this->getConfig()->Facebook->appId)
            . '&redirect_uri=' . urlencode($this->session->lastUri)
            . '&client_secret=' . urlencode($this->getConfig()->Facebook->secret)
            . '&code=' . urlencode($code);
        $response = $this->httpService->get($requestUrl);
        $parts = explode('&', $response->getBody(), 2);
        $parts = explode('=', $parts[0], 2);
        return $parts[1] ?? null;
    }

    /**
     * Given an access token, look up user details.
     *
     * @param string $accessToken Access token
     *
     * @return object
     */
    protected function getDetailsFromAccessToken($accessToken)
    {
        $request = 'https://graph.facebook.com/v2.2/me?'
            . '&access_token=' . urlencode($accessToken);
        $response = $this->httpService->get($request);
        $json = json_decode($response->getBody());
        return $json;
    }
}
