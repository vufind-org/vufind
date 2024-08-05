<?php

/**
 * BrowZine aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017, 2022.
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
 * @package  Search_BrowZine
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\BrowZine;

use VuFindSearch\Command\SearchCommand;

/**
 * BrowZine Search Results
 *
 * @category VuFind
 * @package  Search_BrowZine
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
    protected $backendId = 'BrowZine';

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
        // Not supported
        return [];
    }

    /**
     * Abstract support method for performAndProcessSearch -- perform a search based
     * on the parameters passed to the object. This method is responsible for
     * filling in all of the key class properties: results, resultTotal, etc.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $command = new SearchCommand(
            $this->backendId,
            $query,
            $offset,
            $limit
        );
        $collection = $this->getSearchService()->invoke($command)
            ->getResult();
        $this->resultTotal = $collection->getTotal();

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();
    }
}
