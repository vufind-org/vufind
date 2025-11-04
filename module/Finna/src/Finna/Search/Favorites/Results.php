<?php

/**
 * Favorites aspect of the Search Multi-class (Results)
 *
 * PHP version 8
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

use Finna\Db\Service\UserListServiceInterface;
use VuFind\Db\Entity\UserListEntityInterface;

use function assert;
use function intval;

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
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $list = $this->getListObject();
        $sort = $this->getParams()->getSort();

        assert($this->userListService instanceof UserListServiceInterface);

        if (
            $sort == 'custom_order'
            && (empty($list)
            || !$this->userListService->isCustomOrderAvailable($list))
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
        $filters = $this->getParams()->getRawFilters();
        $listId = $filters['lists'][0] ?? null;
        if (null !== $listId) {
            $listId = intval($listId);
        }

        // Load a list when
        //   a. if we haven't previously tried to load a list ($this->list = false)
        //   b. the requested list is not the same as previously loaded list
        if (
            $this->list === false
            || ($listId && $this->list?->getId() !== $listId)
        ) {
            // Check the filters for a list ID, and load the corresponding object
            // if one is found:
            $this->list = (null === $listId) ? null : $this->userListService->getUserListById($listId);
        }
        return $this->list;
    }
}
