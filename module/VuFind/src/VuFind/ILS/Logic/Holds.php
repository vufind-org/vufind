<?php
/**
 * Hold Logic Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\ILS\Logic;
use VuFind\ILS\Connection as ILSConnection;

/**
 * Hold Logic Class
 *
 * @category VuFind2
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
     * @var \Zend\Config\Config
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
     * @param \Zend\Config\Config           $config  VuFind configuration
     */
    public function __construct(\VuFind\Auth\ILSAuthenticator $ilsAuth,
        ILSConnection $ils, \VuFind\Crypt\HMAC $hmac, \Zend\Config\Config $config
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
     * entry being an array with 'notes', 'summary' and 'items' keys.  The 'notes'
     * and 'summary' arrays are note/summary information collected from within the
     * items.
     */
    protected function formatHoldings($holdings)
    {
        $retVal = [];

        // Handle purchase history alongside other textual fields
        $textFieldNames = $this->catalog->getHoldingsTextFieldNames();
        $textFieldNames[] = 'purchase_history';

        foreach ($holdings as $groupKey => $items) {
            $retVal[$groupKey] = [
                'items' => $items,
                'location' => isset($items[0]['location'])
                    ? $items[0]['location'] : '',
                'locationhref' => isset($items[0]['locationhref'])
                    ? $items[0]['locationhref'] : ''
            ];
            // Copy all text fields from the item to the holdings level
            foreach ($items as $item) {
                foreach ($textFieldNames as $fieldName) {
                    if (!empty($item[$fieldName])) {
                        $fields = is_array($item[$fieldName])
                            ? $item[$fieldName]
                            : [$item[$fieldName]];

                        foreach ($fields as $field) {
                            if (empty($retVal[$groupKey][$fieldName])
                                || !in_array($field, $retVal[$groupKey][$fieldName])
                            ) {
                                $retVal[$groupKey][$fieldName][] = $field;
                            }
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
     * @param string $id  A Bib ID
     * @param array  $ids A list of Source Records (if catalog is for a consortium)
     *
     * @return array A sorted results set
     */
    public function getHoldings($id, $ids = null)
    {
        $holdings = [];

        // Get Holdings Data
        if ($this->catalog) {
            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            $patron = $this->ilsAuth->storedCatalogLogin();

            // Does this ILS Driver handle consortial holdings?
            $config = $this->catalog->checkFunction(
                'Holds', compact('id', 'patron')
            );
            if (isset($config['consortium']) && $config['consortium'] == true) {
                $result = $this->catalog->getConsortialHoldings(
                    $id, $patron ? $patron : null, $ids
                );
            } else {
                $result = $this->catalog->getHolding($id, $patron ? $patron : null);
            }

            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result, $config);
            } else {
                $holdings = $this->generateHoldings($result, $mode, $config);
            }

            $holdings = $this->processStorageRetrievalRequests(
                $holdings, $id, $patron
            );
            $holdings = $this->processILLRequests($holdings, $id, $patron);
        }
        return $this->formatHoldings($holdings);
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
        if (count($result)) {
            foreach ($result as $copy) {
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
     * @param array $result     A result set returned from a driver
     * @param array $holdConfig Hold configuration from driver
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result, $holdConfig)
    {
        $holdings = [];

        if (count($result)) {
            foreach ($result as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    if ($holdConfig) {
                        // Is this copy holdable / linkable
                        if (isset($copy['addLink']) && $copy['addLink']) {
                            // If the hold is blocked, link to an error page
                            // instead of the hold form:
                            $copy['link'] = $copy['addLink'] === 'block'
                                ? $this->getBlockedDetails($copy)
                                : $this->getRequestDetails(
                                    $copy, $holdConfig['HMACKeys'], 'Hold'
                                );
                            // If we are unsure whether hold options are available,
                            // set a flag so we can check later via AJAX:
                            $copy['check'] = $copy['addLink'] == 'check';
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

        $holds_override = isset($this->config->Catalog->allow_holds_override)
            ? $this->config->Catalog->allow_holds_override : false;

        if (count($result)) {
            foreach ($result as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    $groupKey = $this->getHoldingsGroupKey($copy);
                    $holdings[$groupKey][] = $copy;
                    // Are any copies available?
                    if ($copy['availability'] == true) {
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

                        switch($currentType) {
                        case "all":
                            $addlink = true; // always provide link
                            break;
                        case "holds":
                            $addlink = $copy['availability'];
                            break;
                        case "recalls":
                            $addlink = !$copy['availability'];
                            break;
                        case "availability":
                            $addlink = !$copy['availability']
                                && ($any_available == false);
                            break;
                        default:
                            $addlink = false;
                            break;
                        }
                        // If a valid holdable status has been set, use it to
                        // determine if a hold link is created
                        $addlink = isset($copy['is_holdable'])
                            ? ($addlink && $copy['is_holdable']) : $addlink;

                        if ($addlink) {
                            if ($holdConfig['function'] == "getHoldLink") {
                                /* Build opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->catalog->getHoldLink(
                                        $copy['id'], $copy
                                    );
                            } else {
                                /* Build non-opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->getRequestDetails(
                                        $copy, $holdConfig['HMACKeys'], 'Hold'
                                    );
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
     * @param array  $holdings Holdings
     * @param string $id       Record ID
     * @param array  $patron   Patron
     *
     * @return array Modified holdings
     */
    protected function processStorageRetrievalRequests($holdings, $id, $patron)
    {
        if (!is_array($holdings)) {
            return $holdings;
        }

        // Are storage retrieval requests allowed?
        $requestConfig = $this->catalog->checkFunction(
            'StorageRetrievalRequests', compact('id', 'patron')
        );

        if (!$requestConfig) {
            return $holdings;
        }

        // Generate Links
        // Loop through each holding
        foreach ($holdings as &$location) {
            foreach ($location as &$copy) {
                // Is this copy requestable
                if (isset($copy['addStorageRetrievalRequestLink'])
                    && $copy['addStorageRetrievalRequestLink']
                ) {
                    // If the request is blocked, link to an error page
                    // instead of the form:
                    if ($copy['addStorageRetrievalRequestLink'] === 'block') {
                        $copy['storageRetrievalRequestLink']
                            = $this->getBlockedStorageRetrievalRequestDetails($copy);
                    } else {
                        $copy['storageRetrievalRequestLink']
                            = $this->getRequestDetails(
                                $copy,
                                $requestConfig['HMACKeys'],
                                'StorageRetrievalRequest'
                            );
                    }
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
     * @param array  $holdings Holdings
     * @param string $id       Record ID
     * @param array  $patron   Patron
     *
     * @return array Modified holdings
     */
    protected function processILLRequests($holdings, $id, $patron)
    {
        if (!is_array($holdings)) {
            return $holdings;
        }

        // Are storage retrieval requests allowed?
        $requestConfig = $this->catalog->checkFunction(
            'ILLRequests', compact('id', 'patron')
        );

        if (!$requestConfig) {
            return $holdings;
        }

        // Generate Links
        // Loop through each holding
        foreach ($holdings as &$location) {
            foreach ($location as &$copy) {
                // Is this copy requestable
                if (isset($copy['addILLRequestLink'])
                    && $copy['addILLRequestLink']
                ) {
                    // If the request is blocked, link to an error page
                    // instead of the form:
                    if ($copy['addILLRequestLink'] === 'block') {
                        $copy['ILLRequestLink']
                            = $this->getBlockedILLRequestDetails($copy);
                    } else {
                        $copy['ILLRequestLink']
                            = $this->getRequestDetails(
                                $copy,
                                $requestConfig['HMACKeys'],
                                'ILLRequest'
                            );
                    }
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
        // Generate HMAC
        $HMACkey = $this->hmac->generate($HMACKeys, $details);

        // Add Params
        foreach ($details as $key => $param) {
            $needle = in_array($key, $HMACKeys);
            if ($needle) {
                $queryString[] = $key . "=" . urlencode($param);
            }
        }

        // Add HMAC
        $queryString[] = "hashKey=" . urlencode($HMACkey);
        $queryString = implode('&', $queryString);

        // Build Params
        return [
            'action' => $action, 'record' => $details['id'],
            'source' => isset($details['source'])
                ? $details['source'] : DEFAULT_SEARCH_BACKEND,
            'query' => $queryString, 'anchor' => "#tabnav"
        ];
    }

    /**
     * Returns a URL to display a "blocked hold" message.
     *
     * @param array $holdDetails An array of item data
     *
     * @return array             Details for generating URL
     */
    protected function getBlockedDetails($holdDetails)
    {
        // Build Params
        return [
            'action' => 'BlockedHold', 'record' => $holdDetails['id']
        ];
    }

    /**
     * Returns a URL to display a "blocked storage retrieval request" message.
     *
     * @param array $details An array of item data
     *
     * @return array         Details for generating URL
     */
    protected function getBlockedStorageRetrievalRequestDetails($details)
    {
        // Build Params
        return [
            'action' => 'BlockedStorageRetrievalRequest',
            'record' => $details['id']
        ];
    }

    /**
     * Returns a URL to display a "blocked ILL request" message.
     *
     * @param array $details An array of item data
     *
     * @return array         Details for generating URL
     */
    protected function getBlockedILLRequestDetails($details)
    {
        // Build Params
        return [
            'action' => 'BlockedILLRequest',
            'record' => $details['id']
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
        // Group by holdings id unless configured otherwise
        $grouping = isset($this->config->Catalog->holdings_grouping)
            ? $this->config->Catalog->holdings_grouping : 'holdings_id';

        $groupKey = "";
        // Multiple keys may be used here (delimited by comma)
        foreach (explode(",", $grouping) as $key) {
            // backwards-compatibility:
            // The config.ini file originally expected only two possible settings: holdings_id and location_name.
            // However, when location_name was set, the code actually used the value of 'location' instead.
            // From now on, we will expect (via config.ini documentation) the value of 'location', but still continue to honor 'location_name'.
            if ($key == "location_name") $key = "location";

            // backwards-compatibility:
            // Originally, if holdings_id was set and contained no value, then location was used in its place
            if ($key == "holdings_id" && ! $copy[$key]) $key = "location";

            $groupKey .= $copy[$key];
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
