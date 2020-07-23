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
     * Configuration specifying allowed HTTP headers containing IPs (false for none).
     * See [Proxy] allow_forwarded_ips setting in config.ini for more details.
     *
     * @var string|bool
     */
    protected $allowForwardedIps;

    /**
     * Constructor
     *
     * @param Parameters $server            Server parameters
     * @param bool       $allowForwardedIps Forwarded header configuration string
     * (false to disable checking IP-related X- headers)
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
        if ($this->allowForwardedIps) {
            foreach (explode(',', $this->allowForwardedIps) as $chunk) {
                // Extract field and behavior from chunk:
                list($field, $behavior) = explode(':', $chunk . ':', 2);

                // Look up field value; skip if empty:
                $fieldValue = $this->server->get($field);
                if (empty($fieldValue)) {
                    continue;
                }

                // Split up the field value, if it is delimited:
                $parts = explode(',', $fieldValue);

                // Apply the appropriate behavior (note that we trim any trailing
                // colon off the behavior, since we may have added one above to
                // prevent warnings in the explode operation):
                switch (strtolower(rtrim($behavior, ':'))) {
                case 'first':
                    return trim($parts[0]);
                case 'last':
                    return trim(array_pop($parts));
                default:
                    if (count($parts) === 1) {
                        return trim($parts[0]);
                    }
                }
            }
        }
        // Default case: use REMOTE_ADDR directly.
        return $this->server->get('REMOTE_ADDR');
    }
}
