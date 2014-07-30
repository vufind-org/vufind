<?php
/**
 * Summon Search API Interface (Zend Framework 2 implementation)
 *
 * PHP version 5
 *
 * Copyright (C) Serials Solutions 2011.
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
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
namespace SerialsSolutions\Summon;
use SerialsSolutions_Summon_Exception, Zend\Http\Client as HttpClient,
    Zend\Log\LoggerAwareInterface, Zend\Log\LoggerInterface;

/**
 * Summon Search API Interface (Zend Framework 2 implementation)
 *
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class Zend2 extends \SerialsSolutions_Summon_Base implements LoggerAwareInterface
{
    /**
     * The HTTP_Request object used for API transactions
     * @var HttpClient
     */
    protected $client;

    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Constructor
     *
     * Sets up the Summon API Client
     *
     * @param string     $apiId   Summon API ID
     * @param string     $apiKey  Summon API Key
     * @param array      $options Associative array of additional options;
     * legal keys:
     *    <ul>
     *      <li>authedUser - is the end-user authenticated?</li>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>host - base URL of Summon API</li>
     *      <li>sessionId - Summon session ID to apply</li>
     *      <li>version - API version to use</li>
     *      <li>responseType - Acceptable response (json or xml)</li>
     *    </ul>
     * @param HttpClient $client  HTTP client object (optional)
     */
    public function __construct($apiId, $apiKey, $options = array(), $client = null)
    {
        parent::__construct($apiId, $apiKey, $options);
        $this->client = is_object($client) ? $client : new HttpClient();
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        $this->debugPrint(
            "{$method}: {$baseUrl}?{$queryString}"
        );

        $this->client->resetParameters();
        if ($method == 'GET') {
            $baseUrl .= '?' . $queryString;
        } elseif ($method == 'POST') {
            $this->client->setRawBody(
                $queryString, 'application/x-www-form-urlencoded'
            );
        }

        $this->client->setHeaders($headers);

        // Send Request
        $this->client->setUri($baseUrl);
        $result = $this->client->setMethod($method)->send();
        if (!$result->isSuccess()) {
            throw new SerialsSolutions_Summon_Exception($result->getBody());
        }
        return $result->getBody();
    }
}
