<?php
/**
 * CAS authentication module.
 *
 * PHP version 7
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
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Auth;

use VuFind\Exception\Auth as AuthException;

/**
 * CAS authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Tom Misilo <tmisilo@gmail.com>
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CAS extends AbstractBase
{
    /**
     * Already Setup phpCAS
     *
     * @var bool
     */
    protected $phpCASSetup = false;

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
        $cas = $this->config->CAS;
        // Throw an exception if the required server setting is missing.
        if (!isset($cas->server)) {
            throw new AuthException(
                'CAS server configuration parameter is not set.'
            );
        }

        // Throw an exception if the required port setting is missing.
        if (!isset($cas->port)) {
            throw new AuthException(
                'CAS port configuration parameter is not set.'
            );
        }

        // Throw an exception if the required context setting is missing.
        if (!isset($cas->context)) {
            throw new AuthException(
                'CAS context configuration parameter is not set.'
            );
        }

        // Throw an exception if the required CACert setting is missing.
        if (!isset($cas->CACert)) {
            throw new AuthException(
                'CAS CACert configuration parameter is not set.'
            );
        }

        // Throw an exception if the required login setting is missing.
        if (!isset($cas->login)) {
            throw new AuthException(
                'CAS login configuration parameter is not set.'
            );
        }

        // Throw an exception if the required logout setting is missing.
        if (!isset($cas->logout)) {
            throw new AuthException(
                'CAS logout configuration parameter is not set.'
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
        // Configure phpCAS
        $cas = $this->getConfig()->CAS;
        $casauth = $this->setupCAS();
        $casauth->forceAuthentication();

        // Check if username is set.
        if (isset($cas->username) && !empty($cas->username)) {
            $username = $casauth->getAttribute($cas->username);
        } else {
            $username = $casauth->getUser();
        }
        if (empty($username)) {
            throw new AuthException('authentication_error_admin');
        }

        // If we made it this far, we should log in the user!
        $user = $this->getUserTable()->getByUsername($username);

        // Has the user configured attributes to use for populating the user table?
        $attribsToCheck = [
            "cat_username", "cat_password", "email", "lastname", "firstname",
            "college", "major", "home_library"
        ];
        $catPassword = null;
        foreach ($attribsToCheck as $attribute) {
            if (isset($cas->$attribute)) {
                $value = $casauth->getAttribute($cas->$attribute);
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
        if (isset($config->CAS->target)) {
            $casTarget = $config->CAS->target;
        } else {
            $casTarget = $target;
        }
        $append = (strpos($casTarget, '?') !== false) ? '&' : '?';
        $sessionInitiator = $config->CAS->login
            . '?service=' . urlencode($casTarget)
            . urlencode($append . 'auth_method=CAS');

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
        if (isset($config->CAS->username)
            && isset($config->CAS->logout)
        ) {
            $casauth = $this->setupCAS();
            if ($casauth->checkAuthentication() === false) {
                return true;
            }
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
        if (isset($config->CAS->logout)
            && !empty($config->CAS->logout)
        ) {
            $url = $config->CAS->logout . '?service=' . urlencode($url);
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
        $cas = $this->getConfig()->CAS;
        foreach ($cas as $key => $value) {
            if (preg_match("/userattribute_[0-9]{1,}/", $key)) {
                $valueKey = 'userattribute_value_' . substr($key, 14);
                $sortedUserAttributes[$value] = $cas->$valueKey ?? null;

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

    /**
     * Establishes phpCAS Configuration and Enables the phpCAS Client
     *
     * @return object     Returns phpCAS Object
     */
    protected function setupCAS()
    {
        $casauth = new \phpCAS();

        // Check to see if phpCAS has already been setup. If it has, than skip as
        // client can only be called once.
        if (!$this->phpCASSetup) {
            $cas = $this->getConfig()->CAS;
            if (isset($cas->log)
                && !empty($cas->log) && isset($cas->debug) && ($cas->debug)
            ) {
                $casauth->setDebug($cas->log);
            }
            $protocol = constant($cas->protocol ?? 'SAML_VERSION_1_1');
            $casauth->client(
                $protocol, $cas->server, (int)$cas->port, $cas->context, false
            );
            if (isset($cas->CACert) && !empty($cas->CACert)) {
                $casauth->setCasServerCACert($cas->CACert);
            } else {
                $casauth->setNoCasServerValidation();
            }
            $this->phpCASSetup = true;
        }

        return $casauth;
    }
}
