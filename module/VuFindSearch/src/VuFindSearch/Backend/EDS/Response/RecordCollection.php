<?php

/**
 * EDS API record collection.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\EDS\Response;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * EDS API record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
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
     * Facet fields.
     *
     * @var array
     */
    protected $facetFields = null;

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
        return $this->response['SearchResult']['Statistics']['TotalHits'] ?? 0;
    }

    /**
     * Return raw available facet information.
     *
     * @return array
     */
    public function getRawFacets()
    {
        return $this->response['SearchResult']['AvailableFacets'] ?? [];
    }

    /**
     * Return available facets.
     *
     * Returns an associative array with the field name as key. The value is an
     * associative array of available facets for the field, indexed by facet value.
     *
     * @return array
     */
    public function getFacets()
    {
        if ($this->facetFields === null) {
            $this->facetFields = [];
            $facets = $this->response['SearchResult']['AvailableFacets'] ?? [];
            foreach ($facets as $facet) {
                $values = [];
                foreach ($facet['AvailableFacetValues'] as $facetValue) {
                    $values[$facetValue['Value']] = $facetValue['Count'];
                }
                $this->facetFields[$facet['Id']] = $values;
            }
        }
        return $this->facetFields;
    }

    /**
     * Set facets.
     *
     * @param array $facets Facet fields
     *
     * @return void
     */
    public function setFacets(array $facets): void
    {
        $this->facetFields = $facets;
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset()
    {
        if (
            isset($this->response['SearchRequestGet'])
            && !empty($this->response['SearchRequestGet']['QueryString'])
        ) {
            $qsParameters = explode(
                '&',
                $this->response['SearchRequestGet']['QueryString']
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
