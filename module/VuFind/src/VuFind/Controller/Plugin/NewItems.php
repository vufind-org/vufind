<?php
/**
 * VuFind Action Helper - New Items Support Methods
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller\Plugin;
use Zend\Mvc\Controller\Plugin\AbstractPlugin, Zend\Config\Config;

/**
 * Zend action helper to perform new items-related actions
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * @param \VuFind\ILS\Connection                     $catalog ILS connection
     * @param \VuFind\Search\Solr\Params                 $params  Solr parameters
     * @param string                                     $range   Range setting
     * @param string                                     $dept    Department setting
     * @param \Zend\Mvc\Controller\Plugin\FlashMessenger $flash   Flash messenger
     *
     * @return array
     */
    public function getBibIDsFromCatalog($catalog, $params, $range, $dept, $flash)
    {
        // The code always pulls in enough catalog results to get a fixed number
        // of pages worth of Solr results.  Note that if the Solr index is out of
        // sync with the ILS, we may see fewer results than expected.
        $resultPages = $this->getResultPages();
        $perPage = $params->getLimit();
        $newItems = $catalog->getNewItems(1, $perPage * $resultPages, $range, $dept);

        // Build a list of unique IDs
        $bibIDs = [];
        for ($i = 0; $i < count($newItems['results']); $i++) {
            $bibIDs[] = $newItems['results'][$i]['id'];
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
        return isset($this->config->method) ? $this->config->method : 'ils';
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