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
use VuFind\Exception\Auth as AuthException;

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
abstract class AbstractBase implements \VuFind\Db\Table\DbTableAwareInterface
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
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    abstract public function authenticate($request);

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
     * @return \VuFind\Db\Row\User New user row.
     */
    public function create($request)
    {
        throw new AuthException(
            'Account creation not supported by ' . get_class($this)
        );
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user to after login (some drivers may override this).
     *
     * @return bool|string
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
}