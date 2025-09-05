<?php

/**
 * III Sierra REST API driver
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function sprintf;
use function strlen;

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
     * Fine types that allow online payment
     *
     * @var array
     */
    protected $onlinePayableFineTypes = [2, 4, 5, 6];

    /**
     * Manual fine description regexp patterns that allow online payment
     *
     * @var array
     */
    protected $onlinePayableManualFineDescriptionPatterns = [];

    /**
     * Mappings from item status codes to VuFind strings
     *
     * @var array
     */
    protected $itemStatusMappings = [
        '!' => 'On Holdshelf',
        't' => 'In Transit',
        'o' => 'On Reference Desk',
        'k' => 'In Repair',
        'm' => 'Missing',
        'n' => 'Long Overdue',
        '$' => 'lost_loan_and_paid',
        'p' => 'Withdrawn',
        'z' => 'Claims Returned',
        's' => 'On Search',
        'd' => 'In Process',
        '-' => 'On Shelf',
        'Charged' => 'Charged',
        'Ordered' => 'Ordered',
    ];

    /**
     * SOAP options for the IMMS connection
     *
     * @var array
     */
    protected $immsSoapOptions = [
        'soap_version' => SOAP_1_1,
        'exceptions' => true,
        'trace' => false,
        'timeout' => 15,
        'connection_timeout' => 5,
    ];

    /**
     * Days before account expiration to start displaying a notification
     *
     * @var int
     */
    protected $daysBeforeAccountExpirationNotification = 30;

    /**
     * Product code mappings
     *
     * @var array
     */
    protected $productCodeMappings = [];

    /**
     * Number of retries in case an API request fails with a retryable error (see
     * $retryableRequestExceptionPatterns below).
     *
     * @var int
     */
    protected $httpRetryCount = 3;

    /**
     * Exception message regexp patterns for request errors that can be retried
     *
     * @var array
     */
    protected $retryableRequestExceptionPatterns = [
        // cURL adapter:
        '/Error in cURL request: Empty reply from server/',
        '/Error in cURL request: OpenSSL SSL_read/',
        // Socket adapter:
        '/A valid response status line was not found in the provided string/',
    ];

    /**
     * Material types that may have holdings records attached to the bibs.
     *
     * @var array
     */
    protected $materialTypesWithHoldings = ['9', 'j'];

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        parent::init();

        if ($types = $this->config['OnlinePayment']['fineTypes'] ?? '') {
            $this->onlinePayableFineTypes = explode(',', $types);
        }
        $this->onlinePayableManualFineDescriptionPatterns
            = $this->config['OnlinePayment']['manualFineDescriptions'] ?? [];

        $key = 'daysBeforeAccountExpirationNotification';
        if (isset($this->config['Catalog'][$key])) {
            $this->daysBeforeAccountExpirationNotification
                = $this->config['Catalog'][$key];
        }

        if ($mappings = $this->config['OnlinePayment']['productCodeMappings'] ?? []) {
            foreach ($mappings as $mapping) {
                $parts = explode('=', $mapping, 2);
                if (!isset($parts[1])) {
                    continue;
                }
                $this->productCodeMappings[] = [
                    'productCode' => $parts[0],
                    'regexp' => $parts[1],
                ];
            }
        }
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        // If we are using a patron-specific access grant, we can bypass
        // authentication as the credentials are verified when the access token is
        // requested.
        if ($this->isPatronSpecificAccess()) {
            $patron = $this->getPatronInformationFromAuthToken($username, $password);
            if (!$patron) {
                return null;
            }
        } else {
            $patron = $this->authenticatePatron($username, $password);
            if (!$patron) {
                return null;
            }
        }

        $firstname = '';
        $lastname = '';
        if (!empty($patron['names'])) {
            $name = $patron['names'][0];
            $parts = explode(', ', $name, 2);
            $lastname = $parts[0];
            $firstname = $parts[1] ?? '';
        }
        return [
            'id' => $patron['id'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => !empty($patron['emails']) ? $patron['emails'][0] : '',
            'major' => null,
            'college' => null,
            'home_library' => $patron['homeLibraryCode'] ?? '',
        ];
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     * @todo   Support for handling frozen and pickup location change
     */
    public function getMyHolds($patron)
    {
        $holds = parent::getMyHolds($patron);
        foreach ($holds as &$hold) {
            if (!$hold['available']) {
                continue;
            }
            $hold['holdShelf'] = $this->getHoldShelf($hold, $patron);
        }
        unset($hold);

        return $holds;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron, $params = [])
    {
        $result = parent::getMyTransactions($patron, $params);
        // Sort the loans, but only if all fit in result limit:
        if ($result['count'] === count($result['records'])) {
            $sort = explode(' ', $params['sort'] ?? 'checkout desc', 2);
            $sortKeys = [];
            foreach ($result['records'] as $i => $row) {
                switch ($sort[0]) {
                    case 'title':
                        $key = '';
                        break;
                    case 'due':
                        $key = $this->dateConverter->convertFromDisplayDate('Y-m-d', $row['duedate']);
                        break;
                    default:
                        $key = sprintf('%012d', $row['checkout_id']);
                        break;
                }
                // Always append title for disambiguation:
                $key .= '__' . ($row['title'] ?? '');
                $sortKeys[$i] = $key;
            }
            array_multisort(
                $sortKeys,
                ($sort[1] ?? 'asc') === 'desc' ? SORT_DESC : SORT_ASC,
                SORT_LOCALE_STRING,
                $result['records']
            );
        }

        return $result;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for getting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored. The driver must not add new options to the return array
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
                    ),
                ];
            }
            return $locations;
        }

        $result = $this->makeRequest(
            [$this->apiBase, 'branches', 'pickupLocations'],
            [
                'limit' => 10000,
                'offset' => 0,
                'language' => $this->getTranslatorLocale(),
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
                'locationDisplay' => $entry['name'],
            ];
        }

        usort($locations, [$this, 'pickupLocationSortFunction']);
        return $locations;
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
            [$this->apiBase, 'patrons', $patron['id']],
            [
                'fields' => 'default,names,emails,phones,addresses,message,homeLibraryCode,fixedFields',
            ],
            'GET',
            $patron
        );

        if (empty($result)) {
            return [];
        }
        $firstname = '';
        $lastname = '';
        $address = '';
        $zip = '';
        $city = '';
        if (!empty($result['names'])) {
            $nameParts = explode(', ', $result['names'][0], 2);
            $lastname = $nameParts[0];
            $firstname = $nameParts[1] ?? '';
        }
        if (!empty($result['addresses'][0]['lines'][1])) {
            $address = $result['addresses'][0]['lines'][0];
            $postalParts = explode(' ', $result['addresses'][0]['lines'][1], 2);
            if (isset($postalParts[1])) {
                $zip = $postalParts[0];
                $city = $postalParts[1];
            } else {
                $city = $postalParts[0];
            }
        }

        $messages = [];
        foreach ($result['message']['accountMessages'] ?? [] as $message) {
            $messages[] = [
                'message' => $message,
            ];
        }

        $phoneType = $this->config['Profile']['phoneNumberField'] ?? 'p';
        $smsType = $this->config['Profile']['smsNumberField'] ?? 't';
        $phone = '';
        $sms = '';
        foreach ($result['phones'] ?? [] as $entry) {
            if ($phoneType === $entry['type']) {
                $phone = $entry['number'];
            } elseif ($smsType === $entry['type']) {
                $sms = $entry['number'];
            }
        }

        $profile = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => $phone,
            'smsnumber' => $sms,
            'email' => !empty($result['emails']) ? $result['emails'][0] : '',
            'address1' => $address,
            'zip' => $zip,
            'city' => $city,
            'birthdate' => $result['birthDate'] ?? '',
            'messages' => $messages,
            'home_library' => $result['homeLibraryCode'],
        ];

        if (!empty($result['expirationDate'])) {
            $profile['expiration_date'] = $this->dateConverter->convertToDisplayDate(
                'Y-m-d',
                $result['expirationDate']
            );
            $date = \DateTime::createFromFormat('Y-m-d', $result['expirationDate']);
            $diff = $date->diff(new \Datetime());
            if (!$diff->invert && $diff->days > 0) {
                $profile['expired'] = true;
            } elseif (
                $this->daysBeforeAccountExpirationNotification
                && $diff->days === 0
                || ($diff->invert
                && $diff->days <= $this->daysBeforeAccountExpirationNotification)
            ) {
                $profile['expiration_soon'] = true;
            }
        }

        // PCODE3: self-service library access
        if ($field = $result['fixedFields'][46] ?? null) {
            $profile['self_service_library'] = (string)$field['value'] === '1';
        }

        // Checkout history:
        $result = $this->makeRequest(
            [
                'v6', 'patrons', $patron['id'], 'checkouts', 'history',
                'activationStatus',
            ],
            [],
            'GET',
            $patron
        );
        if (array_key_exists('readingHistoryActivation', $result)) {
            $profile['loan_history'] = $result['readingHistoryActivation'];
        }

        return $profile;
    }

    /**
     * Update Patron Transaction History State
     *
     * Enable or disable patron's transaction history
     *
     * @param array $patron The patron array from patronLogin
     * @param mixed $state  Any of the configured values
     *
     * @return array Associative array of the results
     */
    public function updateTransactionHistoryState($patron, $state)
    {
        $request = ['readingHistoryActivation' => $state == '1'];
        $result = $this->makeRequest(
            [
                'v6', 'patrons', $patron['id'], 'checkouts', 'history',
                'activationStatus',
            ],
            json_encode($request),
            'POST',
            $patron
        );

        if (!empty($result['code'])) {
            return [
                'success' => false,
                'status' => $this->formatErrorMessage(
                    $result['description'] ?? $result['name']
                ),
            ];
        }
        return ['success' => true, 'status' => 'request_change_done'];
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
        // Compose a request from the fields:
        $request = [];
        $addressFields = ['address1' => true, 'zip' => true, 'city' => true];
        if (array_intersect_key($details, $addressFields)) {
            $address1 = $details['address1'] ?? '';
            $zip = $details['zip'] ?? '';
            $city = $details['city'] ?? '';
            $request['addresses'][] = [
                'lines' => array_filter(
                    [
                        $address1,
                        trim("$zip $city"),
                        $details['country'] ?? '',
                    ]
                ),
                'type' => 'a',
            ];
        }
        if (array_key_exists('phone', $details)) {
            $request['phones'][] = [
                'number' => $details['phone'],
                'type' => 'p',
            ];
        }
        if (array_key_exists('smsnumber', $details)) {
            $request['phones'][] = [
                'number' => $details['smsnumber'],
                'type' => 't',
            ];
        }
        if (array_key_exists('email', $details)) {
            $request['emails'][] = $details['email'];
        }
        if ($homeLibrary = $details['home_library']) {
            $request['homeLibraryCode'] = $homeLibrary;
        }

        $result = $this->makeRequest(
            [
                'v6', 'patrons', $patron['id'],
            ],
            json_encode($request),
            'PUT',
            $patron,
            true
        );

        if (!in_array($result['statusCode'], ['200', '204'])) {
            $this->logError(
                'Patron update request failed with status code'
                . " {$result['statusCode']}: "
                . (var_export($result['response'] ?? '', true))
            );
            return [
                'success' => false,
                'status' => 'profile_update_failed',
                'sys_message' => $result['description'] ?? '',
            ];
        }

        return [
            'success' => true,
            'status' => 'request_change_accepted',
            'sys_message' => '',
        ];
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
        $result = $this->makeRequest(
            [$this->apiBase, 'patrons', $patron['id'], 'fines'],
            [
                'limit' => 10000,
                'fields' => 'default,invoiceNumber',
            ],
            'GET',
            $patron
        );

        if (!isset($result['entries'])) {
            return [];
        }

        // Collect all item records to fetch:
        $itemIds = [];
        foreach ($result['entries'] as $entry) {
            if (!empty($entry['item'])) {
                $itemIds[] = $this->extractId($entry['item']);
            }
        }
        // Fetch items in a batch and list the bibs:
        $items = $this->getItemRecords($itemIds, null, $patron);
        $bibIds = [];
        foreach ($items as $item) {
            if (!empty($item['bibIds'])) {
                $bibIds[] = $item['bibIds'][0];
            }
        }
        // Fetch bibs in a batch:
        $bibs = $this->getBibRecords($bibIds, null, $patron);

        $fines = [];
        foreach ($result['entries'] as $entry) {
            $amount = $entry['itemCharge'] + $entry['processingFee']
                + $entry['billingFee'];
            $balance = $amount - $entry['paidAmount'];
            $type = $entry['chargeType']['display'] ?? '';
            $bibId = null;
            $title = null;
            if (!empty($entry['item'])) {
                $itemId = $this->extractId($entry['item']);
                // Fetch bib ID from item
                $item = $items[$itemId] ?? [];
                if (!empty($item['bibIds'])) {
                    $bibId = $item['bibIds'][0];
                    // Fetch bib information
                    $bib = $bibs[$bibId] ?? [];
                    $title = $bib['title'] ?? '';
                }
            }

            $fines[] = [
                'amount' => (int)round($amount * 100),
                'fine' => $this->fineTypeMappings[$type] ?? $type,
                'description' => $entry['description'] ?? '',
                'balance' => (int)round($balance * 100),
                'createdate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d',
                    $entry['assessedDate']
                ),
                'checkout' => '',
                'id' => $this->formatBibId($bibId),
                'title' => $title,
                'fine_id' => $this->extractId($entry['id']),
                'organization' => substr($entry['location']['code'] ?? '', 0, 1),
                'payableOnline' => $balance > 0 && $this->finePayableOnline($entry),
                '__invoiceNumber' => $entry['invoiceNumber'],
                '__productCode' => $this->getFineProductCode($entry),
            ];
        }
        return $fines;
    }

    /**
     * Return details on fees payable online.
     *
     * @param array  $patron          Patron
     * @param array  $fines           Patron's fines
     * @param ?array $selectedFineIds Selected fines
     *
     * @throws ILSException
     * @return array Associative array of payment details,
     * false if an ILSException occurred.
     */
    public function getOnlinePaymentDetails($patron, $fines, ?array $selectedFineIds)
    {
        if (!$fines) {
            return [
                'payable' => false,
                'amount' => 0,
                'reason' => 'online_payment_minimum_fee',
            ];
        }

        $nonPayableReason = false;
        $amount = 0;
        $payableFines = [];
        foreach ($fines as $fine) {
            if (
                null !== $selectedFineIds
                && !in_array($fine['fine_id'], $selectedFineIds)
            ) {
                continue;
            }
            if ($fine['payableOnline']) {
                $amount += $fine['balance'];
                $payableFines[] = $fine;
            }
        }
        $config = $this->getConfig('onlinePayment');
        $transactionFee = $config['transactionFee'] ?? 0;
        if (
            isset($config['minimumFee'])
            && $amount + $transactionFee < $config['minimumFee']
        ) {
            $nonPayableReason = 'online_payment_minimum_fee';
        }
        $res = [
            'payable' => empty($nonPayableReason),
            'amount' => $amount,
            'fines' => $payableFines,
        ];
        if ($nonPayableReason) {
            $res['reason'] = $nonPayableReason;
        }
        return $res;
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron            Patron
     * @param int    $amount            Amount to be registered as paid
     * @param string $transactionId     Transaction ID
     * @param int    $transactionNumber Internal transaction number
     * @param ?array $fineIds           Fine IDs to mark paid or null for bulk
     *
     * @throws ILSException
     * @return true|string True on success, error description on error
     */
    public function markFeesAsPaid(
        $patron,
        $amount,
        $transactionId,
        $transactionNumber,
        $fineIds = null
    ) {
        if (empty($fineIds)) {
            $this->logError('Bulk payment not supported');
            return false;
        }

        $fines = $this->getMyFines($patron);
        if (!$fines) {
            $this->logError('No fines to pay found');
            return 'fines_updated';
        }

        $amountRemaining = $amount;
        $payments = [];
        foreach ($fines as $fine) {
            if (
                in_array($fine['fine_id'], $fineIds)
                && $fine['payableOnline'] && $fine['balance'] > 0
            ) {
                $pay = (int)round(min($fine['balance'], $amountRemaining));
                $payments[] = [
                    'amount' => $pay,
                    'paymentType' => 1,
                    'invoiceNumber' => (string)$fine['__invoiceNumber'],
                ];
                $amountRemaining -= $pay;
            }
        }
        if (!$payments) {
            $this->logError('Fine IDs do not match any of the payable fines');
            return 'fines_updated';
        }

        $request = [
            'payments' => $payments,
        ];
        if ($this->statGroup) {
            $request['statgroup'] = $this->statGroup;
        }

        $result = $this->makeRequest(
            [
                'v6', 'patrons', $patron['id'], 'fines', 'payment',
            ],
            json_encode($request),
            'PUT',
            $patron,
            true
        );

        if (!in_array($result['statusCode'], ['200', '204'])) {
            $this->logError(
                "Payment request failed with status code {$result['statusCode']}: "
                . (var_export($result['response'] ?? '', true))
            );
            return 'payment request failed';
        }
        // Sierra doesn't support storing any remaining amount, so we'll just have to
        // live with the assumption that any fine amount didn't somehow get smaller
        // during payment. That would be unlikely in any case.
        return true;
    }

    /**
     * Update holds
     *
     * This is responsible for changing the status of hold requests
     *
     * @param array $holdsDetails The details identifying the holds
     * @param array $fields       An associative array of fields to be updated
     * @param array $patron       Patron array
     *
     * @return array Associative array of the results
     */
    public function updateHolds(
        array $holdsDetails,
        array $fields,
        array $patron
    ): array {
        $results = [];
        foreach ($holdsDetails as $requestId) {
            // Check if we can do the requested changes:
            $updateFields = [];
            if (isset($fields['frozen'])) {
                $updateFields['freeze'] = $fields['frozen'];
            }
            if (isset($fields['pickUpLocation'])) {
                $updateFields['pickupLocation'] = $fields['pickUpLocation'];
            }

            if (!$updateFields) {
                $results[$requestId] = [
                    'success' => false,
                    'status' => 'hold_error_update_blocked_status',
                ];
            } else {
                $result = $this->makeRequest(
                    [$this->apiBase, 'patrons', 'holds', $requestId],
                    json_encode($updateFields),
                    'PUT',
                    $patron
                );

                if (!empty($result['code'])) {
                    $results[$requestId] = [
                        'success' => false,
                        'status' => $this->formatErrorMessage(
                            $result['description'] ?? $result['name']
                        ),
                    ];
                } else {
                    $results[$requestId] = [
                        'success' => true,
                    ];
                }
            }
        }

        return $results;
    }

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
    public function getConfig($function, $params = [])
    {
        if ('getMyTransactions' === $function) {
            return [
                'max_results' => 200,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                    'title asc' => 'sort_title',
                ],
                'default_sort' => 'due asc',
            ];
        }
        if ('onlinePayment' === $function) {
            $result = $this->config['OnlinePayment'] ?? [];
            $result['exactBalanceRequired'] = false;
            $result['selectFines'] = true;
            return $result;
        }
        if ('updateAddress' === $function) {
            $function = 'updateProfile';
            $config = parent::getConfig('updateProfile', $params);
            if (isset($config['fields'])) {
                foreach ($config['fields'] as &$field) {
                    $parts = explode(':', $field);
                    $fieldLabel = $parts[0];
                    $fieldId = $parts[1] ?? '';
                    $fieldRequired = ($parts[3] ?? '') === 'required';
                    if ('home_library' === $fieldId) {
                        $locations = [];
                        $pickUpLocations = $this->getPickUpLocations(
                            $params['patron'] ?? false
                        );
                        foreach ($pickUpLocations as $current) {
                            $locations[$current['locationID']]
                                = $current['locationDisplay'];
                        }
                        $field = [
                            'field' => $fieldId,
                            'label' => $fieldLabel,
                            'type' => 'select',
                            'required' => $fieldRequired,
                            'options' => $locations,
                        ];
                        if ($options = ($parts[4] ?? '')) {
                            $field['options'] = [];
                            foreach (explode(';', $options) as $option) {
                                $keyVal = explode('=', $option, 2);
                                if (isset($keyVal[1])) {
                                    $field['options'][$keyVal[0]] = [
                                        'name' => $keyVal[1],
                                    ];
                                }
                            }
                        }
                    }
                }
                unset($field);
            }
            return $config;
        }

        return parent::getConfig($function, $params);
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id            The record id to retrieve the holdings for
     * @param bool   $checkHoldings Whether to check holdings records
     * @param ?array $patron        Patron information, if available
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBib(string $id, bool $checkHoldings, ?array $patron = null): array
    {
        $bibFields = ['default'];
        // If we need to look at bib call numbers, retrieve varFields:
        if (!empty($this->config['CallNumber']['bib_fields'])) {
            $bibFields[] = 'varFields';
        }
        // Retrieve orders if needed:
        if (!empty($this->config['Holdings']['display_orders'])) {
            $bibFields[] = 'orders';
        }
        // Retrieve hold count if needed:
        if ($this->config['Holdings']['display_total_hold_count'] ?? true) {
            $bibFields[] = 'holdCount';
        }
        $bib = $this->getBibRecord($id, $bibFields);
        $bibCallNumber = $this->getBibCallNumber($bib);
        $orders = [];
        foreach ($bib['orders'] ?? [] as $order) {
            $location = $order['location']['code'];
            $orders[$location][] = $order;
        }
        $holdingsData = [];
        $matType = trim($bib['materialType']['code'] ?? '');
        if ($checkHoldings && $this->apiVersion >= 5.1 && in_array($matType, $this->materialTypesWithHoldings)) {
            $holdingsResult = $this->makeRequest(
                [$this->apiBase, 'holdings'],
                [
                    'bibIds' => $this->extractBibId($id),
                    'deleted' => 'false',
                    'suppressed' => 'false',
                    'fields' => 'fixedFields,varFields',
                ],
                'GET'
            );
            foreach ($holdingsResult['entries'] ?? [] as $entry) {
                $location = '';
                foreach ($entry['fixedFields'] as $code => $field) {
                    if (
                        (string)$code === static::HOLDINGS_LOCATION_FIELD
                        || $field['label'] === 'LOCATION'
                    ) {
                        $location = $field['value'];
                        break;
                    }
                }
                if ('' === $location) {
                    continue;
                }
                $holdingsData[$location][] = $entry;
            }
        }

        $fields = ['default', 'fixedFields', 'varFields'];
        $statuses = [];
        $sort = 0;
        // Fetch hold count for items if needed:
        $displayItemHoldCount = $this->config['Holdings']['display_item_hold_counts'] ?? false;
        if ($displayItemHoldCount) {
            $fields[] = 'holdCount';
        }

        $items = $this->getItemsForBibRecord(
            $id,
            array_unique([...$this->defaultItemFields, ...$fields]),
            $patron
        );
        $itemsTotal = count($items);
        $itemsAvailable = 0;
        $itemsOrdered = 0;
        foreach ($items as $item) {
            $location = $this->translateLocation($item['location']);
            [$status, $duedate, $notes] = $this->getItemStatus($item);
            $available = $status == $this->mapStatusCode('-');
            // OPAC message
            if (isset($item['fixedFields']['108'])) {
                $opacMsg = $item['fixedFields']['108'];
                $trimmedMsg = trim($opacMsg['value']);
                if (strlen($trimmedMsg) && $trimmedMsg != '-') {
                    $notes[] = $this->translateOpacMessage(
                        trim($opacMsg['value'])
                    );
                }
            }
            $callnumber = isset($item['callNumber'])
                ? $this->extractCallNumber($item['callNumber'])
                : $bibCallNumber;

            $number = isset($item['varFields']) ? $this->extractVolume($item) : '';
            if (!$number) {
                $number = $this->getItemSpecificLocation($item);
            }

            if ($available) {
                ++$itemsAvailable;
            }

            $entry = [
                'id' => $id,
                'item_id' => $item['id'],
                'location' => $location,
                'availability' => $available,
                'status' => $status,
                'reserve' => 'N',
                'callnumber' => trim($callnumber),
                'duedate' => $duedate,
                'number' => trim($number),
                'barcode' => $item['barcode'] ?? '',
                'sort' => $sort--,
                'requests_placed' => $displayItemHoldCount ? ($item['holdCount'] ?? null) : null,
            ];
            if ($notes) {
                $entry['item_notes'] = $notes;
            }

            if ($this->isHoldable($item, $bib)) {
                $entry['is_holdable'] = true;
                $entry['level'] = 'copy';
                $entry['addLink'] = true;
            } else {
                $entry['is_holdable'] = false;
            }

            $locationCode = $item['location']['code'] ?? '';
            if (!empty($holdingsData[$locationCode])) {
                $entry += $this->getHoldingsData($holdingsData[$locationCode]);
                $holdingsData[$locationCode]['_hasItems'] = true;
            }

            $statuses[] = $entry;
        }

        // Add holdings that don't have items
        foreach ($holdingsData as $locationCode => $holdings) {
            if (!empty($holdings['_hasItems'])) {
                continue;
            }

            $location = $this->translateLocation(
                ['code' => $locationCode, 'name' => '']
            );
            $code = $locationCode;
            while ('' === $location && $code) {
                $location = $this->getLocationName($code);
                $code = substr($code, 0, -1);
            }
            $entry = [
                'id' => $id,
                'item_id' => 'HLD_' . $holdings[0]['id'],
                'location' => $location,
                'callnumber' => '',
                'requests_placed' => 0,
                'status' => '',
                'use_unknown_message' => true,
                'availability' => false,
                'duedate' => '',
                'barcode' => '',
                'sort' => $sort--,
            ];
            $entry += $this->getHoldingsData($holdings);

            $statuses[] = $entry;
        }

        // Add orders
        foreach ($orders as $locationCode => $orderSet) {
            $location = $this->translateLocation($orderSet[0]['location']);
            $statuses[] = [
                'id' => $id,
                'item_id' => "ORDER_{$id}_$locationCode",
                'location' => $location,
                'callnumber' => $bibCallNumber,
                'number' => '',
                'status' => $this->mapStatusCode('Ordered'),
                'reserve' => 'N',
                'item_notes' => $this->getOrderMessages($orderSet),
                'availability' => false,
                'duedate' => '',
                'barcode' => '',
                'sort' => $sort--,
            ];
            foreach ($orderSet as $order) {
                $itemsOrdered += $order['copies'];
            }
        }

        usort($statuses, [$this, 'statusSortFunction']);

        if ($statuses) {
            // Since summary data is appended to the holdings array as a fake item,
            // we need to add a few dummy-fields that VuFind expects to be
            // defined for all elements.
            $summary = [
                'id' => $id,
                'available' => $itemsAvailable,
                'total' => $itemsTotal,
                'ordered' => $itemsOrdered,
                'locations' => count(array_unique(array_column($statuses, 'location'))),
                'availability' => null,
                'callnumber' => '',
                'location' => '__HOLDINGSSUMMARYLOCATION__',
            ];
            if ($this->config['Holdings']['display_total_hold_count'] ?? true) {
                $summary['reservations'] = $bib['holdCount'] ?? null;
            }

            $statuses[] = $summary;
        }

        return $statuses;
    }

    /**
     * Return item-specific location information as configured
     *
     * @param array $item Koha item
     *
     * @return string
     */
    protected function getItemSpecificLocation($item)
    {
        if (empty($this->config['Holdings']['display_location_per_item'])) {
            return '';
        }

        $result = [];
        foreach (explode(',', $this->config['Holdings']['display_location_per_item']) as $field) {
            switch ($field) {
                case 'location':
                    if ($location = $this->translateLocation($item['location'])) {
                        $result[] = $location;
                    }
                    break;
                case 'callnumber':
                    if ($callNo = $item['callNumber'] ?? false) {
                        if ($callNo = $this->extractCallNumber($callNo)) {
                            $result[] = $callNo;
                        }
                    }
                    break;
            }
        }

        return implode(', ', $result);
    }

    /**
     * Check if a fine can be paid online
     *
     * @param array $fine Fine
     *
     * @return bool
     */
    protected function finePayableOnline(array $fine): bool
    {
        $code = $fine['chargeType']['code'] ?? 0;
        $desc = $fine['description'] ?? '';
        if (in_array($code, $this->onlinePayableFineTypes)) {
            return true;
        }
        foreach ($this->onlinePayableManualFineDescriptionPatterns as $pattern) {
            if (preg_match($pattern, $desc)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a product code for a fine
     *
     * @param array $fine Fine
     *
     * @return ?string
     */
    protected function getFineProductCode(array $fine): ?string
    {
        $location = $fine['location']['code'] ?? '';
        $type = $fine['chargeType']['code'] ?? 0;
        $desc = $fine['description'] ?? '';

        $key = "$location--$type--$desc";
        foreach ($this->productCodeMappings as $mapping) {
            if (preg_match($mapping['regexp'], $key)) {
                return $mapping['productCode'];
            }
        }
        return null;
    }

    /**
     * Get hold shelf for an available hold
     *
     * @param array $hold   Hold
     * @param array $patron The patron array from patronLogin
     *
     * @return string
     */
    protected function getHoldShelf(array $hold, array $patron): string
    {
        $location = $hold['location'];
        $cacheKey = "holdshelf|$location|" . $hold['item_id'];
        if (null !== ($shelf = $this->getCachedData($cacheKey))) {
            return $shelf;
        }

        $result = '';
        $handlerConfig = $this->getHoldShelfHandlerConfig($hold);
        if ($handlerConfig) {
            $type = $handlerConfig['type'];
            $config = $handlerConfig['config'];
            $params = $handlerConfig['params'];
            try {
                switch ($type) {
                    case 'BMA':
                        $result = $this->getHoldShelfWithBMA(
                            $config,
                            $params,
                            $hold,
                            $patron
                        );
                        break;
                    case 'IMMS':
                        $result = $this->getHoldShelfWithIMMS(
                            $config,
                            $params,
                            $hold,
                            $patron
                        );
                        break;
                    default:
                        $this->logError("Unknown hold shelf handler: $type");
                }
            } catch (\Exception $e) {
                $this->logError(
                    "Failed to get hold shelf for item {$hold['item_id']} with"
                    . " handler $type: " . (string)$e
                );
            }
        }
        $this->putCachedData(
            $cacheKey,
            $result,
            $config['locationCacheTime'] ?? 300
        );
        return $result;
    }

    /**
     * Get hold shelf handler configuration
     *
     * @param array $hold Hold
     *
     * @return ?array handler, config and params, or null if not found
     */
    protected function getHoldShelfHandlerConfig(array $hold): ?array
    {
        $location = $hold['location'];
        $handlers = $this->config['Holds']['holdShelfHandler'] ?? [];
        while ($location) {
            if ($setting = $handlers[$location] ?? '') {
                $parts = explode(':', $setting);
                $type = $parts[0];
                $config = !empty($parts[1])
                    ? ($this->config[$parts[1]] ?? null)
                    : null;
                $params = $parts[2] ?? '';
                if ($type && $config) {
                    return compact('type', 'config', 'params');
                }
            }
            $location = substr($location, 0, -1);
        }
        return null;
    }

    /**
     * Get hold shelf for an available hold with IMMS
     *
     * @param array $config IMMS configuration
     * @param array $params Extra parameters
     * @param array $hold   Hold
     * @param array $patron The patron array from patronLogin
     *
     * @return string
     */
    protected function getHoldShelfWithBMA(
        array $config,
        string $params,
        array $hold,
        array $patron
    ): string {
        foreach (['apiKey', 'url'] as $key) {
            if (empty($config[$key])) {
                $this->logError("BMA config missing $key");
                throw new ILSException('Problem with BMA configuration');
            }
        }

        $itemId = $hold['item_id'];
        if (!($barcode = $this->getItemBarcode($itemId, $patron))) {
            $this->logError("Could not retrieve barcode for item $itemId");
            return '';
        }

        $url = $config['url'] . 'reservation/apikey/company/'
            . ((int)$params) . '/null/null/null/' . urlencode($barcode)
            . '/null/null/null/null/null/null/null';
        try {
            $response = $this->httpService->get(
                $url,
                [],
                null,
                [
                    'Authorization: Bearer ' . $config['apiKey'],
                ]
            );
            if (!$response->isSuccess()) {
                throw new \Exception(
                    "BMA request $url failed: " . $response->getReasonPhrase(),
                    $response->getStatusCode()
                );
            }
            $result = json_decode($response->getBody(), true);
            $data = $result['data'] ?? [];
            $lastItem = array_pop($data);
            $indexVar = $lastItem['index_var'] ?? null;
            $indexDayId = $lastItem['index_day_id'] ?? null;
            if (null === $indexVar || null === $indexDayId) {
                throw new \Exception(
                    "index_var or index_day_id not found in BMA response for $url: "
                    . $response->getBody()
                );
            }
            return "$indexVar $indexDayId";
        } catch (\Exception $e) {
            throw new ILSException("BMA request $url failed", $e->getCode(), $e);
        }
        return '';
    }

    /**
     * Get hold shelf for an available hold with IMMS
     *
     * @param array $config IMMS configuration
     * @param array $params Extra parameters
     * @param array $hold   Hold
     * @param array $patron The patron array from patronLogin
     *
     * @return string
     */
    protected function getHoldShelfWithIMMS(
        array $config,
        string $params,
        array $hold,
        array $patron
    ): string {
        foreach (['securityWsdl', 'queryWsdl', 'username', 'password'] as $key) {
            if (empty($config[$key])) {
                $this->logError("IMMS config missing $key");
                throw new ILSException('Problem with IMMS configuration');
            }
        }

        $cacheKeyToken = 'imms|' . md5(var_export($config, true));

        $itemId = $hold['item_id'];
        if (!($barcode = $this->getItemBarcode($itemId, $patron))) {
            $this->logError("Could not retrieve barcode for item $itemId");
            return '';
        }

        $shelf = '';
        try {
            if (!($authToken = $this->getCachedData($cacheKeyToken))) {
                $this->logWarning('Retrieving IMMS auth token');
                $authToken = $this->getIMMSAuthToken($config);
            }

            $client = new ProxySoapClient(
                $this->httpService,
                $config['queryWsdl'],
                $this->immsSoapOptions
            );
            try {
                $response = $client->GetItemDetails(
                    [
                        'Token' => $authToken,
                        'ItemId' => $barcode,
                    ]
                );
            } catch (\SoapFault $e) {
                if ($e->getMessage() === 'Token is invalid') {
                    // Retry with a new authentication token:
                    $this->logWarning('Refreshing IMMS auth token');
                    $authToken = $this->getIMMSAuthToken($config);
                    $response = $client->GetItemDetails(
                        [
                            'Token' => $authToken,
                            'ItemId' => $barcode,
                        ]
                    );
                } else {
                    throw new ILSException('IMMS request failed', $e->getCode(), $e);
                }
            }
            $placement = $response->ItemDetails->CurrentLocation->Placement
                ->ShortPlacementText ?? '';
            preg_match('/(\d+)/', $placement, $matches);
            $shelf = ltrim($matches[1] ?? '', '0');
        } catch (\Exception $e) {
            throw new ILSException('IMMS request failed', $e->getCode(), $e);
        }
        $this->putCachedData(
            $cacheKeyToken,
            $authToken,
            $config['authTokenCacheTime'] ?? 3600
        );
        return $shelf;
    }

    /**
     * Get a new authentication token from IMMS
     *
     * @param array $config IMMS config
     *
     * @return string
     */
    protected function getIMMSAuthToken(array $config): string
    {
        $client = new ProxySoapClient(
            $this->httpService,
            $config['securityWsdl'],
            $this->immsSoapOptions
        );

        $response = $client->Login(
            [
                'Username' => $config['username'],
                'Password' => $config['password'],
            ]
        );
        return (string)$response->Token;
    }

    /**
     * Get item barcode
     *
     * @param string $itemId Item ID
     * @param array  $patron The patron array from patronLogin
     *
     * @return string
     */
    protected function getItemBarcode(string $itemId, array $patron): string
    {
        $items = $this->getItemRecords([$itemId], null, $patron);
        $item = reset($items);
        return $item['barcode'] ?? '';
    }

    /**
     * Make Request
     *
     * Makes a request to the Sierra REST API
     *
     * Finna: Adds caching for bibs and items
     *
     * @param array  $hierarchy    Array of values to embed in the URL path of the
     * request
     * @param array  $params       A keyed array of query data
     * @param string $method       The http request method to use (Default is GET)
     * @param array  $patron       Patron information, if available
     * @param bool   $returnStatus Whether to return HTTP status code and response
     * as a keyed array instead of just the response
     * @param array  $queryParams  Additional query params that are added to the URL
     * regardless of request type
     *
     * @throws ILSException
     * @return mixed JSON response decoded to an associative array, an array of HTTP
     * status code and JSON response when $returnStatus is true or null on
     * authentication error when using patron-specific access
     */
    protected function makeRequest(
        $hierarchy,
        $params = [],
        $method = 'GET',
        $patron = false,
        $returnStatus = false,
        $queryParams = []
    ) {
        $url = $this->getApiUrlFromHierarchy($hierarchy);
        // Allow caching of GET requests for bibs and items:
        $bibsUrl = $this->getApiUrlFromHierarchy([$this->apiBase, 'bibs']);
        $itemsUrl = $this->getApiUrlFromHierarchy([$this->apiBase, 'items']);
        $cacheKey = null;
        if (
            'GET' === $method
            && (strncmp($url, $bibsUrl, strlen($bibsUrl)) === 0
            || strncmp($url, $itemsUrl, strlen($itemsUrl)) === 0)
        ) {
            // Cacheable request, check cache:
            $paramArray = compact('params', 'method', 'patron', 'returnStatus', 'queryParams');
            $cacheKey = "request|$url|" . md5(var_export($paramArray, true));
            if (null !== ($result = $this->getCachedData($cacheKey))) {
                return $result;
            }
        }
        $result = parent::makeRequest(
            $hierarchy,
            $params,
            $method,
            $patron,
            $returnStatus,
            $queryParams
        );
        if ($cacheKey) {
            // Cache records by default for 300 seconds:
            $this->putCachedData(
                $cacheKey,
                $result,
                $this->config['Catalog']['request_cache_time'] ?? 300
            );
        }
        return $result;
    }

    /**
     * Get locations
     *
     * @return array
     */
    protected function getLocations(): array
    {
        // Ensure cache:
        $this->getLocationName('*');
        $locations = $this->getCachedData('locations');
        if (null === $locations) {
            throw new \Exception('Location cache not available');
        }
        return $locations;
    }

    /**
     * Build an API URL from a hierarchy array
     *
     * @param array $hierarchy Hierarchy
     *
     * @return string
     */
    protected function getApiUrlFromHierarchy(array $hierarchy): string
    {
        $url = $this->config['Catalog']['host'];
        foreach ($hierarchy as $value) {
            if (is_array($value)) {
                if ('encoded' === $value['type']) {
                    $url .= '/' . $value['value'];
                    continue;
                }
                $value = $value['value'];
            } else {
                $url .= '/' . urlencode($value);
            }
        }
        return $url;
    }

    /**
     * Authenticate a patron using the API version 6 patrons/auth endpoint
     *
     * Returns patron information on success and null on failure
     *
     * @param string $username Username
     * @param string $password Password
     * @param string $method   Authentication method
     *
     * @return array|null
     */
    protected function authenticatePatronV6(
        string $username,
        string $password,
        string $method
    ): ?array {
        $request = [
            'authMethod' => $method,
            'patronId' => $username,
            'patronSecret' => $password,
        ];
        $result = $this->makeRequest(
            ['v6', 'patrons', 'auth'],
            json_encode($request),
            'POST'
        );
        if (!$result || !empty($result['code'])) {
            return null;
        }
        $result = $this->makeRequest(
            [$this->apiBase, 'patrons', $result],
            ['fields' => 'names,emails,homeLibraryCode']
        );
        if (!$result || !empty($result['code'])) {
            return null;
        }
        return $result;
    }
}
