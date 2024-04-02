<?php

/**
 * Holdings (ILS) tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\ILS\Connection;

use function strlen;

/**
 * Holdings (ILS) tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HoldingsILS extends AbstractBase
{
    /**
     * ILS connection (or null if not applicable)
     *
     * @var Connection
     */
    protected $catalog;

    /**
     * Name of template to use for rendering holdings.
     *
     * @var string
     */
    protected $template;

    /**
     * Whether the holdings tab should be hidden when empty or not.
     *
     * @var bool
     */
    protected $hideWhenEmpty;

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection|null $catalog       ILS connection to use to
     * check for holdings before displaying the tab; may be set to null if no check
     * is needed.
     * @param string|null                 $template      Holdings template to use
     * @param bool                        $hideWhenEmpty Whether the
     * holdings tab should be hidden when empty or not
     */
    public function __construct(
        Connection $catalog = null,
        $template = null,
        $hideWhenEmpty = false
    ) {
        $this->catalog = $catalog;
        $this->template = $template ?? 'standard';
        $this->hideWhenEmpty = $hideWhenEmpty;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Holdings';
    }

    /**
     * Support method used in getUniqueCallNumbers for templates when full
     * details are not supported -- extract all unique call numbers from
     * an array of items.
     *
     * @param array $items Items to search through.
     *
     * @return array
     */
    protected function getSimpleUniqueCallNumbers($items)
    {
        $callNos = [];
        foreach ($items as $item) {
            if (isset($item['callnumber']) && strlen($item['callnumber']) > 0) {
                $callNos[] = $item['callnumber'];
            }
        }
        sort($callNos);
        return array_unique($callNos);
    }

    /**
     * Support method used by template -- extract all unique call numbers from
     * an array of items.
     *
     * @param array $items       Items to search through.
     * @param bool  $fullDetails Whether or not to return the full details about
     *                           call numbers or only the simple legacy format.
     *
     * @return array
     */
    public function getUniqueCallNumbers($items, $fullDetails = false)
    {
        if (!$fullDetails) {
            return $this->getSimpleUniqueCallNumbers($items);
        }

        $callNos = [];
        foreach ($items as $item) {
            if (strlen($item['callnumber'] ?? '') > 0) {
                $prefix = $item['callnumber_prefix'] ?? '';
                $callnumber = $item['callnumber'];
                $display = $prefix ? $prefix . ' ' . $callnumber : $callnumber;
                $callNos[] = compact('callnumber', 'display', 'prefix');
            }
        }

        $unique = [];
        foreach ($callNos as $no) {
            $unique[$no['display']] = $no;
        }
        $callNosUnique = array_values($unique);

        uasort(
            $callNosUnique,
            function ($a, $b) {
                return $a['display'] <=> $b['display'];
            }
        );

        return $callNosUnique;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return ($this->catalog && $this->hideWhenEmpty)
            ? $this->catalog->hasHoldings($this->driver->getUniqueID()) : true;
    }

    /**
     * Get name of template for rendering holdings.
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Getting a paginator for the items list.
     *
     * @param int $totalItemCount Total count of items for a bib record
     * @param int $page           Currently selected page of the items paginator
     * @param int $itemLimit      Max. no of items per page
     *
     * @return \Laminas\Paginator\Paginator
     */
    public function getPaginator($totalItemCount, $page, $itemLimit)
    {
        // Return if a paginator is not needed or not supported ($itemLimit = null)
        if (!$itemLimit || $totalItemCount <= $itemLimit) {
            return;
        }

        // Create the paginator
        $nullAdapter = new \Laminas\Paginator\Adapter\NullFill($totalItemCount);
        $paginator = new \Laminas\Paginator\Paginator($nullAdapter);

        // Some settings for the paginator
        $paginator
            ->setCurrentPageNumber($page)
            ->setItemCountPerPage($itemLimit)
            ->setPageRange(10);

        return $paginator;
    }
}
