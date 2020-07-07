<?php
/**
 * III Sierra REST API driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

/**
 * III Sierra REST API driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class SierraRest extends \VuFind\ILS\Driver\SierraRest
{
    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options
     *
     * @throws \VuFind\Exception\ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        $data = parent::getHolding($id, $patron);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        if (!empty($this->config['pickUpLocations'])) {
            $locations = [];
            foreach ($this->config['pickUpLocations'] as $id => $location) {
                $locations[] = [
                    'locationID' => $id,
                    'locationDisplay' => $this->translateLocation(
                        ['code' => $id, 'name' => $location]
                    )
                ];
            }
            return $locations;
        }

        $result = $this->makeRequest(
            [$this->apiBase, 'branches', 'pickupLocations'],
            [
                'limit' => 10000,
                'offset' => 0,
                'fields' => 'code,name',
                'language' => $this->getTranslatorLocale()
            ],
            'GET',
            $patron
        );
        if (!empty($result['code'])) {
            // An error was returned
            $this->error(
                "Request for pickup locations returned error code: {$result['code']}"
                . ", HTTP status: {$result['httpStatus']}, name: {$result['name']}"
            );
            throw new ILSException('Problem with Sierra REST API.');
        }
        if (empty($result)) {
            return [];
        }

        $locations = [];
        foreach ($result as $entry) {
            $locations[] = [
                'locationID' => $entry['code'],
                'locationDisplay' => $entry['name']
            ];
        }

        usort($locations, [$this, 'pickupLocationSortFunction']);
        return $locations;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $data = parent::getStatus($id);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = $reservationsTotal = 0;
        $locations = [];

        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            $locations[$item['location']] = true;
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.

        return [
           'available' => $availableTotal,
           'total' => count($holdings),
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => '__HOLDINGSSUMMARYLOCATION__'
        ];
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

        $request = [
            'pickupLocation' => $pickUpLocation
        ];

        $result = $this->makeRequest(
            [$this->apiBase, 'patrons', 'holds', $requestId],
            json_encode($request),
            'PUT',
            $patron
        );

        if (!empty($result['code'])) {
            return [
                'success' => false,
                'status' => $this->formatErrorMessage(
                    $result['description'] ?? $result['name']
                )
            ];
        }
        return ['success' => true];
    }

    /**
     * Change request status
     *
     * This is responsible for changing the status of a hold request
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details (at the moment only 'frozen'
     * is supported)
     *
     * @return array Associative array of the results
     */
    public function changeRequestStatus($patron, $holdDetails)
    {
        $requestId = $holdDetails['requestId'];
        $frozen = !empty($holdDetails['frozen']);

        $request = [
            'freeze' => $frozen
        ];

        $result = $this->makeRequest(
            [$this->apiBase, 'patrons', 'holds', $requestId],
            json_encode($request),
            'PUT',
            $patron
        );

        if (!empty($result['code'])) {
            return [
                'success' => false,
                'status' => $this->formatErrorMessage(
                    $result['description'] ?? $result['name']
                )
            ];
        }
        return ['success' => true];
    }
}
