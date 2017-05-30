<?php
/**
 * KohaRest ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
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

use VuFind\Exception\ILS as ILSException;

/**
 * VuFind Driver for Koha, using REST API
 *
 * Minimum Koha Version: work in progress as of 23 Jan 2017
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends \VuFind\ILS\Driver\KohaRest
{
    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null)
    {
        $data = parent::getHolding($id, $patron);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
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
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $fines = parent::getMyFines($patron);
        foreach ($fines as &$fine) {
            $fine['payableOnline'] = true;
        }
        return $fines;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'patrons', $patron['id']], false, 'GET', $patron
        );

        $expirationDate = !empty($result['dateexpiry'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $result['dateexpiry']
            ) : '';
        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $result['mobile'],
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['zipcode'],
            'city' => $result['city'],
            'country' => $result['country'],
            'expiration_date' => $expirationDate,
            'hold_identifier' => $result['othernames'],
            'full_data' => $result
        ];
    }

    /**
     * Update patron's phone number
     *
     * @param array  $patron Patron array
     * @param string $phone  Phone number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updatePhone($patron, $phone)
    {
        $request = [
            'mobile' => $phone
        ];
        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PUT',
            $patron,
            true
        );
        if ($code != 202 && $code != 204) {
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron's email address
     *
     * @param array  $patron Patron array
     * @param String $email  Email address
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateEmail($patron, $email)
    {
        $request = [
            'email' => $email
        ];
        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PUT',
            $patron,
            true
        );
        if ($code != 202 && $code != 204) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron contact information
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of patron contact information
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateAddress($patron, $details)
    {
        $addressFields = isset($this->config['updateAddress']['fields'])
            ? $this->config['updateAddress']['fields'] : [];
        $addressFields = array_map(
            function ($item) {
                $parts = explode(':', $item, 2);
                return isset($parts[1]) ? $parts[1] : '';
            },
            $addressFields
        );
        $addressFields = array_flip($addressFields);

        // Pick the configured fields from the request
        $request = [];
        foreach ($details as $key => $value) {
            if (isset($addressFields[$key])) {
                $request[$key] = $value;
            }
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id']],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the contact information failed',
                'sys_message' => isset($result['error']) ? $result['error'] : $code
            ];
        }

        return [
            'success' => true,
            'status' => $code == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
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
            'branchcode' => $pickUpLocation
        ];

        list($code, $result) = $this->makeRequest(
            ['v1', 'holds', $requestId],
            json_encode($request),
            'PUT',
            $patron,
            true
        );

        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron)
    {
        $fines = $this->getMyFines($patron);
        if (!empty($fines)) {
            $amount = 0;
            foreach ($fines as $fine) {
                $amount += $fine['balance'];
            }
            $config = $this->getConfig('onlinePayment');
            $nonPayableReason = false;
            if (isset($config['minimumFee']) && $amount < $config['minimumFee']) {
                $nonPayableReason = 'online_payment_minimum_fee';
            }
            $res = ['payable' => empty($nonPayableReason), 'amount' => $amount];
            if ($nonPayableReason) {
                $res['reason'] = $nonPayableReason;
            }
            return $res;
        }
        return [
            'payable' => false,
            'amount' => 0,
            'reason' => 'online_payment_minimum_fee'
        ];
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron        Patron.
     * @param int    $amount        Amount to be registered as paid
     * @param string $transactionId Transaction ID
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount, $transactionId)
    {
        $request = [
            'amount' => $amount / 100,
            'note' => "Online transaction $transactionId"
        ];
        $operator = $patron;
        if (!empty($this->config['onlinePayment']['userId'])
            && !empty($this->config['onlinePayment']['userPassword'])
        ) {
            $operator = [
                'cat_username' => $this->config['onlinePayment']['userId'],
                'cat_password' => $this->config['onlinePayment']['userPassword']
            ];
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'payment'],
            json_encode($request),
            'POST',
            $operator,
            true
        );
        if ($code != 204) {
            $error = "Failed to mark payment of $amount paid for patron"
                . " {$patron['id']}: $code: $result";

            $this->error($error);
            throw new ILSException($error);
        }
        // Clear patron's block cache
        $cacheId = 'blocks|' . $patron['id'];
        $this->removeCachedData($cacheId);
        return true;
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
           'location' => null
        ];
    }
}
