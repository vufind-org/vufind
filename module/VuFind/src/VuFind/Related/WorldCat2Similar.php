<?php

/**
 * Related Records: WorldCat v2-based similarity
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */

namespace VuFind\Related;

use Exception;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

use function array_slice;
use function count;

/**
 * Related Records: WorldCat v2-based similarity
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class WorldCat2Similar extends Similar
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
        // Create advanced query:
        $queryObj = new QueryGroup('OR');

        // Add author to query
        $author = $driver->getPrimaryAuthor();
        if (!empty($author)) {
            $queryObj->addQuery(new Query($author, 'au'));
        }

        // Add subjects to query
        $subjects = array_slice($driver->getAllSubjectHeadings(), 0, 4);
        foreach ($subjects as $current) {
            $queryObj->addQuery(new Query('"' . implode(' ', $current) . '"', 'su'));
        }

        // Add title to query
        $title = $driver->getTitle();
        if (!empty($title)) {
            $queryObj->addQuery(new Query('"' . str_replace('"', '', $title) . '"', 'ti'));
        }

        // Not current record ID if this is already a WorldCat v2 record:
        $idToExclude = $driver->getSourceIdentifier() == 'WorldCat2'
            ? $driver->getUniqueId() : null;

        // Perform the search and save filtered results:
        $maxRecommendations = 5;
        $command = new SearchCommand('WorldCat2', $queryObj, 1, $maxRecommendations + 1);
        $this->results = [];
        try {
            $result = $this->searchService->invoke($command)->getResult();
            foreach ($result->getRecords() as $record) {
                if ($record->getUniqueId() !== $idToExclude && count($this->results) < $maxRecommendations) {
                    $this->results[] = $record;
                }
            }
        } catch (Exception $e) {
            error_log('Unexpected error in WorldCat2 similar records module: ' . ((string)$e));
        }
    }
}
