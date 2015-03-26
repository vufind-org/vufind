<?php

/**
 * EDS API record collection.
 *
 * PHP version 5
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\EDS\Response;
use VuFindSearch\Response\AbstractRecordCollection;

/**
 * EDS API record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection extends AbstractRecordCollection
{
    /**
     * Response from API
     *
     * @var array
     */
    protected $response;

    /**
     * Constructor.
     *
     * @param array $response EdsApi response
     *
     * @return void
     */
    public function __construct(array $response)
    {
        $this->response = $response;
        $this->rewind();
    }

    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal()
    {
        $totalHits = 0;
        if (isset($this->response['SearchResult'])
            && isset($this->response['SearchResult']['Statistics'])
            && isset($this->response['SearchResult']['Statistics']['TotalHits'])
        ) {
            $totalHits = $this->response['SearchResult']['Statistics']['TotalHits'];
        }
        return $totalHits;
    }

    /**
     * Return raw available facet information.
     *
     * @return array
     */
    public function getRawFacets()
    {
        return isset($this->response['SearchResult'])
            && isset($this->response['SearchResult']['AvailableFacets'])
            ? $this->response['SearchResult']['AvailableFacets'] : [];
    }

    /**
     * Return available facet information.
     *
     * @return array
     */
    public function getFacets()
    {
        $vufindFacetList = [];
        $facets = isset($this->response['SearchResult'])
            && isset($this->response['SearchResult']['AvailableFacets'])
            ? $this->response['SearchResult']['AvailableFacets'] : [];
        foreach ($facets as $facet) {
            $vufindFacet['displayName'] = $facet['Id'];
            $vufindFacet['displayText'] = $facet['Label'];
            $vufindFacet['fieldName'] = $facet['Id'];
            $values = [];
            foreach ($facet['AvailableFacetValues'] as $availableFacetValue) {
                $values[] = [
                    'value' => $availableFacetValue['Value'],
                    'count' => $availableFacetValue['Count'],
                    'displayText' => $availableFacetValue['Value']
                ];

            }
            $vufindFacet['counts'] = $values;
            $vufindFacetList[$facet['Id']] = $vufindFacet;
        }
        return $vufindFacetList;
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset()
    {
        if (isset($this->response['SearchRequestGet'])
            && !empty($this->response['SearchRequestGet']['QueryString'])
        ) {
            $qsParameters = explode(
                '&', $this->response['SearchRequestGet']['QueryString']
            );
            $page = empty($qsParameters['pagenumber'])
                ? 0 : $qsParameters['pagenumber'];
            $resultsPerPage = empty($qsParameters['resultsperpage'])
                ? 0 : $qsParameters['resultsperpage'];
            return $page * $resultsPerPage;
        }
        return 0;
    }

}