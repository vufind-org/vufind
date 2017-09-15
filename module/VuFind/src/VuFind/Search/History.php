<?php
/**
 * VuFind Search History Helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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
 * @author   Sebastian Böttger <boettger@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search;
use minSO;

/**
 * VuFind Search History Helper
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Böttger <boettger@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class History
{
    /**
     * Search table
     *
     * @var \VuFind\Db\Table\Search
     */
    protected $searchTable;

    /**
     * Current session ID
     *
     * @var string
     */
    protected $sessionId;

    /**
     * Results manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Search memory
     *
     * @var \VuFind\Search\Memory
     */
    protected $searchMemory;

    /**
     * History constructor
     *
     * @param \VuFind\Db\Table\Search              $searchTable    Search table
     * @param string                               $sessionId      Session ID
     * @param \VuFind\Search\Results\PluginManager $resultsManager Results manager
     * @param \VuFind\Search\Memory                $searchMemory   Search memory
     */
    public function __construct($searchTable, $sessionId, $resultsManager,
        $searchMemory
    ) {
        $this->searchTable = $searchTable;
        $this->sessionId = $sessionId;
        $this->resultsManager = $resultsManager;
        $this->searchMemory = $searchMemory;
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getSearchHistory($userId = null, $purged = false)
    {
        // Retrieve search history
        $searchHistory = $this->searchTable->getSearches($this->sessionId, $userId);

        // Build arrays of history entries
        $saved = $unsaved = [];

        // Loop through the history
        /** @var \VuFind\Db\Row\Search $current */
        foreach ($searchHistory as $current) {

            /** @var minSO $minSO */
            $minSO = $current->getSearchObject();

            // Saved searches
            if ($current->saved == 1) {
                $saved[] = $minSO->deminify($this->resultsManager);
            } else {
                // All the others...

                // If this was a purge request we don't need this
                if ($purged) {
                    $current->delete();

                    // We don't want to remember the last search after a purge:
                    $this->searchMemory->forgetSearch();
                } else {
                    // Otherwise add to the list
                    $unsaved[] = $minSO->deminify($this->resultsManager);
                }
            }
        }

        return ['saved' => $saved, 'unsaved' => $unsaved];
    }
}