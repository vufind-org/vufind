<?php
/**
 * Shibboleth authentication module.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Auth;

use VuFind\Exception\Auth as AuthException;

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
        $username = $this->getServerParam($request, $shib->username);
        if (empty($username)) {
            $this->debug(
                "No username attribute ({$shib->username}) present in request: "
                . print_r($request->getServer()->toArray(), true)
            );
            throw new AuthException('authentication_error_admin');
        }

        // Check if required attributes match up:
        foreach ($this->getRequiredAttributes() as $key => $value) {
            $attrValue = $this->getServerParam($request, $key);
            if (!preg_match('/' . $value . '/', $attrValue)) {
                $this->debug(
                    "Attribute '$key' does not match required value '$value' in"
                    . ' request: ' . print_r($request->getServer()->toArray(), true)
                );
                throw new AuthException('authentication_error_invalid_attributes');
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
                $value = $this->getServerParam($request, $shib->$attribute);
                if ($attribute != 'cat_password') {
                    // Special case: don't override existing email address:
                    if ($attribute == 'email') {
                        if (isset($user->email) && trim($user->email) != '') {
                            continue;
                        }
                    }
                    $user->$attribute = ($value === null) ? '' : $value;
                } else {
                    $catPassword = $value;
                }
            }
        }

        $idpParam = $shib->idpserverparam ?? self::DEFAULT_IDPSERVERPARAM;
        $idp = $this->getServerParam($request, $idpParam);
        if (!empty($shib->idp_to_ils_map[$idp])) {
            $parts = explode(':', $shib->idp_to_ils_map[$idp]);
            $username = $this->getServerParam($request, $parts[0]);
            $driver = $parts[1] ?? '';
            if ($username && $driver) {
                $user->cat_username = "$driver.$username";
            }
        }

        // Save credentials if applicable:
        if (!empty($user->cat_username)) {
            $user->saveCredentials($user->cat_username, $catPassword ?? '');
        }

        // Store logout URL in session:
        if (isset($shib->logout_attribute)) {
            $url = $this->getServerParam($request, $shib->logout_attribute);
            if ($url) {
                $session = new \Zend\Session\Container(
                    'Shibboleth', $this->sessionManager
                );
                $session['logoutUrl'] = $url;
            }
        }

        // Add session id mapping to external_session table for single logout support
        if (isset($shib->session_id)) {
            $shibSessionId = $this->getServerParam($request, $shib->session_id);
            if (null !== $shibSessionId) {
                $localSessionId = $this->sessionManager->getId();
                $externalSession = $this->getDbTableManager()
                    ->get('ExternalSession');
                $externalSession->addSessionMapping(
                    $localSessionId, $shibSessionId
                );
                $this->debug(
                    "Cached Shibboleth session id '$shibSessionId' for local session"
                    . " '$localSessionId'"
                );
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
        $session = new \Zend\Session\Container('Shibboleth', $this->sessionManager);
        if (!empty($session['logoutUrl'])) {
            $url = $session['logoutUrl'] . '?return=' . urlencode($url);
            return $url;
        }

        return parent::logout($url);
    }

    /**
     * Get a server parameter taking into account any environment variables
     * redirected by Apache mod_rewrite.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     * @param string                            $param   Parameter name
     *
     * @return mixed
     */
    protected function getServerParam($request, $param)
    {
        return $request->getServer()->get(
            $param, $request->getServer()->get("REDIRECT_$param")
        );
    }
}
