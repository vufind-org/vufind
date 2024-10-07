<?php

/**
 * Shibboleth authentication module.
 *
 * PHP version 8
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
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Auth\Shibboleth\ConfigurationLoaderInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\ExternalSessionServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
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
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Shibboleth extends AbstractBase implements DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Header name for entityID of the IdP that authenticated the user.
     */
    public const DEFAULT_IDPSERVERPARAM = 'Shib-Identity-Provider';

    /**
     * This is array of attributes which $this->authenticate()
     * method should check for.
     *
     * WARNING: can contain only such attributes, which are writeable to user table!
     *
     * @var array attribsToCheck
     */
    protected $attribsToCheck = [
        'cat_username', 'cat_password', 'email', 'lastname', 'firstname',
        'college', 'major', 'home_library',
    ];

    /**
     * Read attributes from headers instead of environment variables
     *
     * @var boolean
     */
    protected $useHeaders = false;

    /**
     * Name of attribute with shibboleth identity provider
     *
     * @var string
     */
    protected $shibIdentityProvider = self::DEFAULT_IDPSERVERPARAM;

    /**
     * Name of attribute with shibboleth session ID
     *
     * @var string
     */
    protected $shibSessionId = null;

    /**
     * Constructor
     *
     * @param \Laminas\Session\ManagerInterface $sessionManager      Session manager
     * @param ConfigurationLoaderInterface      $configurationLoader Configuration loader
     * @param Request                           $request             Http request object
     * @param ILSAuthenticator                  $ilsAuthenticator    ILS authenticator
     */
    public function __construct(
        protected \Laminas\Session\ManagerInterface $sessionManager,
        protected ConfigurationLoaderInterface $configurationLoader,
        protected Request $request,
        protected ILSAuthenticator $ilsAuthenticator
    ) {
    }

    /**
     * Set configuration.
     *
     * @param \Laminas\Config\Config $config Configuration to set
     *
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        $this->useHeaders = $this->config->Shibboleth->use_headers ?? false;
        $this->shibIdentityProvider = $this->config->Shibboleth->idpserverparam
            ?? self::DEFAULT_IDPSERVERPARAM;
        $this->shibSessionId = $this->config->Shibboleth->session_id ?? null;
    }

    /**
     * Validate configuration parameters. This is a support method for getConfig(),
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
                'Shibboleth username is missing in your configuration file.'
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
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request)
    {
        // validate config before authentication
        $this->validateConfig();
        // Check if username is set.
        $entityId = $this->getCurrentEntityId($request);
        $shib = $this->getConfigurationLoader()->getConfiguration($entityId);
        $username = $this->getAttribute($request, $shib['username']);
        if (empty($username)) {
            $details = ($this->useHeaders) ? $request->getHeaders()->toArray()
                : $request->getServer()->toArray();
            $this->debug(
                "No username attribute ({$shib['username']}) present in request: "
                . $this->varDump($details)
            );
            throw new AuthException('authentication_error_admin');
        }

        // Check if required attributes match up:
        foreach ($this->getRequiredAttributes($shib) as $key => $value) {
            if (!preg_match("/$value/", $this->getAttribute($request, $key) ?? '')) {
                $details = ($this->useHeaders) ? $request->getHeaders()->toArray()
                    : $request->getServer()->toArray();
                $this->debug(
                    "Attribute '$key' does not match required value '$value' in"
                    . ' request: ' . $this->varDump($details)
                );
                throw new AuthException('authentication_error_denied');
            }
        }

        // If we made it this far, we should log in the user!
        $userService = $this->getUserService();
        $user = $this->getOrCreateUserByUsername($username);

        // Variable to hold catalog password (handled separately from other
        // attributes since we need to pass it to saveUserAndCredentials method to store it):
        $catPassword = null;

        // Has the user configured attributes to use for populating the user table?
        foreach ($this->attribsToCheck as $attribute) {
            if (isset($shib[$attribute])) {
                $value = $this->getAttribute($request, $shib[$attribute]);
                if ($attribute == 'email' && !empty($value)) {
                    $userService->updateUserEmail($user, $value);
                } elseif (
                    $attribute == 'cat_username' && isset($shib['prefix'])
                    && !empty($value)
                ) {
                    $user->setCatUsername($shib['prefix'] . '.' . $value);
                } elseif ($attribute == 'cat_password') {
                    $catPassword = $value;
                } else {
                    $this->setUserValueByField($user, $attribute, $value ?? '');
                }
            }
        }

        // Save and return user data:
        $this->saveUserAndCredentials($user, $catPassword, $this->ilsAuthenticator);
        $this->storeShibbolethSession($request);
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate). Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        $config = $this->getConfig();
        $shibTarget = $config->Shibboleth->target ?? $target;
        $append = (str_contains($shibTarget, '?')) ? '&' : '?';
        // Adding the auth_method parameter makes it possible to handle logins when
        // using an auth method that proxies others.
        $sessionInitiator = $config->Shibboleth->login
            . '?target=' . urlencode($shibTarget)
            . urlencode($append . 'auth_method=Shibboleth');

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
        if (
            !isset($this->shibSessionId)
            || !($config->Shibboleth->checkExpiredSession ?? true)
        ) {
            return false;
        }
        $sessionId = $this->getAttribute($this->request, $this->shibSessionId);
        return !isset($sessionId);
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
        if (
            isset($config->Shibboleth->logout)
            && !empty($config->Shibboleth->logout)
        ) {
            $append = (str_contains($config->Shibboleth->logout, '?')) ? '&'
                : '?';
            $url = $config->Shibboleth->logout . $append . 'return='
                . urlencode($url);
        }

        // Send back the redirect URL (possibly modified):
        return $url;
    }

    /**
     * Connect user authenticated by Shibboleth to library card.
     *
     * @param Request             $request        Request object containing account credentials.
     * @param UserEntityInterface $connectingUser Connect newly created library card to this user.
     *
     * @return void
     */
    public function connectLibraryCard($request, $connectingUser)
    {
        $entityId = $this->getCurrentEntityId($request);
        $shib = $this->getConfigurationLoader()->getConfiguration($entityId);
        $username = $this->getAttribute($request, $shib['cat_username']);
        if (!$username) {
            throw new \VuFind\Exception\LibraryCard('Missing username');
        }
        $prefix = $shib['prefix'] ?? '';
        if (!empty($prefix)) {
            $username = $shib['prefix'] . '.' . $username;
        }
        $password = $shib['cat_password'] ?? null;
        $this->getDbService(UserCardServiceInterface::class)->persistLibraryCardData(
            $connectingUser,
            null,
            $shib['prefix'],
            $username,
            $password
        );
    }

    /**
     * Return configuration loader
     *
     * @return ConfigurationLoaderInterface configuration loader
     */
    protected function getConfigurationLoader()
    {
        return $this->configurationLoader;
    }

    /**
     * Extract required user attributes from the configuration.
     *
     * @param array $config Shibboleth configuration
     *
     * @return array      Only username and attribute-related values
     * @throws AuthException
     */
    protected function getRequiredAttributes($config)
    {
        // Special case -- store username as-is to establish return array:
        $sortedUserAttributes = [];

        // Now extract user attribute values:
        foreach ($config as $key => $value) {
            if (preg_match('/userattribute_[0-9]{1,}/', $key)) {
                $valueKey = 'userattribute_value_' . substr($key, 14);
                $sortedUserAttributes[$value] = $config[$valueKey] ?? null;

                // Throw an exception if attributes are missing/empty.
                if (empty($sortedUserAttributes[$value])) {
                    throw new AuthException(
                        'User attribute value of ' . $value . ' is missing!'
                    );
                }
            }
        }

        return $sortedUserAttributes;
    }

    /**
     * Add session id mapping to external_session table for single logout support
     *
     * @param Request $request Request object containing account credentials.
     *
     * @return void
     */
    protected function storeShibbolethSession($request)
    {
        if (!isset($this->shibSessionId)) {
            return;
        }
        $shibSessionId = $this->getAttribute($request, $this->shibSessionId);
        if (null === $shibSessionId) {
            return;
        }
        $localSessionId = $this->sessionManager->getId();
        $this->getDbService(ExternalSessionServiceInterface::class)
            ->addSessionMapping($localSessionId, $shibSessionId);
        $this->debug(
            "Cached Shibboleth session id '$shibSessionId' for local session"
            . " '$localSessionId'"
        );
    }

    /**
     * Fetch entityId used for authentication
     *
     * @param Request $request Request object
     *
     * @return string entityId of IdP
     */
    protected function getCurrentEntityId($request)
    {
        return $this->getAttribute($request, $this->shibIdentityProvider) ?? '';
    }

    /**
     * Extract attribute from request.
     *
     * @param Request $request   Request object
     * @param string  $attribute Attribute name
     *
     * @return ?string attribute value
     */
    protected function getAttribute($request, $attribute): ?string
    {
        if ($this->useHeaders) {
            $header = $request->getHeader($attribute);
            return ($header) ? $header->getFieldValue() : null;
        } else {
            return $request->getServer()->get($attribute, null);
        }
    }
}
