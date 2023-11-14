<?php

/**
 * Simulated single sign-on authentication module (for testing purposes only).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Exception\Auth as AuthException;

use function is_array;

/**
 * Simulated single sign-on authentication module (for testing purposes only).
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SimulatedSSO extends AbstractBase
{
    /**
     * Session initiator URL callback
     *
     * @var callable
     */
    protected $getSessionInitiatorCallback;

    /**
     * Configuration settings
     *
     * @var array
     */
    protected $simulatedSSOConfig;

    /**
     * Default user attributes, if not overridden by configuration.
     *
     * @var array
     */
    protected $defaultAttributes = [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'fake@example.com',
    ];

    /**
     * Constructor
     *
     * @param callable $url    Session initiator URL callback
     * @param array    $config Configuration settings
     */
    public function __construct($url, array $config = [])
    {
        $this->getSessionInitiatorCallback = $url;
        $this->simulatedSSOConfig = $config;
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        // If we made it this far, we should log in the user!
        $username = $this->simulatedSSOConfig['General']['username'] ?? 'fakeuser1';
        if (!$username) {
            throw new AuthException('Simulated failure');
        }
        $user = $this->getUserTable()->getByUsername($username);

        // Get attribute configuration -- use defaults if no value is set, and use an
        // empty array if something invalid was provided.
        $attribs = $this->simulatedSSOConfig['General']['attributes']
            ?? $this->defaultAttributes;
        if (!is_array($attribs)) {
            $attribs = [];
        }

        $catPassword = null;
        foreach ($attribs as $attribute => $value) {
            if ($attribute == 'email') {
                $user->updateEmail($value);
            } elseif ($attribute != 'cat_password') {
                $user->$attribute = $value ?? '';
            } else {
                $catPassword = $value;
            }
        }
        if (!empty($user->cat_username)) {
            $user->saveCredentials(
                $user->cat_username,
                empty($catPassword) ? $user->getCatPassword() : $catPassword
            );
        }

        // Save and return the user object:
        $user->save();
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
        $target .= (str_contains($target, '?') ? '&' : '?') . 'auth_method=SimulatedSSO';
        return ($this->getSessionInitiatorCallback)($target);
    }
}
