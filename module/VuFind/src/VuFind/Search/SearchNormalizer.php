<?php

/**
 * Search normalizer.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search;

use DateTime;
use minSO;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager as ResultsManager;

use function count;

/**
 * Search normalizer.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchNormalizer
{
    /**
     * Constructor
     *
     * @param ResultsManager         $resultsManager Search results manager
     * @param SearchServiceInterface $searchService  Search database service
     */
    public function __construct(
        protected ResultsManager $resultsManager,
        protected SearchServiceInterface $searchService
    ) {
    }

    /**
     * Normalize a search
     *
     * @param Results $results Search results object
     *
     * @return NormalizedSearch
     */
    public function normalizeSearch(Results $results): NormalizedSearch
    {
        return new NormalizedSearch($this->resultsManager, $results);
    }

    /**
     * Normalize a minified search
     *
     * @param Minified $minified Minified search results object
     *
     * @return NormalizedSearch
     */
    public function normalizeMinifiedSearch(Minified $minified): NormalizedSearch
    {
        return $this->normalizeSearch($minified->deminify($this->resultsManager));
    }

    /**
     * Return existing search table rows matching the provided normalized search.
     *
     * @param NormalizedSearch $normalized Normalized search to match against
     * @param string           $sessionId  Current session ID
     * @param int|null         $userId     Current user ID
     * @param int              $limit      Max rows to retrieve
     * (default = no limit)
     *
     * @return SearchEntityInterface[]
     */
    public function getSearchesMatchingNormalizedSearch(
        NormalizedSearch $normalized,
        string $sessionId,
        ?int $userId,
        int $limit = PHP_INT_MAX
    ): array {
        // Fetch all rows with the same CRC32 and try to match with the URL
        $checksum = $normalized->getChecksum();
        $results = [];
        foreach ($this->searchService->getSearchesByChecksumAndOwner($checksum, $sessionId, $userId) as $match) {
            if (!($minified = $match->getSearchObject())) {
                throw new \Exception('Problem decoding saved search');
            }
            if ($normalized->isEquivalentToMinifiedSearch($minified)) {
                $results[] = $match;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        return $results;
    }

    /**
     * Add a search into the search table (history)
     *
     * @param \VuFind\Search\Base\Results $results   Search to save
     * @param string                      $sessionId Current session ID
     * @param ?int                        $userId    Current user ID
     *
     * @return SearchEntityInterface
     * @throws Exception
     */
    public function saveNormalizedSearch(
        \VuFind\Search\Base\Results $results,
        string $sessionId,
        ?int $userId
    ): SearchEntityInterface {
        $normalized = $this->normalizeSearch($results);
        $duplicates = $this->getSearchesMatchingNormalizedSearch(
            $normalized,
            $sessionId,
            $userId,
            1 // we only need to identify at most one duplicate match
        );
        if ($existingRow = array_shift($duplicates)) {
            // Update the existing search only if it wasn't already saved
            // (to make it the most recent history entry and make sure it's
            // using the most up-to-date serialization):
            if (!$existingRow->getSaved()) {
                $existingRow->setCreated(new DateTime());
                // Keep the ID of the old search:
                $minified = $normalized->getMinified();
                if (!$searchObject = $existingRow->getSearchObject()) {
                    throw new \Exception('Problem decoding saved search');
                }
                $minified->id = $searchObject->id;
                $existingRow->setSearchObject($minified);
                $existingRow->setSessionId($sessionId);
                $this->searchService->persistEntity($existingRow);
            }
            // Register the appropriate search history database row with the current
            // search results object.
            $results->updateSaveStatus($existingRow);
            return $existingRow;
        }

        // If we got this far, we didn't find a saved duplicate, so we should
        // save the new search:
        $row = $this->searchService->createAndPersistEntityWithChecksum($normalized->getChecksum());

        // Chicken and egg... We didn't know the id before insert
        $results->updateSaveStatus($row);

        // Don't set session ID until this stage, because we don't want to risk
        // ever having a row that's associated with a session but which has no
        // search object data attached to it; this could cause problems!
        $row->setSessionId($sessionId);
        $row->setSearchObject(new minSO($results));
        $this->searchService->persistEntity($row);
        return $row;
    }
}
