<?php

/**
 * AuthorFacets aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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
 * @package  Search_SolrAuthorFacets
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\SolrAuthorFacets;

use VuFindSearch\Command\SearchCommand;

use function array_slice;
use function count;

/**
 * AuthorFacets Search Results
 *
 * @category VuFind
 * @package  Search_SolrAuthorFacets
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query = $this->getParams()->getQuery();
        $params = $this->getParams()->getBackendParameters();
        // Perform the search:
        $command = new SearchCommand($this->backendId, $query, 0, 0, $params);
        $collection = $this->getSearchService()
            ->invoke($command)->getResult();
        $this->responseFacets = $collection->getFacets();

        // Get the facets from which we will build our results:
        $facets = $this->getFacetList(['author_facet' => null]);
        if (isset($facets['author_facet'])) {
            $params = $this->getParams();
            $this->resultTotal
                = (($params->getPage() - 1) * $params->getLimit())
                + count($facets['author_facet']['list']);
            $this->results = array_slice(
                $facets['author_facet']['list'],
                0,
                $params->getLimit()
            );
        }
    }

    /**
     * Is the current search saved in the database?
     *
     * @return bool
     */
    public function isSavedSearch()
    {
        // Author searches are never saved:
        return false;
    }
}
