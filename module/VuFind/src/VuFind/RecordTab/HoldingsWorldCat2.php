<?php

/**
 * Holdings (WorldCat2) tab
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

use VuFindSearch\Backend\WorldCat2\Command\GetHoldingsCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Service;

/**
 * Holdings (WorldCat2) tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HoldingsWorldCat2 extends AbstractBase
{
    /**
     * Constructor
     *
     * @param Service $searchService Search service
     * @param array   $defaults      Default parameters to include in API requests
     */
    public function __construct(protected Service $searchService, protected array $defaults = [])
    {
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
     * Get holdings information from WorldCat (null if none available).
     *
     * @return ?array
     */
    public function getHoldings()
    {
        $ids = $this->getIdentifiers();
        if (!$ids) {
            return null;
        }
        $command = new GetHoldingsCommand('WorldCat2', new ParamBag($ids + $this->defaults));
        return $this->searchService->invoke($command)->getResult();
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $ids = $this->getIdentifiers();
        return !empty($ids);
    }

    /**
     * Get the identifiers from the active record driver.
     *
     * @return array
     */
    protected function getIdentifiers(): array
    {
        $driver = $this->getRecordDriver();
        $params = [];
        if ($oclc = $driver->tryMethod('getCleanOCLCNum')) {
            $params['oclcNumber'] = $oclc;
        } elseif ($isbn = $driver->tryMethod('getCleanISBN')) {
            $params['isbn'] = $isbn;
        } elseif ($issn = $driver->tryMethod('getCleanISSN')) {
            $params['issn'] = $issn;
        }
        return $params;
    }
}
