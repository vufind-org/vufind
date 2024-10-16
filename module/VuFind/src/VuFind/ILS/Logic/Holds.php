<?php

/**
 * Hold Logic Class
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

/**
 * Hold Logic Class
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Holds
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
     * Support method to rearrange the holdings array for displaying convenience.
     *
     * @param array $holdings An associative array of location => item array
     *
     * @return array          An associative array keyed by location with each
     * entry being an array with 'notes', 'summary' and 'items' keys. The 'notes'
     * and 'summary' arrays are note/summary information collected from within the
     * items.
     */
    protected function formatHoldings($holdings)
    {
        $retVal = [];

        $textFieldNames = $this->catalog->getHoldingsTextFieldNames();

        foreach ($holdings as $groupKey => $items) {
            $retVal[$groupKey] = [
                'items' => $items,
                'location' => $items[0]['location'] ?? '',
                'locationhref' => $items[0]['locationhref'] ?? '',
            ];
            // Copy all text fields from the item to the holdings level
            foreach ($items as $item) {
                foreach ($textFieldNames as $fieldName) {
                    if (in_array($fieldName, ['notes', 'holdings_notes'])) {
                        if (empty($item[$fieldName])) {
                            // begin aliasing
                            if (
                                $fieldName == 'notes'
                                && !empty($item['holdings_notes'])
                            ) {
                                // using notes as alias for holdings_notes
                                $item[$fieldName] = $item['holdings_notes'];
                            } elseif (
                                $fieldName == 'holdings_notes'
                                && !empty($item['notes'])
                            ) {
                                // using holdings_notes as alias for notes
                                $item[$fieldName] = $item['notes'];
                            }
                        }
                    }

                    if (!empty($item[$fieldName])) {
                        $targetRef = & $retVal[$groupKey]['textfields'][$fieldName];
                        foreach ((array)$item[$fieldName] as $field) {
                            if (empty($targetRef) || !in_array($field, $targetRef)) {
                                $targetRef[] = $field;
                            }
                        }
                    }
                }

                // Handle purchase history
                if (!empty($item['purchase_history'])) {
                    $targetRef = & $retVal[$groupKey]['purchase_history'];
                    foreach ((array)$item['purchase_history'] as $field) {
                        if (empty($targetRef) || !in_array($field, $targetRef)) {
                            $targetRef[] = $field;
                        }
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id      A Bib ID
     * @param array  $ids     A list of Source Records (if catalog is for a
     * consortium)
     * @param array  $options Optional options to pass on to getHolding()
     *
     * @return array A sorted results set
     */
    public function getHoldings($id, $ids = null, $options = [])
    {
        if (!$this->catalog) {
            return [];
        }
        // Retrieve stored patron credentials; it is the responsibility of the
        // controller and view to inform the user that these credentials are
        // needed for hold data.
        try {
            $patron = $this->ilsAuth->storedCatalogLogin();

            // Does this ILS Driver handle consortial holdings?
            $config = $this->catalog->checkFunction(
                'Holds',
                compact('id', 'patron')
            );
        } catch (ILSException $e) {
            $patron = false;
            $config = [];
        }

        if (isset($config['consortium']) && $config['consortium'] == true) {
            $result = $this->catalog->getConsortialHoldings(
                $id,
                $patron ? $patron : null,
                $ids
            );
        } else {
            $result = $this->catalog
                ->getHolding($id, $patron ? $patron : null, $options);
        }

        $grb = 'getRequestBlocks'; // use variable to shorten line below:
        $blocks
            = $patron && $this->catalog->checkCapability($grb, compact('patron'))
            ? $this->catalog->getRequestBlocks($patron) : false;

        $mode = $this->catalog->getHoldsMode();

        if ($mode == 'disabled') {
            $holdings = $this->standardHoldings($result);
        } elseif ($mode == 'driver') {
            $holdings = $this->driverHoldings($result, $config, !empty($blocks));
        } else {
            $holdings = $this->generateHoldings($result, $mode, $config);
        }

        $holdings = $this->processStorageRetrievalRequests(
            $holdings,
            $id,
            $patron,
            !empty($blocks)
        );
        $holdings = $this->processILLRequests(
            $holdings,
            $id,
            $patron,
            !empty($blocks)
        );

        $result['blocks'] = $blocks;
        $result['holdings'] = $this->formatHoldings($holdings);

        return $result;
    }

    /**
     * Protected method for standard (i.e. No Holds) holdings
     *
     * @param array $result A result set returned from a driver
     *
     * @return array A sorted results set
     */
    protected function standardHoldings($result)
    {
        $holdings = [];
        if ($result['total']) {
            foreach ($result['holdings'] as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    $groupKey = $this->getHoldingsGroupKey($copy);
                    $holdings[$groupKey][] = $copy;
                }
            }
        }
        return $holdings;
    }

    /**
     * Protected method for driver defined holdings
     *
     * @param array $result          A result set returned from a driver
     * @param array $holdConfig      Hold configuration from driver
     * @param bool  $requestsBlocked Are user requests blocked?
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result, $holdConfig, $requestsBlocked)
    {
        $holdings = [];

        if ($result['total']) {
            foreach ($result['holdings'] as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    if ($holdConfig) {
                        // Is this copy holdable / linkable
                        if (
                            !$requestsBlocked
                            && ($copy['addLink'] ?? false)
                            && ($copy['is_holdable'] ?? true)
                        ) {
                            $copy['link'] = $this->getRequestDetails(
                                $copy,
                                $holdConfig['HMACKeys'],
                                'Hold'
                            );
                            $copy['linkLightbox'] = true;
                            // If we are unsure whether hold options are available,
                            // set a flag so we can check later via AJAX:
                            $copy['check'] = $copy['addLink'] === 'check';
                        }
                    }

                    $groupKey = $this->getHoldingsGroupKey($copy);
                    $holdings[$groupKey][] = $copy;
                }
            }
        }
        return $holdings;
    }

    /**
     * Protected method for vufind (i.e. User) defined holdings
     *
     * @param array  $result     A result set returned from a driver
     * @param string $type       The holds mode to be applied from:
     * (all, holds, recalls, availability)
     * @param array  $holdConfig Hold configuration from driver
     *
     * @return array A sorted results set
     */
    protected function generateHoldings($result, $type, $holdConfig)
    {
        $holdings = [];
        $any_available = false;

        $holds_override = $this->config->Catalog->allow_holds_override ?? false;

        if ($result['total']) {
            foreach ($result['holdings'] as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    $groupKey = $this->getHoldingsGroupKey($copy);
                    $holdings[$groupKey][] = $copy;
                    // Are any copies available?
                    if ($copy['availability']->isAvailable()) {
                        $any_available = true;
                    }
                }
            }

            if ($holdConfig && is_array($holdings)) {
                // Generate Links
                // Loop through each holding
                foreach ($holdings as $location_key => $location) {
                    foreach ($location as $copy_key => $copy) {
                        // Override the default hold behavior with a value from
                        // the ILS driver if allowed and applicable:
                        $currentType
                            = ($holds_override && isset($copy['holdOverride']))
                            ? $copy['holdOverride'] : $type;

                        switch ($currentType) {
                            case 'all':
                                $addlink = true; // always provide link
                                break;
                            case 'holds':
                                $addlink = $copy['availability']->isAvailable();
                                break;
                            case 'recalls':
                                $addlink = !$copy['availability']->isAvailable();
                                break;
                            case 'availability':
                                $addlink = !$copy['availability']->isAvailable()
                                    && ($any_available == false);
                                break;
                            default:
                                $addlink = false;
                                break;
                        }
                        // If a valid holdable status has been set, use it to
                        // determine if a hold link is created
                        if ($addlink && ($copy['is_holdable'] ?? true)) {
                            if ($holdConfig['function'] == 'getHoldLink') {
                                /* Build opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->catalog->getHoldLink(
                                        $copy['id'],
                                        $copy
                                    );
                                $holdings[$location_key][$copy_key]['linkLightbox']
                                    = false;
                            } else {
                                /* Build non-opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->getRequestDetails(
                                        $copy,
                                        $holdConfig['HMACKeys'],
                                        'Hold'
                                    );
                                $holdings[$location_key][$copy_key]['linkLightbox']
                                    = true;
                            }
                        }
                    }
                }
            }
        }
        return $holdings;
    }

    /**
     * Process storage retrieval request information in holdings and set the links
     * accordingly.
     *
     * @param array  $holdings        Holdings
     * @param string $id              Record ID
     * @param array  $patron          Patron
     * @param bool   $requestsBlocked Are user requests blocked?
     *
     * @return array Modified holdings
     */
    protected function processStorageRetrievalRequests(
        $holdings,
        $id,
        $patron,
        $requestsBlocked
    ) {
        if (!is_array($holdings)) {
            return $holdings;
        }

        // Are storage retrieval requests allowed?
        $requestConfig = $this->catalog->checkFunction(
            'StorageRetrievalRequests',
            compact('id', 'patron')
        );

        if (!$requestConfig) {
            return $holdings;
        }

        // Generate Links
        // Loop through each holding
        foreach ($holdings as &$location) {
            foreach ($location as &$copy) {
                // Is this copy requestable
                if (
                    !$requestsBlocked
                    && isset($copy['addStorageRetrievalRequestLink'])
                    && $copy['addStorageRetrievalRequestLink']
                ) {
                    $copy['storageRetrievalRequestLink'] = $this->getRequestDetails(
                        $copy,
                        $requestConfig['HMACKeys'],
                        'StorageRetrievalRequest'
                    );
                    // If we are unsure whether request options are
                    // available, set a flag so we can check later via AJAX:
                    $copy['checkStorageRetrievalRequest']
                        = $copy['addStorageRetrievalRequestLink'] === 'check';
                }
            }
        }
        return $holdings;
    }

    /**
     * Process ILL request information in holdings and set the links accordingly.
     *
     * @param array  $holdings        Holdings
     * @param string $id              Record ID
     * @param array  $patron          Patron
     * @param bool   $requestsBlocked Are user requests blocked?
     *
     * @return array Modified holdings
     */
    protected function processILLRequests($holdings, $id, $patron, $requestsBlocked)
    {
        if (!is_array($holdings)) {
            return $holdings;
        }

        // Are storage retrieval requests allowed?
        $requestConfig = $this->catalog->checkFunction(
            'ILLRequests',
            compact('id', 'patron')
        );

        if (!$requestConfig) {
            return $holdings;
        }

        // Generate Links
        // Loop through each holding
        foreach ($holdings as &$location) {
            foreach ($location as &$copy) {
                // Is this copy requestable
                if (
                    !$requestsBlocked && isset($copy['addILLRequestLink'])
                    && $copy['addILLRequestLink']
                ) {
                    $copy['ILLRequestLink'] = $this->getRequestDetails(
                        $copy,
                        $requestConfig['HMACKeys'],
                        'ILLRequest'
                    );
                    // If we are unsure whether request options are
                    // available, set a flag so we can check later via AJAX:
                    $copy['checkILLRequest']
                        = $copy['addILLRequestLink'] === 'check';
                }
            }
        }
        return $holdings;
    }

    /**
     * Get Hold Form
     *
     * Supplies holdLogic with the form details required to place a request
     *
     * @param array  $details  An array of item data
     * @param array  $HMACKeys An array of keys to hash
     * @param string $action   The action for which the details are built
     *
     * @return array             Details for generating URL
     */
    protected function getRequestDetails($details, $HMACKeys, $action)
    {
        // Include request type in the details
        $details['requestType'] = $action;

        if (
            ($details['availability'] ?? null) instanceof AvailabilityStatusInterface
            && empty($details['status'])
        ) {
            $details['status'] = $details['availability']->getStatusDescription();
        }

        // Generate HMAC
        $HMACkey = $this->hmac->generate($HMACKeys, $details);

        // Add Params
        $queryString = [];
        foreach ($details as $key => $param) {
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
            'action' => $action, 'record' => $details['id'],
            'source' => $details['source'] ?? DEFAULT_SEARCH_BACKEND,
            'query' => $queryString, 'anchor' => '#tabnav',
        ];
    }

    /**
     * Get a grouping key for a holdings item
     *
     * @param array $copy Item information
     *
     * @return string Grouping key
     */
    protected function getHoldingsGroupKey($copy)
    {
        // Group by holdings id and location unless configured otherwise
        $grouping = $this->config->Catalog->holdings_grouping
            ?? 'holdings_id,location';

        $groupKey = '';

        // Multiple keys may be used here (delimited by comma)
        foreach (array_map('trim', explode(',', $grouping)) as $key) {
            // backwards-compatibility:
            // The config.ini file originally expected only
            //   two possible settings: holdings_id and location_name.
            // However, when location_name was set, the code actually
            //   used the value of 'location' instead.
            // From now on, we will expect (via config.ini documentation)
            //   the value of 'location', but still continue to honor
            //   'location_name'.
            if ($key == 'location_name') {
                $key = 'location';
            }

            if (isset($copy[$key])) {
                if ($groupKey != '') {
                    $groupKey .= '|';
                }
                $groupKey .= $copy[$key];
            }
        }

        // default:
        if ($groupKey == '') {
            $groupKey = $copy['location'];
        }

        return $groupKey;
    }

    /**
     * Get an array of suppressed location names.
     *
     * @return array
     */
    public function getSuppressedLocations()
    {
        return $this->hideHoldings;
    }
}
