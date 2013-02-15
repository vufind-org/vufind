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
     * Last request.
     *
     * @see self::resubmit()
     *
     * @var array
     */
    protected $lastRequest;

    /**
     * Class of HTTP client adapter to use.
     *
     * @see self::sendRequest()
     *
     * @var string
     */
    protected $httpAdapterClass = 'Zend\Http\Client\Adapter\Socket';

    /**
     * Constructor
     *
     * @param string $url SOLR base URL
     *
     * @return void
     */
    public function __construct($url)
    {
        $this->url = $url;
    }

    /// Public API

    /**
     * Return document specified by id.
     *
     * @param string $id The document to retrieve from Solr
     *
     * @return string
     */
    public function retrieve ($id)
    {
        $params = new ParamBag();
        $params->set('q', sprintf('id:"%s"', addcslashes($id, '"')));
        $result = $this->select($params);
        return $result;
    }

    /**
     * Return records similar to a given record specified by id.
     *
     * Uses MoreLikeThis Request Handler
     *
     * @param string $id Id of given record
     *
     * @return string
     */
    public function similar ($id)
    {
        $params = new ParamBag();
        $params->set('q', sprintf('id:"%s"', addcslashes($id, '"')));
        $params->set('qt', 'morelikethis');
        return $this->select($params);
    }

    /**
     * Execute a search.
     *
     * @param AbstractQuery $query        Search query
     * @param Params        $params       Search parameters
     * @param QueryBuilder  $queryBuilder Query builder
     *
     * @return string
     */
    public function search (AbstractQuery $query, Params $params, QueryBuilder $queryBuilder)
    {
        $queryParams = new ParamBag();
        $queryParams->set('start', $params->getOffset());
        $queryParams->set('rows', $params->getLimit());
        $queryParams->set('sort', $params->getSort());

        $queryParams->mergeWith($queryBuilder->build($query, $params));

        return $this->select($queryParams);
    }

    /**
     * Return the current query invariants.
     *
     * @return ParamBag
     */
    public function getQueryInvariants ()
    {
        if (!$this->invariants) {
            $this->invariants = new ParamBag(array('wt' => 'json', 'json.nl' => 'arrarr', 'fl' => '*,score'));
        }
        return $this->invariants;
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
    public function getLastRequestParameters ()
    {
        return $this->lastRequest['parameters'];
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
     * Set adapter class of HTTP client.
     *
     * Keep in mind that a proxy service might replace the client adapter by a
     * Proxy adapter if necessary.
     *
     * @param string $adapterClass Name of adapter class
     *
     * @return void
     */
    public function setHttpAdapterClass ($adapterClass = 'Zend\Http\Client\Adapter\Socket')
    {
        $this->httpAdapterClass = $adapterClass;
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
        $this->prepare($params);
        $result = $this->sendRequest('select', $params);
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

        $this->prepare($params);

        $result = $this->sendRequest('browse', $params);
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

        $this->prepare($params);

        $result = $this->sendRequest('term', $params);
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
     * @return void
     */
    protected function prepare (ParamBag $params)
    {
        $params->mergeWith($this->getQueryInvariants());
        return;
    }

    /**
     * Repeat the last request with potentially modified parameters.
     *
     * @see self::getLastRequestParameters()
     *
     * @return void
     */
    public function resubmit ()
    {
        $last = $this->lastRequest;
        return $this->sendRequest($last['handler'], $last['parameters'], $last['method']);
    }

    /**
     * Send request to SOLR and return the response.
     *
     * @param string   $handler SOLR request handler to use
     * @param ParamBag $params  Request parameters
     * @param string   $method  Request method
     *
     * @return Zend\Http\Response
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function sendRequest ($handler, ParamBag $params, $method = Request::METHOD_GET)
    {
        $client = new HttpClient();
        $client->setAdapter($this->httpAdapterClass);
        $client->setMethod($method);
        $client->setOptions(array('timeout' => $this->timeout));

        $url    = $this->url . '/' . $handler;

        $paramString = implode('&', $params->request());
        if (strlen($paramString) > self::MAX_GET_URL_LENGTH) {
            $method = Request::METHOD_POST;
        }

        if ($method === Request::METHOD_POST) {
            $client->setUri($url);
            $client->setRawBody($paramString);
            $client->setHeaders(array('Content-Type' => Request::ENC_URLENCODED, 'Content-Length' => strlen($paramString)));
        } else {
            $url = $url . '?' . $paramString;
            $client->setUri($url);
        }

        if ($this->proxy) {
            $this->proxy->proxify($client);
        }

        if ($this->logger) {
            $this->logger->debug(sprintf('=> %s %s', $client->getMethod(), $client->getUri()), array('params' => $params->request()));
        }

        $this->lastRequest = array('parameters' => $params, 'handler' => $handler, 'method' => $method);

        $response = $client->send();

        if ($this->logger) {
            $this->logger->debug(sprintf('<= %s %s', $response->getStatusCode(), $response->getReasonPhrase()));
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
}
