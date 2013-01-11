<?php
/**
 * Class for accessing OCLC WorldCat search API
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
 * @package  WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Connection;
use VuFind\Config\Reader as ConfigReader;

/**
 * WorldCat SRU Search Interface
 *
 * @category VuFind2
 * @package  WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class WorldCat extends SRU
{
    /**
     * OCLC API key
     *
     * @var string
     */
    protected $wskey;

    /**
     * OCLC codes for limiting search results
     *
     * @var string
     */
    protected $limitCodes;

    /**
     * Constructor
     *
     * @param \Zend\Http\Client $client An HTTP client object
     */
    public function __construct(\Zend\Http\Client $client)
    {
        parent::__construct(
            'http://www.worldcat.org/webservices/catalog/search/sru', $client
        );
        $config = ConfigReader::getConfig();
        $this->wskey = isset($config->WorldCat->apiKey)
            ? $config->WorldCat->apiKey : null;
        $this->limitCodes = isset($config->WorldCat->LimitCodes)
            ? $config->WorldCat->LimitCodes : null;
    }

    /**
     * Get holdings information for the specified record.
     *
     * @param string $id Record to obtain holdings for.
     *
     * @throws \Exception
     * @return SimpleXMLElement
     */
    public function getHoldings($id)
    {
        $this->client->resetParameters();
        $uri = "http://www.worldcat.org/webservices/catalog/content/libraries/{$id}";
        $uri .= "?wskey={$this->wskey}&servicelevel=full";
        $this->client->setUri($uri);
        if ($this->debugNeeded()) {
            $this->debug('Connect: ' . $uri);
        }
        $result = $this->client->setMethod('POST')->send();
        $this->checkForHttpError($result);

        return simplexml_load_string($result->getBody());
    }

    /**
     * Retrieve a specific record.
     *
     * @param string $id Record ID to retrieve
     *
     * @throws \Exception
     * @return string    MARC XML
     */
    public function getRecord($id)
    {
        $this->client->resetParameters();
        $uri = 'http://www.worldcat.org/webservices/catalog/content/' . $id;
        $uri .= "?wskey={$this->wskey}&servicelevel=full";
        $this->client->setUri($uri);
        if ($this->debugNeeded()) {
            $this->debug('Connect: ' . $uri);
        }
        $result = $this->client->setMethod('POST')->send();
        $this->checkForHttpError($result);

        return $result->getBody();
    }

    /**
     * Search
     *
     * @param string $query    The search query
     * @param string $oclcCode An OCLC code to exclude from results
     * @param int    $page     The page of records to start with
     * @param int    $limit    The number of records to return per page
     * @param string $sort     The value to be used by for sorting
     *
     * @throws \Exception
     * @return array          An array of query results
     */
    public function search($query, $oclcCode = null, $page = 1, $limit = 10,
        $sort = null
    ) {
        // Exclude current library from results
        if ($oclcCode) {
            $query .= ' not srw.li all "' . $oclcCode . '"';
        }

        // Submit query
        $start = ($page-1) * $limit;
        $params = array('query' => $query,
                        'startRecord' => $start,
                        'maximumRecords' => $limit,
                        'sortKeys' => empty($sort) ? 'relevance' : $sort,
                        'servicelevel' => 'full',
                        'wskey' => $this->wskey);

        // Establish a limitation on searching by OCLC Codes
        if (!empty($this->limitCodes)) {
            $params['oclcsymbol'] = $this->limitCodes;
        }

        return simplexml_load_string($this->call('POST', $params, false));
    }

    /**
     * Build Query string from search parameters
     *
     * @param array $search An array of search parameters
     *
     * @throws \Exception
     * @return string       The query
     */
    public function buildQuery($search)
    {
        $groups   = array();
        $excludes = array();
        if (is_array($search)) {
            $query = '';

            foreach ($search as $params) {
                // Advanced Search
                if (isset($params['group'])) {
                    $thisGroup = array();
                    // Process each search group
                    foreach ($params['group'] as $group) {
                        // Build this group individually as a basic search
                        $thisGroup[] = $this->buildQuery(array($group));
                    }
                    // Is this an exclusion (NOT) group or a normal group?
                    if ($params['group'][0]['bool'] == 'NOT') {
                        $excludes[] = join(" OR ", $thisGroup);
                    } else {
                        $groups[]
                            = join(" ".$params['group'][0]['bool']." ", $thisGroup);
                    }
                }

                // Basic Search
                if (isset($params['lookfor']) && $params['lookfor'] != '') {
                    // Clean and validate input -- note that index may be in a
                    // different field depending on whether this is a basic or
                    // advanced search.
                    $lookfor = str_replace('"', '', $params['lookfor']);
                    if (isset($params['field'])) {
                        $index = $params['field'];
                    } else if (isset($params['index'])) {
                        $index = $params['index'];
                    } else {
                        $index = 'srw.kw';
                    }

                    // The index may contain multiple parts -- we want to search all
                    // listed index fields:
                    $index = explode(':', $index);
                    $clauses = array();
                    foreach ($index as $currentIndex) {
                        $clauses[] = "{$currentIndex} all \"{$lookfor}\"";
                    }
                    $query .= '(' . implode(' OR ', $clauses) . ')';
                }
            }
        }

        // Put our advanced search together
        if (count($groups) > 0) {
            $query = "(" . join(") " . $search[0]['join'] . " (", $groups) . ")";
        }
        // and concatenate exclusion after that
        if (count($excludes) > 0) {
            $query .= " NOT ((" . join(") OR (", $excludes) . "))";
        }

        // Ensure we have a valid query to this point
        return isset($query) ? $query : '';
    }
}
