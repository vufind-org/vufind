<?php

/**
 * Title Hold Logic Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Logic;

use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection as ILSConnection;

use function in_array;
use function is_array;
use function is_bool;

/**
 * Title Hold Logic Class
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TitleHolds
{
    /**
     * ILS authenticator
     *
     * @var \VuFind\Auth\ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * Catalog connection object
     *
     * @var ILSConnection
     */
    protected $catalog;

    /**
     * HMAC generator
     *
     * @var \VuFind\Crypt\HMAC
     */
    protected $hmac;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Holding locations to hide from display
     *
     * @var array
     */
    protected $hideHoldings = [];

    /**
     * Constructor
     *
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator
     * @param ILSConnection                 $ils     A catalog connection
     * @param \VuFind\Crypt\HMAC            $hmac    HMAC generator
     * @param \Laminas\Config\Config        $config  VuFind configuration
     */
    public function __construct(
        \VuFind\Auth\ILSAuthenticator $ilsAuth,
        ILSConnection $ils,
        \VuFind\Crypt\HMAC $hmac,
        \Laminas\Config\Config $config
    ) {
        $this->ilsAuth = $ilsAuth;
        $this->hmac = $hmac;
        $this->config = $config;

        if (isset($this->config->Record->hide_holdings)) {
            foreach ($this->config->Record->hide_holdings as $current) {
                $this->hideHoldings[] = $current;
            }
        }

        $this->catalog = $ils;
    }

    /**
     * Public method for getting title level holds
     *
     * @param string $id A Bib ID
     *
     * @return string|bool URL to place hold, or false if hold option unavailable
     *
     * @todo Indicate login failure or ILS connection failure somehow?
     */
    public function getHold($id)
    {
        // Get Holdings Data
        if ($this->catalog) {
            $mode = $this->catalog->getTitleHoldsMode();
            if ($mode == 'disabled') {
                return false;
            } elseif ($mode == 'driver') {
                try {
                    $patron = $this->ilsAuth->storedCatalogLogin();
                    if (!$patron) {
                        return false;
                    }
                    return $this->driverHold($id, $patron);
                } catch (ILSException $e) {
                    return false;
                }
            } else {
                try {
                    $patron = $this->ilsAuth->storedCatalogLogin();
                } catch (ILSException $e) {
                    $patron = false;
                }
                $mode = $this->checkOverrideMode($id, $mode);
                return $this->generateHold($id, $mode, $patron);
            }
        }
        return false;
    }

    /**
     * Get holdings for a particular record.
     *
     * @param string $id ID to retrieve
     *
     * @return array
     */
    protected function getHoldings($id)
    {
        // Cache results in a static array since the same holdings may be requested
        // multiple times during a run through the class:
        static $holdings = [];

        if (!isset($holdings[$id])) {
            $holdings[$id] = $this->catalog->getHolding($id)['holdings'];
        }
        return $holdings[$id];
    }

    /**
     * Support method for getHold to determine if we should override the configured
     * holds mode.
     *
     * @param string $id   Record ID to check
     * @param string $mode Current mode
     *
     * @return string
     */
    protected function checkOverrideMode($id, $mode)
    {
        if (
            isset($this->config->Catalog->allow_holds_override)
            && $this->config->Catalog->allow_holds_override
        ) {
            $holdings = $this->getHoldings($id);

            // For title holds, the most important override feature to handle
            // is to prevent displaying a link if all items are disabled. We
            // may eventually want to address other scenarios as well.
            $allDisabled = true;
            foreach ($holdings as $holding) {
                if (
                    !isset($holding['holdOverride'])
                    || 'disabled' != $holding['holdOverride']
                ) {
                    $allDisabled = false;
                }
            }
            $mode = (true == $allDisabled) ? 'disabled' : $mode;
        }
        return $mode;
    }

    /**
     * Protected method for driver defined title holds
     *
     * @param string $id     A Bib ID
     * @param array  $patron An Array of patron data
     *
     * @return mixed A url on success, boolean false on failure
     */
    protected function driverHold($id, $patron)
    {
        // Get Hold Details
        $checkHolds = $this->catalog->checkFunction(
            'Holds',
            compact('id', 'patron')
        );

        if (isset($checkHolds['HMACKeys'])) {
            $data = ['id' => $id, 'level' => 'title'];
            $result = $this->catalog->checkRequestIsValid($id, $data, $patron);
            if (
                (is_array($result) && $result['valid'])
                || (is_bool($result) && $result)
            ) {
                return $this->getHoldDetails($data, $checkHolds['HMACKeys']);
            }
        }
        return false;
    }

    /**
     * Protected method for vufind (i.e. User) defined holds
     *
     * @param string $id     A Bib ID
     * @param string $type   The holds mode to be applied from:
     * (disabled, always, availability, driver)
     * @param array  $patron Patron
     *
     * @return mixed A url on success, boolean false on failure
     */
    protected function generateHold($id, $type, $patron)
    {
        $any_available = false;
        $addlink = false;

        $data = [
            'id' => $id,
            'level' => 'title',
        ];

        // Are holds allows?
        $checkHolds = $this->catalog->checkFunction(
            'Holds',
            compact('id', 'patron')
        );

        if ($checkHolds != false) {
            if ($type == 'always') {
                $addlink = true;
            } elseif ($type == 'availability') {
                $holdings = $this->getHoldings($id);
                foreach ($holdings as $holding) {
                    if (
                        $holding['availability']->isAvailable()
                        && !in_array($holding['location'], $this->hideHoldings)
                    ) {
                        $any_available = true;
                    }
                }
                $addlink = !$any_available;
            }

            if ($addlink) {
                if ($checkHolds['function'] == 'getHoldLink') {
                    // Return opac link
                    return $this->catalog->getHoldLink($id, $data);
                } else {
                    // Return non-opac link
                    return $this->getHoldDetails($data, $checkHolds['HMACKeys']);
                }
            }
        }
        return false;
    }

    /**
     * Get Hold Link
     *
     * Supplies the form details required to place a hold
     *
     * @param array $data     An array of item data
     * @param array $HMACKeys An array of keys to hash
     *
     * @return array          Details for generating URL
     */
    protected function getHoldDetails($data, $HMACKeys)
    {
        // Generate HMAC
        $HMACkey = $this->hmac->generate($HMACKeys, $data);

        // Add Params
        $queryString = [];
        foreach ($data as $key => $param) {
            $needle = in_array($key, $HMACKeys);
            if ($needle) {
                $queryString[] = $key . '=' . urlencode($param);
            }
        }

        // Add HMAC
        $queryString[] = 'hashKey=' . urlencode($HMACkey);
        $queryString = implode('&', $queryString);

        // Build Params
        return [
            'action' => 'Hold', 'record' => $data['id'], 'query' => $queryString,
            'anchor' => '#tabnav',
        ];
    }
}
