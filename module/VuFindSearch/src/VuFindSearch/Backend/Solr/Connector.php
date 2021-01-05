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

use InvalidArgumentException;

use Laminas\Http\Client\Adapter\AdapterInterface;
use Laminas\Http\Client\Adapter\Exception\TimeoutException;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request;

use VuFindSearch\Backend\Exception\BackendException;

use VuFindSearch\Backend\Exception\HttpErrorException;

use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Exception\RequestErrorException;
use VuFindSearch\Backend\Solr\Document\AbstractDocument;
use VuFindSearch\ParamBag;

use VuFindSearch\Query\Query;

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
    const MAX_GET_URL_LENGTH = 2048;

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
     * HTTP read timeout.
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Proxy service
     *
     * @var mixed
     */
    protected $proxy;

    /**
     * HTTP client adapter.
     *
     * Either the class name or a adapter instance.
     *
     * @var string|AdapterInterface
     */
    protected $adapter = 'Laminas\Http\Client\Adapter\Socket';

    /**
     * Constructor
     *
     * @param string|array $url       SOLR core URL or an array of alternative URLs
     * @param HandlerMap   $map       Handler map
     * @param string       $uniqueKey Solr field used to store unique identifier
     *
     * @return void
     */
    public function __construct($url, HandlerMap $map, $uniqueKey = 'id')
    {
        $this->url = $url;
        $this->map = $map;
        $this->uniqueKey = $uniqueKey;
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

        return $this->query($handler, $params);
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
        return $this->query($handler, $params);
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
        return $this->query($handler, $params);
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

        return $this->query($handler, $params);
    }

    /**
     * Write to the SOLR index.
     *
     * @param AbstractDocument $document Document to write
     * @param string           $format   Serialization format, either 'json' or 'xml'
     * @param string           $handler  Update handler
     * @param ParamBag         $params   Update handler parameters
     *
     * @return string Response body
     */
    public function write(AbstractDocument $document, $format = 'xml',
        $handler = 'update', ParamBag $params = null
    ) {
        $params = $params ?: new ParamBag();
        $urlSuffix = "/{$handler}";
        if (count($params) > 0) {
            $urlSuffix .= '?' . implode('&', $params->request());
        }
        $callback = function ($client) use ($document, $format) {
            switch ($format) {
            case 'xml':
                $client->setEncType('text/xml; charset=UTF-8');
                $body = $document->asXML();
                break;
            case 'json':
                $client->setEncType('application/json');
                $body = $document->asJSON();
                break;
            default:
                throw new InvalidArgumentException(
                    "Unable to serialize to selected format: {$format}"
                );
            }
            $client->setRawBody($body);
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Content-Length', strlen($body));
        };
        return $this->trySolrUrls('POST', $urlSuffix, $callback);
    }

    /**
     * Set the HTTP proxy service.
     *
     * @param mixed $proxy Proxy service
     *
     * @return void
     *
     * @todo Typehint on ProxyInterface
     */
    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Get the HTTP connect timeout.
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the HTTP connect timeout.
     *
     * @param int $timeout Timeout in seconds
     *
     * @return void
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Set HTTP client adapter.
     *
     * Keep in mind that a proxy service might replace the client adapter by a
     * Proxy adapter if necessary.
     *
     * @param string|AdapterInterface $adapter Adapter or name of adapter class
     *
     * @return void
     */
    public function setAdapter($adapter)
    {
        if (is_object($adapter) && (!$adapter instanceof AdapterInterface)) {
            throw new InvalidArgumentException(
                sprintf(
                    'HTTP client adapter must implement AdapterInterface: %s',
                    get_class($adapter)
                )
            );
        }
        $this->adapter = $adapter;
    }

    /// Internal API

    /**
     * Send query to SOLR and return response body.
     *
     * @param string   $handler SOLR request handler to use
     * @param ParamBag $params  Request parameters
     *
     * @return string Response body
     */
    public function query($handler, ParamBag $params)
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
        return $this->trySolrUrls($method, $urlSuffix, $callback);
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
     *
     * @return string Response body
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function trySolrUrls($method, $urlSuffix, $callback = null)
    {
        // This exception should never get thrown; it's just a safety in case
        // something unanticipated occurs.
        $exception = new \Exception('Unexpected exception.');

        // Loop through all base URLs and try them in turn until one works.
        foreach ((array)$this->url as $base) {
            $client = $this->createClient($base . $urlSuffix, $method);
            if (is_callable($callback)) {
                $callback($client);
            }
            try {
                return $this->send($client);
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
                '<= %s %s', $response->getStatusCode(),
                $response->getReasonPhrase()
            ), ['time' => $time]
        );

        if (!$response->isSuccess()) {
            throw HttpErrorException::createFromResponse($response);
        }
        return $response->getBody();
    }

    /**
     * Create the HTTP client.
     *
     * @param string $url    Target URL
     * @param string $method Request method
     *
     * @return HttpClient
     */
    protected function createClient($url, $method)
    {
        $client = new HttpClient();
        $client->setAdapter($this->adapter);
        $client->setOptions(['timeout' => $this->timeout]);
        $client->setUri($url);
        $client->setMethod($method);
        if ($this->proxy) {
            $this->proxy->proxify($client);
        }
        return $client;
    }
}
