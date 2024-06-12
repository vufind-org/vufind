<?php

/**
 * Password Access authentication class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

use function in_array;

/**
 * Password Access authentication class
 *
 * @category VuFind
 * @package  Authentication
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PasswordAccess extends AbstractBase
{
    /**
     * Get configuration (load automatically if not previously set). Throw an
     * exception if the configuration is invalid.
     *
     * @throws AuthException
     * @return \Laminas\Config\Config
     */
    public function getConfig()
    {
        // Validate configuration if not already validated:
        if (!$this->configValidated) {
            $this->validateConfig();
            $this->configValidated = true;
        }

        return $this->config;
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
        $config = $this->getConfig()->toArray();
        $req_password = trim($request->getPost()->get('password', ''));
        $accessConfig = $config['PasswordAccess']['access_user'] ?? [];
        if (!in_array($req_password, $accessConfig)) {
            throw new AuthException('authentication_error_invalid');
        }

        $userMap = array_flip($accessConfig);
        return $this->getOrCreateUserByUsername($userMap[$req_password]);
    }
}
