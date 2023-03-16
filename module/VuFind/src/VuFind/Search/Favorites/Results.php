<?php

/**
 * Favorites aspect of the Search Multi-class (Results)
 *
 * PHP version 7
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
 * @package  Search_Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Favorites;

use LmcRbacMvc\Service\AuthorizationServiceAwareInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;
use VuFind\Db\Table\Resource as ResourceTable;
use VuFind\Db\Table\UserList as ListTable;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Record\Cache;
use VuFind\Record\Loader;
use VuFind\Search\Base\Results as BaseResults;
use VuFindSearch\Service as SearchService;

/**
 * Search Favorites Results
 *
 * @category VuFind
 * @package  Search_Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Results extends BaseResults implements AuthorizationServiceAwareInterface
{
    use AuthorizationServiceAwareTrait;

    /**
     * Object if user is logged in, false otherwise.
     *
     * @var \VuFind\Db\Row\User|bool
     */
    protected $user = null;

    /**
     * Active user list (false if none).
     *
     * @var \VuFind\Db\Row\UserList|bool
     */
    protected $list = false;

    /**
     * Resource table
     *
     * @var ResourceTable
     */
    protected $resourceTable;

    /**
     * UserList table
     *
     * @var ListTable
     */
    protected $listTable;

    /**
     * Facet list
     *
     * @var array
     */
    protected $facets;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params        Object representing user
     * search parameters.
     * @param SearchService              $searchService Search service
     * @param Loader                     $recordLoader  Record loader
     * @param ResourceTable              $resourceTable Resource table
     * @param ListTable                  $listTable     UserList table
     */
    public function __construct(
        \VuFind\Search\Base\Params $params,
        SearchService $searchService,
        Loader $recordLoader,
        ResourceTable $resourceTable,
        ListTable $listTable
    ) {
        parent::__construct($params, $searchService, $recordLoader);
        $this->resourceTable = $resourceTable;
        $this->listTable = $listTable;
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
        // Make sure we have processed the search before proceeding:
        if (null === $this->user) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $retVal = [];

        // Loop through every requested field:
        $validFields = array_keys($filter);
        foreach ($validFields as $field) {
            if (!isset($this->facets[$field])) {
                $this->facets[$field] = [
                    'label' => $this->getParams()->getFacetLabel($field),
                    'list' => []
                ];
                switch ($field) {
                    case 'tags':
                        if ($this->list) {
                            $tags = $this->list->getResourceTags();
                        } else {
                            $tags = $this->user ? $this->user->getTags() : [];
                        }
                        foreach ($tags as $tag) {
                            $this->facets[$field]['list'][] = [
                                'value' => $tag->tag,
                                'displayText' => $tag->tag,
                                'count' => $tag->cnt,
                                'isApplied' => $this->getParams()
                                    ->hasFilter("$field:" . $tag->tag)
                            ];
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
        $auth = $this->getAuthorizationService();
        $this->user = $auth ? $auth->getIdentity() : false;

        // Make sure the user and/or list objects make it possible to view
        // the current result set -- we need to check logged in status and
        // list permissions.
        if (null === $list && !$this->user) {
            throw new ListPermissionException(
                'Cannot retrieve favorites without logged in user.'
            );
        }
        if (null !== $list && !$list->public
            && (!$this->user || $list->user_id != $this->user->id)
        ) {
            throw new ListPermissionException(
                $this->translate('list_access_denied')
            );
        }

        // How many results were there?
        $userId = null === $list ? $this->user->id : $list->user_id;
        $listId = null === $list ? null : $list->id;
        $rawResults = $this->resourceTable->getFavorites(
            $userId,
            $listId,
            $this->getTagFilters(),
            $this->getParams()->getSort()
        );
        $this->resultTotal = count($rawResults);

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $rawResults = $this->resourceTable->getFavorites(
                $userId,
                $listId,
                $this->getTagFilters(),
                $this->getParams()->getSort(),
                $this->getStartRecord() - 1,
                $limit
            );
        }

        // Retrieve record drivers for the selected items.
        $recordsToRequest = [];
        foreach ($rawResults as $row) {
            $recordsToRequest[] = [
                'id' => $row->record_id, 'source' => $row->source,
                'extra_fields' => [
                    'title' => $row->title
                ]
            ];
        }

        $this->recordLoader->setCacheContext(Cache::CONTEXT_FAVORITE);
        $this->results = $this->recordLoader->loadBatch($recordsToRequest, true);
    }

    /**
     * Get an array of tags being applied as filters.
     *
     * @return array
     */
    protected function getTagFilters()
    {
        $filters = $this->getParams()->getRawFilters();
        return $filters['tags'] ?? [];
    }

    /**
     * Get the list object associated with the current search (null if no list
     * selected).
     *
     * @return \VuFind\Db\Row\UserList|null
     */
    public function getListObject()
    {
        // If we haven't previously tried to load a list, do it now:
        if ($this->list === false) {
            // Check the filters for a list ID, and load the corresponding object
            // if one is found:
            $filters = $this->getParams()->getRawFilters();
            $listId = $filters['lists'][0] ?? null;
            $this->list = (null === $listId)
                ? null : $this->listTable->getExisting($listId);
        }
        return $this->list;
    }
}
