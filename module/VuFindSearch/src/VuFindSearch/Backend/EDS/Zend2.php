<?php
/**
 * EBSCO EDS API Zend2 Framework implementation
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\EDS;

require_once dirname(__FILE__) . '/Base.php';
use Zend\Http\Client as Zend2HttpClient;
use Zend\Log\LoggerAwareInterface;
use Zend\Http\Client\Adapter\Curl as CurlAdapter;

/**
 * EBSCO EDS API Zend2 Framework implementation
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Zend2 extends EdsApi_REST_Base implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

     /**
     * The HTTP Request object to execute EDS API transactions
     * @var Zend2HttpClient
     */
    protected $client;

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

    /**
     * Constructor
     *
     * Sets up the EDS API Client
     *
     * @param array           $settings Associative array of setting to use in
     * conjunction with the EDS API
     *    <ul>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>authtoken - Authentication to use for calls to the API. If using IP
     * Authentication, this is not needed.</li>
     *      <li>username -  EBSCO username for account setup for usage with the EDS
     * API. This is only required for institutions using UID Authentication </li>
     *      <li>password - EBSCO password for account setup for usage with the EDS
     * API. This is only required for institutions using UID Authentication </li>
     *      <li>orgid - Organization making calls to the EDS API </li>
     *      <li>sessiontoken - SessionToken this call is associated with, is one
     * exists. If not, the a profile value must be present </li>
     *      <li>profile - EBSCO profile to use for calls to the API. </li>
     *      <li>isguest - is the user a guest. This needs to be present if there
     * is no session token present</li>
     *    </ul>
     * @param Zend2HttpClient $client   Zend2 HTTP client object (optional)
     */
    public function __construct($settings = [], $client = null)
    {
        parent::__construct($settings);
        $this->client = is_object($client) ? $client : new Zend2HttpClient();
        $this->client->setOptions(['timeout' => 120]);
        $adapter = new CurlAdapter();
        $adapter->setOptions(
            [
                'curloptions' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                ]
            ]
        );
        $this->client->setAdapter($adapter);
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
     * @throws EbscoEdsApiException
     * @return string               HTTP response body
     */
    protected function httpRequest($baseUrl, $method, $queryString, $headers,
        $messageBody = null, $messageFormat = "application/json; charset=utf-8"
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
            throw new \EbscoEdsApiException(json_decode($result->getBody(), true));
        }
        return $result->getBody();
    }
}