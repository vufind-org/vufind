<?php
/**
 * Trait for checking external content url validity
 *
 * Dependencies:
 * - Main configuration available via getConfig method
 * - LoggerAwareTrait
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
namespace Finna\RecordDriver;

/**
 * Trait for checking external content url validity
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
trait UrlCheckTrait
{
    /**
     * A simple runtime cache for results to avoid multiple url lookups during the
     * same script execution.
     *
     * @var array
     */
    protected static $urlCheckResultCache = [];

    /**
     * A simple runtime cache for results to avoid multiple host lookups during the
     * same script execution.
     *
     * @var array
     */
    protected static $hostCheckResultCache = [];

    /**
     * Check if the given URL is loadable according to configured rules
     *
     * @param string $url URL
     * @param string $id  Record ID (for logging)
     *
     * @return bool
     */
    protected function isUrlLoadable(string $url, string $id): bool
    {
        // Easy checks first
        if (empty($url)) {
            return false;
        }

        if (isset(self::$urlCheckResultCache[$url])) {
            return self::$urlCheckResultCache[$url];
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return self::$urlCheckResultCache[$url] = false;
        }

        $config = $this->getConfig();

        $allowedMode = $config->Record->allowed_external_hosts_mode ?? 'enforce';
        if ('disable' === $allowedMode) {
            $allowedList = [];
        } else {
            $allowedList = isset($config->Record->allowed_external_hosts)
                ? $config->Record->allowed_external_hosts->toArray() : [];
        }
        $disallowedMode
            = $config->Record->disallowed_external_hosts_mode ?? 'enforce';
        if ('disable' === $disallowedMode) {
            $disallowedList = [];
        } else {
            $disallowedList = isset($config->Record->disallowed_external_hosts)
                ? $config->Record->disallowed_external_hosts->toArray() : [];
        }

        // Return if nothing to check
        if (!$allowedList && !$disallowedList) {
            return self::$urlCheckResultCache[$url] = true;
        }

        $host = mb_strtolower(parse_url($url, PHP_URL_HOST), 'UTF-8');
        if (!$id) {
            $id = 'n/a';
        }

        if (!isset(self::$hostCheckResultCache[$host])) {
            $result = $this->checkHostAllowedByFilters(
                $id,
                $url,
                $host,
                $disallowedList,
                $disallowedMode,
                $allowedList,
                $allowedMode
            );

            if ($result) {
                // Check IPv4 address against list of disallowed hosts
                $ipv4 = $this->getIPv4Address($host);
                if ($ipv4 && $ipv4 !== $host) {
                    $result = $this->checkHostAllowedByFilters(
                        $id,
                        $url,
                        $ipv4,
                        $disallowedList,
                        $disallowedMode
                    );
                }
            }

            if ($result) {
                // Check IPv6 address against list of disallowed hosts
                $ipv6 = $this->getIPv6Address($host);
                if ($ipv6 && $ipv6 !== $host) {
                    $result = $this->checkHostAllowedByFilters(
                        $id,
                        $url,
                        $ipv6,
                        $disallowedList,
                        $disallowedMode
                    );
                }
            }

            self::$hostCheckResultCache[$host] = $result;
        } else {
            $result = self::$hostCheckResultCache[$host];
        }

        return self::$urlCheckResultCache[$url] = $result;
    }

    /**
     * Check if the given host is allowed by the given filters
     *
     * @param string $id             Record ID
     * @param string $url            Full URL
     * @param string $host           Host
     * @param array  $disallowedList List of disallowed hosts
     * @param string $disallowedMode Disallowed list handling mode
     * @param array  $allowedList    List of allowed hosts
     * @param string $allowedMode    Allowed list handling mode
     *
     * @return bool
     */
    protected function checkHostAllowedByFilters(string $id, string $url,
        string $host, array $disallowedList, string $disallowedMode,
        array $allowedList = [], string $allowedMode = 'disable'
    ): bool {
        // Check disallowed hosts first (probably a short list)
        if ($disallowedList && $this->checkHostFilterMatch($host, $disallowedList)) {
            if ('report' === $disallowedMode) {
                $this->logWarning("URL check: $url would be blocked (record $id)");
            } elseif ('enforce-report' === $disallowedMode) {
                $this->logWarning("URL check: $url blocked (record $id)");
            }
            if (in_array($disallowedMode, ['enforce', 'enforce-report'])) {
                return false;
            }
        }

        // Check allowed list
        if ($allowedList && !$this->checkHostFilterMatch($host, $allowedList)) {
            if ('report' === $allowedMode) {
                $this->logWarning(
                    "URL check: $url would not be allowed (record $id)"
                );
            } elseif ('enforce-report' === $allowedMode) {
                $this->logWarning("URL check: $url not allowed (record $id)");
            }
            if (in_array($allowedMode, ['enforce', 'enforce-report'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the host name matches a filter
     *
     * @param string $host       Lower-cased host name
     * @param array  $filterList Filters
     *
     * @return bool
     */
    protected function checkHostFilterMatch(string $host, array $filterList): bool
    {
        foreach ($filterList as $filter) {
            if (strncmp('/', $filter, 1) === 0 && substr($filter, -1) === '/') {
                // Regular expression
                $match = preg_match($filter, $host);
            } else {
                $match = $filter === $host;
            }
            if ($match) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the IPv4 address for a host
     *
     * @param string $host Host
     *
     * @return string
     */
    protected function getIPv4Address(string $host): string
    {
        return gethostbyname($host);
    }

    /**
     * Get the IPv6 address for a host
     *
     * @param string $host Host
     *
     * @return string
     */
    protected function getIPv6Address(string $host): string
    {
        foreach (dns_get_record($host, DNS_AAAA) as $dnsRec) {
            $ipv6 = $dnsRec['ipv6'] ?? '';
            if ($ipv6) {
                return $ipv6;
            }
        }

        return '';
    }
}
