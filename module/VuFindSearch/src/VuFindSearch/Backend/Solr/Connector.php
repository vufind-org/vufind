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

use VuFindSearch\Backend\Exception\HttpErrorException;

use VuFindSearch\Backend\Solr\Document\AbstractDocument;

use Zend\Http\Request;
use Zend\Http\Client as HttpClient;
use Zend\Http\Client\Adapter\AdapterInterface;

use Zend\Log\LoggerInterface;

use InvalidArgumentException;
use XMLWriter;

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
     * @see self::query()
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
    protected $adapter = 'Zend\Http\Client\Adapter\Socket';

    /**
     * Constructor
     *
     * @param string     $url       SOLR base URL
     * @param HandlerMap $map       Handler map
     * @param string     $uniqueKey Solr field used to store unique identifier
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
     * Uses MoreLikeThis Request Handler
     *
     * @param string   $id     Id of given record
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function similar($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params
            ->set('q', sprintf('%s:"%s"', $this->uniqueKey, addcslashes($id, '"')));
        $params->set('qt', 'morelikethis');

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
        $url    = "{$this->url}/{$handler}";
        if (count($params) > 0) {
            $url .= '?' . implode('&', $params->request());
        }
        $client = $this->createClient($url, 'POST');
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
        return $this->send($client);
    }

    /**
     * Set logger instance.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        if (is_object($adapter) && (!$adapter instanceOf AdapterInterface)) {
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
    protected function send(HttpClient $client)
    {
        if ($this->logger) {
            $this->logger->debug(
                sprintf('=> %s %s', $client->getMethod(), $client->getUri())
            );
        }

        $time     = microtime(true);
        $response = $client->send();
        $time     = microtime(true) - $time;

        if ($this->logger) {
            $this->logger->debug(
                sprintf(
                    '<= %s %s', $response->getStatusCode(),
                    $response->getReasonPhrase()
                ), array('time' => $time)
            );
        }

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
        $client->setOptions(array('timeout' => $this->timeout));
        $client->setUri($url);
        $client->setMethod($method);
        if ($this->proxy) {
            $this->proxy->proxify($client);
        }
        return $client;
    }
}
