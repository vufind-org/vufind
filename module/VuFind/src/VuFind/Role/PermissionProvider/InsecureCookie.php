<?php

/**
 * Insecure cookie permission provider for VuFind.
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

use VuFind\Cookie\CookieManager;

/**
 * Insecure cookie permission provider for VuFind.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Alex Buckley <alexbuckley@catalyst.net.nz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

class InsecureCookie implements PermissionProviderInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param CookieManager $cookieManager Cookie manager
     */
    public function __construct(
        protected CookieManager $cookieManager
    ) {
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
            if (!(isset($cookie))) {
                $this->debug('getPermissions: result = false');
                return [];
            }
            $this->debug('getPermissions: result = true');
        }
        return ['guest', 'loggedin'];
    }
}
