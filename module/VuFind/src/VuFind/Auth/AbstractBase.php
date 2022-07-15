<?php
/**
 * Abstract authentication base class
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

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Row\User;
use VuFind\Exception\Auth as AuthException;

/**
 * Abstract authentication base class
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Has the configuration been validated?
     *
     * @param bool
     */
    protected $configValidated = false;

    /**
     * Configuration settings
     *
     * @param \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Get configuration (load automatically if not previously set).  Throw an
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
     * Inspect the user's request prior to processing a login request; this is
     * essentially an event hook which most auth modules can ignore. See
     * ChoiceAuth for a use case example.
     *
     * @param Request $request Request object.
     *
     * @throws AuthException
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function preLoginCheck($request)
    {
        // By default, do no checking.
    }

    /**
     * Reset any internal status; this is essentially an event hook which most auth
     * modules can ignore. See ChoiceAuth for a use case example.
     *
     * @return void
     */
    public function resetState()
    {
        // By default, do no checking.
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
        $this->config = $config;
        $this->configValidated = false;
    }

    /**
     * Whether this authentication method needs CSRF checking for the request.
     *
     * @param Request $request Request object.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function needsCsrfCheck($request)
    {
        // Enabled by default
        return true;
    }

    /**
     * Returns any authentication method this request should be delegated to.
     *
     * @param Request $request Request object.
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDelegateAuthMethod(Request $request)
    {
        // No delegate by default
        return false;
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
        // By default, do no checking.
    }

    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return User Object representing logged-in user.
     */
    abstract public function authenticate($request);

    /**
     * Validate the credentials in the provided request, but do not change the state
     * of the current logged-in user. Return true for valid credentials, false
     * otherwise.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return bool
     */
    public function validateCredentials($request)
    {
        try {
            $user = $this->authenticate($request);
        } catch (AuthException $e) {
            return false;
        }
        return $user instanceof User;
    }

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired()
    {
        // By default, logins do not expire:
        return false;
    }

    /**
     * Create a new user account from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return User New user row.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function create($request)
    {
        throw new AuthException(
            'Account creation not supported by ' . get_class($this)
        );
    }

    /**
     * Update a user's password from the request.
     *
     * @param Request $request Request object containing new account details.
     *
     * @throws AuthException
     * @return User New user row.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updatePassword($request)
    {
        throw new AuthException(
            'Account password updating not supported by ' . get_class($this)
        );
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getSessionInitiator($target)
    {
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
        // No special cleanup or URL modification needed by default.
        return $url;
    }

    /**
     * Does this authentication method support account creation?
     *
     * @return bool
     */
    public function supportsCreation()
    {
        // By default, account creation is not supported.
        return false;
    }

    /**
     * Does this authentication method support password changing
     *
     * @return bool
     */
    public function supportsPasswordChange()
    {
        // By default, password changing is not supported.
        return false;
    }

    /**
     * Does this authentication method support password recovery
     *
     * @return bool
     */
    public function supportsPasswordRecovery()
    {
        // By default, password recovery is not supported.
        return false;
    }

    /**
     * Does this authentication method support connecting library card of
     * currently authenticated user?
     *
     * @return bool
     */
    public function supportsConnectingLibraryCard()
    {
        return method_exists($this, 'connectLibraryCard');
    }

    /**
     * Return a canned password policy hint when available
     *
     * @param string $pattern Current policy pattern
     *
     * @return string
     */
    protected function getCannedPasswordPolicyHint($pattern)
    {
        return (in_array($pattern, ['numeric', 'alphanumeric']))
            ? 'password_only_' . $pattern : null;
    }

    /**
     * Password policy for a new password (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getPasswordPolicy()
    {
        $policy = [];
        $config = $this->getConfig();
        if (isset($config->Authentication->minimum_password_length)) {
            $policy['minLength']
                = $config->Authentication->minimum_password_length;
        }
        if (isset($config->Authentication->maximum_password_length)) {
            $policy['maxLength']
                = $config->Authentication->maximum_password_length;
        }
        if (isset($config->Authentication->password_pattern)) {
            $policy['pattern']
                = $config->Authentication->password_pattern;
        }
        if (isset($config->Authentication->password_hint)) {
            $policy['hint'] = $config->Authentication->password_hint;
        } else {
            $policy['hint'] = $this->getCannedPasswordPolicyHint(
                $policy['pattern'] ?? null
            );
        }
        return $policy;
    }

    /**
     * Get access to the user table.
     *
     * @return \VuFind\Db\Table\User
     */
    public function getUserTable()
    {
        return $this->getDbTableManager()->get('User');
    }

    /**
     * Verify that a password fulfills the password policy. Throws exception if
     * the password is invalid.
     *
     * @param string $password Password to verify
     *
     * @return void
     * @throws AuthException
     */
    protected function validatePasswordAgainstPolicy($password)
    {
        $policy = $this->getPasswordPolicy();
        if (isset($policy['minLength'])
            && strlen($password) < $policy['minLength']
        ) {
            throw new AuthException(
                $this->translate(
                    'password_minimum_length',
                    ['%%minlength%%' => $policy['minLength']]
                )
            );
        }
        if (isset($policy['maxLength'])
            && strlen($password) > $policy['maxLength']
        ) {
            throw new AuthException(
                $this->translate(
                    'password_maximum_length',
                    ['%%maxlength%%' => $policy['maxLength']]
                )
            );
        }
        if (!empty($policy['pattern'])) {
            $valid = true;
            if ($policy['pattern'] == 'numeric') {
                if (!ctype_digit($password)) {
                    $valid = false;
                }
            } elseif ($policy['pattern'] == 'alphanumeric') {
                if (preg_match('/[^\da-zA-Z]/', $password)) {
                    $valid = false;
                }
            } else {
                $result = preg_match(
                    "/({$policy['pattern']})/",
                    $password,
                    $matches
                );
                if ($result === false) {
                    throw new \Exception(
                        'Invalid regexp in password pattern: ' . $policy['pattern']
                    );
                }
                if (!$result || $matches[1] != $password) {
                    $valid = false;
                }
            }
            if (!$valid) {
                throw new AuthException($this->translate('password_error_invalid'));
            }
        }
    }
}
