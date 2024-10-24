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
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

use function array_slice;
use function count;
use function in_array;

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
     * Maximum number of recommendations to return.
     *
     * @var int
     */
    protected int $maxRecommendations = 5;

    /**
     * Maximum term count in query.
     *
     * @var int
     */
    protected int $termLimit = 30;

    /**
     * Add a phrase to the query if it's not already too long.
     *
     * @param QueryGroup $queryObj Query object being updated
     * @param string     $phrase   Query phrase
     * @param string     $type     Search type
     *
     * @return void
     */
    protected function addPhraseToQuery(QueryGroup $queryObj, string $phrase, string $type): void
    {
        $terms = explode(' ', trim($queryObj->getAllTerms() . ' ' . $phrase));
        if (count($terms) <= $this->termLimit) {
            $queryObj->addQuery(new Query('"' . $phrase . '"', $type));
        }
    }

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

        // Add subjects to query
        $subjects = array_slice($driver->tryMethod('getAllSubjectHeadings', default: []), 0, 4);
        foreach ($subjects as $current) {
            $this->addPhraseToQuery($queryObj, implode(' ', $current), 'su');
        }

        // Add author to query
        $author = $driver->tryMethod('getPrimaryAuthor', default: '');
        if (!empty($author)) {
            $this->addPhraseToQuery($queryObj, $author, 'au');
        }

        // Add title to query
        $title = $driver->tryMethod('getTitle', default: '');
        if (!empty($title)) {
            $this->addPhraseToQuery($queryObj, str_replace('"', '', $title), 'ti');
        }

        // We don't want to show a recommendation that represents the same OCLC record.
        $idsToExclude = (array)($driver->tryMethod('getOCLC', default: []));

        // Group together related editions to ensure that suggestions are more diverse:
        $extraParams = new ParamBag(['groupRelatedEditions' => 'true']);

        // Perform the search and save filtered results:
        $command = new SearchCommand('WorldCat2', $queryObj, 1, $this->maxRecommendations + 1, $extraParams);
        $this->results = [];
        try {
            $result = $this->searchService->invoke($command)->getResult();
            foreach ($result->getRecords() as $record) {
                if (
                    !in_array($record->getUniqueId(), $idsToExclude)
                    && count($this->results) < $this->maxRecommendations
                ) {
                    $this->results[] = $record;
                }
            }
        } catch (Exception $e) {
            error_log('Unexpected error in WorldCat2 similar records module: ' . ((string)$e));
        }
    }

    /**
     * Set the term limit.
     *
     * @param int $limit Term limit
     *
     * @return void
     */
    public function setTermLimit(int $limit): void
    {
        $this->termLimit = $limit;
    }
}
