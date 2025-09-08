<?php

/**
 * Reservation List Results
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Search_ReservationList
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Search\ReservationList;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\Db\Service\FinnaResourceListResourceServiceInterface;
use Finna\Db\Service\FinnaResourceListServiceInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Record\Cache;
use VuFind\Record\Loader;
use VuFind\Search\Base\Results as BaseResults;
use VuFindSearch\Service as SearchService;

use function array_slice;
use function count;

/**
 * Reservation List Results
 *
 * @category VuFind
 * @package  Search_ReservationList
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
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
     * @var FinnaResourceListEntityInterface|bool
     */
    protected $list = false;

    /**
     * Resource table
     *
     * @var FinnaResourceListResourceServiceInterface
     */
    protected $resourceListResourceService;

    /**
     * UserList table
     *
     * @var FinnaResourceListServiceInterface
     */
    protected $listService;

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
     * @param \VuFind\Search\Base\Params                $params          Object representing user
     *                                                                   search parameters.
     * @param SearchService                             $searchService   Search service
     * @param Loader                                    $recordLoader    Record loader
     * @param FinnaResourceListResourceServiceInterface $resourceService Resource table
     * @param FinnaResourceListServiceInterface         $listService     Reservation list table
     */
    public function __construct(
        \VuFind\Search\Base\Params $params,
        SearchService $searchService,
        Loader $recordLoader,
        FinnaResourceListResourceServiceInterface $resourceService,
        FinnaResourceListServiceInterface $listService
    ) {
        parent::__construct($params, $searchService, $recordLoader);
        $this->resourceListResourceService = $resourceService;
        $this->listService = $listService;
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
                    'list' => [],
                ];
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
                'Cannot retrieve reservation lists without logged in user.'
            );
        }
        if (!$this->user || $list->getUser()?->getId() !== $this->user->getId()) {
            throw new ListPermissionException(
                $this->translate('list_access_denied')
            );
        }

        // How many results were there?
        $rawResults = $this->resourceListResourceService->getResourcesForList(
            $this->user,
            $list
        );
        $this->resultTotal = count($rawResults);
        $this->allIds = array_map(function ($result) {
            return $result['source'] . '|' . $result['record_id'];
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
     * Get the list object associated with the current search (null if no list
     * selected).
     *
     * @return FinnaResourceListEntityInterface|null
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
                ? null : $this->listService->getResourceListById($listId);
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
