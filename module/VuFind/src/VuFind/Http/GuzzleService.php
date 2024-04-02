<?php

/**
 * Guzzle service.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Http;

/**
 * Guzzle service.
 *
 * N.B. Use only for dependencies that require Guzzle.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 * @todo     Merge with PSR-18 HTTP Client Service when implemented
 */
class GuzzleService
{
    /**
     * Default regular expression matching a request to localhost.
     *
     * @var string
     */
    public const LOCAL_ADDRESS_RE = '@^(localhost|127(\.\d+){3}|\[::1\])@';

    /**
     * VuFind configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Regular expression matching a request to localhost or hosts
     * that are not proxied.
     *
     * @see \Laminas\Http\Client\Adapter\Proxy::$config
     *
     * @var string
     */
    protected $localAddressesRegEx = self::LOCAL_ADDRESS_RE;

    /**
     * Mappings from VuFind HTTP settings to Guzzle
     *
     * @var array
     */
    protected $guzzleHttpSettingsMap = [
        'timeout' => 'timeout',
        'curloptions' => 'curl',
    ];

    /**
     * Constructor.
     *
     * @param array $config VuFind configuration
     *
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        if (isset($config['Proxy']['localAddressesRegEx'])) {
            $this->localAddressesRegEx = $config['Proxy']['localAddressesRegEx'];
        }
    }

    /**
     * Return a new Guzzle client.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     *
     * @return \GuzzleHttp\ClientInterface
     */
    public function createClient(?string $url = null, ?float $timeout = null): \GuzzleHttp\ClientInterface
    {
        return new \GuzzleHttp\Client($this->getGuzzleConfig($url, $timeout));
    }

    /**
     * Get Guzzle options
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds
     *
     * @return array
     */
    protected function getGuzzleConfig(?string $url, ?float $timeout): array
    {
        $guzzleConfig = $this->config['Http'] ?? [];

        // Map known one-to-one configuration settings to Guzzle settings:
        $guzzleConfig = array_combine(
            array_map(
                function ($key) {
                    return $this->guzzleHttpSettingsMap[$key] ?? $key;
                },
                array_keys($guzzleConfig)
            ),
            array_values($guzzleConfig)
        );

        // Override timeout if requested:
        if (null !== $timeout) {
            $guzzleConfig['timeout'] = $timeout;
        }

        // Handle maxredirects:
        if (isset($guzzleConfig['maxredirects'])) {
            $guzzleConfig['allow_redirects'] = [
                'max' => $guzzleConfig['maxredirects'],
                'strict' => $guzzleConfig['strictredirects'] ?? false,
                'referer' => false,
                'protocols' => ['http', 'https'],
                'track_redirects' => false,
            ];
            unset($guzzleConfig['maxredirects']);
            unset($guzzleConfig['strictredirects']);
        }

        // Handle useragent:
        if (isset($guzzleConfig['useragent'])) {
            $guzzleConfig['headers']['User-Agent'] = $guzzleConfig['useragent'];
            unset($guzzleConfig['useragent']);
        }

        // Handle sslcapath, sslcafile and sslverifypeer:
        if ($guzzleConfig['sslverifypeer'] ?? true) {
            if ($verify = $guzzleConfig['sslcafile'] ?? $guzzleConfig['sslcapath'] ?? null) {
                $guzzleConfig['verify'] = $verify;
            }
        } else {
            $guzzleConfig['verify'] = false;
        }
        unset($guzzleConfig['sslverifypeer']);
        unset($guzzleConfig['sslcapath']);
        unset($guzzleConfig['sslcafile']);

        // Handle proxy configuration:
        if (!$this->isLocal($url)) {
            $proxyConfig = $this->config['Proxy'] ?? [];
            if (!empty($proxyConfig['host'])) {
                $guzzleConfig['curl'][CURLOPT_PROXY] = $proxyConfig['host'];
            }
            if (!empty($proxyConfig['port'])) {
                $guzzleConfig['curl'][CURLOPT_PROXYPORT] = $proxyConfig['port'];
            }
            // HTTP is default, so handle only the SOCKS 5 proxy types
            switch ($proxyConfig['type'] ?? '') {
                case 'socks5':
                    $guzzleConfig['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                    break;
                case 'socks5_hostname':
                    $guzzleConfig['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
                    break;
            }
        }
        return $guzzleConfig;
    }

    /**
     * Check if given URL is a local address
     *
     * @param ?string $host Host to check
     *
     * @return bool
     */
    protected function isLocal(?string $host): bool
    {
        return $host && preg_match($this->localAddressesRegEx, $host);
    }
}
