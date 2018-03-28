<?php
/**
 * VuFind Search History Helper
 *
 * PHP version 7
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
     * History constructor
     *
     * @param \VuFind\Db\Table\Search              $searchTable    Search table
     * @param string                               $sessionId      Session ID
     * @param \VuFind\Search\Results\PluginManager $resultsManager Results manager
     */
    public function __construct($searchTable, $sessionId, $resultsManager)
    {
        $this->searchTable = $searchTable;
        $this->sessionId = $sessionId;
        $this->resultsManager = $resultsManager;
    }

    /**
     * Purge the user's unsaved search history.
     *
     * @param int $userId User ID (null if logged out)
     *
     * @return void
     */
    public function purgeSearchHistory($userId = null)
    {
        $this->searchTable->destroySession($this->sessionId, $userId);
    }

    /**
     * Get the user's saved and temporary search histories.
     *
     * @param int $userId User ID (null if logged out)
     *
     * @return array
     */
    public function getSearchHistory($userId = null)
    {
        // Retrieve search history
        $searchHistory = $this->searchTable->getSearches($this->sessionId, $userId);

        // Loop through and sort the history
        $saved = $unsaved = [];
        foreach ($searchHistory as $current) {
            $search = $current->getSearchObject()->deminify($this->resultsManager);
            if ($current->saved == 1) {
                $saved[] = $search;
            } else {
                $unsaved[] = $search;
            }
        }

        return compact('saved', 'unsaved');
    }
}
