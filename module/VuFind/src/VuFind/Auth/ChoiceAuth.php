<?php
/**
 * MultiAuth Authentication plugin
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
 * @author   Anna Headley <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * ChoiceAuth Authentication plugin
 *
 * This module enables a user to choose between two authentication methods. 
 * choices are presented side-by-side and one is manually selected.
 *
 * See config.ini for more details
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Anna Headley <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
class ChoiceAuth extends AbstractBase
{
    /**
     * Authentication strategies to present
     *
     * @var array
     */
    protected $strategies = array();

    /** 
     * Auth strategy selected by user
     *
     * @var string
     */
    protected $strategy;

    /**
     * Username input
     *
     * @var string
     */
    protected $username;

    /**
     * Password input
     *
     * @var string
     */
    protected $password;

    /**
     * Plugin manager for obtaining other authentication objects
     *
     * @var PluginManager
     */
    protected $manager;

    /**
     * Session container
     *
     * @var \Zend\Session\Container
     */
    protected $session;

    /**
     * Constructor
     */
    public function __construct() 
    {
        // Set up session container and load cached strategy (if found):
        $this->session = new \Zend\Session\Container('ChoiceAuth');
        $this->strategy = isset($this->session->auth_method)
            ? $this->session->auth_method : false;
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
        if (!isset($this->config->ChoiceAuth)
            || !isset($this->config->ChoiceAuth->choice_order)
            || !strlen($this->config->ChoiceAuth->choice_order)
        ) {
            throw new AuthException(
                "One or more ChoiceAuth parameters are missing. " .
                "Check your config.ini!"
            );
        }
    }

    /**
     * Set configuration; throw an exception if it is invalid.
     *
     * @param \Zend\Config\Config $config Configuration to set
     *
     * @throws AuthException
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        $this->strategies = array_map(
            'trim', explode(',', $config->ChoiceAuth->choice_order)
        );
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
        $this->username = trim($request->getPost()->get('username'));
        $this->password = trim($request->getPost()->get('password'));

        // Set new strategy; fall back to old one if there is a problem:
        $defaultStrategy = $this->strategy;
        $this->strategy = trim($request->getPost()->get('auth_method'));
        if ($this->strategy == '') {
            $this->strategy = $defaultStrategy;
            if (empty($this->strategy)) {
                throw new AuthException('authentication_error_technical');
            }
        }

        // Do the actual authentication work:
        $user = $this->proxyAuthMethod('authenticate', func_get_args());
        if ($user) {
            $this->session->auth_method = $this->strategy;
        }
        return $user;
    }

    /**
     * Set the manager for loading other authentication plugins.
     *
     * @param PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setPluginManager(PluginManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Get the manager for loading other authentication plugins.
     *
     * @throws \Exception
     * @return PluginManager
     */
    public function getPluginManager()
    {
        if (null === $this->manager) {
            throw new \Exception('Plugin manager missing.');
        }
        return $this->manager;
    }

    /**
     * Return an array of authentication options allowed by this class.
     *
     * @return array
     */
    public function getSelectableAuthOptions()
    {
        return $this->strategies;
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
        // clear user's login choice, if necessary:
        if (isset($this->session->auth_method)) {
            unset($this->session->auth_method);
        }

        // If we have a selected strategy, proxy the appropriate class; otherwise,
        // perform default behavior of returning unmodified URL:
        return $this->strategy
            ? $this->proxyAuthMethod('logout', func_get_args()) : $url;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication strategy should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        return $this->proxyAuthMethod('getSessionInitiator', func_get_args());
    }

    /**
     * Proxy auth method; a helper function to be called like:
     *   return $this->proxyAuthMethod(METHOD, func_get_args());
     *
     * @param string $method the method to proxy
     * @param array  $params array of params to pass
     *
     * @throws AuthException
     * @return mixed
     */
    protected function proxyAuthMethod($method, $params)
    {
        // If no strategy is found, we can't do anything -- return false.
        if (!$this->strategy) {
            return false;
        }

        $manager = $this->getPluginManager();
        $authenticator = $manager->get($this->strategy);
        $authenticator->setConfig($this->getConfig());
        if (!is_callable(array($authenticator, $method))) {
            throw new AuthException($this->strategy . "has no method $method");
        }
        return call_user_func_array(array($authenticator, $method), $params);
    }

}
