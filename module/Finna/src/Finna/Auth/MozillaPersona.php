<?php
/**
 * Mozilla Persona authentication module.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Authentication
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
namespace Finna\Auth;

use VuFind\Exception\Auth as AuthException;

/**
 * Mozilla Persona authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MozillaPersona extends \VuFind\Auth\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

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
        $assertion = $request->getPost('assertion');
        if ($assertion === null) {
            throw new AuthException('authentication_missing_assertion');
        }
        $protocol = $request->getServer('HTTPS');
        $audience = (empty($protocol) ? 'http://' : 'https://') .
            $request->getServer('SERVER_NAME') . ':' .
            $request->getServer('SERVER_PORT');

        $client = $this->httpService->createClient(
            'https://verifier.login.persona.org/verify',
            \Zend\Http\Request::METHOD_POST
        );
        $client->setParameterPost(
            ['assertion' => $assertion, 'audience' => $audience]
        );
        $response = $client->send();

        $result = json_decode($response->getContent());
        if ($result->status !== 'okay') {
            throw new AuthException('authentication_error_invalid');
        }
        $username = $result->email;

        $user = $this->getUserTable()->getByUsername($username, false);
        if ($user === false) {
            $user = $this->createPersonaUser($username, $result->email);
        }

        return $user;
    }

    /**
     * Create a new Mozilla Persona authenticated user
     *
     * @param type $username user's name
     * @param type $email    User's email
     *
     * @return type
     * @throws AuthException
     */
    protected function createPersonaUser($username, $email)
    {
        $table = $this->getUserTable();
        $user = $table->createRowForUsername($username);
        $user->email = $email;
        $user->save();
        return $user;
    }

}
