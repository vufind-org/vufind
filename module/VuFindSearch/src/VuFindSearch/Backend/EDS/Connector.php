<?php
/**
 * EBSCO EDS API Connector
 *
 * PHP version 7
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\EDS;

use Laminas\Http\Client\Adapter\Curl as CurlAdapter;
use Laminas\Http\Client as HttpClient;
use Laminas\Log\LoggerAwareInterface;

/**
 * EBSCO EDS API Connector
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector extends Base implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * The HTTP Request object to execute EDS API transactions
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * Constructor
     *
     * Sets up the EDS API Client
     *
     * @param array      $settings Associative array of setting to use in
     * conjunction with the EDS API
     *    <ul>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>orgid - Organization making calls to the EDS API</li>
     *      <li>timeout - HTTP timeout value (default = 120)</li>
     *    </ul>
     * @param HttpClient $client   HTTP client object (optional)
     */
    public function __construct($settings = [], $client = null)
    {
        parent::__construct($settings);
        if ($client) {
            $this->client = $client;
        } else {
            $this->client = new HttpClient();
            $this->client->setAdapter(new CurlAdapter());
        }
        $this->client->setOptions(
            [
                'timeout' => $settings['timeout'] ?? 120,
                'sslverifypeer' => false,
            ]
        );
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $baseUrl       Base URL for request
     * @param string $method        HTTP method for request (GET,POST, etc.)
     * @param string $queryString   Query string to append to URL
     * @param array  $headers       HTTP headers to send
     * @param string $messageBody   Message body to for HTTP Request
     * @param string $messageFormat Format of request $messageBody and respones
     *
     * @throws ApiException
     * @return string               HTTP response body
     */
    protected function httpRequest(
        $baseUrl,
        $method,
        $queryString,
        $headers,
        $messageBody = null,
        $messageFormat = "application/json; charset=utf-8"
    ) {
        $this->debugPrint("{$method}: {$baseUrl}?{$queryString}");

        $this->client->resetParameters();

        $this->client->setHeaders($headers);
        $this->client->setMethod($method);

        if ($method == 'GET' && !empty($queryString)) {
            $baseUrl .= '?' . $queryString;
        } elseif ($method == 'POST' && isset($messageBody)) {
            $this->client->setRawBody($messageBody);
        }
        $this->client->setUri($baseUrl);
        $this->client->setEncType($messageFormat);
        $result = $this->client->send();
        if (!$result->isSuccess()) {
            $error = $result->getBody();
            $decodedError = json_decode($error, true);
            throw new ApiException($decodedError ? $decodedError : $error);
        }
        return $result->getBody();
    }

    /**
     * Print a message if debug is enabled.
     *
     * @param string $msg Message to print
     *
     * @return void
     */
    protected function debugPrint($msg)
    {
        if ($this->logger) {
            $this->logger->debug("$msg\n");
        } else {
            parent::debugPrint($msg);
        }
    }
}
