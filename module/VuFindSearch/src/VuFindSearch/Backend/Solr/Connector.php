<?php

/**
 * SOLR connector.
 *
 * PHP version 7
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

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Client\Adapter\Exception\TimeoutException;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request;

use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Exception\RequestErrorException;
use VuFindSearch\Backend\Solr\Document\DocumentInterface;
use VuFindSearch\ParamBag;

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
     * HTTP client
     *
     * @var HttpClient
     */
    protected $client;

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
     * Request cache
     *
     * @var StorageInterface
     */
    protected $cache = null;

    /**
     * Constructor
     *
     * @param string|array $url       SOLR core URL or an array of alternative URLs
     * @param HandlerMap   $map       Handler map
     * @param HttpClient   $client    HTTP client
     * @param string       $uniqueKey Solr field used to store unique identifier
     */
    public function __construct(
        $url,
        HandlerMap $map,
        HttpClient $client,
        $uniqueKey = 'id'
    ) {
        $this->url = $url;
        $this->map = $map;
        $this->uniqueKey = $uniqueKey;
        $this->client = $client;
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
        return $this->query($handler, $params, true);
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

    /**
     * Set the cache storage
     *
     * @param StorageInterface $cache Cache
     *
     * @return void
     */
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
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
        $saveClient = $this->client;
        try {
            $this->client = clone $this->client;
            $this->client->setOptions($options);
            return call_user_func_array([$this, $method], $args);
        } finally {
            $this->client = $saveClient;
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
        return $ex instanceof TimeoutException
            || $ex instanceof RequestErrorException;
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
        if ($ex instanceof RemoteErrorException
            || $ex instanceof RequestErrorException
            || $ex instanceof HttpErrorException
        ) {
            return $ex;
        }
        return new BackendException('Problem connecting to Solr.', null, $ex);
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
            $this->client->resetParameters();
            $this->client->setMethod($method);
            $this->client->setUri($base . $urlSuffix);
            if (is_callable($callback)) {
                $callback($this->client);
            }
            // Always create the cache key from the first server, and only after any
            // callback has been called above.
            if ($cacheable && $this->cache && null === $cacheKey) {
                $cacheKey = md5($this->client->getRequest()->toString());
                try {
                    if ($result = $this->cache->getItem($cacheKey)) {
                        $this->debug('Returning cached results');
                        return $result;
                    }
                } catch (\Exception $ex) {
                    $this->logWarning('Cache getItem failed: ' . $ex->getMessage());
                }
            }
            try {
                $result = $this->send($this->client);

                if ($cacheKey) {
                    try {
                        $this->cache->setItem($cacheKey, $result);
                    } catch (\Laminas\Cache\Exception\RuntimeException $ex) {
                        // Try to determine if caching failed due to response size
                        // and log the case accordingly.
                        // Unfortunately Laminas Cache does not translate exceptions
                        // to any common error codes, so we must check codes and/or
                        // message for adapter-specific values.
                        // 'ITEM TOO BIG' is the message from the Memcached adapter
                        // and comes directly from libmemcached.
                        if ($ex->getMessage() === 'ITEM TOO BIG') {
                            $this->debug(
                                'Cache setItem failed: ' . $ex->getMessage()
                            );
                        } else {
                            $this->logWarning(
                                'Cache setItem failed: ' . $ex->getMessage()
                            );
                        }
                    } catch (\Exception $ex) {
                        $this->logWarning(
                            'Cache setItem failed: ' . $ex->getMessage()
                        );
                    }
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
            throw HttpErrorException::createFromResponse($response);
        }
        return $response->getBody();
    }
}
