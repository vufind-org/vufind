<?php

/**
 * Session key permission provider.
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

/**
 * Session key permission provider.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Alex Buckley <alexbuckley@catalyst.net.nz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

class SessionKey implements PermissionProviderInterface
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
     * @param $session Session container
     */
    public function __construct(
        Container $session
    ) {
        $this->session = $session;
    }

    /**
     * Return an array of roles which may be granted the permission based on
     * the options.
     *
     * @param mixed $options Options (sessionKeys) provided from configuration.
     *
     * @return array
     */
    public function getPermissions($options)
    {
        foreach ((array)$options as $sessionKey) {
            $this->debug("getPermissions: option sessionKey '{$sessionKey}'");
            $sessionValue = $this->session->$sessionKey;
            if (!isset($sessionValue)) {
                $this->debug('getPermissions: result = false');
                return [];
            } elseif ($sessionValue === false) {
                $this->debug('getPermissions: result = false');
                return [];
            }
            $this->debug('getPermissions: result = true');
        }
        return ['guest', 'loggedin'];
    }

    /**
     * Set a boolean true value for a key in the Session container.
     *
     * @param $sessionKey - Set a boolean true value for this session key.
     *
     * @return string
     */
    public function setSessionValue($sessionKey)
    {
        // Set a sessionKey - if not defined in $sessionKey parameter
        if (!isset($sessionKey)) {
            $sessionKey = null;
            $sessionKey = '';
        }

        // Store boolean true value for the sessionKey
        $this->session->$sessionKey = true;

        return $this->session->$sessionKey;
    }
}
