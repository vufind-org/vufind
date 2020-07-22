<?php
/**
 * Service to retrieve user IP address.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Net
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Net;

use Laminas\Stdlib\Parameters;

/**
 * Service to retrieve user IP address.
 *
 * @category VuFind
 * @package  Net
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserIpReader
{
    /**
     * Server parameters
     *
     * @var Parameters
     */
    protected $server;

    /**
     * Should we respect the X-Forwarded-For header?
     *
     * @var bool
     */
    protected $allowForwardedIps;

    /**
     * Constructor
     *
     * @param Parameters $server            Server parameters
     * @param bool       $allowForwardedIps Should we respect the X-Forwarded-For
     * header?
     */
    public function __construct(Parameters $server, $allowForwardedIps = false)
    {
        $this->server = $server;
        $this->allowForwardedIps = $allowForwardedIps;
    }

    /**
     * Get the active user's IP address. Returns null if no address can be found.
     *
     * @return string
     */
    public function getUserIp()
    {
        $forwardedIp = $this->allowForwardedIps
            ? $this->server->get('HTTP_X_FORWARDED_FOR') : null;
        return $forwardedIp ?? $this->server->get('REMOTE_ADDR');
    }
}
