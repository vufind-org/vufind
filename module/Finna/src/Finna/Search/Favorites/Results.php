<?php
/**
 * Favorites aspect of the Search Multi-class (Results)
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Search_Favorites
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Favorites;

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
        parent::performSearch();

        $sort = $this->getParams()->getSort();
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
        return isset($filters['tags']) ? $filters['tags'] : [];
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
            $filters = $this->getParams()->getFilters();
            $listId = isset($filters['lists'][0]) ? $filters['lists'][0] : null;
            if (null === $listId) {
                $this->list = null;
            } else {
                $table = $this->getTable('UserList');
                $this->list = $table->getExisting($listId);
            }
        }
        return $this->list;
    }
}
