<?php

/**
 * EBSCO Search API abstract base class
 *
 * PHP version 8
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
 * @link     http://edswiki.ebscohost.com/EDS_API_Documentation
 */

namespace VuFindSearch\Backend\EDS;

use Laminas\Log\LoggerAwareInterface;

use function is_array;

/**
 * EBSCO Search API abstract base class
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://edswiki.ebscohost.com/EDS_API_Documentation
 */
abstract class Base implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * EDS or EPF API host.
     *
     * @var string
     */
    protected $apiHost;

    /**
     * Auth host
     *
     * @var string
     */
    protected $authHost = 'https://eds-api.ebscohost.com/authservice/rest';

    /**
     * Session host.
     *
     * @var string
     */
    protected $sessionHost = 'https://eds-api.ebscohost.com/edsapi/rest';

    /**
     * The organization id use for authentication
     *
     * @var ?string
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
     * Search HTTP method
     *
     * @var string
     */
    protected $searchHttpMethod = 'POST';

    /**
     * Constructor
     *
     * Sets up the EDS API Client
     *
     * @param array $settings Associative array of setting to use in
     *                        conjunction with the EDS API
     *    <ul>
     *      <li>orgid - Organization making calls to the EDS API </li>
     *      <li>search_http_method - HTTP method for search API calls</li>
     *    </ul>
     */
    public function __construct($settings = [])
    {
        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                switch ($key) {
                    case 'api_url':
                        $this->apiHost = $value;
                        break;
                    case 'auth_url':
                        $this->authHost = $value;
                        break;
                    case 'session_url':
                        $this->sessionHost = $value;
                        break;
                    case 'orgid':
                        $this->orgId = $value;
                        break;
                    case 'search_http_method':
                        $this->searchHttpMethod = $value;
                }
            }
        }
    }

    /**
     * Obtain edsapi search criteria and application related settings
     *
     * @param string $authenticationToken Authentication token
     * @param string $sessionToken        Session token
     *
     * @return array
     */
    public function info($authenticationToken = null, $sessionToken = null)
    {
        $this->debug('Info');
        $url = $this->apiHost . '/info';
        $headers = $this->setTokens($authenticationToken, $sessionToken);
        return $this->call($url, $headers);
    }

    /**
     * Creates a new session
     *
     * @param string $profile   Profile to use
     * @param string $isGuest   Whether or not this session will be a guest session
     * @param string $authToken Authentication token
     *
     * @return array
     */
    public function createSession(
        $profile = null,
        $isGuest = null,
        $authToken = null
    ) {
        $this->debug(
            'Create Session for profile: '
            . "$profile, guest: $isGuest, authToken: $authToken "
        );
        $qs = ['profile' => $profile, 'guest' => $isGuest];
        $url = $this->sessionHost . '/createsession';
        $headers = $this->setTokens($authToken, null);
        return $this->call($url, $headers, $qs, 'GET', null, '', false);
    }

    /**
     * Retrieves an EDS record specified by its identifiers
     *
     * @param string $an                  An of the record to retrieve from the
     * EdsApi
     * @param string $dbId                Database identifier of the record to
     * retrieve from the EdsApi
     * @param string $authenticationToken Authentication token
     * @param string $sessionToken        Session token
     * @param string $highlightTerms      Comma separated list of terms to highlight
     * in the retrieved record responses
     * @param array  $extraQueryParams    Extra query string parameters
     *
     * @return array    The requested record
     *
     * @deprecated Use retrieveEdsItem
     */
    public function retrieve(
        $an,
        $dbId,
        $authenticationToken,
        $sessionToken,
        $highlightTerms = null,
        $extraQueryParams = []
    ) {
        return $this->retrieveEdsItem(
            $an,
            $dbId,
            $authenticationToken,
            $sessionToken,
            $highlightTerms,
            $extraQueryParams
        );
    }

    /**
     * Retrieves an EDS record specified by its identifiers
     *
     * @param string $an                  An of the record to retrieve from the
     * EdsApi
     * @param string $dbId                Database identifier of the record to
     * retrieve from the EdsApi
     * @param string $authenticationToken Authentication token
     * @param string $sessionToken        Session token
     * @param string $highlightTerms      Comma separated list of terms to highlight
     * in the retrieved record responses
     * @param array  $extraQueryParams    Extra query string parameters
     *
     * @return array    The requested record
     */
    public function retrieveEdsItem(
        $an,
        $dbId,
        $authenticationToken,
        $sessionToken,
        $highlightTerms = null,
        $extraQueryParams = []
    ) {
        $this->debug(
            "Get Record. an: $an, dbid: $dbId, $highlightTerms: $highlightTerms"
        );
        $qs = $extraQueryParams + ['an' => $an, 'dbid' => $dbId];
        if (null != $highlightTerms) {
            $qs['highlightterms'] = $highlightTerms;
        }
        $url = $this->apiHost . '/retrieve';
        $headers = $this->setTokens($authenticationToken, $sessionToken);
        return $this->call($url, $headers, $qs);
    }

    /**
     * Retrieves an EPF record specified by its identifiers
     *
     * @param string $pubId               Id of the record to retrieve from the
     * EpfApi
     * @param string $authenticationToken Authentication token
     * @param string $sessionToken        Session token
     *
     * @return array    The requested record
     */
    public function retrieveEpfItem(
        $pubId,
        $authenticationToken,
        $sessionToken
    ) {
        $this->debug(
            "Get Record. pubId: $pubId"
        );
        $qs = ['id' => $pubId];
        $url = $this->apiHost . '/retrieve';
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
        $method = $this->searchHttpMethod;
        $json = $method === 'GET' ? null : $query->convertToSearchRequestJSON();
        $qs = $method === 'GET' ? $query->convertToQueryStringParameterArray() : [];
        $this->debug(
            'Query: ' . ($method === 'GET' ? $this->varDump($qs) : $json)
        );
        $url = $this->apiHost . '/search';
        $headers = $this->setTokens($authenticationToken, $sessionToken);
        return $this->call($url, $headers, $qs, $method, $json);
    }

    /**
     * Parse autocomplete response from API in an array of terms
     *
     * @param array $msg Response from API
     *
     * @return array of terms
     */
    protected function parseAutocomplete($msg)
    {
        $result = [];
        if (isset($msg['terms']) && is_array($msg['terms'])) {
            foreach ($msg['terms'] as $value) {
                $result[] = $value['term'];
            }
        }
        return $result;
    }

    /**
     * Execute an EdsApi autocomplete
     *
     * @param string $query Search term
     * @param string $type  Autocomplete type (e.g. 'rawqueries' or 'holdings')
     * @param array  $data  Autocomplete API details (from authenticating with
     * 'autocomplete' option set -- requires token, custid and url keys).
     * @param bool   $raw   Should we return the results raw (true) or processed
     * (false)?
     *
     * @return array An array of autocomplete terns as returned from the api
     */
    public function autocomplete($query, $type, $data, $raw = false)
    {
        // $filters is an array of filter objects
        // filter objects consist of name and an array of values (customer ids)
        $filters = [['name' => 'custid', 'values' => [$data['custid']]]];

        $params = [
            'idx' => $type,
            'token' => $data['token'],
            'filters' => json_encode($filters),
            'term' => $query,
        ];

        $url = $data['url'] . '?' . http_build_query($params);

        $this->debug('Autocomplete URL: ' . $url);
        $response = $this->call($url, null, null, 'GET', null);
        return $raw ? $response : $this->parseAutocomplete($response);
    }

    /**
     * Generate an authentication token with a valid EBSCO EDS Api account
     *
     * @param string $username username associated with an EBSCO EdsApi account
     * @param string $password password associated with an EBSCO EdsApi account
     * @param string $orgid    Organization id the request is initiated from
     * @param array  $params   optional params (autocomplete)
     *
     * @return array
     */
    public function authenticate(
        $username = null,
        $password = null,
        $orgid = null,
        $params = null
    ) {
        $this->debug(
            "Authenticating: username: $username, password: XXXXXXX, orgid: $orgid"
        );
        $url = $this->authHost . '/uidauth';
        $org = $orgid ?? $this->orgId;
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
        if (isset($params)) {
            $authInfo['Options'] = $params;
        }
        $messageBody = json_encode($authInfo);
        return $this->call($url, null, null, 'POST', $messageBody, '', false);
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
                    $queryParameters[] = $key . '=' . urlencode($value ?? '');
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
     * @param bool   $cacheable     Whether the request is cacheable
     *
     * @throws ApiException
     * @return object         EDS API response (or an Error object).
     */
    protected function call(
        $baseUrl,
        $headerParams,
        $params = [],
        $method = 'GET',
        $message = null,
        $messageFormat = '',
        $cacheable = true
    ) {
        // Build Query String Parameters
        $queryParameters = $this->createQSFromArray($params);
        $queryString = implode('&', $queryParameters);
        $this->debug("Querystring to use: $queryString ");
        // Build headers
        $headers = [
            'Accept' => $this->accept,
            'Content-Type' => $this->contentType,
            'Accept-Encoding' => 'gzip,deflate',
        ];
        if (null != $headerParams) {
            foreach ($headerParams as $key => $value) {
                $headers[$key] = $value;
            }
        }
        $response = $this->httpRequest(
            $baseUrl,
            $method,
            $queryString,
            $headers,
            $message,
            $messageFormat,
            $cacheable
        );
        return $this->process($response);
    }

    /**
     * Process EDS API response message
     *
     * @param string $input The raw response from EDS API
     *
     * @throws ApiException
     * @return array        The processed response from EDS API
     */
    protected function process($input)
    {
        //process response.
        try {
            $result = json_decode($input, true);
        } catch (\Exception $e) {
            throw new ApiException(
                'An error occurred when processing EDS Api response: '
                . $e->getMessage()
            );
        }
        if (!isset($result)) {
            throw new ApiException('Unknown error processing response');
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
     * @param bool   $cacheable     Whether the request is cacheable
     *
     * @return string             HTTP response body
     */
    abstract protected function httpRequest(
        $baseUrl,
        $method,
        $queryString,
        $headers,
        $messageBody,
        $messageFormat,
        $cacheable = true
    );
}
