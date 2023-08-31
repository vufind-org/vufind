<?php

/**
 * IP address utility functions.
 *
 * PHP version 8
 *
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
 * @package  Net
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Net;

use function count;
use function defined;

/**
 * IP address utility functions.
 *
 * @category VuFind
 * @package  Net
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IpAddressUtils
{
    /**
     * Normalize an IP address or a beginning of it to an IPv6 address
     *
     * @param string $ip  IP Address
     * @param bool   $end Whether to make a partial address  an "end of range"
     * address
     *
     * @return string|false Packed in_addr representation if successful, false
     * for invalid IP address
     */
    public function normalizeIp($ip, $end = false)
    {
        // The check for AF_INET6 allows fallback to IPv4 only if necessary.
        // Hopefully that's not necessary.
        if (!str_contains($ip, ':') || !defined('AF_INET6')) {
            // IPv4 address

            // Append parts until complete
            $addr = explode('.', $ip);
            for ($i = count($addr); $i < 4; $i++) {
                $addr[] = $end ? 255 : 0;
            }

            // Get rid of leading zeros etc.
            $ip = implode('.', array_map('intval', $addr));
            if (!defined('AF_INET6')) {
                return inet_pton($ip);
            }
            $ip = "::$ip";
        } else {
            // IPv6 address

            // Expand :: with '0:' as many times as necessary for a complete address
            $count = substr_count($ip, ':');
            if ($count < 8) {
                $ip = str_replace(
                    '::',
                    ':' . str_repeat('0:', 8 - $count),
                    $ip
                );
            }
            if ($ip[0] == ':') {
                $ip = "0$ip";
            }
            // Append ':0' or ':ffff' to complete the address
            $count = substr_count($ip, ':');
            if ($count < 7) {
                $ip .= str_repeat($end ? ':ffff' : ':0', 7 - $count);
            }
        }
        return inet_pton($ip);
    }

    /**
     * Check if an IP address is in a range. Works also with mixed IPv4 and IPv6
     * addresses.
     *
     * @param string $ip     IP address to check
     * @param array  $ranges An array of IP addresses or address ranges to check
     *
     * @return bool
     */
    public function isInRange($ip, $ranges)
    {
        $ip = $this->normalizeIp($ip);
        foreach ($ranges as $range) {
            $ips = explode('-', $range, 2);
            if (!isset($ips[1])) {
                $ips[1] = $ips[0];
            }
            $ips[0] = $this->normalizeIp($ips[0]);
            $ips[1] = $this->normalizeIp($ips[1], true);
            if ($ips[0] === false || $ips[1] === false) {
                continue;
            }
            if ($ip >= $ips[0] && $ip <= $ips[1]) {
                return true;
            }
        }
        return false;
    }
}
