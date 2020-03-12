<?php
/**
 * Holdings Helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Zend\View\Helper\AbstractHelper;

/**
 * Holdings Settings Helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Holdings extends AbstractHelper
{
    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config = null)
    {
        $this->config = $config;
    }

    /**
     * Return the configured holding details mode
     *
     * @return string
     */
    public function getDetailsMode()
    {
        return empty($this->config->Record['holdings_details'])
            ? 'expand-first'
            : $this->config->Record['holdings_details'];
    }

    /**
     * Return configured thresholds for collapsing holdings.
     *
     * @return array|null
     */
    public function getCollapseThreshold()
    {
        return empty($this->config->Item_Status->collapse_threshold)
            ? null : $this->config->Item_Status->collapse_threshold->toArray();
    }

    /**
     * Return configured setting for showing holdings details after the location
     * group title so that they can be seen without "expanding" the group.
     * This only affects record page holdings.
     *
     * @return boolean
     */
    public function showDetailsAfterLocationGroup()
    {
        return empty($this->config->Item_Status->show_details_after_location_group)
            ? false
            : (bool)$this->config->Item_Status->show_details_after_location_group;
    }

    /**
     * Return configured option for showing
     * link to record page in search results holdings.
     *
     * @return boolean
     */
    public function showLinkToRecordPage()
    {
        return empty($this->config->Item_Status->show_link_to_record_page)
            ? false
            : (bool)$this->config->Item_Status->show_link_to_record_page;
    }

    /**
     * Return configured option for showing
     * place hold button link in search results holdings.
     *
     * @return boolean
     */
    public function showSearchResultsTitleHold()
    {
        return empty($this->config->Item_Status->show_title_hold)
            ? false
            : (bool)$this->config->Item_Status->show_title_hold;
    }

    /**
     * Return configured setting for showing
     * holdings summariy on record page.
     *
     * @return boolean
     */
    public function showRecordPageSummary()
    {
        return empty($this->config->Item_Status->show_holdings_summary)
            ? false
            : (bool)$this->config->Item_Status->show_holdings_summary;
    }

    /**
     * Return configured setting for overriding holdings ordering and
     * re-sorting holdings by availability and location name. This only affects
     * non-Axiell search results holdings.
     *
     * @return boolean
     */
    public function overrideSortOrder()
    {
        return empty($this->config->Item_Status->override_sort_order)
            ? false
            : (bool)$this->config->Item_Status->override_sort_order;
    }

    /**
     * Return configured limit for truncating holdings. This only affects non-Axiell
     * search results holdings.
     *
     * @return boolean
     */
    public function getTruncateLimit()
    {
        return !isset($this->config->Item_Status->truncate_limit)
            ? false
            : $this->config->Item_Status->truncate_limit;
    }

    /**
     * Get grouped unique call numbers for an items list
     *
     * @param array $items Items
     *
     * @return array
     */
    public function getGroupedCallNumbers($items)
    {
        $callnumbers = [];
        $callNos = [];
        foreach ($items as $item) {
            if (isset($item['callnumber']) && strlen($item['callnumber']) > 0) {
                $callNos[] = $item['callnumber'];
            }
        }
        sort($callNos);

        foreach (array_unique($callNos) as $callNo) {
            $collection = null;
            $location = null;
            foreach ($items as $item) {
                if ($item['callnumber'] === $callNo) {
                    if (!$collection && isset($item['collection'])) {
                        $collection = $item['collection'];
                    }
                    if (!$location && isset($item['location'])) {
                        $location = $item['location'];
                    }
                    if ($collection && $location) {
                        break;
                    }
                }
            }
            $callnumbers[] = compact('callNo', 'collection', 'location');
        }
        return $callnumbers;
    }
}
