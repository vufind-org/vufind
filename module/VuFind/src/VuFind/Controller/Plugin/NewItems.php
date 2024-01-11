<?php

/**
 * VuFind Action Helper - New Items Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

use function array_slice;
use function count;
use function intval;
use function is_string;

/**
 * Action helper to perform new items-related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class NewItems extends AbstractPlugin
{
    /**
     * Configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Figure out which bib IDs to load from the ILS.
     *
     * @param \VuFind\ILS\Connection     $catalog ILS connection
     * @param \VuFind\Search\Solr\Params $params  Solr parameters
     * @param string                     $range   Range setting
     * @param string                     $dept    Department setting
     * @param FlashMessenger             $flash   Flash messenger
     *
     * @return array
     */
    public function getBibIDsFromCatalog($catalog, $params, $range, $dept, $flash)
    {
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
    public function getFundList()
    {
        if ($this->getMethod() == 'ils') {
            $catalog = $this->getController()->getILS();
            return $catalog->checkCapability('getFunds')
                ? $catalog->getFunds() : [];
        }
        return [];
    }

    /**
     * Get the hidden filter settings.
     *
     * @return array
     */
    public function getHiddenFilters()
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
    public function getMaxAge()
    {
        return max($this->getRanges());
    }

    /**
     * Get method setting
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->config->method ?? 'ils';
    }

    /**
     * Get range settings
     *
     * @return array
     */
    public function getRanges()
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
    public function getResultPages()
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
    public function getSolrFilter($range)
    {
        return 'first_indexed:[NOW-' . $range . 'DAY TO NOW]';
    }
}
