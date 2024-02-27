<?php

/**
 * Component parts display tab
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019, 2022.
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

use VuFindSearch\Command\SearchCommand;

/**
 * Component parts display tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class ComponentParts extends AbstractBase
{
    /**
     * Similar records
     *
     * @var array
     */
    protected $results;

    /**
     * Maximum results to display
     *
     * @var int
     */
    protected $maxResults = 100;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service $search Search service
     */
    public function __construct(\VuFindSearch\Service $search)
    {
        $this->searchService = $search;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'child_records';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $children = $this->getRecordDriver()->tryMethod('getChildRecordCount');
        return $children !== null && $children > 0;
    }

    /**
     * Get the maximum result count.
     *
     * @return int
     */
    public function getMaxResults()
    {
        return $this->maxResults;
    }

    /**
     * Get the contents for display.
     *
     * @return RecordCollectionInterface
     */
    public function getResults()
    {
        $record = $this->getRecordDriver();
        $safeId = addcslashes($record->getUniqueId(), '"');
        $query = new \VuFindSearch\Query\Query(
            'hierarchy_parent_id:"' . $safeId . '"'
        );
        $params = new \VuFindSearch\ParamBag(
            [
                // Disable highlighting for efficiency; not needed here:
                'hl' => ['false'],
                // Sort appropriately:
                'sort' => 'hierarchy_sequence ASC,title ASC',
            ]
        );
        $command = new SearchCommand(
            $record->getSourceIdentifier(),
            $query,
            0,
            // retrieve 1 more than max results, so we know when to
            // display a "more" link:
            $this->maxResults + 1,
            $params
        );
        return $this->searchService->invoke($command)->getResult();
    }
}
