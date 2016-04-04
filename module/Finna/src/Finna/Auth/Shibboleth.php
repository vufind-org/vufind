<?php
/**
 * Shibboleth authentication module.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Auth;

use VuFind\Exception\Auth as AuthException;
use Zend\Session\Container as SessionContainer;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Shibboleth extends \VuFind\Auth\Shibboleth
{
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
        $username = $request->getServer()->get($shib->username);
        if (empty($username)) {
            $this->logError(
                'Shibboleth login failed for request: no username attribute present'
                . ' in request: ' . print_r($request->getServer()->toArray(), true)
            );
            throw new AuthException('authentication_error_admin');
        }

        // Check if required attributes match up:
        foreach ($this->getRequiredAttributes() as $key => $value) {
            if (!preg_match('/' . $value . '/', $request->getServer()->get($key))) {
                throw new AuthException('authentication_error_denied');
            }
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
                    // Special case: don't override existing email address:
                    if ($attribute == 'email') {
                        if (isset($user->email) && trim($user->email) != '') {
                            continue;
                        }
                    }
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

        // Store logout URL in session:
        $config = $this->getConfig()->Shibboleth;
        if (isset($config->logout_attribute)) {
            $url = $request->getServer()->get($config->logout_attribute);
            if ($url) {
                $sessionContainer = new SessionContainer('Shibboleth');
                $sessionContainer['logoutUrl'] = $url;
            }
        }

        // Save and return the user object:
        $user->save();
        return $user;
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
        // Check for a dynamic logout url:
        $sessionContainer = new SessionContainer('Shibboleth');
        if (!empty($sessionContainer['logoutUrl'])) {
            $url = $sessionContainer['logoutUrl'] . '?return=' . urlencode($url);
            return $url;
        }

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

}
