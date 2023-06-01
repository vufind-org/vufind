<?php

/**
 * IpRegEx permission provider for VuFind.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Role\PermissionProvider;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Net\UserIpReader;

/**
 * IpRegEx permission provider for VuFind.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IpRegEx implements PermissionProviderInterface
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * User IP address reader
     *
     * @var UserIpReader
     */
    protected $userIpReader;

    /**
     * Constructor
     *
     * @param Request      $request      Request object
     * @param UserIpReader $userIpReader User IP address reader
     */
    public function __construct(Request $request, UserIpReader $userIpReader)
    {
        $this->request = $request;
        $this->userIpReader = $userIpReader;
    }

    /**
     * Return an array of roles which may be granted the permission based on
     * the options.
     *
     * @param mixed $options Options provided from configuration.
     *
     * @return array
     */
    public function getPermissions($options)
    {
        // Check if any regex matches....
        $ipAddr = $this->userIpReader->getUserIp();
        foreach ((array)$options as $current) {
            if (preg_match($current, $ipAddr)) {
                // Match? Grant to all users (guest or logged in).
                return ['guest', 'loggedin'];
            }
        }

        //  No match? No permissions.
        return [];
    }
}
