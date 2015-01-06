<?php
/**
 * SRU Search Interface
 *
 * PHP version 5
 *
 * Copyright (C) Andrew Nagy 2008.
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
 * @package  SRU
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFindSearch\Backend\SRU;

use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFindSearch\Backend\Exception\BackendException;

use VuFind\XSLT\Processor as XSLTProcessor;

use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

/**
 * SRU Search Interface
 *
 * @category VuFind2
 * @package  SRU
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Connector implements LoggerAwareInterface
{
    /**
     * Logger object for debug info (or false for no debugging).
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Whether to Serialize to a PHP Array or not.
     *
     * @var bool
     */
    protected $raw = false;

    /**
     * The HTTP_Request object used for REST transactions
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * The host to connect to
     *
     * @var string
     */
    protected $host;

    /**
     * The version to specify in the URL
     *
     * @var string
     */
    protected $sruVersion = '1.1';

    /**
     * Constructor
     *
     * Sets up the SOAP Client
     *
     * @param string            $host   The URL of the SRU Server
     * @param \Zend\Http\Client $client An HTTP client object
     */
    public function __construct($host, \Zend\Http\Client $client)
    {
        // Initialize properties needed for HTTP connection:
        $this->host = $host;
        $this->client = $client;
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * Get records similar to one record
     *
     * @param array  $record An associative array of the record data
     * @param string $id     The record id
     * @param int    $max    The maximum records to return; Default is 5
     *
     * @return array         An array of query results
     */
    public function getMoreLikeThis($record, $id, $max = 5)
    {
        // More Like This Query
        $query = 'title="' . $record['245']['a'] . '" ' .
                 "NOT rec.id=$id";

        // Query String Parameters
        $options = array('operation' => 'searchRetrieve',
                         'query' => $query,
                         'maximumRecords' => $max,
                         'startRecord' => 1,
                         'recordSchema' => 'marcxml');

        $this->debug('More Like This Query: ' . print_r($query, true));

        return $this->call('GET', $options);
    }

    /**
     * Scan
     *
     * @param string $clause   The CQL clause specifying the start point
     * @param int    $pos      The position of the start point in the response
     * @param int    $maxTerms The maximum number of terms to return
     *
     * @return string          XML response
     */
    public function scan($clause, $pos = null, $maxTerms = null)
    {
        $options = array('operation' => 'scan',
                         'scanClause' => $clause);
        if (!is_null($pos)) {
            $options['responsePosition'] = $pos;
        }
        if (!is_null($maxTerms)) {
            $options['maximumTerms'] = $maxTerms;
        }

        return $this->call('GET', $options, false);
    }

    /**
     * Search
     *
     * @param string $query   The search query
     * @param string $start   The record to start with
     * @param string $limit   The amount of records to return
     * @param string $sortBy  The value to be used by for sorting
     * @param string $schema  Record schema to use in results list
     * @param bool   $process Process into array (true) or return raw (false)
     *
     * @return array          An array of query results
     */
    public function search($query, $start = 1, $limit = null, $sortBy = null,
        $schema = 'marcxml', $process = true
    ) {
        $this->debug('Query: ' . print_r($query, true));

        // Query String Parameters
        $options = array('operation' => 'searchRetrieve',
                         'query' => $query,
                         'startRecord' => ($start) ? $start : 1,
                         'recordSchema' => $schema);
        if (!is_null($limit)) {
            $options['maximumRecords'] = $limit;
        }
        if (!is_null($sortBy)) {
            $options['sortKeys'] = $sortBy;
        }

        return $this->call('GET', $options, $process);
    }

    /**
     * Check for HTTP errors in a response.
     *
     * @param \Zend\Http\Response $result The response to check.
     *
     * @throws BackendException
     * @return void
     */
    public function checkForHttpError($result)
    {
        if (!$result->isSuccess()) {
            throw HttpErrorException::createFromResponse($result);
        }
    }

    /**
     * Submit REST Request
     *
     * @param string $method  HTTP Method to use: GET or POST
     * @param array  $params  An array of parameters for the request
     * @param bool   $process Should we convert the MARCXML?
     *
     * @return string|SimpleXMLElement The response from the XServer
     */
    protected function call($method = 'GET', $params = null, $process = true)
    {
        if ($params) {
            $query = array('version='.$this->sruVersion);
            foreach ($params as $function => $value) {
                if (is_array($value)) {
                    foreach ($value as $additional) {
                        $additional = urlencode($additional);
                        $query[] = "$function=$additional";
                    }
                } else {
                    $value = urlencode($value);
                    $query[] = "$function=$value";
                }
            }
            $queryString = implode('&', $query);
        }

        $this->debug('Connect: ' . print_r($this->host . '?' . $queryString, true));

        // Send Request
        $this->client->resetParameters();
        $this->client->setUri($this->host . '?' . $queryString);
        $result = $this->client->setMethod($method)->send();
        $this->checkForHttpError($result);

        // Return processed or unprocessed response, as appropriate:
        return $process ? $this->process($result->getBody()) : $result->getBody();
    }

    /**
     * Process an SRU response.  Returns either the raw XML string or a
     * SimpleXMLElement based on the contents of the class' raw property.
     *
     * @param string $response SRU response
     *
     * @return string|SimpleXMLElement
     */
    protected function process($response)
    {
        // Send back either the raw XML or a SimpleXML object, as requested:
        $result = XSLTProcessor::process('sru-convert.xsl', $response);
        if (!$result) {
            throw new BackendException(
                sprintf('Error processing SRU response: %20s', $response)
            );
        }
        return $this->raw ? $result : simplexml_load_string($result);
    }
}
