<?php
/**
 * EDS API Results
 *
 * PHP version 7
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\EDS;

/**
 * EDS API Results
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'EDS';

    /**
     * Facet list
     *
     * @var array
     */
    protected $responseFacets;

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $collection = $this->getSearchService()->search(
            $this->backendId,
            $query,
            $offset,
            $limit,
            $params
        );
        if (null != $collection) {
            $this->responseFacets = $collection->getFacets();
            $this->resultTotal = $collection->getTotal();

            // Add a publication date facet
            $this->responseFacets[] = [
                'fieldName' => 'PublicationDate',
                'displayName' => 'PublicationDate',
                'displayText' => 'Publication Date',
                'counts' => []
            ];

            // Construct record drivers for all the items in the response:
            $this->results = $collection->getRecords();
        }
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }
        return $this->buildFacetList($this->responseFacets, $filter);
    }
}
