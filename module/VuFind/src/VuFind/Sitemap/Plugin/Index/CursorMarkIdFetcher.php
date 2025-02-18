<?php

/**
 * Plugin to get IDs for a sitemap from a backend using cursor marks (if supported).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021, 2022.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Sitemap\Plugin\Index;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\Command\GetIdsCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Plugin to get IDs for a sitemap from a backend using cursor marks (if supported).
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class CursorMarkIdFetcher extends AbstractIdFetcher
{
    /**
     * Previous cursor mark
     *
     * @var string
     */
    protected $prevCursorMark = '';

    /**
     * Default parameters to send to Solr with each request
     *
     * @var array
     */
    protected $defaultParams = [
        'q' => '*:*',
        'start' => 0, // Always 0 when using a cursorMark
        'wt' => 'json',
        // Override any default timeAllowed since it cannot be used with
        // cursorMark
        'timeAllowed' => -1,
    ];

    /**
     * Get the initial offset to seed the search process
     *
     * @return string
     */
    public function getInitialOffset(): string
    {
        return '*';
    }

    /**
     * Set up the backend.
     *
     * @param string $backend Search backend ID
     *
     * @return void
     */
    public function setupBackend(string $backend): void
    {
        // Set up the record factory. We use a very simple factory since performance
        // is important and we only need the identifier.
        $recordFactory = function ($data) {
            return new \VuFindSearch\Response\SimpleRecord($data);
        };
        $this->searchService->invoke(
            new \VuFindSearch\Command\SetRecordCollectionFactoryCommand(
                $backend,
                new RecordCollectionFactory($recordFactory)
            )
        );

        // Reset the "previous cursor mark" (in case we're reusing this object on
        // multiple backends).
        $this->prevCursorMark = '';
    }

    /**
     * Retrieve a batch of IDs. Returns an array with two possible keys: ids (the
     * latest set of retrieved IDs) and nextOffset (an offset which can be passed
     * to the next call to this function to retrieve the next page). When all IDs
     * have been retrieved, the nextOffset value MUST NOT be included in the return
     * array.
     *
     * @param string $backend      Search backend ID
     * @param string $cursorMark   String representing progress through set
     * @param int    $countPerPage Page size
     * @param array  $filters      Filters to apply to the search
     *
     * @return array
     */
    public function getIdsFromBackend(
        string $backend,
        string $cursorMark,
        int $countPerPage,
        array $filters
    ): array {
        // If the previous cursor mark matches the current one, we're finished!
        if ($cursorMark === $this->prevCursorMark) {
            return ['ids' => []];
        }
        $this->prevCursorMark = $cursorMark;

        $getKeyCommand = new \VuFindSearch\Command\GetUniqueKeyCommand($backend, []);
        $key = $this->searchService->invoke($getKeyCommand)->getResult();
        $params = new ParamBag(
            $this->defaultParams + [
                'rows' => $countPerPage,
                'sort' => $key . ' asc',
                'cursorMark' => $cursorMark,
                'fl' => 'last_indexed',
            ]
        );
        // Apply filters:
        foreach ($filters as $filter) {
            $params->add('fq', $filter);
        }
        $command = new GetIdsCommand(
            $backend,
            new Query('*:*'),
            0,
            $countPerPage,
            $params
        );

        $results = $this->searchService->invoke($command)->getResult();
        $ids = [];
        $lastmods = [];
        foreach ($results->getRecords() as $doc) {
            $ids[] = $doc->get($key);
            $lastmods[] = $doc->get('last_indexed');
        }
        $nextOffset = $results->getCursorMark();
        return compact('ids', 'nextOffset', 'lastmods');
    }
}
