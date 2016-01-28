<?php
/**
 * Autocomplete view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Autocomplete view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Autocomplete extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Search configuration.
     *
     * @var \Zend\Config\Config
     */
    protected $searchConfig = null;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $searchConfig Search configiration.
     */
    public function __construct($searchConfig)
    {
        $this->searchConfig = $searchConfig;
    }

    /**
     * Get filters.
     *
     * @param string $searchTab Current search tab.
     *
     * @return array
     */
    public function getFilters($searchTab = null)
    {
        $filters = !empty($this->searchConfig->Autocomplete_Sections->filters)
            ? $this->searchConfig->Autocomplete_Sections->filters->toArray() : [];

        if (empty($filters)) {
            return [];
        }
        $result = [];
        foreach ($filters as $filter) {
            $data = explode('|', $filter);
            if (count($data) < 2) {
                continue;
            }
            $tabs = count($data) > 2 && !empty($data[2])
                ? explode('&', $data[2]) : [];
            if (!empty($tabs) && !$searchTab || !in_array($searchTab, $tabs)) {
                continue;
            }

            $filterItems = [];
            foreach (explode('&', $data[0]) as $filterItem) {
                $filterItem = explode(':', $filterItem);
                if (count($filterItem) != 2) {
                    continue;
                }
                $filterItems[] = [$filterItem[0], $filterItem[1]];
            }
            $result[] = ['label' => $data[1], 'filters' => $filterItems];
        }
        return $result;
    }

    /**
     * Get search handlers.
     *
     * @param string $searchTab Current search tab.
     *
     * @return array
     */
    public function getHandlers($searchTab = null)
    {
        $handlers = !empty($this->searchConfig->Autocomplete_Sections->handlers)
            ? $this->searchConfig->Autocomplete_Sections->handlers : [];

        $result = [];
        foreach ($handlers as $handler) {
            $handlerItem = explode('|', $handler);
            if ($searchTab) {
                $tabs = count($handlerItem) > 2 && !empty($handlerItem[2])
                    ? explode('&', $handlerItem[2]) : [];
                if (!empty($tabs) && !in_array($searchTab, $tabs)) {
                    continue;
                }
            }
            $result[] = [
               'handler' => $handlerItem[0],
               'label' => count($handlerItem) > 1 ? $handlerItem[1] : $handlerItem[0]
            ];
        }
        return $result;
    }

    /**
     * Is phrase search option enabled?
     *
     * @return boolean
     */
    public function getPhraseSearch(
    ) {
        return !empty($this->searchConfig->Autocomplete_Sections->phrase)
            ? $this->searchConfig->Autocomplete_Sections->phrase : false;
    }
}
