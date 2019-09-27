<?php
/**
 * Favorites aspect of the Search Multi-class (Results)
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Favorites;

use VuFind\Db\Table\Resource as ResourceTable;
use VuFind\Db\Table\UserList as ListTable;
use VuFind\Db\Table\UserResource as UserResourceTable;
use VuFind\Record\Loader;
use VuFindSearch\Service as SearchService;

/**
 * Search Favorites Results
 *
 * @category VuFind
 * @package  Search_Favorites
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Results extends \VuFind\Search\Favorites\Results
{
    /**
     * UserResource table
     *
     * @var UserResourceTable
     */
    protected $userResourceTable;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params            Object representing user
     * search parameters.
     * @param SearchService              $searchService     Search service
     * @param Loader                     $recordLoader      Record loader
     * @param ResourceTable              $resourceTable     Resource table
     * @param ListTable                  $listTable         UserList table
     * @param UserResourceTable          $userResourceTable UserResource table
     */
    public function __construct(\VuFind\Search\Base\Params $params,
        SearchService $searchService, Loader $recordLoader,
        ResourceTable $resourceTable, ListTable $listTable,
        UserResourceTable $userResourceTable
    ) {
        parent::__construct(
            $params, $searchService, $recordLoader, $resourceTable, $listTable
        );
        $this->userResourceTable = $userResourceTable;
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
        $sort = $this->getParams()->getSort();

        if ($sort == 'custom_order'
            && (empty($list)
            || !$this->userResourceTable->isCustomOrderAvailable($list->id))
        ) {
            $sort = 'id desc';
        }

        $this->getParams()->setSort($sort);

        parent::performSearch();

        // Other sort options are handled in the database, but format is language-
        // specific
        if ($sort === 'format') {
            $records = [];
            foreach ($this->results as $result) {
                $formats = $result->getFormats();
                $format = end($formats);
                $format = $this->translate($format);

                $records[$format . '_' . $result->getUniqueID()] = $result;
            }
            ksort($records);
            $this->results = array_values($records);
        }
    }

    /**
     * Get an array of tags being applied as filters.
     *
     * @return array
     */
    protected function getTagFilters()
    {
        $filters = $this->getParams()->getFilters();
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
        $filters = $this->getParams()->getFilters();
        $listId = $filters['lists'][0] ?? null;

        // Load a list when
        //   a. if we haven't previously tried to load a list ($this->list = false)
        //   b. the requested list is not the same as previously loaded list
        if ($this->list === false
            || ($listId && ($this->list['id'] ?? null) !== $listId)
        ) {
            // Check the filters for a list ID, and load the corresponding object
            // if one is found:
            if (null === $listId) {
                $this->list = null;
            } else {
                $this->list = $this->listTable->getExisting($listId);
            }
        }
        return $this->list;
    }
}
