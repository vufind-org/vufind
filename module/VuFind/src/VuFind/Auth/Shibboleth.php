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
class Shibboleth extends  AbstractBase
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
        foreach ($shib->toArray() as $key => $value) {
            if (preg_match("/^userattribute/", $key)) {
                throw new AuthException(
                    'You must change your configuration. 
                 Attributes in login process is not longer supported. 
                 Please look at permissions.ini'
                );
            }
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
        $shib = $this->getConfig()->Shibboleth;

        $entityId = $request->getServer()->get('Shib-Identity-Provider');
        if (!$entityId) {
            throw new AuthException('authentication_error_admin');
        }

        $username = $request->getServer()->get('REMOTE_USER');
        if (empty($username)) {
            throw new AuthException('authentication_error_admin');
        }

        if (isset($shib->idpentityid) 
            && !in_array($entityId, $shib->idpentityid->toArray())
        ) {
                 throw new AuthException('authentication_error_denied');
        }

        // If we made it this far, we should log in the user!
        $user = $this->getUserTable()->getByUsername($username);

        // Variable to hold catalog password (handled separately from other
        // attributes since we need to use saveCredentials method to store it):
        $catPassword = null;

        // Has the user configured attributes to use for populating the user table?
        $attribsToCheck = [
            'cat_username', 'cat_password', 'email', 'lastname', 'firstname',
            'college', 'major', 'home_library'
        ];
        foreach ($attribsToCheck as $attribute) {
            if (isset($shib->$attribute)) {
                $value = $request->getServer()->get($shib->$attribute);
                if ($attribute != 'cat_password') {
                    $user->$attribute = $value;
                } else {
                    $catPassword = $value;
                }
            }
        }

        // Save credentials if applicable:
        if (!empty($catPassword) && !empty($user->cat_username)) {
            $user->saveCredentials($user->cat_username, $catPassword);
        }

        // Save and return the user object:
        $user->save();

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

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @return string     Redirect URL (usually same as $url, but modified in
     * some authentication modules).
     */
    public function logout($url)
    {
        // If single log-out is enabled, use a special URL:
        $config = $this->getConfig();
        if (isset($config->Shibboleth->logout)
            && !empty($config->Shibboleth->logout)
        ) {
            $url = $config->Shibboleth->logout . '?return=' . urlencode($url);
        }

        // Send back the redirect URL (possibly modified):
        return $url;
    }


    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        $config = $this->getConfig();
        if (isset($config->Shibboleth->target)) {
            $shibTarget = $config->Shibboleth->target;
        } else {
            $shibTarget = $target;
        }
        $append = (strpos($shibTarget, '?') !== false) ? '&' : '?';
        $sessionInitiator = $config->Shibboleth->login
            . '?target=' . urlencode($shibTarget)
            . urlencode($append . 'auth_method=Shibboleth'); 
                                                    // makes it possible to
                                                    // handle logins when using
                                                    // an auth method that
                                                    // proxies others

        if (isset($config->Shibboleth->idpentityid) 
            && is_string($config->Shibboleth->idpentityid)
        ) {
            $sessionInitiator = $sessionInitiator . '&entityID=' .
                urlencode($config->Shibboleth->idpentityid);
        } 

        return $sessionInitiator;
    }


}

