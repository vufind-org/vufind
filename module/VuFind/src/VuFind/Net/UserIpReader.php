<?php

/**
 * Service to retrieve user IP address.
 *
 * PHP version 8
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

use function count;

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
     * IP addresses to exclude from consideration
     *
     * @var array
     */
    protected $ipFilter;

    /**
     * Constructor
     *
     * @param Parameters  $server            Server parameters
     * @param string|bool $allowForwardedIps Forwarded header configuration string
     * (false to disable checking IP-related X- headers)
     * @param array       $ipFilter          IP addresses to exclude from
     * consideration
     */
    public function __construct(
        Parameters $server,
        $allowForwardedIps = false,
        array $ipFilter = []
    ) {
        $this->server = $server;
        $this->allowForwardedIps = $allowForwardedIps;
        $this->ipFilter = array_map('trim', $ipFilter);
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
                [$field, $behavior] = explode(':', $chunk . ':', 2);

                // Look up field value; skip if empty:
                $fieldValue = $this->server->get($field);
                if (empty($fieldValue)) {
                    continue;
                }

                // Split up the field value, if it is delimited, then filter it:
                $parts = array_diff(
                    array_map('trim', explode(',', $fieldValue)),
                    $this->ipFilter
                );

                // Apply the appropriate behavior (note that we trim any trailing
                // colon off the behavior, since we may have added one above to
                // prevent warnings in the explode operation):
                //
                // Also note that we need to use array_shift/array_pop/current here
                // in place of specific indexes, because the filtering above may have
                // left non-consecutive keys in place.
                $finalBehavior = strtolower(rtrim($behavior, ':'));
                $partCount = count($parts);
                if ($finalBehavior === 'first' && $partCount > 0) {
                    return array_shift($parts);
                } elseif ($finalBehavior === 'last' && $partCount > 0) {
                    return array_pop($parts);
                } elseif ($partCount === 1) {
                    return current($parts);
                }
            }
        }
        // Default case: use REMOTE_ADDR directly.
        return $this->server->get('REMOTE_ADDR');
    }
}
