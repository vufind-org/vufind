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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
namespace VuFind\ILS\Logic;
use VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Crypt\HMAC,
    VuFind\ILS\Connection as ILSConnection;

/**
 * Hold Logic Class
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
class Holds
{
    protected $account;
    protected $catalog;
    protected $config;
    protected $hideHoldings = array();

    /**
     * Constructor
     *
     * @param \VuFind\Account\Manager $account Account manager object
     * @param ILSConnection           $catalog A catalog connection
     */
    public function __construct($account, $catalog = false)
    {
        $this->account = $account;
        $this->config = ConfigReader::getConfig();

        if (isset($this->config->Record->hide_holdings)) {
            foreach ($this->config->Record->hide_holdings as $current) {
                $this->hideHoldings[] = $current;
            }
        }

        $this->catalog = ($catalog !== false)
            ? $catalog : ConnectionManager::connectToCatalog();
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

        foreach ($holdings as $location => $items) {
            $notes = array();
            $summaries = array();
            foreach ($items as $item) {
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
            }
            $retVal[$location] = array(
                'notes' => $notes, 'summary' => $summaries, 'items' => $items
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
            $mode = ILSConnection::getHoldsMode();

            if ($mode == "disabled") {
                 $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result);
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
                    $holdings[$copy['location']][] = $copy;
                }
            }
        }
        return $holdings;
    }

    /**
     * Protected method for driver defined holdings
     *
     * @param array $result A result set returned from a driver
     *
     * @return array A sorted results set
     */
    protected function driverHoldings($result)
    {
        $holdings = array();

        // Are holds allows?
        $checkHolds = $this->catalog->checkFunction("Holds");

        if (count($result)) {
            foreach ($result as $copy) {
                $show = !in_array($copy['location'], $this->hideHoldings);
                if ($show) {
                    if ($checkHolds != false) {
                        // Is this copy holdable / linkable
                        if (isset($copy['addLink']) && $copy['addLink']) {
                            // If the hold is blocked, link to an error page
                            // instead of the hold form:
                            $copy['link'] = (strcmp($copy['addLink'], 'block') == 0)
                                ? $this->getBlockedDetails($copy)
                                : $this->getHoldDetails(
                                    $copy, $checkHolds['HMACKeys']
                                );
                            // If we are unsure whether hold options are available,
                            // set a flag so we can check later via AJAX:
                            $copy['check'] = (strcmp($copy['addLink'], 'check') == 0)
                                ? true : false;
                        }
                    }
                    $holdings[$copy['location']][] = $copy;
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
                    $holdings[$copy['location']][] = $copy;
                    // Are any copies available?
                    if ($copy['availability'] == true) {
                        $any_available = true;
                    }
                }
            }

            // Are holds allows?
            $checkHolds = $this->catalog->checkFunction("Holds");

            if ($checkHolds != false) {
                if (is_array($holdings)) {
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
                                $holdLink = "";
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
     * @return string A url link (with HMAC key)
     */
    protected function getHoldDetails($holdDetails, $HMACKeys)
    {
        // Generate HMAC
        $HMACkey = HMAC::generate($HMACKeys, $holdDetails);

        // Add Params
        foreach ($holdDetails as $key => $param) {
            $needle = in_array($key, $HMACKeys);
            if ($needle) {
                $queryString[] = $key. "=" .urlencode($param);
            }
        }

        //Add HMAC
        $queryString[] = "hashKey=" . $HMACkey;

        // Build Params
        $router = Zend_Controller_Front::getInstance()->getRouter();
        $urlParams = $router->assemble(
            array('id' => $holdDetails['id'], 'action' => 'Hold'), 'record', true,
            false
        );
        $urlParams .= "?" . implode("&", $queryString);
        return $urlParams."#tabnav";
    }

    /**
     * Returns a URL to display a "blocked hold" message.
     *
     * @param array $holdDetails An array of item data
     *
     * @return string A url link
     */
    protected function getBlockedDetails($holdDetails)
    {
        // Build Params
        $router = Zend_Controller_Front::getInstance()->getRouter();
        return $router->assemble(
            array('id' => $holdDetails['id'], 'action' => 'BlockedHold'), 'record',
            true, false
        );
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
