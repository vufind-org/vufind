<?php

/**
 * Secure cookie permission provider.
 *
 * PHP version 8
 *
 * Copyright (C) Catalyst IT 2024.
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
 * @package  Authorization
 * @author   Alex Buckley <alexbuckley@catalyst.net.nz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

namespace VuFind\Role\PermissionProvider;

use Laminas\Session\Container;
use VuFind\Cookie\CookieManager;

/**
 * Secure cookie permission provider.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Alex Buckley <alexbuckley@catalyst.net.nz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

class SecureCookie implements PermissionProviderInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param CookieManager $cookieManager Cookie manager
     * @param $session       Session container
     */
    public function __construct(
        protected CookieManager $cookieManager,
        Container $session
    ) {
        $this->session = $session;
    }

    /**
     * Return an array of roles which may be granted the permission based on
     * the options.
     *
     * @param mixed $options Options (cookieNames) provided from configuration.
     *
     * @return array
     */
    public function getPermissions($options)
    {
        foreach ((array)$options as $cookieName) {
            $this->debug("getPermissions: option cookieName '{$cookieName}'");
            $cookie = $this->cookieManager->get($cookieName);
            $sessionValue = $this->session->$cookieName;
            if (!isset($cookie)) {
                $this->debug('getPermissions: result = false');
                return [];
            } elseif (!isset($sessionValue)) {
                $this->debug('getPermissions: result = false');
                return [];
            } elseif ($cookie != $sessionValue) {
                $this->debug('getPermissions: result = false');
                return [];
            }
            $this->debug('getPermissions: result = true');
        }
        return ['guest', 'loggedin'];
    }

    /**
     * Create a secret (uuid) - if not passed in $secret parameter.
     * Store the secret in the Session container and in the cookie.
     *
     * @param $cookieName - Name of cookie and session container variable to store the secret in.
     * @param $secret     - Set a specific secret value if desired.
     *
     * @return string
     */
    public function setSecret($cookieName, $secret)
    {
        // Create a secret - if not defined in $secret parameter
        if (!isset($secret)) {
            $secret = null;
            $secret = uniqid();
        }

        // Store secret in cookie
        $this->cookieManager->set($cookieName, $secret);

        // Store secret in session container
        $this->session->$cookieName = $secret;

        return $this->session->$cookieName;
    }
}
