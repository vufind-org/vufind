<?php
/**
 * Tags aspect of the Search Multi-class (Results)
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
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Tags;
use VuFind\Search\Base\Results as BaseResults;

/**
 * Search Tags Results
 *
 * @category VuFind2
 * @package  Search_Tags
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
        $table = $this->getTable('Tags');
        $tag = $table->getByText($this->getParams()->getDisplayQuery());
        if (!empty($tag)) {
            $rawResults = $tag->getResources(null, $this->getParams()->getSort());
        } else {
            $rawResults = [];
        }

        // How many results were there?
        $this->resultTotal = count($rawResults);

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $rawResults = $tag->getResources(
                null, $this->getParams()->getSort(), $this->getStartRecord() - 1,
                $limit
            );
        }

        // Retrieve record drivers for the selected items.
        $recordsToRequest = [];
        foreach ($rawResults as $row) {
            $recordsToRequest[]
                = ['id' => $row->record_id, 'source' => $row->source];
        }
        $this->results = $this->getServiceLocator()->get('VuFind\RecordLoader')
            ->loadBatch($recordsToRequest);
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
        // Facets not supported:
        return [];
    }
}