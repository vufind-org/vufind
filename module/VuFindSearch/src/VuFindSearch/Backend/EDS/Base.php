<?php
/**
 * EBSCO Search API abstract base class
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
 * @link     http://edswiki.ebscohost.com/EDS_API_Documentation
 */
namespace VuFindSearch\Backend\EDS;

require_once dirname(__FILE__) . '/Exception.php';

/**
 * EBSCO Search API abstract base class
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://edswiki.ebscohost.com/EDS_API_Documentation
 */
abstract class EdsApi_REST_Base
{
    /**
     * A boolean value determining whether to print debug information
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * EDSAPI host
     *
     * @var string
     */
     protected $edsApiHost = 'http://eds-api.ebscohost.com/edsapi/rest';

    /**
     * Auth host
     *
     * @var string
     */
    protected $authHost = 'https://eds-api.ebscohost.com/authservice/rest';

    /**
     * The organization id use for authentication
     *
     * @var string
     */
    protected $orgId;

    /**
     * Accept header
     *
     * @var string
     */
    protected $accept  = 'application/json';

    /**
     * Content type header
     *
     * @var string
     */
    protected $contentType = 'application/json';

    /**
     * Constructor
     *
     * Sets up the EDS API Client
     *
     * @param array $settings Associative array of setting to use in
     *                         conjunction with the EDS API
     *    <ul>
     *      <li>debug - boolean to control debug mode</li>
     *      <li>orgid - Organization making calls to the EDS API </li>
     *      <li>profile - EBSCO profile to use for calls to the API. </li>
     *    </ul>
     */
    public function __construct($settings = [])
    {
        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                switch($key) {
                case 'debug':
                    $this->debug = $value;
                    break;
                case 'orgid':
                    $this->orgId = $value;
                    break;
                }
            }
        }
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
        if ($this->debug) {
            echo "<pre>{$msg}</pre>\n";
        }
    }

    /**
     * Obtain edsapi search critera and application related settings
     *
     * @param string $authenticationToken Authentication token
     * @param string $sessionToken        Session token
     *
     * @return array
     */
    public function info($authenticationToken = null, $sessionToken = null)
    {
        $this->debugPrint("Info");
        $url = $this->edsApiHost . '/info';
        $headers = $this->setTokens($authenticationToken, $sessionToken);
        return $this->call($url, $headers);
    }

    /**
     * Creates a new session
     *
     * @param string $profile   Profile to use
     * @param string $isGuest   Whether or not this sesssion will be a guest session
     * @param string $authToken Authentication token
     *
     * @return array
     */
    public function createSession($profile = null, $isGuest = null,
        $authToken = null
    ) {
        $this->debugPrint(
            'Create Session for profile: '
            . "$profile, guest: $isGuest, authToken: $authToken "
        );
        $qs = ['profile' => $profile, 'guest' => $isGuest];
        $url = $this->edsApiHost . '/createsession';
        $headers = $this->setTokens($authToken, null);
        return $this->call($url, $headers, $qs);
    }

    /**
     * Retrieves a record specified by its identifiers
     *
     * @param string $an                  An of the record to retrieve from the
     * EdsApi
     * @param string $dbId                Database identifier of the record to
     * retrieve from the EdsApi
     * @param string $authenticationToken Authentication token
     * @param string $sessionToken        Session token
     * @param string $highlightTerms      Comma separated list of terms to highlight
     * in the retrieved record responses
     *
     * @return array    The requested record
     */
    public function retrieve($an, $dbId, $authenticationToken, $sessionToken,
        $highlightTerms = null
    ) {
        $this->debugPrint(
            "Get Record. an: $an, dbid: $dbId, $highlightTerms: $highlightTerms"
        );
        $qs = ['an' => $an, 'dbid' => $dbId];
        if (null != $highlightTerms) {
            $qs['highlightterms'] = $highlightTerms;
        }
        $url = $this->edsApiHost . '/retrieve';
        $headers = $this->setTokens($authenticationToken, $sessionToken);
        return $this->call($url, $headers, $qs);

    }

    /**
     * Execute an EdsApi search
     *
     * @param SearchRequestModel $query               Search request object
     * @param string             $authenticationToken Authentication token
     * @param string             $sessionToken        Session token
     *
     * @return array An array of query results as returned from the api
     */
    public function search($query, $authenticationToken, $sessionToken)
    {
        // Query String Parameters
        $qs = $query->convertToQueryStringParameterArray();
        $this->debugPrint('Query: ' . print_r($qs, true));
        $url = $this->edsApiHost . '/search';
        $headers = $this->setTokens($authenticationToken, $sessionToken);
        return $this->call($url, $headers, $qs);
    }

    /**
     * Generate an authentication token with a valid EBSCO EDS Api account
     *
     * @param string $username username associated with an EBSCO EdsApi account
     * @param string $password password associated with an EBSCO EdsApi account
     * @param string $orgid    Organization id the request is initiated from
     *
     * @return array
     */
    public function authenticate($username = null, $password = null, $orgid = null)
    {
        $this->debugPrint(
            "Authenticating: username: $username, password: $password, orgid: $orgid"
        );
        $url = $this->authHost . '/uidauth';
        $org = isset($orgid) ? $orgid : $this->orgId;
        $authInfo = [];
        if (isset($username)) {
            $authInfo['UserId'] = $username;
        }
        if (isset($password)) {
            $authInfo['Password'] = $password;
        }
        if (isset($org)) {
            $authInfo['orgid'] = $org;
        }
        $messageBody = json_encode($authInfo);
        return $this->call($url, null,  null, 'POST', $messageBody);
    }

    /**
     * Convert an array of search parameters to EDS API querystring parameters
     *
     * @param array $params Parameters to convert to querystring parameters
     *
     * @return array
     */
    protected function createQSFromArray($params)
    {
        $queryParameters = [];
        if (null != $params && is_array($params)) {
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $parameterName = $key;
                    if (SearchRequestModel::isParameterIndexed($parameterName)) {
                        $parameterName = SearchRequestModel::getIndexedParameterName(
                            $parameterName
                        );
                    }
                    $cnt = 0;
                    foreach ($value as $subValue) {
                        $cnt = $cnt + 1;
                        $finalParameterName = $parameterName;
                        if (SearchRequestModel::isParameterIndexed($key)) {
                            $finalParameterName = $parameterName . '-' . $cnt;
                        }
                        $queryParameters[]
                            = $finalParameterName . '=' . urlencode($subValue);
                    }
                } else {
                    $queryParameters[] = $key . '=' . urlencode($value);
                }
            }
        }
        return $queryParameters;
    }

    /**
     * Submit REST Request
     *
     * @param string $baseUrl       URL of service
     * @param array  $headerParams  An array of headers to add to the request
     * @param array  $params        An array of parameters for the request
     * @param string $method        The HTTP Method to use
     * @param string $message       Message to POST if $method is POST
     * @param string $messageFormat Format of request $messageBody and responses
     *
     * @throws \EbscoEdsApiException
     * @return object         EDS API response (or an Error object).
     */
    protected function call($baseUrl, $headerParams, $params = [],
        $method = 'GET', $message = null, $messageFormat = ""
    ) {
        // Build Query String Parameters
        $queryParameters = $this->createQSFromArray($params);
        $queryString = '';
        if (null != $queryParameters && !empty($queryParameters)) {
            $queryString = implode('&', $queryParameters);
        }
        $this->debugPrint("Querystring to use: $queryString ");
        // Build headers
        $headers = [
            'Accept' => $this->accept,
            'Content-Type' => $this->contentType
        ];
        if (null != $headerParams && !empty($headerParams)) {
            foreach ($headerParams as $key => $value) {
                $headers[$key] = $value;
            }
        }
        $response = $this->httpRequest(
            $baseUrl, $method, $queryString, $headers, $message, $messageFormat
        );
        return $this->process($response);
    }

    /**
     * Process EDSAPI response message
     *
     * @param array $input The raw response from Summon
     *
     * @throws EbscoEdsApiException
     * @return array       The processed response from EDS API
     */
    protected function process($input)
    {
        //process response.
        try {
            $result = json_decode($input, true);
        } catch(Exception $e) {
            throw new EbscoEdsApiException(
                'An error occurred when processing EDS Api response: '
                . $e->getMessage()
            );
        }
        if (!isset($result)) {
            throw new EbscoEdsApiException('Unknown error processing response');
        }
        return $result;
    }

    /**
     * Populate an associative array of session and authentication parameters to
     * send to the EDS API
     *
     * @param string $authenticationToken Authentication token to add
     * @param string $sessionToken        Session token to add
     *
     * @return array Associative array of header parameters to add.
     */
    protected function setTokens($authenticationToken = null, $sessionToken = null)
    {
        $headers = [];
        if (!empty($authenticationToken)) {
            $headers['x-authenticationToken'] = $authenticationToken;
        }
        if (!empty($sessionToken)) {
            $headers['x-sessionToken'] = $sessionToken;
        }
        return $headers;
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $baseUrl       Base URL for request
     * @param string $method        HTTP method for request (GET, POST, etc.)
     * @param string $queryString   Query string to append to URL
     * @param array  $headers       HTTP headers to send
     * @param string $messageBody   Message body to for HTTP Request
     * @param string $messageFormat Format of request $messageBody and responses
     *
     * @return string             HTTP response body
     */
    abstract protected function httpRequest(
        $baseUrl, $method, $queryString, $headers, $messageBody, $messageFormat
    );
}
