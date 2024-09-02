<?php

/**
 * Favorites aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
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
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Record\Cache;
use VuFind\Record\Loader;
use VuFind\Search\Base\Results as BaseResults;
use VuFind\Tags\TagsService;
use VuFindSearch\Service as SearchService;

use function array_slice;
use function count;

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
     * Object if user is logged in, null otherwise.
     *
     * @var ?UserEntityInterface
     */
    protected $user = null;

    /**
     * Active user list (false if we haven't tried to load yet; null if inapplicable).
     *
     * @var UserListEntityInterface|null|false
     */
    protected $list = false;

    /**
     * Facet list
     *
     * @var array
     */
    protected $facets;

    /**
     * All ids
     *
     * @var array
     */
    protected $allIds;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params          Object representing user search parameters
     * @param SearchService              $searchService   Search service
     * @param Loader                     $recordLoader    Record loader
     * @param ResourceServiceInterface   $resourceService Resource database service
     * @param UserListServiceInterface   $userListService UserList database service
     * @param TagsService                $tagsService     Tags service
     */
    public function __construct(
        \VuFind\Search\Base\Params $params,
        SearchService $searchService,
        Loader $recordLoader,
        protected ResourceServiceInterface $resourceService,
        protected UserListServiceInterface $userListService,
        protected TagsService $tagsService
    ) {
        parent::__construct($params, $searchService, $recordLoader);
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
        if (null === $this->results) {
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
                    'list' => [],
                ];
                switch ($field) {
                    case 'tags':
                        if ($list = $this->getListObject()) {
                            $tags = $this->tagsService->getUserTagsFromFavorites($list->getUser(), $list);
                        } else {
                            $tags = $this->tagsService->getUserTagsFromFavorites($this->user);
                        }
                        foreach ($tags as $tag) {
                            $this->facets[$field]['list'][] = [
                                'value' => $tag['tag'],
                                'displayText' => $tag['tag'],
                                'count' => $tag['cnt'],
                                'isApplied' => $this->getParams()
                                    ->hasFilter("$field:" . $tag['tag']),
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
        $this->user = $this->getAuthorizationService()?->getIdentity();

        // Make sure the user and/or list objects make it possible to view
        // the current result set -- we need to check logged in status and
        // list permissions.
        if (!$list && !$this->user) {
            throw new ListPermissionException(
                'Cannot retrieve favorites without logged in user.'
            );
        }
        if ($list && !$list->isPublic() && $list->getUser()->getId() !== $this->user?->getId()) {
            throw new ListPermissionException(
                $this->translate('list_access_denied')
            );
        }

        // How many results were there?
        $userId = $list ? $list->getUser()->getId() : $this->user->getId();
        $listId = $list?->getId();
        // Get results as an array so that we can rewind it:
        $rawResults = $this->resourceService->getFavorites(
            $userId,
            $listId,
            $this->getTagFilters(),
            $this->getParams()->getSort(),
            caseSensitiveTags: $this->tagsService->hasCaseSensitiveTags()
        );
        $this->resultTotal = count($rawResults);
        $this->allIds = array_map(function ($result) {
            return $result->getSource() . '|' . $result->getRecordId();
        }, $rawResults);

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $rawResults = array_slice($rawResults, $this->getStartRecord() - 1, $limit);
        }

        // Retrieve record drivers for the selected items.
        $recordsToRequest = [];
        foreach ($rawResults as $row) {
            $recordsToRequest[] = [
                'id' => $row->getRecordId(), 'source' => $row->getSource(),
                'extra_fields' => [
                    'title' => $row->getTitle(),
                ],
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
     * @return ?UserListEntityInterface
     */
    public function getListObject(): ?UserListEntityInterface
    {
        // If we haven't previously tried to load a list, do it now:
        if ($this->list === false) {
            // Check the filters for a list ID, and load the corresponding object
            // if one is found:
            $filters = $this->getParams()->getRawFilters();
            $listId = $filters['lists'][0] ?? null;
            $this->list = (null === $listId) ? null : $this->userListService->getUserListById($listId);
        }
        return $this->list;
    }

    /**
     * Get all ids.
     *
     * @return array
     */
    public function getAllIds()
    {
        return $this->allIds;
    }
}
