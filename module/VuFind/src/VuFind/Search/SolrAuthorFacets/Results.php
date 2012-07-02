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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\SolrAuthorFacets;
use VuFind\Connection\Manager as ConnectionManager,
    VuFind\Search\Base\Results as BaseResults;

/**
 * AuthorFacets Search Results
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Results extends BaseResults
{
    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $solr = ConnectionManager::connectToIndex();

        // Collect the search parameters:
        $params = array(
            'query' => $solr->buildQuery($this->getSearchTerms()),
            'handler' => $this->getSearchHandler(),
            'limit' => 0,
            'facet' => $this->params->getFacetSettings(),
        );

        // Perform the search:
        $this->rawResponse = $solr->search($params);

        // Get the facets from which we will build our results:
        $facets = $this->getFacetList(array('authorStr' => null));
        if (isset($facets['authorStr'])) {
            $this->resultTotal
                = (($this->getPage() - 1) * $this->getLimit())
                + count($facets['authorStr']['list']);
            $this->results = array_slice(
                $facets['authorStr']['list'], 0, $this->getLimit()
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