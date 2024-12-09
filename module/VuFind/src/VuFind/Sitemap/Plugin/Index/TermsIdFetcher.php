<?php

/**
 * Plugin to get IDs for a sitemap from a backend using terms (if supported).
 *
 * PHP version 8
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

namespace VuFind\Sitemap\Plugin\Index;

use VuFindSearch\Command\GetUniqueKeyCommand;
use VuFindSearch\Command\TermsCommand;

use function count;

/**
 * Plugin to get IDs for a sitemap from a backend using terms (if supported).
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class TermsIdFetcher extends AbstractIdFetcher
{
    /**
     * Get the initial offset to seed the search process
     *
     * @return string
     */
    public function getInitialOffset(): string
    {
        return '';
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
        // No special action needed.
    }

    /**
     * Retrieve a batch of IDs. Returns an array with two possible keys: ids (the
     * latest set of retrieved IDs) and nextOffset (an offset which can be passed
     * to the next call to this function to retrieve the next page). When all IDs
     * have been retrieved, the nextOffset value MUST NOT be included in the return
     * array.
     *
     * @param string $backend      Search backend ID
     * @param string $lastTerm     String representing progress through set
     * @param int    $countPerPage Page size
     * @param array  $filters      Filters to apply to the search
     *
     * @return array
     */
    public function getIdsFromBackend(
        string $backend,
        string $lastTerm,
        int $countPerPage,
        array $filters
    ): array {
        if (!empty($filters)) {
            throw new \Exception('extraFilters[] option incompatible with terms');
        }
        $getKeyCommand = new GetUniqueKeyCommand($backend, []);
        $key = $this->searchService->invoke($getKeyCommand)->getResult();
        $termsCommand = new TermsCommand($backend, $key, $lastTerm, $countPerPage);
        $info = $this->searchService->invoke($termsCommand)->getResult()
            ->getFieldTerms($key);
        $ids = null === $info ? [] : array_keys($info->toArray());
        // Only include the next offset if we have a non-empty set:
        return empty($ids)
            ? compact('ids')
            : ['ids' => $ids, 'nextOffset' => $ids[count($ids) - 1]];
    }
}
