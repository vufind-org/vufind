<?php

/**
 * Abstract Driver for API-based ILS drivers.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Response;
use Psr\Log\LoggerAwareInterface;
use VuFind\Exception\BadConfig;
use VuFind\Exception\ILS as ILSException;
use VuFind\Http\GuzzleLivePool;
use VuFind\Http\GuzzleServiceAwareInterface;
use VuFind\Http\GuzzleServiceAwareTrait;
use VuFindHttp\HttpServiceAwareInterface;

use function in_array;
use function intval;
use function is_string;

/**
 * Abstract Driver for API-based ILS drivers.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
abstract class AbstractAPI extends AbstractBase implements
    HttpServiceAwareInterface,
    LoggerAwareInterface,
    GuzzleServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;
    use GuzzleServiceAwareTrait;

    /**
     * Guzzle client
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Guzzle live pool
     *
     * @var \VuFind\Http\GuzzleLivePool
     */
    protected $pool;

    /**
     * Get the class' Guzzle client, instantiating it if needed
     *
     * @return Client
     */
    protected function getClient(): Client
    {
        return $this->client ??= $this->getGuzzleService()->createClient(
            $this->config['API']['base_url'],
            120
        );
    }

    /**
     * Get the class' Guzzle pool, instantiating it if needed
     *
     * @return GuzzleLivePool
     */
    protected function getPool(): GuzzleLivePool
    {
        $concurrency = intval($this->config['Catalog']['concurrency'] ?? null);
        return $this->pool ??= new GuzzleLivePool($this->getClient(), $concurrency);
    }

    /**
     * Allow default corrections to all requests.
     *
     * @param \Laminas\Http\Headers $headers the request headers
     * @param array                 $params  the parameters object
     *
     * @return array
     */
    protected function preRequest(\Laminas\Http\Headers $headers, $params)
    {
        return [$headers, $params];
    }

    /**
     * Function that obscures and logs debug data.
     *
     * @param string                $method      Request method
     * (GET/POST/PUT/DELETE/etc.)
     * @param string                $path        Request URL
     * @param array                 $params      Request parameters
     * @param \Laminas\Http\Headers $req_headers Headers object
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
            ' Params: ' . $this->varDump($logParams) . '.' .
            ' Headers: ' . $this->varDump($logHeaders)
        );
    }

    /**
     * Does $code match the setting for allowed failure codes?
     *
     * @param int               $code                Code to check.
     * @param true|int[]|string $allowedFailureCodes HTTP failure codes that should
     * NOT cause an ILSException to be thrown. May be an array of integers, a regular
     * expression, or boolean true to allow all codes.
     *
     * @return bool
     */
    protected function failureCodeIsAllowed(int $code, $allowedFailureCodes): bool
    {
        if ($allowedFailureCodes === true) {    // "allow everything" case
            return true;
        }
        return is_string($allowedFailureCodes)
            ? preg_match($allowedFailureCodes, (string)$code)
            : in_array($code, (array)$allowedFailureCodes);
    }

    /**
     * Support method for makeRequest to process an unexpected status code. Can return true to trigger
     * a retry of the API call or false to throw an exception.
     *
     * @param Response|Psr7\Response $response      HTTP response
     * @param int                    $attemptNumber Counter to keep track of attempts
     *                                              (starts at 1 for the first attempt)
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function shouldRetryAfterUnexpectedStatusCode(
        Response|Psr7\Response $response,
        int $attemptNumber
    ): bool {
        // No retries by default.
        return false;
    }

    /**
     * Make requests.
     *
     * @param string            $method              GET/POST/PUT/DELETE/etc
     * @param string            $path                API path (with a leading /)
     * @param string|array      $params              Query parameters
     * @param array             $headers             Additional headers
     * @param true|int[]|string $allowedFailureCodes HTTP failure codes that should
     * NOT cause an ILSException to be thrown. May be an array of integers, a regular
     * expression, or boolean true to allow all codes.
     * @param string|array      $debugParams         Value to use in place of $params
     * in debug messages (useful for concealing sensitive data, etc.)
     * @param int               $attemptNumber       Counter to keep track of attempts
     * (starts at 1 for the first attempt)
     *
     * @return \Laminas\Http\Response
     * @throws ILSException
     */
    public function makeRequest(
        $method = 'GET',
        $path = '/',
        $params = [],
        $headers = [],
        $allowedFailureCodes = [],
        $debugParams = null,
        $attemptNumber = 1
    ) {
        $client = $this->httpService->createClient(
            $this->config['API']['base_url'] . $path,
            $method,
            120
        );

        // Add default headers and parameters
        $req_headers = $client->getRequest()->getHeaders();
        $req_headers->addHeaders($headers);
        [$req_headers, $params] = $this->preRequest($req_headers, $params);

        if ($this->logger) {
            $this->debugRequest($method, $path, $debugParams ?? $params, $req_headers);
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
        $startTime = microtime(true);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . $e::class . ': ' . (string)$e);
            throw new ILSException('Error during send operation.');
        }
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        $this->debug('Request Response Time --- ' . $responseTime . ' seconds. ' . $path);
        $code = $response->getStatusCode();
        if (
            !$response->isSuccess()
            && !$this->failureCodeIsAllowed($code, $allowedFailureCodes)
        ) {
            $this->logError(
                "Unexpected error response (attempt #$attemptNumber"
                . "); code: {$response->getStatusCode()}, body: {$response->getBody()}"
            );
            if ($this->shouldRetryAfterUnexpectedStatusCode($response, $attemptNumber)) {
                return $this->makeRequest(
                    $method,
                    $path,
                    $params,
                    $headers,
                    $allowedFailureCodes,
                    $debugParams,
                    $attemptNumber + 1
                );
            } else {
                throw new ILSException("Unexpected error code '$code' returned from $path.");
            }
        }
        if ($jsonLog = ($this->config['API']['json_log_file'] ?? false)) {
            if (APPLICATION_ENV !== 'development') {
                $this->logError(
                    'SECURITY: json_log_file enabled outside of development mode; disabling feature.'
                );
            } else {
                $body = $response->getBody();
                $jsonBody = @json_decode($body);
                $json = file_exists($jsonLog)
                    ? json_decode(file_get_contents($jsonLog)) : [];
                $json[] = [
                    'expectedMethod' => $method,
                    'expectedPath' => $path,
                    'expectedParams' => $params,
                    'body' => $jsonBody ? $jsonBody : $body,
                    'bodyType' => $jsonBody ? 'json' : 'string',
                    'status' => $code,
                ];
                file_put_contents($jsonLog, json_encode($json));
            }
        }
        return $response;
    }

    /**
     * Make GET request async; async requests always use the GET method
     *
     * @param string            $path                API path (with a leading /)
     * @param string|array      $params              Query parameters
     * @param array             $headers             Additional headers
     * @param true|int[]|string $allowedFailureCodes HTTP failure codes that should
     * NOT cause an ILSException to be thrown. May be an array of integers, a regular
     * expression, or boolean true to allow all codes.
     * @param string|array      $debugParams         Value to use in place of $params
     * in debug messages (useful for concealing sensitive data, etc.)
     * @param int               $attemptNumber       Counter to keep track of attempts
     * (starts at 1 for the first attempt)
     * @param ?string           $baseUrl             Provide an alternate schema, host,
     * and optionally port to submit the request to (http://alt.example.edu:8080)
     * For async API to work propery, even across different hosts, they need to make
     * use of the same GuzzleHTTP client instance, so API calls to other endpoints
     * should use this function instead of instantiating their own client instnace.
     *
     * @return \GuzzleHttp\Promise\Promise for a Psr7\Response
     * @throws ILSException possible on Promise unwrap
     */
    public function makeRequestAsync(
        $path = '/',
        $params = [],
        $headers = [],
        $allowedFailureCodes = [],
        $debugParams = null,
        $attemptNumber = 1,
        $baseUrl = null
    ) {
        $req_headers = new \Laminas\Http\Headers();
        $req_headers->addHeaders($headers);
        [$req_headers, $params] = $this->preRequest($req_headers, $params);
        if (!empty($headers)) {
            foreach ($headers as $header) {
                $matches = $req_headers->get(explode(':', $header)[0]);

                if ($matches instanceof \ArrayIterator) {
                    foreach ($req_headers as $req_header) {
                        $req_headers->removeHeader($req_header);
                    }
                } elseif ($matches instanceof HeaderInterface) {
                    $req_headers->removeHeader($matches);
                }
                if ($matches != false) {
                    $req_headers->addHeaderLine($header);
                }
            }
        }
        $folioBaseUrl = $this->config['API']['base_url'];
        $baseUrl ??= $folioBaseUrl;
        $logPath = ($folioBaseUrl != $baseUrl ? $baseUrl . $path : $path);
        $request = new Psr7\Request('GET', $baseUrl . $path);

        if ($this->logger) {
            $this->debugRequest('GET', $path, $debugParams ?? $params, $headers);
        }

        $this->debug('Request ASYNC start for path ' . $logPath);
        $startTime = microtime(true);
        $promise = $this->getPool()->add(
            $request,
            ['headers' => $req_headers->toArray(), 'query' => $params]
        );
        return $promise->then(
            function (Psr7\Response $response) use (
                $startTime,
                $path,
                $params,
                $headers,
                $allowedFailureCodes,
                $debugParams,
                $attemptNumber,
                $baseUrl,
                $logPath
            ) {
                $endTime = microtime(true);
                $responseTime = $endTime - $startTime;
                $this->debug('Request ASYNC time to unwrap --- ' . $responseTime . ' seconds for ' . $logPath);
                $code = $response->getStatusCode();
                if (
                    !($code >= 200 && $code < 300)
                    && !$this->failureCodeIsAllowed($code, $allowedFailureCodes)
                ) {
                    $this->logError(
                        "Unexpected error response (attempt #$attemptNumber"
                        . "); code: {$code}, body: {$response->getBody()}"
                    );
                    if ($this->shouldRetryAfterUnexpectedStatusCode($response, $attemptNumber)) {
                        return $this->makeRequestAsync(
                            $path,
                            $params,
                            $headers,
                            $allowedFailureCodes,
                            $debugParams,
                            $attemptNumber + 1,
                            $baseUrl
                        );
                    } else {
                        throw new ILSException('Unexpected error code.');
                    }
                }
                return $response;
            },
            function (\Throwable $e): void {
                $this->logError('Unexpected ' . $e::class . ': ' . (string)$e);
                throw new ILSException('Error during send operation.');
            }
        );
    }

    /**
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @throws BadConfig if base url excluded
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        // Base URL required for API drivers
        if (!isset($config['API']['base_url'])) {
            throw new BadConfig('API Driver configured without base url.');
        }
    }
}
