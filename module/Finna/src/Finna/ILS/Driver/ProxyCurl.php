<?php
/**
 * Modified BeSimple Curl for VuFind HTTP Service
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
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use BeSimple\SoapClient\Curl;
use VuFindHttp\HttpServiceInterface;

/**
 * Modified BeSimple Curl for VuFind HTTP Service
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class ProxyCurl extends Curl
{
    /**
     * HTTP Service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService;

    /**
     * Response
     *
     * @var \Zend\Http\Response
     */
    protected $response = null;

    /**
     * Options
     *
     * @var array
     */
    protected $options;

    /**
     * Constructor.
     *
     * @param HttpServiceInterface $httpService                HTTP Service
     * @param array                $options                    Options array from
     * SoapClient
     * constructor
     * @param int                  $followLocationMaxRedirects Redirection limit for
     * Location header
     */
    public function __construct(HttpServiceInterface $httpService,
        array $options = [], $followLocationMaxRedirects = 10
    ) {
        $this->httpService = $httpService;
        $this->followLocationMaxRedirects = $followLocationMaxRedirects;
        $this->options = $options;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
    }

    /**
     * Set a cURL option
     *
     * @param string $curlOption      Option
     * @param string $curlOptionValue Value
     *
     * @return void
     */
    public function setOption($curlOption, $curlOptionValue)
    {
        throw new \Exception('setOption not currently supported');
    }

    /**
     * Execute HTTP request.
     * Returns true if request was successfull.
     *
     * @param string $location       HTTP location
     * @param string $request        Request body
     * @param array  $requestHeaders Request header strings
     * @param array  $requestOptions An array of request options
     *
     * @return bool
     */
    public function exec($location, $request = null, $requestHeaders = [],
        $requestOptions = []
    ) {
        $client = $this->httpService->createClient($location);

        if (isset($this->options['connection_timeout'])) {
            $client->setOptions(
                [
                    'connect_timeout' => $this->options['connection_timeout']
                ]
            );
        }
        $authType = $this->options['auth_type'] ?? Curl::AUTH_TYPE_NONE;
        if (isset($this->options['login']) && Curl::AUTH_TYPE_NONE !== $authType) {
            $client->setAuth(
                $this->options['login'],
                $this->options['password'] ?? '',
                $authType === Curl::AUTH_TYPE_NTLM
                    ? \Zend\Http\Client::AUTH_DIGEST : \Zend\Http\Client::AUTH_BASIC
            );
        }

        if (null !== $request) {
            $client->setMethod(\Zend\Http\Request::METHOD_POST);
            $client->setRawBody($request);
        }

        if ($requestHeaders) {
            $client->setHeaders($requestHeaders);
        }

        if ($requestOptions) {
            throw new \Exception('Request options not currently supported');
        }

        $this->lastErrorCode = 0;
        $this->lastErrorMessage = '';
        $this->response = false;
        $this->requestHeaders = $client->getRequest()->getHeaders()->toString();
        try {
            $this->response = $client->send();
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
        }

        return (false === $this->response) ? false : true;
    }

    /**
     * Custom curl_exec wrapper that allows to follow redirects when specific
     * http response code is set. SOAP only allows 307.
     *
     * @param int $redirects Current redirection count
     *
     * @return mixed
     */
    protected function execManualRedirect($redirects = 0)
    {
        throw new \Exception('execManualRedirect not currently supported');
    }

    /**
     * Gets the curl error message.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    /**
     * Gets the request headers as a string.
     *
     * @return string
     */
    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * Gets the whole response (including headers) as a string.
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->response ? $this->response->toString() : '';
    }

    /**
     * Gets the response body as a string.
     *
     * @return string
     */
    public function getResponseBody()
    {
        return $this->response ? $this->response->getBody() : '';
    }

    /**
     * Gets the response content type.
     *
     * @return string
     */
    public function getResponseContentType()
    {
        return $this->response ?
            $this->response->getHeaders()->get('Content-Type')->getFieldValue() : '';
    }

    /**
     * Gets the response headers as a string.
     *
     * @return string
     */
    public function getResponseHeaders()
    {
        return $this->response ? $this->response->getHeaders()->toString() : '';
    }

    /**
     * Gets the response http status code.
     *
     * @return string
     */
    public function getResponseStatusCode()
    {
        return $this->response ? $this->response->getStatusCode() : '';
    }

    /**
     * Gets the response http status message.
     *
     * @return string
     */
    public function getResponseStatusMessage()
    {
        return $this->response ? $this->response->getReasonPhrase() : '';
    }
}
