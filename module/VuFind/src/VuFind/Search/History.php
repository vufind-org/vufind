<?php

/**
 * VuFind Search History Helper
 *
 * PHP version 8
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

use Exception;
use Laminas\Config\Config;
use VuFind\Db\Service\SearchServiceInterface;

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
     * History constructor
     *
     * @param SearchServiceInterface               $searchService  Search table
     * @param string                               $sessionId      Session ID
     * @param \VuFind\Search\Results\PluginManager $resultsManager Results manager
     * @param ?\Laminas\Config\Config              $config         Configuration
     */
    public function __construct(
        protected SearchServiceInterface $searchService,
        protected string $sessionId,
        protected \VuFind\Search\Results\PluginManager $resultsManager,
        protected ?\Laminas\Config\Config $config = null
    ) {
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
        $this->searchService->destroySession($this->sessionId, $userId);
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
        $searchHistory = $this->searchService->getSearches($this->sessionId, $userId);

        // Loop through and sort the history
        $saved = $schedule = $unsaved = [];
        foreach ($searchHistory as $current) {
            $search = $current->getSearchObject()?->deminify($this->resultsManager);
            if (!$search) {
                throw new Exception("Problem getting search object from search {$current->getId()}.");
            }
            if ($current->getSaved()) {
                $saved[] = $search;
            } else {
                $unsaved[] = $search;
            }
            if ($search->getOptions()->supportsScheduledSearch()) {
                $schedule[$current->getId()] = $current->getNotificationFrequency();
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
                0 => 'schedule_none', 1 => 'schedule_daily', 7 => 'schedule_weekly',
            ];
        }
        // If we have a setting, make sure it is properly formatted as an array:
        return $this->config->Account->scheduled_search_frequencies instanceof Config
            ? $this->config->Account->scheduled_search_frequencies->toArray()
            : (array)$this->config->Account->scheduled_search_frequencies;
    }
}
