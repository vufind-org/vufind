<?php
/**
 * Abstract authentication base class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Auth;
use VuFind\Db\Row\User, VuFind\Exception\Auth as AuthException;
use Zend\Log\LoggerInterface;

/**
 * Abstract authentication base class
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
abstract class AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface, \Zend\Log\LoggerAwareInterface
{
    /**
     * Has the configuration been validated?
     *
     * @param bool
     */
    protected $configValidated = false;

    /**
     * Configuration settings
     *
     * @param \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Database table plugin manager
     *
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $tableManager;

    /**
     * Translator
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator;

    /**
     * Logger (or false for none)
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return TranslatorAwareInterface
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Get configuration (load automatically if not previously set).  Throw an
     * exception if the configuration is invalid.
     *
     * @throws AuthException
     * @return \Zend\Config\Config
     */
    public function getConfig()
    {
        // Validate configuration if not already validated:
        if (!$this->configValidated) {
            $this->validateConfig();
        }

        return $this->config;
    }

    /**
     * Set configuration.
     *
     * @param \Zend\Config\Config $config Configuration to set
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
        $this->configValidated = false;
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
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
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
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
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
        return isset($user) && $user instanceof User;
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
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return User New user row.
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
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return User New user row.
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
     * Password policy for a new password (e.g. minLength, maxLength)
     *
     * @return array
     */
    public function getPasswordPolicy()
    {
        $policy = array();
        $config = $this->getConfig();
        if (isset($config->Authentication->minimum_password_length)) {
            $policy['minLength']
                = $config->Authentication->minimum_password_length;
        }
        if (isset($config->Authentication->maximum_password_length)) {
            $policy['maxLength']
                = $config->Authentication->maximum_password_length;
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
     * Get the table plugin manager.  Throw an exception if it is missing.
     *
     * @throws \Exception
     * @return \VuFind\Db\Table\PluginManager
     */
    public function getDbTableManager()
    {
        if (null === $this->tableManager) {
            throw new \Exception('DB table manager missing.');
        }
        return $this->tableManager;
    }

    /**
     * Set the table plugin manager.
     *
     * @param \VuFind\Db\Table\PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setDbTableManager(\VuFind\Db\Table\PluginManager $manager)
    {
        $this->tableManager = $manager;
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
                    array('%%minlength%%' => $policy['minLength'])
                )
            );
        }
        if (isset($policy['maxLength'])
            && strlen($password) > $policy['maxLength']
        ) {
            throw new AuthException(
                $this->translate(
                    'password_maximum_length',
                    array('%%maxlength%%' => $policy['maxLength'])
                )
            );
        }
    }

    /**
     * Translate a string
     *
     * @param string $str    String to translate
     * @param array  $tokens Tokens to inject into the translated string
     *
     * @return string
     * @todo Use TranslatorAwareTrait instead when it's implemented
     */
    public function translate($str, $tokens = array())
    {
        $msg = $this->translator->translate($str);

        // Do we need to perform substitutions?
        if (!empty($tokens)) {
            $in = $out = array();
            foreach ($tokens as $key => $value) {
                $in[] = $key;
                $out[] = $value;
            }
            $msg = str_replace($in, $out, $msg);
        }

        return $msg;
    }
}
