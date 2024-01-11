<?php

/**
 * Mixed List aspect of the Search Multi-class (Params)
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_MixedList
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\MixedList;

use function count;

/**
 * Search Mixed List Parameters
 *
 * @category VuFind
 * @package  Search_MixedList
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Array of target record ids
     *
     * @var array
     */
    protected $recordsToRequest;

    /**
     * Initialize the object's search settings from a request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSearch($request)
    {
        // Convert special 'id' parameter into a standard hidden filter:
        $idParam = $request->get('id', []);
        if (!empty($idParam)) {
            $this->addHiddenFilter('ids:' . implode("\t", $idParam));
        }
    }

    /**
     * Parse record ids from a filter value and set as the ID list.
     *
     * @param string $filterValue Filter value
     *
     * @return void
     */
    protected function setRecordIdsFromFilter($filterValue)
    {
        $this->recordsToRequest = explode("\t", $filterValue);
        $this->setLimit(count($this->recordsToRequest));
    }

    /**
     * Take a filter string and add it into the protected hidden filters
     *   array checking for duplicates.
     *
     * Special case for 'ids': populate the ID list and remove from hidden filters.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addHiddenFilter($newFilter)
    {
        [$field, $value] = $this->parseFilter($newFilter);
        if ($field == 'ids') {
            $this->setRecordIdsFromFilter($value);
        } else {
            parent::addHiddenFilter($newFilter);
        }
    }

    /**
     * Restore settings from a minified object found in the database.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function deminify($minified)
    {
        parent::deminify($minified);
        if (isset($this->hiddenFilters['ids'][0])) {
            $this->setRecordIdsFromFilter($this->hiddenFilters['ids'][0]);
            unset($this->hiddenFilters['ids']);
        }
    }

    /**
     * Build a string for onscreen display.
     *
     * @return string
     */
    public function getDisplayQuery()
    {
        return $this->translate(
            'result_count',
            ['%%count%%' => count($this->recordsToRequest)]
        );
    }

    /**
     * Return record ids as a hidden filter list so that it is properly stored when
     * the search is represented as an URL or stored in the database.
     *
     * @return array
     */
    public function getHiddenFilters()
    {
        $filters = parent::getHiddenFilters();
        $filters['ids'] = [implode("\t", $this->recordsToRequest)];
        return $filters;
    }

    /**
     * Get list of records to display.
     *
     * @return array
     */
    public function getRecordsToRequest()
    {
        return $this->recordsToRequest;
    }
}
