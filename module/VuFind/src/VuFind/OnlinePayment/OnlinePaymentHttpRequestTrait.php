<?php

/**
 * Online payment HTTP request trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment;

use Laminas\Stdlib\Parameters;

use function strlen;

/**
 * Online payment HTTP request trait.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait OnlinePaymentHttpRequestTrait
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * HTTP service.
     *
     * @var \VuFindHttp\HttpService
     */
    protected $http;

    /**
     * Set HTTP service.
     *
     * @param \VuFindHttp\HttpService $http HTTP service.
     *
     * @return void
     */
    public function setHttpService(\VuFindHttp\HttpService $http): void
    {
        $this->http = $http;
    }

    /**
     * Make a GET request to payment provider.
     *
     * @param string $url      URL
     * @param array  $options  Laminas HTTP client options
     * @param array  $headers  HTTP headers (key-value list).
     * @param string $username Username for HTTP basic authentication.
     * @param string $password Password for HTTP basic authentication.
     *
     * @return bool|array false on error, otherwise an array with keys:
     * - httpCode => Response status code
     * - response => Response body
     * - headers => Response headers
     */
    protected function getRequest(
        $url,
        $options = [],
        $headers = [],
        $username = null,
        $password = null
    ) {
        return $this->sendHttpRequest(
            \Laminas\Http\Request::METHOD_GET,
            $url,
            '',
            $options,
            $headers,
            $username,
            $password
        );
    }

    /**
     * Make a POST request to payment provider.
     *
     * @param string $url      URL
     * @param string $body     Request body
     * @param array  $options  Laminas HTTP client options
     * @param array  $headers  HTTP headers (key-value list).
     * @param string $username Username for HTTP basic authentication.
     * @param string $password Password for HTTP basic authentication.
     *
     * @return bool|array false on error, otherwise an array with keys:
     * - httpCode => Response status code
     * - response => Response body
     * - headers => Response headers
     */
    protected function postRequest(
        $url,
        $body,
        $options = [],
        $headers = [],
        $username = null,
        $password = null
    ) {
        return $this->sendHttpRequest(
            \Laminas\Http\Request::METHOD_POST,
            $url,
            $body,
            $options,
            $headers,
            $username,
            $password
        );
    }

    /**
     * Send a request to payment provider.
     *
     * @param string  $method   HTTP method
     * @param string  $url      URL
     * @param string  $body     Request body (POST requests)
     * @param array   $options  Laminas HTTP client options
     * @param array   $headers  HTTP headers (key-value list).
     * @param ?string $username Username for HTTP basic authentication.
     * @param ?string $password Password for HTTP basic authentication.
     *
     * @return bool|array false on error, otherwise an array with keys:
     * - httpCode => Response status code
     * - response => Response body
     * - headers => Response headers
     */
    protected function sendHttpRequest(
        string $method,
        string $url,
        string $body,
        array $options = [],
        array $headers = [],
        ?string $username = null,
        ?string $password = null
    ) {
        try {
            $client = $this->http->createClient($url, $method, 30);
            if (!empty($username) && !empty($password)) {
                $client->setAuth($username, $password);
            }
            $client->setOptions($options);
            $headers = array_merge(
                [
                    'Content-Type' => 'application/json',
                    'Content-Length' => strlen($body),
                ],
                $headers
            );
            $client->setHeaders($headers);
            $client->setRawBody($body);
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logger->err(
                "Error sending $method request: " . $e->getMessage()
                . ", url: $url, body: $body, headers: " . var_export($headers, true)
            );
            if ($this->logger instanceof \VuFind\Log\Logger) {
                $this->logger->logException($e, new Parameters());
            }
            return false;
        }

        $this->logger->warn(
            "Online payment request: method: $method, url: $url, body: $body, headers: "
            . var_export($headers, true) . ', response: '
            . (string)$response
        );

        $status = $response->getStatusCode();
        $content = $response->getBody();

        if (!$response->isSuccess()) {
            $this->logger->err(
                "Error sending $method request: invalid status code: $status"
                . ", url: $url, body: $body, headers: " . var_export($headers, true)
                . ", response: $content"
            );
            return false;
        }

        return [
            'httpCode' => $status,
            'response' => $content,
            'headers' => $response->getHeaders()->toArray(),
        ];
    }
}
