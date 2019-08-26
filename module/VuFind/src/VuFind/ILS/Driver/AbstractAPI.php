<?php
/**
 * Abstract Driver for API-based ILS drivers
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2018.
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Exception\BadRequest as BadRequest;
use VuFind\Exception\Forbidden as Forbidden;
use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\RecordMissing as RecordMissing;
use VuFindHttp\HttpServiceAwareInterface;
use Zend\Log\LoggerAwareInterface;

/**
 * Abstract Driver for API-based ILS drivers
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
abstract class AbstractAPI extends AbstractBase implements HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Allow default corrections to all requests
     *
     * @param \Zend\Http\Headers $headers the request headers
     * @param array              $params  the parameters object
     *
     * @return array
     */
    protected function preRequest(\Zend\Http\Headers $headers, $params)
    {
        return [$headers, $params];
    }

    /**
     * Function that obscures and logs debug data
     *
     * @param string             $method      Request method GET/POST/PUT/DELETE/etc
     * @param string             $path        Request URL
     * @param array              $params      Request parameters
     * @param \Zend\Http\Headers $req_headers Headers object
     *
     * @return void
     */
    protected function debugRequest($method, $path, $params, $req_headers)
    {
        $logParams = [];
        $logHeaders = [];
        if ($method == 'GET') {
            $logParams = $params;
            $logHeaders = $req_headers->toArray();
        }
        $this->debug(
            $method . ' request.' .
            ' URL: ' . $path . '.' .
            ' Params: ' . print_r($logParams, true) . '.' .
            ' Headers: ' . print_r($logHeaders, true)
        );
    }

    /**
     * Make requests
     *
     * @param string $method  GET/POST/PUT/DELETE/etc
     * @param string $path    API path (with a leading /)
     * @param array  $params  Parameters object to be sent as data
     * @param array  $headers Additional headers
     *
     * @return \Zend\Http\Response
     */
    public function makeRequest($method = "GET", $path = "/", $params = [],
        $headers = []
    ) {
        $client = $this->httpService->createClient(
            $this->config['API']['base_url'] . $path,
            $method,
            120
        );

        // Add default headers and parameters
        $req_headers = $client->getRequest()->getHeaders();
        $req_headers->addHeaders($headers);
        list($req_headers, $params) = $this->preRequest($req_headers, $params);

        if ($this->logger) {
            $this->debugRequest($method, $path, $params, $req_headers);
        }

        // Add params
        if ($method == 'GET') {
            $client->setParameterGet($params);
        } else {
            if (is_string($params)) {
                $client->getRequest()->setContent($params);
            } else {
                $client->setParameterPost($params);
            }
        }
        $response = $client->send();
        switch ($response->getStatusCode()) {
        case 400:
            throw new BadRequest($response->getBody());
        case 401:
        case 403:
            throw new Forbidden($response->getBody());
        case 404:
            throw new RecordMissing($response->getBody());
        case 500:
            throw new ILSException("500: Internal Server Error");
        }
        return $response;
    }

    /**
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @throws ILSException if base url excluded
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        // Base URL required for API drivers
        if (!isset($config['API']['base_url'])) {
            throw new ILSException('API Driver configured without base url.');
        }
    }
}
