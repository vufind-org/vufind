<?php

/**
 * ILS support for MARC and other types of records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Feature;

use VuFind\Exception\ILS as ILSException;

use function in_array;

/**
 * ILS support for MARC and other types of records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait IlsAwareTrait
{
    /**
     * ILS connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils = null;

    /**
     * Backends with ILS integration.
     *
     * @var string[]
     */
    protected $ilsBackends = [];

    /**
     * Hold logic
     *
     * @var \VuFind\ILS\Logic\Holds
     */
    protected $holdLogic = null;

    /**
     * Title hold logic
     *
     * @var \VuFind\ILS\Logic\TitleHolds
     */
    protected $titleHoldLogic = null;

    /**
     * Attach an ILS connection and related logic to the driver
     *
     * @param \VuFind\ILS\Connection       $ils            ILS connection
     * @param \VuFind\ILS\Logic\Holds      $holdLogic      Hold logic handler
     * @param \VuFind\ILS\Logic\TitleHolds $titleHoldLogic Title hold logic handler
     *
     * @return void
     */
    public function attachILS(
        \VuFind\ILS\Connection $ils,
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
    protected function hasILS()
    {
        return null !== $this->ils
            && in_array($this->getSourceIdentifier(), $this->ilsBackends);
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        return $this->hasILS() ? $this->holdLogic->getHoldings(
            $this->getUniqueID(),
            $this->tryMethod('getConsortialIDs')
        ) : [];
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
        if ($this->hasILS()) {
            $biblioLevel = strtolower($this->tryMethod('getBibliographicLevel'));
            if ('monograph' == $biblioLevel || strstr($biblioLevel, 'part')) {
                if ($this->ils->getTitleHoldsMode() != 'disabled') {
                    return $this->titleHoldLogic->getHold($this->getUniqueID());
                }
            }
        }

        return false;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $params = [$this->getUniqueId()];
        return $this->hasILS() && $this->ils->checkCapability('getUrlsForRecord', $params)
            ? $this->ils->getUrlsForRecord($this->getUniqueId())
            : [];
    }

    /**
     * Set the list of backends that support ILS integration.
     *
     * @param array $backends List of backends that support ILS integration
     *
     * @return void
     */
    public function setIlsBackends($backends)
    {
        $this->ilsBackends = $backends;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        // as AJAX status lookups are done via the ILS AJAX status lookup support is
        // only given if the ILS is available for this record
        return $this->hasILS();
    }
}
