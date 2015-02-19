<?php
/**
 * AuthorFacets aspect of the Search Multi-class (Results)
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search_SolrAuthorFacets
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\SolrAuthorFacets;

/**
 * AuthorFacets Search Results
 *
 * @category VuFind2
 * @package  Search_SolrAuthorFacets
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
        $collection = $this->getSearchService()
            ->search($this->backendId, $query, 0, 0, $params);

        $this->responseFacets = $collection->getFacets();

        // Get the facets from which we will build our results:
        $facets = $this->getFacetList(['authorStr' => null]);
        if (isset($facets['authorStr'])) {
            $params = $this->getParams();
            $this->resultTotal
                = (($params->getPage() - 1) * $params->getLimit())
                + count($facets['authorStr']['list']);
            $this->results = array_slice(
                $facets['authorStr']['list'], 0, $params->getLimit()
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