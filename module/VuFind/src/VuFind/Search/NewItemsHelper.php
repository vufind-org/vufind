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

use function intval;

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
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct(protected array $config)
    {
    }

    /**
     * Get default setting (null to use regular default).
     *
     * @return ?string
     */
    public function getDefaultSort(): ?string
    {
        return $this->config['default_sort'] ?? null;
    }

    /**
     * Should we include facets in the new items search page?
     *
     * @return bool
     */
    public function includeFacets(): bool
    {
        return $this->config['include_facets'] ?? false;
    }

    /**
     * Get the hidden filter settings.
     *
     * @return array
     */
    public function getHiddenFilters(): array
    {
        return (array)($this->config['filter'] ?? []);
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
        return $this->config['method'] ?? 'ils';
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
        if (isset($this->config['ranges'])) {
            $tmp = explode(',', $this->config['ranges']);
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
        if (isset($this->config['result_pages'])) {
            $resultPages = intval($this->config['result_pages']);
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
