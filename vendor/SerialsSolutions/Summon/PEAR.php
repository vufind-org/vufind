<?php
/**
 * Summon Search API Interface (PEAR implementation)
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
require_once 'PEAR.php';
require_once 'HTTP/Request.php';
require_once 'SerialsSolutions/Summon/Base.php';

/**
 * Summon Search API Interface (PEAR implementation)
 *
 * @category SerialsSolutions
 * @package  Summon
 * @author   Andrew Nagy <andrew.nagy@serialssolutions.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://api.summon.serialssolutions.com/help/api/ API Documentation
 */
class SerialsSolutions_Summon_PEAR extends SerialsSolutions_Summon_Base
{
    /**
     * The HTTP_Request object used for API transactions
     * @var object HTTP_Request
     */
    protected $client;

    /**
     * Constructor
     *
     * Sets up the Summon API Client
     *
     * @param string $apiId   Summon API ID
     * @param string $apiKey  Summon API Key
     * @param array  $options Associative array of additional options; legal keys:
     *    <ul>
     *      <li>authedUser - is the end-user authenticated?</li>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>host - base URL of Summon API</li>
     *      <li>sessionId - Summon session ID to apply</li>
     *      <li>version - API version to use</li>
     *    </ul>
     */
    public function __construct($apiId, $apiKey, $options = array())
    {
        parent::__construct($apiId, $apiKey, $options);
        $this->client = new HTTP_Request(null, array('useBrackets' => false));
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
        PEAR::raiseError(new PEAR_Error($e->getMessage()));
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

        // Set up request
        $this->client->setURL($baseUrl);
        $this->client->setMethod($method);
        $this->client->addRawQueryString($queryString);
        foreach ($headers as $key => $value) {
            $this->client->addHeader($key, $value);
        }

        // Send Request
        $result = $this->client->sendRequest();
        if (PEAR::isError($result)) {
            throw new SerialsSolutions_Summon_Exception($result->getMessage());
        }
        return $this->client->getResponseBody();
    }
}
