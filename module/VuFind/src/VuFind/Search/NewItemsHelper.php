<?php

/**
 * VuFind Helper - New Items Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search;

use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use VuFind\Config\Config;
use VuFind\ILS\Connection;

use function array_slice;
use function count;
use function intval;
use function is_string;

/**
 * Helper to perform new items-related actions
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class NewItemsHelper
{
    /**
     * Configuration
     *
     * @var Config
     */
    protected Config $config;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected Connection $catalog;

    /**
     * Constructor
     *
     * @param Config     $config  Configuration
     * @param Connection $catalog ILS connection
     */
    public function __construct(Config $config, Connection $catalog)
    {
        $this->config = $config;
        $this->catalog = $catalog;
    }

    /**
     * Figure out which bib IDs to load from the ILS.
     *
     * @param Connection                 $catalog ILS connection (for backward compatibility)
     * @param \VuFind\Search\Solr\Params $params  Solr parameters
     * @param string                     $range   Range setting
     * @param string                     $dept    Department setting
     * @param FlashMessenger             $flash   Flash messenger
     *
     * @return array
     */
    public function getBibIDsFromCatalog(
        Connection $catalog,
        \VuFind\Search\Solr\Params $params,
        string $range,
        string $dept,
        FlashMessenger $flash
    ): array {
        // The code always pulls in enough catalog results to get a fixed number
        // of pages worth of Solr results. Note that if the Solr index is out of
        // sync with the ILS, we may see fewer results than expected.
        $resultPages = $this->getResultPages();
        $perPage = $params->getLimit();
        $newItems = $catalog->getNewItems(1, $perPage * $resultPages, $range, $dept);

        // Build a list of unique IDs
        $bibIDs = [];
        if (isset($newItems['results'])) {
            for ($i = 0; $i < count($newItems['results']); $i++) {
                $bibIDs[] = $newItems['results'][$i]['id'];
            }
        }

        // Truncate the list if it is too long:
        $limit = $params->getQueryIDLimit();
        if (count($bibIDs) > $limit) {
            $bibIDs = array_slice($bibIDs, 0, $limit);
            $flash->addMessage('too_many_new_items', 'info');
        }

        return $bibIDs;
    }

    /**
     * Get default setting (null to use regular default).
     *
     * @return ?string
     */
    public function getDefaultSort(): ?string
    {
        return $this->config->default_sort ?? null;
    }

    /**
     * Should we include facets in the new items search page?
     *
     * @return bool
     */
    public function includeFacets(): bool
    {
        return $this->config->include_facets ?? false;
    }

    /**
     * Get fund list
     *
     * @return array
     */
    public function getFundList(): array
    {
        if ($this->getMethod() == 'ils') {
            return $this->catalog->checkCapability('getFunds')
                ? $this->catalog->getFunds() : [];
        }
        return [];
    }

    /**
     * Get the hidden filter settings.
     *
     * @return array
     */
    public function getHiddenFilters(): array
    {
        if (!isset($this->config->filter)) {
            return [];
        }
        if (is_string($this->config->filter)) {
            return [$this->config->filter];
        }
        $hiddenFilters = [];
        foreach ($this->config->filter as $current) {
            $hiddenFilters[] = $current;
        }
        return $hiddenFilters;
    }

    /**
     * Get the maximum range setting (or return 0 for no limit).
     *
     * @return int
     */
    public function getMaxAge(): int
    {
        return max($this->getRanges());
    }

    /**
     * Get method setting
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->config->method ?? 'ils';
    }

    /**
     * Get range settings
     *
     * @return array
     */
    public function getRanges(): array
    {
        // Find out if there are user configured range options; if not,
        // default to the standard 1/5/30 days:
        $ranges = [];
        if (isset($this->config->ranges)) {
            $tmp = explode(',', $this->config->ranges);
            foreach ($tmp as $range) {
                $range = intval($range);
                if ($range > 0) {
                    $ranges[] = $range;
                }
            }
        }
        if (empty($ranges)) {
            $ranges = [1, 5, 30];
        }
        return $ranges;
    }

    /**
     * Get the result pages setting.
     *
     * @return int
     */
    public function getResultPages(): int
    {
        if (isset($this->config->result_pages)) {
            $resultPages = intval($this->config->result_pages);
            if ($resultPages < 1) {
                $resultPages = 10;
            }
        } else {
            $resultPages = 10;
        }
        return $resultPages;
    }

    /**
     * Get a Solr filter to limit to the specified number of days.
     *
     * @param int $range Days to search
     *
     * @return string
     */
    public function getSolrFilter(int $range): string
    {
        return 'first_indexed:[NOW-' . $range . 'DAY TO NOW]';
    }
}