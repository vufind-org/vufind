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

use Laminas\Config\Config;

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
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * History constructor
     *
     * @param \VuFind\Db\Table\Search              $searchTable    Search table
     * @param string                               $sessionId      Session ID
     * @param \VuFind\Search\Results\PluginManager $resultsManager Results manager
     * @param \Laminas\Config\Config               $config         Configuration
     */
    public function __construct(
        $searchTable,
        $sessionId,
        $resultsManager,
        \Laminas\Config\Config $config = null
    ) {
        $this->searchTable = $searchTable;
        $this->sessionId = $sessionId;
        $this->resultsManager = $resultsManager;
        $this->config = $config;
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
        $saved = $schedule = $unsaved = [];
        foreach ($searchHistory as $current) {
            $search = $current->getSearchObject()->deminify($this->resultsManager);
            if ($current->saved == 1) {
                $saved[] = $search;
            } else {
                $unsaved[] = $search;
            }
            if ($search->getOptions()->supportsScheduledSearch()) {
                $schedule[$search->getSearchId()] = $current->notification_frequency;
            }
        }

        return compact('saved', 'schedule', 'unsaved');
    }

    /**
     * Get a list of scheduling options (empty list if scheduling disabled).
     *
     * @return array
     */
    public function getScheduleOptions()
    {
        // If scheduled searches are disabled, return no options:
        if (!($this->config->Account->schedule_searches ?? false)) {
            return [];
        }
        // If custom frequencies are not provided, return defaults:
        if (!isset($this->config->Account->scheduled_search_frequencies)) {
            return [
                0 => 'schedule_none', 1 => 'schedule_daily', 7 => 'schedule_weekly'
            ];
        }
        // If we have a setting, make sure it is properly formatted as an array:
        return $this->config->Account->scheduled_search_frequencies instanceof Config
            ? $this->config->Account->scheduled_search_frequencies->toArray()
            : (array)$this->config->Account->scheduled_search_frequencies;
    }
}
