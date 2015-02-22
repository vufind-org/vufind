<?php
/**
 * Shibboleth authentication module.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Shibboleth2 extends Shibboleth
{
    /**
     * Validate configuration parameters.  This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        // Throw an exception if no login endpoint is available.
        $shib = $this->config->Shibboleth;
        if (!isset($shib->login)) {
            throw new AuthException(
                'Shibboleth login configuration parameter is not set.'
            );
        }

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
        // Check if username is set.
        $shib = $this->getConfig()->Shibboleth2;
        $username = $request->getServer()->get('REMOTE_USER');
        if (empty($username)) {
            throw new AuthException('authentication_error_admin');
        }

        $entityId = $request->getServer()->get('Shib-Identity-Provider');
        if (is_array($shib->idpentityid)) {
            if (!in_array($entityId, $shib->idpentityid)) {
                  throw new AuthException('authentication_error_denied');
            }
        } elseif (is_string($shib->idpentityid)) {
            if ($shib->idpentityid!=$entityId) {
                 throw new AuthException('authentication_error_denied');
            }
        } else { 
            throw new AuthException('authentication_error_admin'); 
        }

        // If we made it this far, we should log in the user!
        $user = $this->getUserTable()->getByUsername($username);

        return $user;
    }

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired()
    {
        $config = $this->getConfig();
        return false;
    }
}

