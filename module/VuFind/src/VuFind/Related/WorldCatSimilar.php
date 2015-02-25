<?php
/**
 * Related Records: WorldCat-based similarity
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
namespace VuFind\Related;

/**
 * Related Records: WorldCat-based similarity
 *
 * @category VuFind2
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
class WorldCatSimilar extends Similar
{
    /**
     * Establishes base settings for making recommendations.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        // Create array of query parts:
        $parts = [];

        // Add Dewey class to query
        $deweyClass = $driver->tryMethod('getDeweyCallNumber');
        if (!empty($deweyClass)) {
            // Skip "English Fiction" Dewey class -- this won't give us useful
            // matches because there's too much of it and it's too broad.
            if (substr($deweyClass, 0, 3) != '823') {
                $parts[] = 'srw.dd any "' . $deweyClass . '"';
            }
        }

        // Add author to query
        $author = $driver->getPrimaryAuthor();
        if (!empty($author)) {
            $parts[] = 'srw.au all "' . $author . '"';
        }

        // Add subjects to query
        $subjects = $driver->getAllSubjectHeadings();
        foreach ($subjects as $current) {
            $parts[] = 'srw.su all "' . implode(' ', $current) . '"';
        }

        // Add title to query
        $title = $driver->getTitle();
        if (!empty($title)) {
            $parts[] = 'srw.ti any "' . str_replace('"', '', $title) . '"';
        }

        // Build basic query:
        $query = '(' . implode(' or ', $parts) . ')';

        // Not current record ID if this is already a WorldCat record:
        if ($driver->getResourceSource() == 'WorldCat') {
            $id = $driver->getUniqueId();
            $query .= " not srw.no all \"$id\"";
        }

        // Perform the search and save results:
        $queryObj = new \VuFindSearch\Query\Query($query);
        $result = $this->searchService->search('WorldCat', $queryObj, 0, 5);
        $this->results = $result->getRecords();
    }
}
