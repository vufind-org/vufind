<?php
/**
 * Trait for attaching a record driver to an ILS.
 *
 * Prerequisite: the getUniqueID() method must be defined
 *
 * Helpful method: more functionality is available if getConsortialIDs() is defined
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use VuFind\Exception\ILS as ILSException,
    VuFind\ILS\Connection,
    VuFind\ILS\Logic\Holds,
    VuFind\ILS\Logic\TitleHolds;

/**
 * Trait for attaching a record driver to an ILS.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
trait IlsAwareTrait
{
    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils = null;

    /**
     * Hold logic
     *
     * @var Holds
     */
    protected $holdLogic;

    /**
     * Title hold logic
     *
     * @var TitleHolds
     */
    protected $titleHoldLogic;

    /**
     * Attach an ILS connection and related logic to the driver
     *
     * @param Connection $ils        ILS connection
     * @param Holds      $holds      Hold logic handler
     * @param TitleHolds $titleHolds Title hold logic handler
     *
     * @return void
     */
    public function attachILS(Connection $ils, Holds $holds, TitleHolds $titleHolds)
    {
        $this->ils = $ils;
        $this->holdLogic = $holds;
        $this->titleHoldLogic = $titleHolds;
    }

    /**
     * Do we have an attached ILS connection?
     *
     * @return bool
     */
    protected function hasILS()
    {
        return null !== $this->ils;
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        $consortialIDs = is_callable([$this, 'getConsortialIDs'])
            ? $this->getConsortialIDs() : null;
        return $this->hasILS()
            ? $this->holdLogic->getHoldings($this->getUniqueID(), $consortialIDs)
            : [];
    }

    /**
     * Get an array of information about record history, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Get Acquisitions Data
        if (!$this->hasILS()) {
            return [];
        }
        try {
            return $this->ils->getPurchaseHistory($this->getUniqueID());
        } catch (ILSException $e) {
            return [];
        }
    }

    /**
     * Get a link for placing a title level hold.
     *
     * @return mixed A url if a hold is possible, boolean false if not
     */
    public function getRealTimeTitleHold()
    {
        if ($this->hasILS() && $this->titleLevelHoldAllowed()) {
            if ($this->ils->getTitleHoldsMode() != 'disabled') {
                return $this->titleHoldLogic
                    ->getHold($this->getUniqueID(), $this->getResourceSource());
            }
        }

        return false;
    }

    /**
     * Is a title level hold allowed on this item?
     *
     * @return bool
     */
    protected function titleLevelHoldAllowed()
    {
        // Allowed by default; this method may be overridden in individual classes
        // implementing this trait.
        return true;
    }
}