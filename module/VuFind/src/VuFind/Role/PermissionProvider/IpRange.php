<?php

/**
 * IpRange permission provider for VuFind.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Role\PermissionProvider;

use Laminas\Stdlib\RequestInterface;
use VuFind\Net\IpAddressUtils;
use VuFind\Net\UserIpReader;

/**
 * IpRange permission provider for VuFind.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IpRange implements PermissionProviderInterface
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * IpAddressUtils object
     *
     * @var IpAddressUtils
     */
    protected $ipAddressUtils;

    /**
     * User IP address reader
     *
     * @var UserIpReader
     */
    protected $userIpReader;

    /**
     * Constructor
     *
     * @param RequestInterface $request      Request object
     * @param IpAddressUtils   $ipUtils      IpAddressUtils object
     * @param UserIpReader     $userIpReader User IP address reader
     */
    public function __construct(
        RequestInterface $request,
        IpAddressUtils $ipUtils,
        UserIpReader $userIpReader
    ) {
        $this->request = $request;
        $this->ipAddressUtils = $ipUtils;
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
        if ($this->ipAddressUtils->isInRange($ipAddr, (array)$options)) {
            // Match? Grant to all users (guest or logged in).
            return ['guest', 'loggedin'];
        }

        //  No match? No permissions.
        return [];
    }
}
