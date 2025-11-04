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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

use function array_is_list;
use function strlen;

/**
 * Guzzle service.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 * @todo     Merge with PSR-18 HTTP Client Service when implemented
 */
class GuzzleService implements HttpServiceInterface
{
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
     * Return a generic PSR-compliant client.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     *
     * @return ClientInterface
     */
    public function createClient(?string $url = null, ?float $timeout = null): ClientInterface
    {
        return $this->createGuzzleClient($url, $timeout);
    }

    /**
     * Return a new Guzzle client.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     *
     * @return \GuzzleHttp\ClientInterface
     */
    public function createGuzzleClient(?string $url = null, ?float $timeout = null): \GuzzleHttp\ClientInterface
    {
        return new \GuzzleHttp\Client($this->getGuzzleConfig($url, $timeout));
    }

    /**
     * Perform a GET request.
     *
     * @param string $url     Request URL
     * @param array  $params  Request parameters (query string)
     * @param float  $timeout Request timeout in seconds
     * @param array  $headers Request HTTP headers
     *
     * @return ResponseInterface
     */
    public function get(
        string $url,
        array $params = [],
        ?float $timeout = null,
        array $headers = []
    ): ResponseInterface {
        if ($params) {
            $query = $this->createQueryString($params);
            $url .= (str_contains($url, '?') ? '&' : '?') . $query;
        }
        $client = $this->createGuzzleClient($url, $timeout);
        $options = $headers ? compact('headers') : [];
        return $client->request('GET', $url, $options);
    }

    /**
     * Perform a POST request.
     *
     * @param string  $url     Request URL
     * @param ?string $body    Request body document
     * @param string  $type    Request body content type
     * @param float   $timeout Request timeout in seconds
     * @param array   $headers Request HTTP headers
     *
     * @return ResponseInterface
     */
    public function post(
        string $url,
        ?string $body = null,
        string $type = 'application/octet-stream',
        ?float $timeout = null,
        array $headers = []
    ): ResponseInterface {
        $client = $this->createGuzzleClient($url, $timeout);

        $options = [
            'body' => $body,
            'headers' => array_merge(
                [
                    'Content-Type' => $type,
                    'Content-Length' => strlen($body ?? ''),
                ],
                $headers
            ),
        ];

        return $client->request('POST', $url, $options);
    }

    /**
     * Create a query string from an array of parameters.
     *
     * @param array $params Parameters (either an associative key=>value array,
     * or a regular array of preformatted key=value strings)
     *
     * @return string
     */
    protected function createQueryString(array $params = []): string
    {
        return !array_is_list($params)
            ? http_build_query($params)
            : implode('&', $params);
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
        $guzzleConfig['http_errors'] = false;

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
     * @param ?string $url URL to check
     *
     * @return bool
     */
    protected function isLocal(?string $url): bool
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;
        return $host && preg_match($this->localAddressesRegEx, $host);
    }
}
