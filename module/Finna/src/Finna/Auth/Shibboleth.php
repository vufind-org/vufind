<?php

/**
 * Shibboleth authentication module.
 *
 * PHP version 8
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

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Shibboleth\ConfigurationLoaderInterface;
use VuFind\Db\Entity\UserEntityInterface;
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
     * Constructor
     *
     * @param \Laminas\Session\ManagerInterface $sessionManager      Session manager
     * @param ConfigurationLoaderInterface      $configurationLoader Configuration loader
     * @param Request                           $request             Http request object
     * @param ILSAuthenticator                  $ilsAuthenticator    ILS authenticator
     * @param \Finna\ILS\Connection             $ils                 ILS connection
     */
    public function __construct(
        \Laminas\Session\ManagerInterface $sessionManager,
        ConfigurationLoaderInterface $configurationLoader,
        Request $request,
        ILSAuthenticator $ilsAuthenticator,
        protected \Finna\ILS\Connection $ils
    ) {
        parent::__construct($sessionManager, $configurationLoader, $request, $ilsAuthenticator);
        $this->ils = $ils;
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $user = parent::authenticate($request);

        $shib = $this->getConfig()->Shibboleth;
        $idpParam = $shib->idpserverparam ?? self::DEFAULT_IDPSERVERPARAM;
        $idp = $this->getServerParam($request, $idpParam);
        if (!empty($shib->idp_to_ils_map[$idp])) {
            foreach (explode('|', $shib->idp_to_ils_map[$idp]) as $mapping) {
                $parts = explode(':', $mapping);
                $catUsername = $this->getServerParam($request, $parts[0]);
                $driver = $parts[1] ?? '';
                if (!$catUsername || !$driver) {
                    continue;
                }
                // Check whether the credentials work:
                $catUsername = "$driver.$catUsername";
                try {
                    if ($this->ils->patronLogin($catUsername, null)) {
                        $this->ilsAuthenticator->saveUserCatalogCredentials($user, $catUsername, null);
                        $this->debug(
                            "ILS account '$catUsername' linked to user '{$user->getUsername()}'"
                        );
                        break;
                    }
                    $this->debug(
                        "ILS account '$catUsername' not valid for user '{$user->getUsername()}'"
                    );
                } catch (\Exception $e) {
                    $this->logError(
                        'Failed to check username validity: ' . (string)$e
                    );
                }
            }
        }

        // Store logout URL in session:
        if (isset($shib->logout_attribute)) {
            $url = $this->getServerParam($request, $shib->logout_attribute);
            if ($url) {
                $session = new \Laminas\Session\Container(
                    'Shibboleth',
                    $this->sessionManager
                );
                $session['logoutUrl'] = $url;
            }
        }

        $this->storeShibbolethSession($request);

        return $user;
    }

    /**
     * Get URL users should be redirected to for logout in external services if necessary.
     *
     * @param string $url Internal URL to redirect user to after logging out.
     *
     * @return string Redirect URL (usually same as $url, but modified in some authentication modules).
     */
    public function getLogoutRedirectUrl(string $url): string
    {
        // Check for a dynamic logout url:
        $session
            = new \Laminas\Session\Container('Shibboleth', $this->sessionManager);
        if (!empty($session['logoutUrl'])) {
            $url = $session['logoutUrl'] . '?return=' . urlencode($url);
            return $url;
        }

        return parent::getLogoutRedirectUrl($url);
    }

    /**
     * Get a server parameter taking into account any environment variables
     * redirected by Apache mod_rewrite.
     *
     * @param Request $request Request object containing account credentials.
     * @param string  $param   Parameter name
     *
     * @return mixed
     */
    protected function getServerParam($request, $param)
    {
        return $request->getServer()->get(
            $param,
            $request->getServer()->get("REDIRECT_$param")
        );
    }
}
