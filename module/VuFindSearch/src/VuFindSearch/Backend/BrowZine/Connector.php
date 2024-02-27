<?php

/**
 * BrowZine connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\BrowZine;

use Laminas\Http\Client as HttpClient;

/**
 * BrowZine connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * The base URI for API requests
     *
     * @var string
     */
    protected $base = 'https://api.thirdiron.com/public/v1/';

    /**
     * The HTTP Request client used for API transactions
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * The API access token
     *
     * @var string
     */
    protected $token;

    /**
     * The library ID number to use
     *
     * @var string
     */
    protected $libraryId;

    /**
     * Constructor
     *
     * Sets up the BrowZine Client
     *
     * @param HttpClient $client HTTP client
     * @param string     $token  API access token
     * @param string     $id     Library ID number
     */
    public function __construct(HttpClient $client, $token, $id)
    {
        $this->client = $client;
        $this->token = $token;
        $this->libraryId = $id;
    }

    /**
     * Perform a DOI lookup
     *
     * @param string $doi            DOI
     * @param bool   $includeJournal Include journal data in response?
     *
     * @return mixed
     */
    public function lookupDoi($doi, $includeJournal = false)
    {
        // Documentation says URL encoding of DOI is not necessary.
        return $this->request(
            'articles/doi/' . $doi,
            $includeJournal ? ['include' => 'journal'] : []
        );
    }

    /**
     * Perform an ISSN lookup.
     *
     * @param string|array $issns ISSN(s) to look up.
     *
     * @return mixed
     */
    public function lookupIssns($issns)
    {
        return $this->request('search', ['issns' => implode(',', (array)$issns)]);
    }

    /**
     * Perform a search
     *
     * @param string $query Search query
     *
     * @return mixed
     */
    public function search($query)
    {
        return $this->request('search', compact('query'));
    }

    /**
     * Get a full request URL for a relative path
     *
     * @param string $path URL path for service
     *
     * @return string
     */
    protected function getUri($path)
    {
        return $this->base . 'libraries/' . $this->libraryId . '/' . $path;
    }

    /**
     * Perform an API request and return the response body
     *
     * @param string $path   URL path for service
     * @param array  $params GET parameters
     *
     * @return mixed
     */
    protected function request($path, $params = [])
    {
        $params['access_token'] = $this->token;
        $uri = $this->getUri($path);
        $this->debug('BrowZine request: ' . $uri);
        $this->client->setUri($uri);
        $this->client->setParameterGet($params);
        $result = $this->client->send();
        if ($result->isSuccess()) {
            return json_decode($result->getBody(), true);
        } else {
            $this->debug('API failure; status: ' . $result->getStatusCode());
        }
        return null;
    }
}
