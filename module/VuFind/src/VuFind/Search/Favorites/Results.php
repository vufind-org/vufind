<?php
/**
 * Favorites aspect of the Search Multi-class (Results)
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
namespace VuFind\Search\Favorites;
use VuFind\Account\Manager as AccountManager,
    VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Record,
    VuFind\Search\Base\Results as BaseResults,
    VuFind\Translator\Translator;

/**
 * Search Favorites Results
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Results extends BaseResults
{
    protected $user = null;
    protected $list = false;

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
        // Make sure we have processed the search before proceeding:
        if (is_null($this->user)) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (is_null($filter)) {
            $filter = $this->params->getFacetConfig();
        }

        // Start building the facet list:
        $retVal = array();

        // Loop through every requested field:
        $validFields = array_keys($filter);
        foreach ($validFields as $field) {
            if (!isset($this->facets[$field])) {
                $this->facets[$field] = array(
                    'label' => $this->params->getFacetLabel($field),
                    'list' => array()
                );
                switch ($field) {
                case 'lists':
                    $lists = $this->user ? $this->user->getLists() : array();
                    foreach ($lists as $list) {
                        $this->facets[$field]['list'][] = array(
                            'value' => $list->id,
                            'displayText' => $list->title,
                            'count' => $list->cnt,
                            'isApplied' =>
                                $this->params->hasFilter("$field:".$list->id)
                        );
                    }
                    break;
                case 'tags':
                    if ($this->list) {
                        $tags = $this->list->getTags();
                    } else {
                        $tags = $this->user ? $this->user->getTags() : array();
                    }
                    foreach ($tags as $tag) {
                        $this->facets[$field]['list'][] = array(
                            'value' => $tag->tag,
                            'displayText' => $tag->tag,
                            'count' => $tag->cnt,
                            'isApplied' =>
                                $this->params->hasFilter("$field:".$tag->tag)
                        );
                    }
                    break;
                }
            }
            if (isset($this->facets[$field])) {
                $retVal[$field] = $this->facets[$field];
            }
        }
        return $retVal;
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $list = $this->getListObject();
        $account = AccountManager::getInstance();
        $this->user = $account->isLoggedIn();

        // Make sure the user and/or list objects make it possible to view
        // the current result set -- we need to check logged in status and
        // list permissions.
        if (is_null($list) && !$this->user) {
            throw new ListPermissionException(
                'Cannot retrieve favorites without logged in user.'
            );
        }
        if (!is_null($list) && !$list->public
            && (!$this->user || $list->user_id != $this->user->id)
        ) {
            throw new ListPermissionException(
                Translator::translate('list_access_denied')
            );
        }

        $resource = new VuFind_Model_Db_Resource();
        $rawResults = $resource->getFavorites(
            is_null($list) ? $this->user->id : $list->user_id,
            isset($list->id) ? $list->id : null,
            $this->getTagFilters(), $this->getSort()
        );

        // How many results were there?
        $this->resultTotal = count($rawResults);

        // Retrieve record drivers for the selected items.
        $end = $this->getEndRecord();
        $recordsToRequest = array();
        for ($i = $this->getStartRecord() - 1; $i < $end; $i++) {
            $row = $rawResults->getRow($i);
            $recordsToRequest[] = array(
                'id' => $row->record_id, 'source' => $row->source,
                'extra_fields' => array(
                    'title' => $row->title
                )
            );
        }
        $this->results = Record::loadBatch($recordsToRequest);
    }

    /**
     * Static method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @return \VuFind\RecordDriver\Base
     */
    public static function getRecord($id)
    {
        throw new \Exception(
            'getRecord not supported by VuFind\\Search\\Favorites\\Results'
        );
    }

    /**
     * Get an array of tags being applied as filters.
     *
     * @return array
     */
    protected function getTagFilters()
    {
        $filters = $this->params->getFilters();
        return isset($filters['tags']) ? $filters['tags'] : array();
    }

    /**
     * Get the list object associated with the current search (null if no list
     * selected).
     *
     * @return VuFind_Model_Db_UserListRow|null
     */
    public function getListObject()
    {
        // If we haven't previously tried to load a list, do it now:
        if ($this->list === false) {
            // Check the filters for a list ID, and load the corresponding object
            // if one is found:
            $filters = $this->params->getFilters();
            $listId = isset($filters['lists'][0]) ? $filters['lists'][0] : null;
            $this->list = is_null($listId)
                ? null : VuFind_Model_Db_UserList::getExisting($listId);
        }
        return $this->list;
    }
}