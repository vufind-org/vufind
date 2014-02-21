<?php
/**
 * Model for Opac records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Marburg University 2013.
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
 * @category VuFind
 * @package  RecordDrivers
 * @author   Goetz Hatop <hatop@ub.uni-marburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */

namespace LBS4\RecordDriver;
use VuFind\RecordDriver\SolrDefault as SolrDefault;
use VuFind\Connection\Manager as ConnectionManager;
use VuFindHttp\HttpService as Service;
use VuFind\Exception\LoginRequired as LoginRequiredException;

/**
 * @category VuFind
 * @package RecordDrivers
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public 
 * @author Goetz Hatop 
 * @title Model for Pica RDF records in Solr.
 */

class SolrOpac extends SolrDefault 
{
    protected $fullRecord = null;

    /**
     * ILS connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils = null;

    /**
     * Hold logic
     *
     * @var \VuFind\ILS\Logic\Holds
     */
    protected $holdLogic;

    /**
     * Title hold logic
     *
     * @var \VuFind\ILS\Logic\TitleHolds
     */
    protected $titleHoldLogic;

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        if (isset($this->fields['format']) 
            && is_array($this->fields['format'])) {
            foreach ($this->fields['format'] as $format) {
            if ( $format == 'Book' )
                 return true;
            }
        }
        return false;
    }

    /**
     * Attach an ILS connection and related logic to the driver
     *
     * @param \VuFind\ILS\Connection       $ils            ILS connection
     * @param \VuFind\ILS\Logic\Holds      $holdLogic      Hold logic handler
     * @param \VuFind\ILS\Logic\TitleHolds $titleHoldLogic Title hold logic handler
     *
     * @return void
     */
    public function attachILS(\VuFind\ILS\Connection $ils,
        \VuFind\ILS\Logic\Holds $holdLogic,
        \VuFind\ILS\Logic\TitleHolds $titleHoldLogic
    ) {
        $this->ils = $ils;
        $this->holdLogic = $holdLogic;
        $this->titleHoldLogic = $titleHoldLogic;
    }


    /**
     * Do we have an attached ILS connection?
     *
     * @return bool
     */
    public function hasILS()
    {
        return null !== $this->ils;
    }
 

    /**
     * Get a link for placing a title level hold.
     *
     * @return mixed A url if a hold is possible, boolean false if not
     */
    public function getRealTimeTitleHold()
    {
        if ($this->hasILS() && isset($this->fields['id'])) {
            if (isset($this->fields['format']) 
                && is_array($this->fields['format'])) {
                foreach ($this->fields['format'] as $format) {
                if ( $format == 'Book' ) {
                     return $this->titleHoldLogic->getHold($this->fields['id']);
                }
                }
            }
        }
        return false;
    }

    /**
     * Get an array of information about record holdings, 
     * obtained in real-time from the ILS.
     * @return array
     */
    public function getRealTimeHoldings()
    {
        if (isset($this->fields['id'])) {
            return $this->hasILS()
                ? $this->holdLogic->getHoldings($this->fields['id'])
                : array();
        }
        return false;
    }
     
}
