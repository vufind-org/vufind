<?php

/**
 * Central class for connecting to EIT resources used by VuFind.
 *
 * PHP version 8
 *
 * Copyright (C) Julia Bauder 2013.
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
 * @package  Connection
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture Wiki
 */

namespace VuFindSearch\Backend\EIT;

use Laminas\Http\Client;
use VuFindSearch\Backend\Exception\HttpErrorException;
use VuFindSearch\ParamBag;

use function is_array;

/**
 * Central class for connecting to EIT resources used by VuFind.
 *
 * @category VuFind
 * @package  Connection
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture Wiki
 */
class Connector implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base url for searches
     *
     * @var string
     */
    protected $base;

    /**
     * The HTTP_Request object used for REST transactions
     *
     * @var Client
     */
    protected $client;

    /**
     * EBSCO EIT Profile used for authentication
     *
     * @var string
     */
    protected $prof;

    /**
     * Password associated with the EBSCO EIT Profile
     *
     * @var string
     */
    protected $pwd;

    /**
     * Array of 3-character EBSCO database abbreviations to include in search
     *
     * @var array
     */
    protected $dbs = [];

    /**
     * Constructor
     *
     * @param string $base   Base URL
     * @param Client $client HTTP client
     * @param string $prof   Profile
     * @param string $pwd    Password
     * @param string $dbs    Database list (comma-separated abbrevs.)
     */
    public function __construct($base, Client $client, $prof, $pwd, $dbs)
    {
        $this->base = $base;
        $this->client = $client;
        $this->prof = $prof;
        $this->pwd = $pwd;
        $this->dbs = $dbs;
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param int      $offset Search offset
     * @param int      $limit  Search limit
     *
     * @return array
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        $startrec = $offset + 1;
        $params->set('startrec', $startrec);
        $params->set('numrec', $limit);
        $params->set('prof', $this->prof);
        $params->set('pwd', $this->pwd);
        $response = $this->call('GET', $params->getArrayCopy());
        $xml = simplexml_load_string($response);
        $finalDocs = [];
        foreach ($xml->SearchResults->records->rec ?? [] as $doc) {
            $finalDocs[] = simplexml_load_string($doc->asXML());
        }
        return [
            'docs' => $finalDocs,
            'offset' => $offset,
            'total' => (int)$xml->Hits,
        ];
    }

    /**
     * Check for HTTP errors in a response.
     *
     * @param \Laminas\Http\Response $result The response to check.
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     * @return void
     */
    public function checkForHttpError($result)
    {
        if (!$result->isSuccess()) {
            throw HttpErrorException::createFromResponse($result);
        }
    }

    /**
     * Make an API call
     *
     * @param string $method GET or POST
     * @param array  $params Parameters to send
     *
     * @return \SimpleXMLElement
     */
    protected function call($method = 'GET', $params = null)
    {
        $queryString = '';
        if ($params) {
            $query = [];
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

        $dbs = explode(',', $this->dbs);
        $dblist = '';
        foreach ($dbs as $db) {
            $dblist .= '&db=' . $db;
        }

        $url = $this->base . '?' . $queryString . $dblist;
        $this->debug('Connect: ' . $url);

        // Send Request
        $this->client->resetParameters();
        $this->client->setUri($url);
        $result = $this->client->setMethod($method)->send();
        $body = $result->getBody();
        $xml = simplexml_load_string($body);
        $this->debug($this->varDump($xml));
        return $body;
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return array
     */
    public function getRecord($id, ParamBag $params = null)
    {
        $query = 'AN ' . $id;
        $params = $params ?: new ParamBag();
        $params->set('prof', $this->prof);
        $params->set('pwd', $this->pwd);
        $params->set('query', $query);
        $this->client->resetParameters();
        $response = $this->call('GET', $params->getArrayCopy());
        $xml = simplexml_load_string($response);
        $finalDocs = [];
        foreach ($xml->SearchResults->records->rec ?? [] as $doc) {
            $finalDocs[] = simplexml_load_string($doc->asXML());
        }
        return [
            'docs' => $finalDocs,
            'offset' => 0,
            'total' => (int)$xml->Hits,
        ];
    }
}
