<?php

/**
 * Holdings Helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use function strlen;

/**
 * Holdings Settings Helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Holdings extends \VuFind\View\Helper\Root\Holdings
{
    /**
     * Return the configured holding details mode
     *
     * @return string
     */
    public function getDetailsMode()
    {
        return empty($this->config['Record']['holdings_details'])
            ? 'expand-first'
            : $this->config['Record']['holdings_details'];
    }

    /**
     * Return configured thresholds for collapsing holdings.
     *
     * @return array|null
     */
    public function getCollapseThreshold()
    {
        return $this->config['Item_Status']['collapse_threshold'] ?? null;
    }

    /**
     * Return configured setting for showing holdings details after the location
     * group title so that they can be seen without "expanding" the group.
     * This only affects record page holdings.
     *
     * @return bool
     */
    public function showDetailsAfterLocationGroup()
    {
        $result = $this->config['Item_Status']['show_details_after_location_group']
            ?? false;
        return (bool)$result;
    }

    /**
     * Return configured option for showing link to record page in search results
     * holdings.
     *
     * @return bool
     */
    public function showLinkToRecordPage()
    {
        $result = $this->config['Item_Status']['show_link_to_record_page'] ?? false;
        return (bool)$result;
    }

    /**
     * Return configured option for showing place hold button link in search results
     * holdings.
     *
     * @return bool
     */
    public function showSearchResultsTitleHold()
    {
        return (bool)($this->config['Item_Status']['show_title_hold'] ?? false);
    }

    /**
     * Return configured setting for showing holdings summariy on record page.
     *
     * @return bool
     */
    public function showRecordPageSummary()
    {
        $result = $this->config['Item_Status']['show_holdings_summary'] ?? false;
        return (bool)$result;
    }

    /**
     * Return configured setting for overriding holdings ordering and re-sorting
     * holdings by availability and location name. This only affects non-Axiell
     * search results holdings.
     *
     * @return bool
     */
    public function overrideSortOrder()
    {
        return (bool)($this->config['Item_Status']['override_sort_order'] ?? false);
    }

    /**
     * Return configured limit for truncating holdings. This only affects non-Axiell
     * search results holdings.
     *
     * @return int
     */
    public function getTruncateLimit()
    {
        return (int)($this->config['Item_Status']['truncate_limit'] ?? 0);
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
            $result = [
                'location' => null,
                'collection' => null,
                'branch' => null,
                'department' => null,
                'id' => null, // for Wayfinder
            ];
            foreach ($items as $item) {
                if ($item['callnumber'] === $callNo) {
                    foreach (array_keys($result) as $key) {
                        if (!$result[$key] && isset($item[$key])) {
                            $result[$key] = $item[$key];
                        }
                    }
                }
            }
            // Use callnumber as the primary field but include callNo for backward
            // compatibility:
            $result['callnumber'] = $callNo;
            $result['callNo'] = $callNo;
            $callnumbers[] = $result;
        }
        return $callnumbers;
    }
}
