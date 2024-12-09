<?php

/**
 * SIP2 authentication module.
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
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

/**
 * SIP2 authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class SIP2 extends AbstractBase
{
    /**
     * Constructor
     *
     * @param ILSAuthenticator $ilsAuthenticator ILS authenticator
     */
    public function __construct(protected ILSAuthenticator $ilsAuthenticator)
    {
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
        $username = trim($request->getPost()->get('username', ''));
        $password = trim($request->getPost()->get('password', ''));
        if ($username == '' || $password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Attempt SIP2 Authentication
        $mysip = new \sip2();
        $config = $this->getConfig();
        if (isset($config->SIP2)) {
            $mysip->hostname = $config->SIP2->host;
            $mysip->port = $config->SIP2->port;
        }

        if (!$mysip->connect()) {
            throw new AuthException('authentication_error_technical');
        }

        //send selfcheck status message
        $in = $mysip->msgSCStatus();
        $msg_result = $mysip->get_message($in);

        // Make sure the response is 98 as expected
        if (!preg_match('/^98/', $msg_result)) {
            $mysip->disconnect();
            throw new AuthException('authentication_error_technical');
        }
        $result = $mysip->parseACSStatusResponse($msg_result);

        //  Use result to populate SIP2 settings
        $mysip->AO = $result['variable']['AO'][0];
        $mysip->AN = $result['variable']['AN'][0];

        $mysip->patron = $username;
        $mysip->patronpwd = $password;

        $in = $mysip->msgPatronStatusRequest();
        $msg_result = $mysip->get_message($in);

        // Make sure the response is 24 as expected
        if (!preg_match('/^24/', $msg_result)) {
            $mysip->disconnect();
            throw new AuthException('authentication_error_technical');
        }

        $result = $mysip->parsePatronStatusResponse($msg_result);
        $mysip->disconnect();
        if (
            ($result['variable']['BL'][0] == 'Y')
            and ($result['variable']['CQ'][0] == 'Y')
        ) {
            // Success!!!
            $user = $this->processSIP2User($result, $username, $password);
        } else {
            throw new AuthException('authentication_error_invalid');
        }

        return $user;
    }

    /**
     * Process SIP2 User Account
     *
     * Based on code by Bob Wicksall <bwicksall@pls-net.org>.
     *
     * @param array  $info     An array of user information
     * @param string $username The user's ILS username
     * @param string $password The user's ILS password
     *
     * @throws AuthException
     * @return UserEntityInterface Processed User object.
     */
    protected function processSIP2User($info, $username, $password)
    {
        $user = $this->getOrCreateUserByUsername($info['variable']['AA'][0]);

        // This could potentially be different depending on the ILS. Name could be
        // Bob Wicksall or Wicksall, Bob. This is currently assuming Wicksall, Bob
        $ae = $info['variable']['AE'][0];
        $user->setFirstname(trim(substr($ae, 1 + strripos($ae, ','))));
        $user->setLastname(trim(substr($ae, 0, strripos($ae, ','))));
        // I'm inserting the sip username and password since the ILS is the source.
        // Should revisit this.
        $this->ilsAuthenticator->saveUserCatalogCredentials($user, $username, $password);
        return $user;
    }
}
