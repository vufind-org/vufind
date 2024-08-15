<?php

/**
 * WorldCat v2 Search Results
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Search_WorldCat2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\WorldCat2;

use VuFindSearch\Command\SearchCommand;

use function strlen;

/**
 * WorldCat v2 Search Parameters
 *
 * @category VuFind
 * @package  Search_WorldCat2
 * @author   Demian Katz <demian.katz@villanova.edu>
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
    protected $backendId = 'WorldCat2';

    /**
     * Facet list
     *
     * @var array|null
     */
    protected $responseFacets = null;

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        if (strlen($query->getAllTerms())) {
            $limit  = $this->getParams()->getLimit();
            $offset = $this->getStartRecord();
            $params = $this->getParams()->getBackendParameters();
            $command = new SearchCommand(
                $this->backendId,
                $query,
                $offset,
                $limit,
                $params
            );
            $collection = $this->getSearchService()->invoke($command)->getResult();

            $this->resultTotal = $collection->getTotal();
            $this->results = $collection->getRecords();
            $this->responseFacets = $collection->getFacets();
        } else {
            // Empty queries are not supported, so treat the response as empty.
            $this->resultTotal = 0;
            $this->results = [];
            $this->responseFacets = [];
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
