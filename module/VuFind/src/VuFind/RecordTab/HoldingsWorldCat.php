<?php
/**
 * Holdings (WorldCat) tab
 *
 * PHP version 7
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

use VuFindSearch\Backend\WorldCat\Command\GetHoldingsCommand;
use VuFindSearch\Service;

/**
 * Holdings (WorldCat) tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HoldingsWorldCat extends AbstractBase
{
    /**
     * Search service
     *
     * @var Service
     */
    protected $searchService;

    /**
     * Constructor
     *
     * @param Service $searchService Search service
     */
    public function __construct(Service $searchService)
    {
        $this->searchService = $searchService;
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
     * Get holdings information from WorldCat (false if none available).
     *
     * @return \SimpleXMLElement|bool
     */
    public function getHoldings()
    {
        $id = $this->getOCLCNum();
        if (empty($id)) {
            return false;
        }
        $command = new GetHoldingsCommand('WorldCat', $id);
        return $this->searchService->invoke($command)->getResult();
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $id = $this->getOCLCNum();
        return !empty($id);
    }

    /**
     * Get the OCLC number from the active record driver.
     *
     * @return string
     */
    protected function getOCLCNum()
    {
        return $this->getRecordDriver()->tryMethod('getCleanOCLCNum');
    }
}
