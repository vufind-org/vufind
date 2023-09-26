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
     * Constructor
     *
     * @param callable $url Session initiator URL callback
     */
    public function __construct($url)
    {
        $this->getSessionInitiatorCallback = $url;
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
        $user = $this->getUserTable()->getByUsername('fakeuser1');
        $user->firstname = 'Test';
        $user->lastname = 'User';
        $user->email = 'fake@example.com';

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
        return ($this->getSessionInitiatorCallback)($target);
    }
}
