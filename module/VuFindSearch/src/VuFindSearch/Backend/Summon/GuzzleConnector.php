<?php

/**
 * Summon Search API Interface (Guzzle and Psr implementation)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Search
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindSearch\Backend\Summon;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use SerialsSolutions_Summon_Exception;

/**
 * Guzzle and PSR-compliant port of SerialsSolutions\Summon\Laminas connector
 *
 * @category VuFind
 * @package  Search
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GuzzleConnector extends \SerialsSolutions_Summon_Base implements LoggerAwareInterface
{
    /**
     * HTTP client instance
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * Logger instance.
     *
     * @var ?LoggerInterface
     */
    protected $logger = null;

    /**
     * Constructor.
     *
     * @param string     $apiId   Summon API ID
     * @param string     $apiKey  Summon API Key
     * @param array      $options Options for the parent constructor
     * @param HttpClient $client  Optional HTTP client to use
     */
    public function __construct(string $apiId, string $apiKey, array $options = [], ?HttpClient $client = null)
    {
        parent::__construct($apiId, $apiKey, $options);
        $this->client = $client ?? new HttpClient();
    }

    /**
     * Sets the logger instance.
     *
     * @param LoggerInterface $logger The logger instance to set.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Prints a debug message if debug is enabled.
     *
     * @param string $msg The message to debug.
     *
     * @return void
     */
    protected function debugPrint($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        } else {
            parent::debugPrint($msg);
        }
    }

    /**
     * Handle a fatal error.
     *
     * @param SerialsSolutions_Summon_Exception $e Exception to process.
     *
     * @return void
     */
    public function handleFatalError($e)
    {
        throw $e;
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $baseUrl     Base URL for request
     * @param string $method      HTTP method for request
     * @param string $queryString Query string to append to URL
     * @param array  $headers     HTTP headers to send
     *
     * @throws SerialsSolutions_Summon_Exception
     * @return string             HTTP response body
     */
    protected function httpRequest($baseUrl, $method, $queryString, $headers)
    {
        $this->debugPrint("{$method}: {$baseUrl}?{$queryString}");

        $options = ['headers' => $headers];

        if ($method == 'GET') {
            $baseUrl .= '?' . $queryString;
        } elseif ($method == 'POST') {
            $options['body'] = $queryString;
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $result = $this->client->request($method, $baseUrl, $options);

        if ($result->getStatusCode() < 200 || $result->getStatusCode() >= 300) {
            throw new SerialsSolutions_Summon_Exception($result->getBody()->getContents());
        }

        return $result->getBody()->getContents();
    }
}
