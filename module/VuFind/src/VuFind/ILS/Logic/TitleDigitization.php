<?php

/**
 * Title Digitization Logic Class.
 *
 * PHP version 8
 *
 * Copyright (C) Mannheim University Library 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
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
 * Title Digitization Logic Class.
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TitleDigitization
{
    /**
     * Constructor.
     *
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuth ILS authenticator
     * @param ILSConnection                 $catalog A catalog connection
     * @param \VuFind\Crypt\HMAC            $hmac    HMAC generator
     * @param array                         $config  VuFind configuration
     */
    public function __construct(
        protected \VuFind\Auth\ILSAuthenticator $ilsAuth,
        protected ILSConnection $catalog,
        protected \VuFind\Crypt\HMAC $hmac,
        protected array $config
    ) {
    }

    /**
     * Public method for getting title level digitization requests.
     *
     * @param string $id            A Bib ID
     * @param array  $linkOverrides Optional id and source to override standard record driver
     *
     * @return string|bool URL to place digitization request, or false if unavailable
     */
    public function getDigitizationRequest($id, array $linkOverrides = [])
    {
        $mode = $this->catalog->getTitleDigitizationMode();
        if ($mode == 'disabled') {
            return false;
        } elseif ($mode == 'driver') {
            try {
                $patron = $this->ilsAuth->storedCatalogLogin();
                if (!$patron) {
                    return false;
                }
                return $this->driverDigitization($id, $patron, $linkOverrides);
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
            return $this->generateDigitizationRequest($id, $mode, $patron, $linkOverrides);
        }
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
     * Support method for getDigitizationRequest to determine if we should override the configured
     * digitization request mode.
     *
     * @param string $id   Record ID to check
     * @param string $mode Current mode
     *
     * @return string
     */
    protected function checkOverrideMode($id, $mode)
    {
        if ($this->config['Catalog']['allow_digitization_override'] ?? false) {
            $holdings = $this->getHoldings($id);

            // For title digitization, the most important override feature to handle
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
     * Protected method for driver defined title digitization.
     *
     * @param string $id            A Bib ID
     * @param array  $patron        An Array of patron data
     * @param array  $linkOverrides Optional id and source to override standard record driver
     *
     * @return mixed A url on success, boolean false on failure
     */
    protected function driverDigitization($id, $patron, array $linkOverrides = [])
    {
        // Get Hold Details
        $checkDigitizationRequests = $this->catalog->checkFunction(
            'DigitizationRequests',
            compact('id', 'patron')
        );

        if (isset($checkDigitizationRequests['HMACKeys'])) {
            $data = ['id' => $id, 'level' => 'title'];
            $result = $this->catalog->checkDigitizationRequestIsValid($id, $data, $patron);
            if (
                (is_array($result) && $result['valid'])
                || (is_bool($result) && $result)
            ) {
                return $this->getDigitizationRequestDetails($data, $checkDigitizationRequests['HMACKeys'], $linkOverrides);
            }
        }
        return false;
    }

    /**
     * Protected method to generate a digitization request.
     *
     * @param string $id            A Bib ID
     * @param string $type          The digitization mode to be applied from:
     *                              (disabled, always, availability,
     *                              driver)
     * @param array  $patron        Patron
     * @param array  $linkOverrides Optional id and source to override standard record driver
     *
     * @return mixed A url on success, boolean false on failure
     */
    protected function generateDigitizationRequest($id, $type, $patron, array $linkOverrides = [])
    {
        $any_available = false;
        $addlink = false;

        $data = [
            'id' => $id,
            'level' => 'title',
        ];
        // Are digitization requests allowed?
        $checkDigitizationRequests = $this->catalog->checkFunction(
            'DigitizationRequests',
            compact('id', 'patron')
        );

        if ($checkDigitizationRequests) {
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
                if ($checkDigitizationRequests['function'] == 'getDigitizationRequestLink') {
                    // Return opac link
                    return $this->catalog->getDigitizationRequestLink($id, $data);
                } else {
                    // Return non-opac link
                    return $this->getDigitizationRequestDetails($data, $checkDigitizationRequests['HMACKeys'], $linkOverrides);
                }
            }
        }
        return false;
    }

    /**
     * Get Request Details.
     *
     * Supplies the form details required to place a hold
     *
     * @param array $data          An array of item data
     * @param array $HMACKeys      An array of keys to hash
     * @param array $linkOverrides Optional id and source to override standard record driver
     *
     * @return array Details for generating URL
     */
    protected function getDigitizationRequestDetails($data, $HMACKeys, array $linkOverrides)
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
            'action' => 'DigitizationRequest',
            'record' => $linkOverrides['id'] ?? $data['id'],
            'source' => $linkOverrides['source'] ?? DEFAULT_SEARCH_BACKEND,
            'query' => $queryString,
            'anchor' => '#tabnav',
        ];
    }
}
