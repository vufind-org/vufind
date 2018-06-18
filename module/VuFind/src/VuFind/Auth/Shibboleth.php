<?php
/**
 * Shibboleth authentication module.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Shibboleth extends AbstractBase
{
    const DEFAULT_IDPSERVERPARAM = 'Shib-Identity-Provider';

    /**
     * Session manager
     *
     * @var \Zend\Session\ManagerInterface
     */
    protected $sessionManager;

    /**
     * Constructor
     *
     * @param \Zend\Session\ManagerInterface $sessionManager Session manager
     */
    public function __construct(\Zend\Session\ManagerInterface $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

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
        // Throw an exception if the required username setting is missing.
        $shib = $this->config->Shibboleth;
        if (!isset($shib->username) || empty($shib->username)) {
            throw new AuthException(
                "Shibboleth username is missing in your configuration file."
            );
        }

        // Throw an exception if no login endpoint is available.
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
        $shib = $this->getConfig()->Shibboleth;
        $username = $request->getServer()->get($shib->username);
        if (empty($username)) {
            $this->debug(
                "No username attribute ({$shib->username}) present in request: "
                . print_r($request->getServer()->toArray(), true)
            );
            throw new AuthException('authentication_error_admin');
        }

        // Check if required attributes match up:
        foreach ($this->getRequiredAttributes() as $key => $value) {
            if (!preg_match('/' . $value . '/', $request->getServer()->get($key))) {
                $this->debug(
                    "Attribute '$key' does not match required value '$value' in"
                    . ' request: ' . print_r($request->getServer()->toArray(), true)
                );
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
                    $user->$attribute = ($value === null) ? '' : $value;
                } else {
                    $catPassword = $value;
                }
            }
        }

        // Save credentials if applicable. Note that we want to allow empty
        // passwords (see https://github.com/vufind-org/vufind/pull/532), but
        // we also want to be careful not to replace a non-blank password with a
        // blank one in case the auth mechanism fails to provide a password on
        // an occasion after the user has manually stored one. (For discussion,
        // see https://github.com/vufind-org/vufind/pull/612). Note that in the
        // (unlikely) scenario that a password can actually change from non-blank
        // to blank, additional work may need to be done here.
        if (!empty($user->cat_username)) {
            $user->saveCredentials(
                $user->cat_username,
                empty($catPassword) ? $user->getCatPassword() : $catPassword
            );
        }

        // Add session id mapping to external_session table for single logout support
        if (isset($shib->session_id)) {
            $shibSessionId = $request->getServer()->get($shib->session_id);
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

        if (isset($config->Shibboleth->provider_id)) {
            $sessionInitiator = $sessionInitiator . '&entityID=' .
                urlencode($config->Shibboleth->provider_id);
        }

        return $sessionInitiator;
    }

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired()
    {
        $config = $this->getConfig();
        if (isset($config->Shibboleth->username)
            && isset($config->Shibboleth->logout)
        ) {
            // It would be more proper to call getServer on a Zend request
            // object... except that the request object doesn't exist yet when
            // this routine gets called.
            $username = isset($_SERVER[$config->Shibboleth->username])
                ? $_SERVER[$config->Shibboleth->username] : null;
            return empty($username);
        }
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
            $append = (strpos($config->Shibboleth->logout, '?') !== false) ? '&'
                : '?';
            $url = $config->Shibboleth->logout . $append . 'return='
                . urlencode($url);
        }

        // Send back the redirect URL (possibly modified):
        return $url;
    }

    /**
     * Extract required user attributes from the configuration.
     *
     * @return array      Only username and attribute-related values
     */
    protected function getRequiredAttributes()
    {
        // Special case -- store username as-is to establish return array:
        $sortedUserAttributes = [];

        // Now extract user attribute values:
        $shib = $this->getConfig()->Shibboleth;
        foreach ($shib as $key => $value) {
            if (preg_match("/userattribute_[0-9]{1,}/", $key)) {
                $valueKey = 'userattribute_value_' . substr($key, 14);
                $sortedUserAttributes[$value] = isset($shib->$valueKey)
                    ? $shib->$valueKey : null;

                // Throw an exception if attributes are missing/empty.
                if (empty($sortedUserAttributes[$value])) {
                    throw new AuthException(
                        "User attribute value of " . $value . " is missing!"
                    );
                }
            }
        }

        return $sortedUserAttributes;
    }
}
