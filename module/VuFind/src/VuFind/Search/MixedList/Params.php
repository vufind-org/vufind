<?php
/**
 * Mixed List aspect of the Search Multi-class (Params)
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
 * @category VuFind
 * @package  Search_MixedList
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\MixedList;

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
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSearch($request)
    {
        $this->recordsToRequest = $request->get('id', []);

        // We always want to display the entire list as one page:
        $this->setLimit(count($this->recordsToRequest));
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
        $ids = explode(' OR ', $filterValue);
        $ids = array_map(
            function ($s) {
                return trim($s, '()');
            },
            $ids
        );
        $this->recordsToRequest = $ids;
        $this->setLimit(count($this->recordsToRequest));
    }

    /**
     * Take a filter string and add it into the protected hidden filters
     *   array checking for duplicates.
     *
     * Special case for 'id': populate the ID list and remove from hidden filters.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addHiddenFilter($newFilter)
    {
        list($field, $value) = $this->parseFilter($newFilter);
        if ($field == 'id') {
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
        if (isset($this->hiddenFilters['id'][0])) {
            $this->setRecordIdsFromFilter($this->hiddenFilters['id'][0]);
            unset($this->hiddenFilters['id']);
        }
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
        unset($filters['id']);
        $ids = array_map(
            function ($s) {
                return '(' . addcslashes($s, '"') . ')';
            },
            $this->recordsToRequest
        );
        $filters['id'][] = implode(' OR ', $ids);
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
