<?php

/**
 * LibGuides connector.
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
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\LibGuides;

use Zend\Http\Client as HttpClient;

/**
 * LibGuides connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Chelsea Lobdell <clobdel1@swarthmore.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * The HTTP_Request object used for API transactions
     *
     * @var HttpClient
     */
    public $client;

    /**
     * Institution code
     *
     * @var string
     */
    protected $iid;

    /**
     * Base URL for API
     *
     * @var string
     */
    protected $host;

    /**
     * API version number
     *
     * @var float
     */
    protected $apiVersion;

    /**
     * Constructor
     *
     * Sets up the LibGuides Client
     *
     * @param string     $iid        Institution ID
     * @param HttpClient $client     HTTP client
     * @param float      $apiVersion API version number
     * @param string     $baseUrl    API base URL (optional)
     */
    public function __construct($iid, $client, $apiVersion = 1, $baseUrl = null)
    {
        $this->apiVersion = $apiVersion;
        if (empty($baseUrl)) {
            $this->host = ($this->apiVersion < 2)
                ? "http://api.libguides.com/api_search.php?"
                : "http://lgapi.libapps.com/widgets.php?";
        } else {
            // Ensure appropriate number of question marks:
            $this->host = rtrim($baseUrl, '?') . '?';
        }
        $this->iid = $iid;
        $this->client = $client;
    }

    /**
     * Execute a search.  adds all the querystring parameters into
     * $this->client and returns the parsed response
     *
     * @param array $params    Incoming search parameters.
     * @param int   $offset    Search offset
     * @param int   $limit     Search limit
     * @param bool  $returnErr Should we return errors in a structured way (true)
     * or simply throw an exception (false)?
     *
     * @throws \Exception
     * @return array             An array of query results
     */
    public function query(array $params, $offset = 0, $limit = 20, $returnErr = true)
    {
        $args = $this->prepareParams($params);

        // run search, deal with exceptions
        try {
            $result = $this->call(http_build_query($args));
            $result['documents']
                = array_slice($result['documents'], $offset, $limit);
        } catch (\Exception $e) {
            if ($returnErr) {
                $this->debug($e->getMessage());
                $result = [
                    'recordCount' => 0,
                    'documents' => [],
                    'error' => $e->getMessage()
                ];
            } else {
                throw $e;
            }
        }
        $result['offset'] = $offset;
        $result['limit'] = $limit;
        return $result;
    }

    /**
     * Small wrapper for sendRequest, process to simplify error handling.
     *
     * @param string $qs     Query string
     * @param string $method HTTP method
     *
     * @return object    The parsed data
     * @throws \Exception
     */
    protected function call($qs, $method = 'GET')
    {
        $this->debug("{$method}: {$this->host}{$qs}");
        $this->client->resetParameters();
        if ($method == 'GET') {
            $baseUrl = $this->host . $qs;
        } elseif ($method == 'POST') {
            throw new \Exception('POST not supported');
        }

        // Send Request
        $this->client->setUri($baseUrl);
        $result = $this->client->setMethod($method)->send();
        if (!$result->isSuccess()) {
            throw new \Exception($result->getBody());
        }
        return $this->process($result->getBody());
    }

    /**
     * Translate API response into more convenient format.
     *
     * @param array $data The raw response
     *
     * @return array      The processed response
     */
    protected function process($data)
    {
        // make sure data exists
        if (strlen($data) == 0) {
            throw new \Exception('LibGuides did not return any data');
        }

        $items = [];

        // Extract titles and URLs from response:
        $regex = '/<a href="([^"]*)"[^>]*>([^<]*)</';
        $count = preg_match_all($regex, $data, $matches);

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'id' => $matches[1][$i],    // ID = URL
                'title' => $matches[2][$i],
            ];
        }

        $results = [
            'recordCount' => count($items),
            'documents' => $items
        ];

        return $results;
    }

    /**
     * Prepare API parameters
     *
     * @param array $params Incoming parameters
     *
     * @return array
     */
    protected function prepareParams(array $params)
    {
        // defaults for params (vary by version)
        if ($this->apiVersion < 2) {
            $args = [
                'iid' => $this->iid,
                'type' => 'guides',
                'more' => 'false',
                'sortby' => 'relevance',
            ];
        } else {
            $args = [
                'site_id' => $this->iid,
                'sort_by' => 'relevance',
                'widget_type' => 1,
                'search_match' => 2,
                'search_type' => 0,
                'sort_by' => 'relevance',
                'list_format' => 1,
                'output_format' => 1,
                'load_type' => 2,
                'enable_description' => 0,
                'enable_group_search_limit' => 0,
                'enable_subject_search_limit' => 0,
                'widget_embed_type' => 2,
            ];
            // remap v1 --> v2 params:
            if (isset($params['search'])) {
                $params['search_terms'] = $params['search'];
                unset($params['search']);
            }
        }
        return array_merge($args, $params);
    }
}
