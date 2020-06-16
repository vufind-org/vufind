<?php
/**
 * OnlinePayment module trait.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\OnlinePayment;

/**
 * OnlinePayment module trait.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait OnlinePaymentModuleTrait
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * HTTP service.
     *
     * @var \VuFind\Http
     */
    protected $http;

    /**
     * Set HTTP service.
     *
     * @param \VuFind\Http $http HTTP service.
     *
     * @return void
     */
    public function setHttpService($http)
    {
        $this->http = $http;
    }

    /**
     * Post request to payment provider.
     *
     * @param string $url      URL
     * @param string $body     Request body
     * @param array  $options  Laminas HTTP client options
     * @param array  $headers  HTTP headers (key-value list).
     * @param string $username Username for HTTP basic authentication.
     * @param string $password Password for HTTP basic authentication.
     *
     * @return false on error, otherwise array with keys:
     * - httpCode => Response status code
     * - contentType => Response content type
     * - response => Response body
     */
    protected function postRequest(
        $url, $body, $options = [], $headers = [],
        $username = null, $password = null
    ) {
        try {
            $client = $this->http->createClient(
                $url, \Laminas\Http\Request::METHOD_POST, 30
            );
            if (!empty($username) && !empty($password)) {
                $client->setAuth($username, $password);
            }
            $client->setOptions($options);
            $headers = array_merge(
                [
                    'Content-Type' => 'application/json',
                    'Content-Length' => strlen($body)
                ],
                $headers
            );
            $client->setHeaders($headers);
            $client->setRawBody($body);
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logger->err(
                "Error posting request: " . $e->getMessage()
                . ", url: $url, body: $body, headers: " . var_export($headers, true)
            );
            $this->logger->logException($e, new \Laminas\Stdlib\Parameters());
            return false;
        }

        $status = $response->getStatusCode();
        $content = $response->getBody();

        if (!$response->isSuccess()) {
            $this->logger->err(
                "Error posting request: invalid status code: $status"
                . ", url: $url, body: $body, headers: " . var_export($headers, true)
                . ", response: $content"
            );
            return false;
        }

        return [
           'httpCode' => $status,
           'contentType' => $response->getContent(),
           'response' => $content
        ];
    }
}
