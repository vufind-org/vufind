<?php
/**
 * WorldCat Search Results
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\WorldCat;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\ParamBag;

/**
 * WorldCat Search Parameters
 *
 * @category VuFind2
 * @package  Search_WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
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
        $params = $this->createBackendParameters($query, $this->getParams());
        $collection = $this->getSearchService()->search('WorldCat', $query, $offset, $limit, $params);

        $this->resultTotal = $collection->getTotal();
        $this->results = $collection->getRecords();
    }

    /**
     * Method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @throws RecordMissingException
     * @return \VuFind\RecordDriver\Base
     */
    public function getRecord($id)
    {
        $collection = $this->getSearchService()->retrieve('WorldCat', $id);

        if (count($collection) == 0) {
            throw new RecordMissingException(
                'Record ' . $id . ' does not exist.'
            );
        }

        return current($collection->getRecords());
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
        // No facets in WorldCat:
        return array();
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @param Params $params Search parameters
     *
     * @return ParamBag
     * @tag NEW SEARCH
     */
    protected function createBackendParameters (AbstractQuery $query, Params $params)
    {
        $backendParams = new ParamBag();

        // Sort
        $sort = $params->getSort();
        $backendParams->set('sortKeys', empty($sort) ? 'relevance' : $sort);

        return $backendParams;
    }
}