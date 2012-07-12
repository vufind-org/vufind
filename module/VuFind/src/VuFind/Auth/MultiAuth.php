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
 * @author   Sam Moffatt <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * MultiAuth Authentication plugin
 *
 * This module enables chaining of multiple authentication plugins.  Authentication
 * plugins are executed in order, and the first successful authentication is
 * returned with the rest ignored.  The last error message is used to be returned
 * to the calling function.
 *
 * The plugin works by being defined as the authentication handler for the system
 * and then defining its own order for plugins.  For example, you could edit
 * config.ini like this:
 *
 * [Authentication]
 * method = MultiAuth
 *
 * [MultiAuth]
 * method_order = "ILS,LDAP"
 * filters = "username:strtoupper,username:trim,password:trim"
 *
 * This example uses a combination of ILS and LDAP authentication, checking the ILS
 * first and then failing over to LDAP.
 *
 * The filters follow the format fieldname:PHP string function, where fieldname is
 * either "username" or "password."  In the example, we uppercase the username and
 * trim the username and password fields. This is done to enable common filtering
 * before handing off to the authentication handlers.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Sam Moffatt <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class MultiAuth extends AbstractBase
{
    protected $filters = array();
    protected $methods;
    protected $username;
    protected $password;

    /**
     * Constructor
     *
     * @param object $config Optional configuration object to pass through (loads
     * default configuration if none specified).
     */
    public function __construct($config = null)
    {
        parent::__construct($config);

        if (!isset($config->MultiAuth) || !isset($config->MultiAuth->method_order)
            || !strlen($config->MultiAuth->method_order)
        ) {
            throw new AuthException(
                "One or more MultiAuth parameters are missing. " .
                "Check your config.ini!"
            );
        }
        $this->methods = explode(',', $config->MultiAuth->method_order);
        if (isset($config->MultiAuth->filters)
            && strlen($config->MultiAuth->filters)
        ) {
            $this->filters = explode(',', $config->MultiAuth->filters);
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
        $this->filterCredentials($request);

        // Check for empty credentials before we do any extra work:
        if ($this->username == '' || $this->password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Update request with our filtered credentials:
        $request->getPost()->set('username', $this->username);
        $request->getPost()->set('password', $this->password);

        // Do the actual authentication work:
        return $this->authUser($request);
    }

    /**
     * Load credentials into the object and apply internal filter settings to them.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @return void
     */
    protected function filterCredentials($request)
    {
        $this->username = $request->getPost()->get('username');
        $this->password = $request->getPost()->get('password');

        foreach ($this->filters as $filter) {
            $parts = explode(':', $filter);
            $property = trim($parts[0]);
            if (isset($this->$property)) {
                $this->$property = call_user_func(trim($parts[1]), $this->$property);
            }
        }
    }

    /**
     * Do the actual work of authenticating the user (support method for
     * authenticate()).
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    protected function authUser($request)
    {
        // Try authentication methods until we find one that works:
        foreach ($this->methods as $method) {
            $authenticator = Factory::getAuth(trim($method), $this->config);
            try {
                $user = $authenticator->authenticate($request);

                // If we got this far without throwing an exception, we can break
                // out of the loop -- we are logged in!
                break;
            } catch (AuthException $exception) {
                // Do nothing -- just keep looping!  We'll deal with the exception
                // below if we don't find a successful login anywhere.
            }
        }

        // At this point, there are three possibilities: $user is a valid,
        // logged-in user; $exception is an Exception that we need to forward
        // along; or both variables are undefined, indicating that $this->methods
        // is empty and thus something is wrong!
        if (!isset($user)) {
            if (isset($exception)) {
                throw $exception;
            } else {
                throw new AuthException('authentication_error_technical');
            }
        }
        return $user;
    }
}