<?php
/**
 * Command to generate a sitemap from a backend using cursor marks (if supported).
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
namespace VuFind\Sitemap\Command;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Command to generate a sitemap from a backend using cursor marks (if supported).
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GenerateSitemapWithCursorMarkCommand extends AbstractGenerateSitemapCommand
{
    /**
     * Get the initial offset to seed the search process
     *
     * @return string
     */
    protected function getInitialOffset(): string
    {
        return '*';
    }

    /**
     * Set up the backend.
     *
     * @param Backend $backend Search backend
     *
     * @return void
     */
    protected function setupBackend(Backend $backend): void
    {
        // Set up the record factory. We use a very simple factory since performance
        // is important and we only need the identifier.
        $recordFactory = function ($data) {
            return new \VuFindSearch\Response\SimpleRecord($data);
        };
        $collectionFactory = new RecordCollectionFactory($recordFactory);
        $backend->setRecordCollectionFactory($collectionFactory);
    }

    /**
     * Retrieve a batch of IDs.
     *
     * @param Backend $backend      Search backend
     * @param string  $cursorMark   String representing progress through set
     * @param int     $countPerPage Page size
     *
     * @return array
     */
    protected function getIdsFromBackend(
        Backend $backend,
        string $cursorMark,
        int $countPerPage
    ): array {
        // If the previous cursor mark matches the current one, we're finished!
        static $prevCursorMark = '';
        if ($cursorMark === $prevCursorMark) {
            return ['ids' => [], 'cursorMark' => $cursorMark];
        }
        $prevCursorMark = $cursorMark;

        $connector = $backend->getConnector();
        $key = $connector->getUniqueKey();
        $params = new ParamBag(
            [
                'q' => '*:*',
                'rows' => $countPerPage,
                'start' => 0, // Always 0 when using a cursorMark
                'wt' => 'json',
                'sort' => $key . ' asc',
                // Override any default timeAllowed since it cannot be used with
                // cursorMark
                'timeAllowed' => -1,
                'cursorMark' => $cursorMark
            ]
        );
        $results = $this->searchService->getIds(
            $backend->getIdentifier(),
            new Query('*:*'),
            0,
            $countPerPage,
            $params
        );
        $ids = [];
        foreach ($results->getRecords() as $doc) {
            $ids[] = $doc->get($key);
        }
        $nextOffset = $results->getCursorMark();
        return compact('ids', 'nextOffset');
    }
}
