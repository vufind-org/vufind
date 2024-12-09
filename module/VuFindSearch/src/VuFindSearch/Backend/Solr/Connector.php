<?php

/**
 * SOLR connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr;

use Laminas\Http\Client\Adapter\Exception\TimeoutException;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request;
use Laminas\Uri\Http;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Exception\RequestErrorException;
use VuFindSearch\Backend\Solr\Document\DocumentInterface;
use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\ParamBag;

use function call_user_func_array;
use function count;
use function is_callable;
use function sprintf;
use function strlen;

/**
 * SOLR connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindSearch\Backend\Feature\ConnectorCacheTrait;

    /**
     * Maximum length of a GET url.
     *
     * Switches to POST if the SOLR target URL exceeds this length.
     *
     * @see \VuFindSearch\Backend\Solr\Connector::query()
     *
     * @var int
     */
    public const MAX_GET_URL_LENGTH = 2048;

    /**
     * HTTP client factory
     *
     * @var callable
     */
    protected $clientFactory;

    /**
     * URL or an array of alternative URLs of the SOLR core.
     *
     * @var string|array
     */
    protected $url;

    /**
     * Handler map.
     *
     * @var HandlerMap
     */
    protected $map;

    /**
     * Solr field used to store unique identifier
     *
     * @var string
     */
    protected $uniqueKey;

    /**
     * Url of the last request
     *
     * @var ?Http
     */
    protected $lastUrl = null;

    /**
     * Constructor
     *
     * @param string|array        $url       SOLR core URL or an array of alternative
     * URLs
     * @param HandlerMap          $map       Handler map
     * @param callable|HttpClient $cf        HTTP client factory or a client to clone
     * @param string              $uniqueKey Solr field used to store unique
     * identifier
     */
    public function __construct(
        $url,
        HandlerMap $map,
        $cf,
        $uniqueKey = 'id'
    ) {
        $this->url = $url;
        $this->map = $map;
        $this->uniqueKey = $uniqueKey;
        if ($cf instanceof HttpClient) {
            $this->clientFactory = function () use ($cf) {
                return clone $cf;
            };
        } else {
            $this->clientFactory = $cf;
        }
    }

    /// Public API

    /**
     * Get the Solr URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Return handler map.
     *
     * @return HandlerMap
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * Get unique key.
     *
     * @return string
     */
    public function getUniqueKey()
    {
        return $this->uniqueKey;
    }

    /**
     * Get the last request url.
     *
     * @return ?Http
     */
    public function getLastUrl()
    {
        return $this->lastUrl;
    }

    /**
     * Clears the last url
     *
     * @return void
     */
    public function resetLastUrl()
    {
        $this->lastUrl = null;
    }

    /**
     * Return document specified by id.
     *
     * @param string   $id     The document to retrieve from Solr
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function retrieve($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params
            ->set('q', sprintf('%s:"%s"', $this->uniqueKey, addcslashes($id, '"')));

        $handler = $this->map->getHandler(__FUNCTION__);
        $this->map->prepare(__FUNCTION__, $params);

        return $this->query($handler, $params, true);
    }

    /**
     * Return records similar to a given record specified by id.
     *
     * Uses MoreLikeThis Request Component or MoreLikeThis Handler
     *
     * @param string   $id     ID of given record (not currently used, but
     * retained for backward compatibility / extensibility).
     * @param ParamBag $params Parameters
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function similar($id, ParamBag $params)
    {
        $handler = $this->map->getHandler(__FUNCTION__);
        $this->map->prepare(__FUNCTION__, $params);

        try {
            return $this->query($handler, $params, true);
        } catch (RequestErrorException $e) {
            // If Solr was unable to fetch the record, just act like we have no similar records:
            if (str_contains($e->getMessage(), 'Could not fetch document with id')) {
                return '{}';
            }
            throw $e;
        }
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function search(ParamBag $params)
    {
        $handler = $this->map->getHandler(__FUNCTION__);
        $this->map->prepare(__FUNCTION__, $params);
        return $this->query($handler, $params, true);
    }

    /**
     * Extract terms from a SOLR index.
     *
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function terms(ParamBag $params)
    {
        $handler = $this->map->getHandler(__FUNCTION__);
        $this->map->prepare(__FUNCTION__, $params);

        return $this->query($handler, $params, true);
    }

    /**
     * Write to the SOLR index.
     *
     * @param DocumentInterface $document Document to write
     * @param string            $handler  Update handler
     * @param ParamBag          $params   Update handler parameters
     *
     * @return string Response body
     */
    public function write(
        DocumentInterface $document,
        $handler = 'update',
        ParamBag $params = null
    ) {
        $params = $params ?: new ParamBag();
        $urlSuffix = "/{$handler}";
        if (count($params) > 0) {
            $urlSuffix .= '?' . implode('&', $params->request());
        }
        $callback = function ($client) use ($document) {
            $client->setEncType($document->getContentType());
            $body = $document->getContent();
            $client->setRawBody($body);
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Content-Length', strlen($body));
        };
        return $this->trySolrUrls('POST', $urlSuffix, $callback);
    }

    /// Internal API

    /**
     * Send query to SOLR and return response body.
     *
     * @param string   $handler   SOLR request handler to use
     * @param ParamBag $params    Request parameters
     * @param bool     $cacheable Whether the query is cacheable
     *
     * @return string Response body
     */
    public function query($handler, ParamBag $params, bool $cacheable = false)
    {
        $urlSuffix = '/' . $handler;
        $paramString = implode('&', $params->request());
        if (strlen($paramString) > self::MAX_GET_URL_LENGTH) {
            $method = Request::METHOD_POST;
            $callback = function ($client) use ($paramString) {
                $client->setRawBody($paramString);
                $client->setEncType(HttpClient::ENC_URLENCODED);
                $client->setHeaders(['Content-Length' => strlen($paramString)]);
            };
        } else {
            $method = Request::METHOD_GET;
            $urlSuffix .= '?' . $paramString;
            $callback = null;
        }

        $this->debug(sprintf('Query %s', $paramString));
        return $this->trySolrUrls($method, $urlSuffix, $callback, $cacheable);
    }

    /**
     * Call a method with provided options for the HTTP client
     *
     * @param array  $options HTTP client options
     * @param string $method  Method to call
     * @param array  ...$args Method parameters
     *
     * @return mixed
     */
    public function callWithHttpOptions(
        array $options,
        string $method,
        ...$args
    ) {
        $reflectionMethod = new \ReflectionMethod($this, $method);
        if (!$reflectionMethod->isPublic()) {
            throw new InvalidArgumentException("Method '$method' is not public");
        }
        if (empty($options)) {
            return call_user_func_array([$this, $method], $args);
        }
        $originalFactory = $this->clientFactory;
        try {
            $this->clientFactory = function (string $url) use (
                $originalFactory,
                $options
            ) {
                $client = $originalFactory($url);
                $client->setOptions($options);
                return $client;
            };
            return call_user_func_array([$this, $method], $args);
        } finally {
            $this->clientFactory = $originalFactory;
        }
    }

    /**
     * Check if an exception from a Solr request should be thrown rather than retried
     *
     * @param \Exception $ex Exception
     *
     * @return bool
     */
    protected function isRethrowableSolrException($ex)
    {
        // Solr can return 404 when the instance hasn't completed startup, so allow that to be retried:
        return $ex instanceof TimeoutException
            || (($ex instanceof RequestErrorException) && $ex->getResponse()->getStatusCode() !== 404);
    }

    /**
     * If an unexpected exception type was received, wrap it in a generic
     * BackendException to standardize upstream handling.
     *
     * @param \Exception $ex Exception
     *
     * @return \Exception
     */
    protected function forceToBackendException($ex)
    {
        // Don't wrap specific backend exceptions....
        if (
            $ex instanceof RemoteErrorException
            || $ex instanceof RequestErrorException
            || $ex instanceof HttpErrorException
        ) {
            return $ex;
        }
        return
            new BackendException('Problem connecting to Solr.', $ex->getCode(), $ex);
    }

    /**
     * Try all Solr URLs until we find one that works (or throw an exception).
     *
     * @param string   $method    HTTP method to use
     * @param string   $urlSuffix Suffix to append to all URLs tried
     * @param callable $callback  Callback to configure client (null for none)
     * @param bool     $cacheable Whether the request is cacheable
     *
     * @return string Response body
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function trySolrUrls(
        $method,
        $urlSuffix,
        $callback = null,
        bool $cacheable = false
    ) {
        // This exception should never get thrown; it's just a safety in case
        // something unanticipated occurs.
        $exception = new \Exception('Unexpected exception.');

        // Loop through all base URLs and try them in turn until one works.
        $cacheKey = null;
        foreach ((array)$this->url as $base) {
            $client = ($this->clientFactory)($base . $urlSuffix);
            $client->setMethod($method);
            if (is_callable($callback)) {
                $callback($client);
            }
            // Always create the cache key from the first server, and only after any
            // callback has been called above.
            if ($cacheable && $this->cache && null === $cacheKey) {
                $cacheKey = $this->getCacheKey($client);
                if ($result = $this->getCachedData($cacheKey)) {
                    return $result;
                }
            }
            try {
                $result = $this->send($client);
                if ($cacheKey) {
                    $this->putCachedData($cacheKey, $result);
                }
                return $result;
            } catch (\Exception $ex) {
                if ($this->isRethrowableSolrException($ex)) {
                    throw $this->forceToBackendException($ex);
                }
                $exception = $ex;
            }
        }

        // If we got this far, everything failed -- throw a BackendException with
        // the most recent exception caught above set as the previous exception.
        throw $this->forceToBackendException($exception);
    }

    /**
     * Extract the Solr core from the connector's URL.
     *
     * @return string
     */
    public function getCore(): string
    {
        $url = rtrim($this->getUrl(), '/');
        $parts = explode('/', $url);
        return array_pop($parts);
    }

    /**
     * Send request the SOLR and return the response.
     *
     * @param HttpClient $client Prepared HTTP client
     *
     * @return string Response body
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function send(HttpClient $client)
    {
        $this->debug(
            sprintf('=> %s %s', $client->getMethod(), $client->getUri())
        );

        $this->lastUrl = $client->getUri();

        $time     = microtime(true);
        $response = $client->send();
        $time     = microtime(true) - $time;

        $this->debug(
            sprintf(
                '<= %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ),
            ['time' => $time]
        );

        if (!$response->isSuccess()) {
            // Return a more detailed error message for a 400 error:
            if ($response->getStatusCode() === 400) {
                $json = json_decode($response->getBody(), true);
                $msgParts = ['400', $response->getReasonPhrase()];
                if ($msg = $json['error']['msg'] ?? '') {
                    $msgParts[] = $msg;
                }
                throw new RequestErrorException(
                    implode(' ', $msgParts),
                    400,
                    $response
                );
            }
            throw HttpErrorException::createFromResponse($response);
        }
        return $response->getBody();
    }
}
