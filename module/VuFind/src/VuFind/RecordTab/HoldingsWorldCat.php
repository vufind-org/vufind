<?php
/**
 * Holdings (WorldCat) tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;
use VuFindSearch\Backend\WorldCat\Connector;

/**
 * Holdings (WorldCat) tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class HoldingsWorldCat extends AbstractBase
{
    /**
     * WorldCat connection
     *
     * @var Connector
     */
    protected $wc;

    /**
     * Constructor
     *
     * @param Connector $wc WorldCat connection
     */
    public function __construct(Connector $wc)
    {
        $this->wc = $wc;
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
        return empty($id) ? false : $this->wc->getHoldings($id);
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
        static $id = false;     // cache value in static variable
        if (!$id) {
            $id = $this->getRecordDriver()->tryMethod('getCleanOCLCNum');
        }
        return $id;
    }
}