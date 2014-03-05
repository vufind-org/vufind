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
     * Auth manager object
     *
     * @var \VuFind\Auth\Manager
     */
    protected $account;

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
    protected $hideHoldings = array();

    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager $account Auth manager object
     * @param ILSConnection        $ils     A catalog connection
     * @param \VuFind\Crypt\HMAC   $hmac    HMAC generator
     * @param \Zend\Config\Config  $config  VuFind configuration
     */
    public function __construct(\VuFind\Auth\Manager $account, ILSConnection $ils,
        \VuFind\Crypt\HMAC $hmac, \Zend\Config\Config $config
    ) {
        $this->account = $account;
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
        $retVal = array();

        foreach ($holdings as $groupKey => $items) {
            $notes = array();
            $summaries = array();
            $supplements = array();
            $indexes = array();
            $locationName = '';
            foreach ($items as $item) {
                $locationName = $item['location'];
                if (isset($item['notes'])) {
                    if (!is_array($item['notes'])) {
                        $item['notes'] = empty($item['notes'])
                            ? array() : array($item['notes']);
                    }
                    foreach ($item['notes'] as $note) {
                        if (!in_array($note, $notes)) {
                            $notes[] = $note;
                        }
                    }
                }
                if (isset($item['summary'])) {
                    if (!is_array($item['summary'])) {
                        $item['summary'] = empty($item['summary'])
                            ? array() : array($item['summary']);
                    }
                    foreach ($item['summary'] as $summary) {
                        if (!in_array($summary, $summaries)) {
                            $summaries[] = $summary;
                        }
                    }
                }
                if (isset($item['supplements'])) {
                    if (!is_array($item['supplements'])) {
                        $item['summary'] = empty($item['supplements'])
                            ? array() : array($item['supplements']);
                    }
                    foreach ($item['supplements'] as $supplement) {
                        if (!in_array($supplement, $supplements)) {
                            $supplements[] = $supplement;
                        }
                    }
                }
                if (isset($item['indexes'])) {
                    if (!is_array($item['indexes'])) {
                        $item['indexes'] = empty($item['indexes'])
                            ? array() : array($item['indexes']);
                    }
                    foreach ($item['indexes'] as $index) {
                        if (!in_array($index, $indexes)) {
                            $indexes[] = $index;
                        }
                    }
                }
            }
            $retVal[$groupKey] = array(
                'location' => $locationName,
                'notes' => $notes,
                'summary' => $summaries,
                'supplements' => $supplements,
                'indexes' => $indexes,
                'items' => $items
            );
        }

        return $retVal;
    }

    /**
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id A Bib ID
     *
     * @return array A sorted results set
     */

    public function getHoldings($id)
    {
        $holdings = array();

        // Get Holdings Data
        if ($this->catalog) {
            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            $patron = $this->account->storedCatalogLogin();
            $result = $this->catalog->getHolding($id, $patron);
            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                 $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result, $id);
            } else {
                $holdings = $this->generateHoldings($result, $mode);
            }
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
        $holdings = array();
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
     * @param array  $result A result set returned from a driver
     * @param string $id     Record ID   
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result, $id)
    {
        $holdings = array();

        if (count($result)) {
            // Are holds allowed?
            $checkHolds = $this->catalog->checkFunction("Holds", $id);

            // Are storage retrieval requests allowed?
            $checkStorageRetrievalRequests = $this->catalog->checkFunction(
                "StorageRetrievalRequests"
            );

            foreach ($result as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    if ($checkHolds) {
                        // Is this copy holdable / linkable
                        if (isset($copy['addLink']) && $copy['addLink']) {
                            // If the hold is blocked, link to an error page
                            // instead of the hold form:
                            $copy['link'] = $copy['addLink'] === 'block'
                                ? $this->getBlockedDetails($copy)
                                : $this->getHoldDetails(
                                    $copy, $checkHolds['HMACKeys']
                                );
                            // If we are unsure whether hold options are available,
                            // set a flag so we can check later via AJAX:
                            $copy['check'] = $copy['addLink'] == 'check';
                        }
                    }

                    if ($checkStorageRetrievalRequests) {
                        // Is this copy requestable
                        if (isset($copy['addStorageRetrievalRequestLink'])
                            && $copy['addStorageRetrievalRequestLink']
                        ) {
                            // If the request is blocked, link to an error page
                            // instead of the hold form:
                            $copy['storageRetrievalRequestLink']
                                = $copy['addStorageRetrievalRequestLink'] === 'block'
                                ? $this->getBlockedStorageRetrievalRequestDetails(
                                    $copy
                                )
                                : $this->getStorageRetrievalRequestDetails(
                                    $copy,
                                    $checkStorageRetrievalRequests['HMACKeys']
                                );
                            // If we are unsure whether request options are
                            // available, set a flag so we can check later via AJAX:
                            $copy['checkStorageRetrievalRequest']
                                = $copy['addStorageRetrievalRequestLink']
                                    === 'check';
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
     * @param array  $result A result set returned from a driver
     * @param string $type   The holds mode to be applied from:
     * (all, holds, recalls, availability)
     *
     * @return array A sorted results set
     */
    protected function generateHoldings($result, $type)
    {
        $holdings = array();
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

            // Are holds allowed?
            $checkHolds = $this->catalog->checkFunction("Holds");

            // Are storage retrieval requests allowed?
            $checkStorageRetrievalRequests = $this->catalog->checkFunction(
                "StorageRetrievalRequests"
            );

            if ($checkHolds && is_array($holdings)) {
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
                            if ($checkHolds['function'] == "getHoldLink") {
                                /* Build opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->catalog->getHoldLink(
                                        $copy['id'], $copy
                                    );
                            } else {
                                /* Build non-opac link */
                                $holdings[$location_key][$copy_key]['link']
                                    = $this->getHoldDetails(
                                        $copy, $checkHolds['HMACKeys']
                                    );
                            }
                        }
                    }
                }
            }

            if ($checkStorageRetrievalRequests && is_array($holdings)) {
                // Generate Links
                // Loop through each holding
                foreach ($holdings as $location_key => $location) {
                    foreach ($location as $copy_key => $copy) {
                        if (isset($copy['addStorageRetrievalRequestLink'])
                            && $copy['addStorageRetrievalRequestLink']
                            && $copy['addStorageRetrievalRequestLink'] !== 'block'
                        ) {
                            $copy['storageRetrievalRequestLink']
                                = $this->getStorageRetrievalRequestDetails(
                                    $copy,
                                    $checkStorageRetrievalRequests['HMACKeys']
                                );
                            // If we are unsure whether storage retrieval
                            // request is available, set a flag so we can check
                            // later via AJAX:
                            $copy['checkStorageRetrievalRequest']
                                = $copy['addStorageRetrievalRequestLink'] ===
                                'check';
                        }
                    }
                }
            }
        }
        return $holdings;
    }

    /**
     * Get Hold Form
     *
     * Supplies holdLogic with the form details required to place a hold
     *
     * @param array $holdDetails An array of item data
     * @param array $HMACKeys    An array of keys to hash
     *
     * @return array             Details for generating URL
     */
    protected function getHoldDetails($holdDetails, $HMACKeys)
    {
        // Generate HMAC
        $HMACkey = $this->hmac->generate($HMACKeys, $holdDetails);

        // Add Params
        foreach ($holdDetails as $key => $param) {
            $needle = in_array($key, $HMACKeys);
            if ($needle) {
                $queryString[] = $key. "=" .urlencode($param);
            }
        }

        // Add HMAC
        $queryString[] = "hashKey=" . urlencode($HMACkey);
        $queryString = implode('&', $queryString);

        // Build Params
        return array(
            'action' => 'Hold', 'record' => $holdDetails['id'],
            'query' => $queryString, 'anchor' => "#tabnav"
        );
    }

    /**
     * Get Storage Retrieval Request Form
     *
     * Supplies holdLogic with the form details required to place a storage
     * retrieval request
     *
     * @param array $details  An array of item data
     * @param array $HMACKeys An array of keys to hash
     *
     * @return array          Details for generating URL
     */
    protected function getStorageRetrievalRequestDetails($details, $HMACKeys)
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
        return array(
            'action' => 'StorageRetrievalRequest',
            'record' => $details['id'],
            'query' => $queryString,
            'anchor' => "#tabnav"
        );
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
        return array(
            'action' => 'BlockedHold', 'record' => $holdDetails['id']
        );
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
        return array(
            'action' => 'BlockedStorageRetrievalRequest',
            'record' => $details['id']
        );
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
        return array(
            'action' => 'BlockedILLRequest',
            'record' => $details['id']
        );
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
        // Group by holdings id unless configured otherwise or holdings id not
        // available
        if ($this->config->Catalog->holdings_grouping != 'location_name'
            && isset($copy['holdings_id'])
        ) {
            return $copy['holdings_id'];
        }
        return $copy['location'];    
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
