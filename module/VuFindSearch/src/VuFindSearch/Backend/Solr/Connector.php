<?php

/**
 * SOLR connector.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;

use VuFindSearch\ParamBag;
use VuFindSearch\Query\Params;

use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Exception\RequestErrorException;

use Zend\Http\Request;
use Zend\Http\Client as HttpClient;

use Zend\Log\LoggerInterface;

use InvalidArgumentException;

/**
 * SOLR connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Connector
{
    /**
     * Maximum length of a GET url.
     *
     * Switches to POST if the SOLR target URL exeeds this length.
     *
     * @see self::sendRequest()
     *
     * @var integer
     */
    const MAX_GET_URL_LENGTH = 2048;

    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * URL of SOLR core.
     *
     * @var string
     */
    protected $url;

    /**
     * HTTP read timeout.
     *
     * @var float
     */
    protected $timeout = 30;

    /**
     * Proxy service
     *
     * @var mixed
     */
    protected $proxy;

    /**
     * Query invariants.
     *
     * @var ParamBag
     */
    protected $invariants;

    /**
     * Query defaults.
     *
     * @var ParamBag
     */
    protected $defaults;

    /**
     * Query appends.
     *
     * @var ParamBag
     */
    protected $appends;

    /**
     * Last query.
     *
     * @see self::resubmit()
     *
     * @var array
     */
    protected $lastQuery;

    /**
     * HTTP client adapter.
     *
     * Either the class name or a adapter instance.
     *
     * @var string|AdapterInterface
     */
    protected $adapter = 'Zend\Http\Client\Adapter\Socket';

    /**
     * Constructor
     *
     * @param string $url SOLR base URL
     *
     * @return void
     */
    public function __construct ($url)
    {
        $this->invariants = new ParamBag();
        $this->defaults   = new ParamBag();
        $this->appends    = new ParamBag();
        $this->url        = $url;
    }

    /// Public API

    /**
     * Return document specified by id.
     *
     * @param string   $id     The document to retrieve from Solr
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function retrieve ($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params->set('q', sprintf('id:"%s"', addcslashes($id, '"')));
        $result = $this->select($params);
        return $result;
    }

    /**
     * Return records similar to a given record specified by id.
     *
     * Uses MoreLikeThis Request Handler
     *
     * @param string   $id     Id of given record
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function similar ($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params->set('q', sprintf('id:"%s"', addcslashes($id, '"')));
        $params->set('qt', 'morelikethis');
        return $this->select($params);
    }

    /**
     * Execute a search.
     *
     * @param AbstractQuery $query        Search query
     * @param integer       $offset       Search offset
     * @param integer       $limit        Search limit
     * @param QueryBuilder  $queryBuilder Query builder
     * @param ParamBag      $params       Parameters
     *
     * @return string
     */
    public function search (AbstractQuery $query, $offset, $limit, QueryBuilder $queryBuilder, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params->set('start', $offset);
        $params->set('rows', $limit);

        $params->mergeWith($queryBuilder->build($query));

        return $this->select($params);
    }

    /**
     * Return the current query invariants.
     *
     * @return ParamBag
     */
    public function getQueryInvariants ()
    {
        return $this->invariants;
    }

    /**
     * Return query defaults.
     *
     * @return ParamBag
     */
    public function getQueryDefaults ()
    {
        return $this->defaults;
    }

    /**
     * Return query appends.
     *
     * @return ParamBag
     */
    public function getQueryAppends ()
    {
        return $this->appends;
    }

    /**
     * Set the query invariants.
     *
     * @param array $invariants Query invariants
     *
     * @return void
     */
    public function setQueryInvariants (array $invariants)
    {
        $this->invariants = new ParamBag($invariants);
    }

    /**
     * Set the query defaults.
     *
     * @param array $defaults Query defaults
     *
     * @return void
     */
    public function setQueryDefaults (array $defaults)
    {
        $this->defaults = new ParamBag($defaults);
    }

    /**
     * Set the query appends.
     *
     * @param array $appends Query appends
     *
     * @return void
     */
    public function setQueryAppends (array $appends)
    {
        $this->appends = new ParamBag($appends);
    }

    /**
     * Add a query invariant.
     *
     * @param string $parameter Query parameter
     * @param string $value     Query parameter value
     *
     * @return void
     */
    public function addQueryInvariant ($parameter, $value)
    {
        $this->getQueryInvariants()->add($parameter, $value);
    }

    /**
     * Add a query default.
     *
     * @param string $parameter Query parameter
     * @param string $value     Query parameter value
     *
     * @return void
     */
    public function addQueryDefault ($parameter, $value)
    {
        $this->getQueryDefaults()->add($parameter, $value);
    }

    /**
     * Add a query append.
     *
     * @param string $parameter Query parameter
     * @param string $value     Query parameter value
     *
     * @return void
     */
    public function addQueryAppend ($parameter, $value)
    {
        $this->getQueryAppends()->add($parameter, $value);
    }

    /**
     * Set logger instance.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger (LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return parameters of the last request.
     *
     * @return ParamBag
     */
    public function getLastQueryParameters ()
    {
        return $this->lastQuery['parameters'];
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
    public function setProxy ($proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Set the HTTP connect timeout.
     *
     * @param float $timeout Timeout in seconds
     *
     * @return void
     */
    public function setTimeout ($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Set HTTP client adapter.
     *
     * Keep in mind that a proxy service might replace the client adapter by a
     * Proxy adapter if necessary.
     *
     * @param string|AdapterInterface $adapterClass Adapter or name of adapter class
     *
     * @return void
     */
    public function setAdapter ($adapter)
    {
        if (is_object($adapter) && (!$adapter instanceOf AdapterInterface)) {
            throw new InvalidArgumentException(sprintf('HTTP client adapter must implement AdapterInterface: %s', get_class($adapter)));
        }
        $this->adapter = $adapter;
    }

    /// Internal API

    /**
     * Send request to `select' query handler and return response.
     *
     * @param ParamBag $params Request parameters
     *
     * @return array
     */
    protected function select (ParamBag $params)
    {
        $params = $this->prepare($params);
        $result = $this->query('select', $params);
        return $result;
    }

    /**
     * Obtain information from an alphabetic browse index.
     *
     * @param string $source    Name of index to search
     * @param string $from      Starting point for browse results
     * @param int    $page      Result page to return (starts at 0)
     * @param int    $page_size Number of results to return on each page
     *
     * @return string
     *
     * @todo Deserialize and process the response in backend
     */
    public function alphabeticBrowse ($source, $from, $page, $page_size = 20)
    {
        $params = new ParamBag();
        $params->set('from', $from);
        $params->set('offset', $page * $page_size);
        $params->set('rows', $page_size);
        $params->set('source', $source);

        $url = $this->url . '/browse';

        $params = $this->prepare($params);
        $result = $this->query('browse', $params);
        return $result;
    }

    /**
     * Extract terms from the Solr index.
     *
     * @param string $field Field to extract terms from
     * @param string $start Starting term to extract (blank for beginning of list)
     * @param int    $limit Maximum number of terms to return (-1 for no limit)
     *
     * @return string
     *
     * @todo Deserialize and process the response in backend
     */
    public function getTerms ($field, $start, $limit)
    {
        $params = new ParamBag();
        $params->set('terms', 'true');
        $params->set('terms.lower.incl', 'false');
        $params->set('terms.fl', $field);
        $params->set('terms.lower', $start);
        $params->set('terms.limit', $limit);
        $params->set('terms.sort', 'index');

        $params = $this->prepare($params);
        $result = $this->query('term', $params);
        return $result;
    }

    /// Internal API

    /**
     * Prepare final request parameters.
     *
     * This function is called right before the request is send. Adds the
     * invariants of our SOLR queries.
     *
     * @param ParamBag $params Parameters
     *
     * @return ParamBag
     */
    protected function prepare (ParamBag $params)
    {
        $params     = $params->getArrayCopy();
        $invariants = $this->getQueryInvariants()->getArrayCopy();
        $defaults   = $this->getQueryDefaults()->getArrayCopy();
        $appends    = $this->getQueryAppends()->getArrayCopy();

        $params = array_replace($defaults, $params);
        $params = array_replace($params, $invariants);
        $params = array_merge_recursive($params, $appends);

        return new ParamBag($params);
    }

    /**
     * Repeat the last request with potentially modified parameters.
     *
     * @param ParamBag $params Request parameters
     *
     * @return string
     */
    public function resubmit (ParamBag $params)
    {
        $last   = $this->lastQuery;
        $params = $this->prepare($params);
        return $this->query($last['handler'], $params, $last['method']);
    }

    /**
     * Send query to SOLR and return response body.
     *
     * @param string   $handler SOLR request handler to use
     * @param ParamBag $params  Request parameters
     *
     * @return string Response body
     */
    protected function query ($handler, ParamBag $params)
    {

        $url         = $this->url . '/' . $handler;
        $paramString = implode('&', $params->request());
        if (strlen($paramString) > self::MAX_GET_URL_LENGTH) {
            $method = Request::METHOD_POST;
        } else {
            $method = Request::METHOD_GET;
        }

        if ($method === Request::METHOD_POST) {
            $client = $this->createClient($url, $method);
            $client->setRawBody($paramString);
            $client->setEncType(HttpClient::ENC_URLENCODED);
            $client->setHeaders(array('Content-Length' => strlen($paramString)));
        } else {
            $url = $url . '?' . $paramString;
            $client = $this->createClient($url, $method);
        }

        if ($this->logger) {
            $this->logger->debug(sprintf('Query %s', $paramString));
        }
        $this->lastQuery = array('parameters' => $params, 'handler' => $handler, 'method' => $method);
        return $this->send($client);
    }

    /**
     * Send request the SOLR and return the response.
     *
     * @param HttpClient $client Prepare HTTP client
     *
     * @return string Response body
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function send (HttpClient $client)
    {
        if ($this->logger) {
            $this->logger->debug(sprintf('=> %s %s', $client->getMethod(), $client->getUri()));
        }

        $time     = microtime(true);
        $response = $client->send();
        $time     = microtime(true) - $time;

        if ($this->logger) {
            $this->logger->debug(sprintf('<= %s %s', $response->getStatusCode(), $response->getReasonPhrase()), array('time' => $time));
        }

        if (!$response->isSuccess()) {
            $status = $response->getStatusCode();
            $phrase = $response->getReasonPhrase();
            if ($status >= 500) {
                throw new RemoteErrorException($phrase, $status);
            } else {
                throw new RequestErrorException($phrase, $status);
            }
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
    protected function createClient ($url, $method)
    {
        $client = new HttpClient();
        $client->setAdapter($this->adapter);
        $client->setOptions(array('timeout' => $this->timeout));
        $client->setUri($url);
        $client->setMethod($method);
        if ($this->proxy) {
            $this->proxy->proxify($client);
        }
        return $client;
    }
}
