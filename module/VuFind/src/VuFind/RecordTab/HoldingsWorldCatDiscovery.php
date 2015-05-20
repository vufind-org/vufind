<?php
/**
 * Holdings (WorldCatDiscovery) tab
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

/**
 * Holdings (WorldCatDiscovery) tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class HoldingsWorldCatDiscovery extends AbstractBase
{
	
    /**
     * Constructor
     */
	public function __construct($config){
		$this->config = $config;
	}
    
    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Other Library Holdings';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $offers = $this->getHoldings();
        return !empty($offers);
    }
    
    /**
     * Get the holdings for libraries based
     * @return array
     */
    public function getHoldings()
    {
    	if ($this->config->General->showAllHoldings == true){
    		$offers = $this->getRecordDriver()->getOffers();
    	} else {
    		$offers = $this->getRecordDriver()->getOtherLibraryOffers();
    	}
    	return $offers;
    }
    
}