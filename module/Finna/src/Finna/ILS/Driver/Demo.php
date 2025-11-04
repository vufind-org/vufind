<?php

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * Note that some sample values (holds, transactions, fines) are stored in
 * the session. You can log out and log back in to get a different set of
 * values.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
 * Copyright (C) The National Library of Finland 2014-2022.
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
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Finna\ILS\Driver;

use function count;

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Demo extends \VuFind\ILS\Driver\Demo
{
    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        $result = parent::getConfig($function, $params);
        if ($function == 'Holdings') {
            $result['display_total_item_count_in_results']
                = $this->config['Holdings']['display_total_item_count_in_results'] ?? true;
            $result['display_ordered_item_count_in_results']
                = $this->config['Holdings']['display_ordered_item_count_in_results'] ?? false;
        }
        return $result;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $result = parent::getStatus($id);
        if (!empty($result)) {
            $result[] = $this->getHoldingsSummary($result, $id);
        }
        return $result;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param ?array $patron  Patron data
     * @param array  $options Extra options
     *
     * @return array On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, ?array $patron = null, array $options = [])
    {
        $result = parent::getHolding($id, $patron, $options);
        if (!empty($result['holdings'])) {
            $result['holdings'][] = $this->getHoldingsSummary($result['holdings'], $id);
        }
        return $result;
    }

    /**
     * Change pickup location
     *
     * This is responsible for changing the pickup location of a hold
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Associative array of the results
     */
    public function changePickupLocation($patron, $holdDetails)
    {
        $requestId = $holdDetails['requestId'];
        $pickUpLocation = $holdDetails['pickupLocationId'];

        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $session = $this->getSession();
        if (!isset($session->holds)) {
            return $this->holdError('ils_connection_failed');
        }
        foreach ($session->holds as &$hold) {
            if (isset($hold['requestId']) && $hold['requestId'] == $requestId) {
                $hold['location'] = $pickUpLocation;
                return ['success' => true];
            }
        }
        return $this->holdError('hold_error_failed');
    }

    /**
     * Generate a list of holds, storage retrieval requests or ILL requests.
     *
     * @param string $requestType Request type (Holds, StorageRetrievalRequests or
     * ILLRequests)
     *
     * @return ArrayObject List of requests
     */
    protected function createRequestList($requestType)
    {
        $list = parent::createRequestList($requestType);
        if ('Holds' === $requestType) {
            $i = 0;
            foreach ($list as $key => $item) {
                $list[$key]['requestId'] = ++$i;
                $list[$key]['is_editable'] = empty($item['available'])
                    && empty($item['inTransit']);
                if (!isset($item['available'])) {
                    $list[$key]['available'] = false;
                }
                if (!empty($list[$key]['last_pickup_date'])) {
                    $days = rand(1, 7);
                    $list[$key]['last_pickup_date'] = $this->dateConverter
                            ->convertToDisplayDate('U', strtotime("now + $days days"));
                }
            }
        }
        return $list;
    }

    /**
     * Return a hold error message
     *
     * @param string $message Error message
     *
     * @return array
     */
    protected function holdError($message)
    {
        return [
            'success' => false,
            'sysMessage' => $message,
        ];
    }

    /**
     * Is the selected pickup location valid for the hold?
     *
     * @param string $pickUpLocation Selected pickup location
     * @param array  $patron         Patron information returned by the patronLogin
     * method.
     * @param array  $holdDetails    Details of hold being placed
     *
     * @return bool
     */
    protected function pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)
    {
        $pickUpLibs = $this->getPickUpLocations($patron, $holdDetails);
        foreach ($pickUpLibs as $location) {
            if ($location['locationID'] == $pickUpLocation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array  $holdings Parsed holdings items
     * @param string $id       Record id
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings, $id)
    {
        $availableTotal = $itemsTotal = 0;
        $requests = 0;
        $locations = [];

        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            $itemsTotal++;
            $locations[$item['location']] = true;
            if (($item['requests_placed'] ?? 0) > $requests) {
                $requests = $item['requests_placed'];
            }
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.

        // Use a stupid location name to make sure this doesn't get mixed with
        // real items that don't have a proper location.
        $result = [
            'id' => $id,
            'available' => $availableTotal,
            'total' => $itemsTotal,
            'locations' => count($locations),
            'availability' => null,
            'callnumber' => '',
            'location' => '__HOLDINGSSUMMARYLOCATION__',
            'reservations' => rand(0, 8),
            'ordered' => rand(0, 20),
        ];
        return $result;
    }
}
