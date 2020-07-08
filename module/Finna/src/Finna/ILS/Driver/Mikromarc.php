<?php
/**
 * Mikromarc ILS Driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017-2019.
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

/**
 * Mikromarc ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Mikromarc extends \VuFind\ILS\Driver\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFind\ILS\Driver\CacheTrait;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Institution settings for the order of organisations
     *
     * @var string
     */
    protected $holdingsOrganisationOrder;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Mappings from fee (account line) types
     *
     * @var array
     */
    protected $feeTypeMappings = [
        'Overdue charge' => 'Overdue',
        'Extra service' => 'Extra service'
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
    }

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
        $this->holdingsOrganisationOrder
            = isset($this->config['Holdings']['holdingsOrganisationOrder'])
            ? explode(':', $this->config['Holdings']['holdingsOrganisationOrder'])
            : [];

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation']
            : '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }
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
    public function getConfig($function, $params = null)
    {
        if ('getMyTransactionHistory' === $function) {
            if (empty($this->config['getMyTransactionHistory']['enabled'])) {
                return false;
            }
            return [
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'return desc' => 'sort_return_date_desc',
                    'return asc' => 'sort_return_date_asc',
                    'everything desc' => 'sort_loan_history'
                ],
                'default_sort' => 'everything desc',
            ];
        }
        $functionConfig = $this->config[$function] ?? false;
        if ($functionConfig && 'onlinePayment' === $function) {
            $functionConfig['exactBalanceRequired'] = true;
        }
        if ($functionConfig && 'Holds' === $function) {
            if (isset($functionConfig['titleHoldBibLevels'])
                && !is_array($functionConfig['titleHoldBibLevels'])
            ) {
                $functionConfig['titleHoldBibLevels']
                    = explode(':', $functionConfig['titleHoldBibLevels']);
            }
        }
        return $functionConfig;
    }

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
        return $this->getItemStatusesForBiblio($id, $patron);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        return [];
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
        return $this->getItemStatusesForBiblio($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getItemStatusesForBiblio($id);
        }
        return $items;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        $request = json_encode(
            [
              'Barcode' => $username,
              'Pin' => $password
            ]
        );
        list($code, $patronId) = $this->makeRequest(
            ['odata', 'Borrowers', 'Default.Authenticate'],
            $request, 'POST', true
        );
        if (($code != 200 && $code != 403) || empty($patronId)) {
            return null;
        } elseif ($code == 403 && !empty($patronId['error']['code'])
            && $patronId['error']['code'] == 'Defaulted'
        ) {
            $defaultedPatron = $this->makeRequest(
                ['odata', 'Borrowers', 'Default.AuthenticateDebtor'],
                $request, 'POST', false
            );
            $patronId = $defaultedPatron['BorrowerId'];
        }
        $patron = [
            'cat_username' => $username,
            'cat_password' => $password,
            'id' => $patronId
        ];

        if ($profile = $this->getMyProfile($patron)) {
            $profile['major'] = null;
            $profile['college'] = null;
        }
        return $profile;
    }

    /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getRequestBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all unpaid fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {

        // All fines, ciAccountEntryStatus = 2
        $allFines = $this->makeRequest(
            ['BorrowerDebts', $patron['cat_username'], '2', '0']
        );
        if (empty($allFines)) {
            return [];
        }

        // All non-paid fines
        $allFines = array_filter(
            $allFines,
            function ($fine) {
                return $fine['State'] === 'Unpaid';
            }
        );

        // Payable fines, ciAccountEntryStatus = 1
        $payableFines = $this->makeRequest(
            ['BorrowerDebts', $patron['cat_username'], '1', '0']
        );
        $payableIds = array_map(
            function ($fine) {
                return $fine['Id'];
            }, $payableFines
        );

        $fines = [];
        foreach ($allFines as $entry) {
            $createDate = !empty($entry['DeptDate'])
                ? $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['DeptDate'])
                )
                : '';
            $type = $entry['Notes'];
            if (isset($this->feeTypeMappings[$type])) {
                $type = $this->feeTypeMappings[$type];
            }
            $amount = $entry['Remainder'] * 100;
            $fineId = $entry['Id'] ?? null;
            $fine = [
                'amount' => $amount,
                'balance' => $amount,
                'fine' => $type,
                'createdate' => $createDate,
                'checkout' => '',
                'id' => $entry['MarcRecordId'] ?? null,
                'item_id' => $entry['ItemId'],
                // Append payment information
                'payableOnline' => $fineId && in_array($fineId, $payableIds),
                'fineId' => $fineId
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $fine['title'] = $entry['Id'] . ': ' . $entry['MarcRecordTitle'];
            }
            $fines[] = $fine;
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
        $cacheKey = $this->getPatronCacheKey($patron, 'profile');
        if ($profile = $this->getCachedData($cacheKey)) {
            return $profile;
        }
        $result = $this->makeRequest(['odata', 'Borrowers(' . $patron['id'] . ')']);
        $expirationDate = !empty($result['Expires'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $result['Expires']
            ) : '';

        $name = explode(',', $result['Name'], 2);
        $messagingConf = isset($this->config['messaging'])
            ? $this->config['messaging'] : null;

        $messagingSettings = [];

        $type = 'dueDateNotice';
        $dueDateNoticeActive = !$result['RefuseReminderMessages'];
        $messagingSettings[$type] = [
           'type' => $type,
           'settings' => [
              'digest' => [
                 'type' => 'boolean',
                 'readonly' => false,
                 'active' => $dueDateNoticeActive,
                 'label' => 'messaging_settings_option_' .
                    ($dueDateNoticeActive ? 'active' : 'inactive')
              ]
           ]
        ];

        if (!empty($messagingConf['checkoutNotice'])) {
            $checkoutNoticeFormat = $result['ReceiptMessageFormat'];
            $type = 'checkoutNotice';
            $options = [];
            foreach ($messagingConf['checkoutNotice'] as $option) {
                list($key, $label) = explode(':', $option);
                $options[$key] = [
                   'name' => $this->translate("messaging_settings_option_$label"),
                   'value' => $key,
                   'active' => $checkoutNoticeFormat == $key
                ];
            }
            $messagingSettings[$type] = [
               'type' => $type,
               'settings' => [
                  'transport_types' => [
                     'type' => 'select',
                     'value' => $checkoutNoticeFormat,
                     'options' => $options
                  ]
               ]
            ];
        }

        if (!empty($messagingConf['notifications'])) {
            $type = 'notifications';
            $map = ['Email' => 'LettersByEmail', 'SMS' => 'LettersBySMS'];
            $options = [];
            foreach ($messagingConf['notifications'] as $option) {
                list($key, $label) = explode(':', $option);
                $options[$key] = [
                   'name' => $this->translate("messaging_settings_option_$label"),
                   'value' => $key,
                   'active' => $result[$map[$key]],
                ];
            }
            $messagingSettings[$type] = [
               'type' => $type,
               'settings' => [
                  'transport_types' => [
                     'type' => 'multiselect',
                     'options' => $options
                  ]
               ]
            ];
        }

        $profile = [
            'firstname' => trim($name[1] ?? ''),
            'lastname' => ucfirst(trim($name[0])),
            'phone' => !empty($result['MainPhone'])
                ? $result['MainPhone'] : $result['Mobile'],
            'email' => $result['MainEmail'],
            'address1' => $result['MainAddrLine1'],
            'address2' => $result['MainAddrLine2'],
            'zip' => $result['MainZip'],
            'city' => $result['MainPlace'],
            'expiration_date' => $expirationDate,
            'messagingServices' => $messagingSettings
        ];

        if (isset($this->config['updateTransactionHistoryState']['method'])) {
            $profile['loan_history'] = $result['StoreBorrowerHistory'];
        }

        $profile = array_merge($patron, $profile);
        $this->putCachedData($cacheKey, $profile);
        return $profile;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $result = $this->makeRequest(
            ['odata', 'BorrowerLoans'],
            ['$filter' => 'BorrowerId eq' . ' ' . $patron['id']]
        );
        if (empty($result)) {
            return [];
        }
        $renewLimit = $this->config['Loans']['renewalLimit'];
        $transactions = [];
        foreach ($result as $entry) {
            $renewalCount = $entry['RenewalCount'];
            $transaction = [
                'id' => $entry['MarcRecordId'],
                'checkout_id' => $entry['Id'],
                'item_id' => $entry['ItemId'],
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['DueTime'])
                ),
                'dueStatus' => $entry['ServiceCode'],
                'renew' => $renewalCount,
                'renewLimit' => $renewLimit,
                'renewable' => ($renewLimit - $renewalCount) > 0,
                'message' => $entry['Notes']
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $transaction['title'] = $entry['MarcRecordTitle'];
            }
            $transactions[] = $transaction;
        }
        return $transactions;
    }

    /**
     * Get Renew Details
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['checkout_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $finalResult = ['details' => []];
        foreach ($renewDetails['details'] as $details) {
            $checkedOutId = $details;
            list($code, $result) = $this->makeRequest(
                ['odata', "BorrowerLoans($checkedOutId)", 'Default.RenewLoan'],
                false, 'POST', true
            );
            if ($code != 200 || $result['ServiceCode'] != 'LoanRenewed') {
                $map = ['ReservedForOtherBorrower' => 'renew_item_requested'];
                $errorCode = $result['error']['code'] ?? null;
                $sysMsg = $map[$errorCode] ?? null;
                $finalResult['details'][$checkedOutId] = [
                    'item_id' => $checkedOutId,
                    'success' => false,
                    'sysMessage' => $sysMsg
                ];
            } else {
                $newDate = $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($result['DueTime'])
                );
                $finalResult['details'][$checkedOutId] = [
                    'item_id' => $checkedOutId,
                    'success' => true,
                    'new_date' => $newDate
                ];
                $this->putCachedData(
                    $this->getPatronCacheKey(
                        $renewDetails['patron'], 'transactionHistory'
                    ), null
                );
            }
        }
        return $finalResult;
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
     */
    public function getMyHolds($patron)
    {
        $request = [
            '$filter' => 'BorrowerId eq ' . $patron['id'],
            '$orderby' => 'DeliverAtLocalUnitId'
        ];
        $result = $this->makeRequest(
            ['odata', 'BorrowerReservations'],
            $request
        );
        if (!isset($result)) {
            return [];
        }
        $holds = [];
        foreach ($result as $entry) {
            $hold = [
                'id' => $entry['MarcRecordId'],
                'item_id' => $entry['ItemId'],
                'location' =>
                   $this->getLibraryUnitName($entry['DeliverAtLocalUnitId']),
                'create' => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['ResTime'])
                ),
                'expire' => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['ResValidUntil'])
                ),
                'position' => $entry['NumberInQueue'],
                'available' => ($entry['ServiceCode'] === 'ReservationArrived'
                    || $entry['ServiceCode'] === 'ReservationNoticeSent')
                        ? true : false,
                'requestId' => $entry['Id'],
                'frozen' => !$entry['ResActiveToday']
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $hold['title'] = $entry['MarcRecordTitle'];
            }
            $holds[] = $hold;
        }
        return $holds;
    }

    /**
     * Get Patron Transaction History
     *
     * This is responsible for retrieving all historical transactions
     * (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Retrieval params that may contain the following keys:
     *   sort   Sorting order with date ascending or descending
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactionHistory($patron, $params)
    {
        $sort = strpos($params['sort'], 'desc') ? 'desc' : 'asc';
        $request = [
            '$filter' => 'BorrowerId eq' . ' ' . $patron['id'],
            '$orderby' => 'ServiceTime' . ' ' . $sort
        ];
        $result = $this->makeRequest(
            ['odata', 'BorrowerServiceHistories'],
            $request
        );
        $history = [
            'count' => count($result),
            'transactions' => []
        ];
        $serviceCodeMap = [
            'Returned' => 'returndate',
            'OnLoan' => 'checkoutdate',
            'LoanRenewed' => 'checkoutdate'
        ];
        foreach ($result as $entry) {
            $code = $entry['ServiceCode'];
            if (!isset($serviceCodeMap[$code])) {
                continue;
            }

            $transaction = [
                'id' => $entry['MarcRecordId'],
            ];

            $entryTime = $this->dateConverter->convertToDisplayDate(
                'U', strtotime($entry['ServiceTime'])
            );

            $transaction[$serviceCodeMap[$code]] = $entryTime;
            if (isset($entry['MarcRecordTitle'])) {
                $transaction['title'] = $entry['MarcRecordTitle'];
            }
            if ($params['sort'] == 'checkout ' . $sort
                && isset($transaction['checkoutdate'])
            ) {
                $history['transactions'][] = $transaction;
            } elseif ($params['sort'] == 'return ' . $sort
                && isset($transaction['returndate'])
            ) {
                $history['transactions'][] = $transaction;
            } elseif ($params['sort'] == 'everything desc') {
                $history['transactions'][] = $transaction;
            }
        }
        return $history;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        $pickUpLocation = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->defaultPickUpLocation;
        $itemId = $holdDetails['item_id'] ?? false;

        // Make sure pickup location is valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }
        $request = [
            'BorrowerId' =>  $patron['id'],
            'MarcId' => $holdDetails['id'],
            'DeliverAtUnitId' => $pickUpLocation
        ];
        list($code, $result) = $this->makeRequest(
            ['odata', 'BorrowerReservations', 'Default.Create'],
            json_encode($request),
            'POST',
            true
        );
        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Get Cancel Hold Details
     *
     * Get required data for canceling a hold. This value is used by relayed to the
     * cancelHolds function when the user attempts to cancel a hold.
     *
     * @param array $holdDetails An array of hold data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['available'] ? '' : $holdDetails['requestId'];
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold. The data in $cancelDetails['details'] is determined
     * by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $details = $cancelDetails['details'];
        $count = 0;
        $response = [];
        foreach ($details as $detail) {
            list($resultCode, $result) = $this->makeRequest(
                ['odata', 'BorrowerReservations(' . $detail . ')'],
                false, 'DELETE', true
            );
            if ($resultCode != 204) {
                $response[$detail] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false
                ];
            } else {
                $response[$detail] = [
                    'success' => true,
                    'status' => 'hold_cancel_success'
                ];
                ++$count;
            }
        }
        return ['count' => $count, 'items' => $response];
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
        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];

        $units = $this->getLibraryUnits();
        $locations = [];
        foreach ($units as $key => $val) {
            if (in_array($key, $excluded) || $val['department']) {
                continue;
            }
            $locations[] = [
                'locationID' => $key,
                'locationDisplay' => $val['name']
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = isset($this->config['Holds']['pickUpLocationOrder'])
            ? $this->config['Holds']['pickUpLocationOrder'] : 'default';
        if (count($locations) > 1 && !empty($orderSetting)) {
            $locationOrder = $orderSetting === 'default'
                ? [] : array_flip(explode(':', $orderSetting));
            $sortFunction = function ($a, $b) use ($locationOrder) {
                $aLoc = $a['locationID'];
                $bLoc = $b['locationID'];
                if (isset($locationOrder[$aLoc])) {
                    if (isset($locationOrder[$bLoc])) {
                        return $locationOrder[$aLoc] - $locationOrder[$bLoc];
                    }
                    return -1;
                }
                if (isset($locationOrder[$bLoc])) {
                    return 1;
                }
                return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
            };
            usort($locations, $sortFunction);
        }
        return $locations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return false|string      The default pickup location for the patron or false
     * if the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
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
        $code = $this->updatePatronInfo(
            $patron, ['MainPhone' => $phone, 'Mobile' => $phone]
        );
        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed'
            ];
        }
        return [
            'success' => true,
            'status' => 'request_change_accepted'
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
        $code = $this->updatePatronInfo($patron, ['MainEmail' => $email]);
        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed'
            ];
        }
        return [
            'success' => true,
            'status' => 'request_change_accepted'
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
        $map = [
            'address1' => 'MainAddrLine1',
            'address2' => 'MainAddrLine2',
            'zip' => 'MainZip',
            'city' => 'MainPlace'
        ];

        $request = [];
        foreach ($details as $field => $val) {
            if (!isset($map[$field])) {
                continue;
            }
            $field = $map[$field];
            $request[$field] = $val;
        }

        $code = $this->updatePatronInfo($patron, $request);

        if ($code != 200) {
            $message = 'An error has occurred';
            return [
                'success' => false, 'status' => $message
            ];
        }
        $this->putCachedData($this->getPatronCacheKey($patron, 'profile'), null);
        return ['success' => true, 'status' => 'request_change_done'];
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
        $code = $this->updatePatronInfo(
            $patron, ['StoreBorrowerHistory' => $state == 1]
        );

        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the checkout history state failed'
            ];
        }
        $this->putCachedData($this->getPatronCacheKey($patron, 'profile'), null);

        return [
            'success' => true,
            'status' => 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Update patron messaging settings
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of messaging settings
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateMessagingSettings($patron, $details)
    {
        $settings = [];
        if (!empty($details['dueDateNotice'])) {
            $settings['RefuseReminderMessages']
                = !$details['dueDateNotice']['settings']['digest']['active'];
        }
        if (isset($details['checkoutNotice'])) {
            $settings['ReceiptMessageFormat']
                = $details['checkoutNotice']['settings']['transport_types']['value'];
        }
        if (isset($details['notifications'])) {
            $options
                = $details['notifications']['settings']['transport_types']['options']
                ;
            if (!empty($options['SMS'])) {
                $settings['LettersBySMS'] = $options['SMS']['active'];
            }
            if (!empty($options['Email'])) {
                $settings['LettersByEmail'] = $options['Email']['active'];
            }
        }

        $code = $this->updatePatronInfo($patron, $settings);

        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the preferences failed',
            ];
        }
        $this->putCachedData($this->getPatronCacheKey($patron, 'profile'), null);

        return [
            'success' => true,
            'status' => 'request_change_accepted',
            'sys_message' => ''
        ];
    }

    /**
     * Returns a list of parameters that are required for registering
     * online payments to the ILS. The parameters are configured in
     * OnlinePayment > registrationParams.
     *
     * @return array
     */
    public function getOnlinePaymentRegistrationParams()
    {
        return [];
    }

    /**
     * Support method for getMyFines that augments the fines with
     * extra information. The driver may also append the information
     * in getMyFines implement markOnlinePayableFines as a stub.
     *
     * The following keys are appended to each fine:
     * - payableOnline <boolean> May the fine be payed online?
     *
     * The following keys are appended when required:
     * - blockPayment <boolean> True if the fine prevents starting
     * the payment process.
     *
     * @param array $fines Processed fines.
     *
     * @return array $fines Fines.
     */
    public function markOnlinePayableFines($fines)
    {
        return $fines;
    }

    /**
     * Registers an online payment to the ILS.
     *
     * @param array  $patron   Patron
     * @param int    $amount   Total amount paid
     * @param string $currency Currency
     * @param array  $params   Registration configuration parameters
     *
     * @return boolean  true on success, false on failed registration
     */
    public function registerOnlinePayment($patron, $amount, $currency, $params)
    {
        $fines = $this->getMyFines($patron);
        $payableFines = array_filter(
            $fines, function ($fine) {
                return $fine['payableOnline'];
            }
        );
        $total = array_reduce(
            $payableFines, function ($carry, $fine) {
                $carry += $fine['amount'];
                return $carry;
            }
        );

        if ($total != $amount) {
            return 'fines_updated';
        }

        $success = true;
        $errorIds = [];
        foreach ($payableFines as $fine) {
            $fineId = $fine['fineId'];
            $request = ['Amount' => $fine['amount'] / 100.0];

            list($code, $result) = $this->makeRequest(
                ['BorrowerDebts', $patron['cat_username'], $fineId],
                json_encode($request),
                'POST', true
            );
            if ($code !== 200) {
                $errorIds[] = $fineId;
                $this->error(
                    "Registration error for fine $fineId "
                    . "(HTTP status $code): $result"
                );
                return false;
            }
        }

        if (!empty($errorIds)) {
            return 'Registration failed for fines: ' . implode(',', $errorIds);
        }
        return true;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param array $patron Patron array
     * @param array $info   Array of new profile key => value pairs
     *
     * @return int result HTTP code
     */
    protected function updatePatronInfo($patron, $info)
    {
        list($code, $result) = $this->makeRequest(
            ['odata',
             'Borrowers(' . $patron['id'] . ')'],
            json_encode($info),
            'PATCH',
            true
        );
        return $code;
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
            'PickupUnitId' => $pickUpLocation
        ];

        list($code, $result) = $this->makeRequest(
            ['odata', 'BorrowerReservations(' . $requestId . ')',
             'Default.ChangePickupUnit'],
            json_encode($request),
            'POST',
            true
        );

        if ($code > 204) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password:
     *
     * 'patron'      The patron array from patronLogin
     * 'oldPassword' Old password
     * 'newPassword' New password
     *
     * @return array An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        $request = [
            'NewPin' => $details['newPassword'],
            'OldPin' => $details['oldPassword']
        ];

        list($code, $result) = $this->makeRequest(
            ['odata',
             'Borrowers(' . $details['patron']['id'] . ')',
             'Default.ChangePinCode'],
            json_encode($request),
            'POST',
            true
        );

        if ($code != 204) {
            return [
                'success' => false,
                'status' => 'authentication_error_invalid_attributes'
            ];
        }
        return ['success' => true, 'status' => 'change_password_ok'];
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'markFeesAsPaid') {
            return $this->supportsOnlinePayment();
        }

        // Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     * @param array $fines  Patron's fines
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron, $fines)
    {
        if (!empty($fines)) {
            $nonPayableReason = false;
            $amount = 0;
            $allowPayment = true;
            foreach ($fines as $fine) {
                if (!$fine['payableOnline']) {
                    $nonPayableReason
                        = 'online_payment_fines_contain_nonpayable_fees';
                } else {
                    $amount += $fine['balance'];
                }
                if ($allowPayment && !empty($fine['blockPayment'])) {
                    $allowPayment = false;
                }
            }
            $config = $this->getConfig('onlinePayment');
            if (!$nonPayableReason
                && isset($config['minimumFee']) && $amount < $config['minimumFee']
            ) {
                $nonPayableReason = 'online_payment_minimum_fee';
            }
            $res = ['payable' => $allowPayment, 'amount' => $amount];
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
     * @param array  $patron            Patron
     * @param int    $amount            Amount to be registered as paid
     * @param string $transactionId     Transaction ID
     * @param int    $transactionNumber Internal transaction number
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount, $transactionId,
        $transactionNumber
    ) {
        if (!$this->validateOnlinePaymentConfig(true)) {
            throw new ILSException(
                'Online payment disabled or configuration missing.'
            );
        }

        $paymentConfig = $this->getOnlinePaymentConfig();
        $params
            = $paymentConfig['registrationParams'] ?? []
        ;
        $currency = $paymentConfig['currency'];
        $userId = $patron['id'];
        $patronId = $patron['cat_username'];
        $errFun = function ($userId, $patronId, $error) {
            $this->error(
                "Online payment error (user: $userId, driver: "
                . $this->dbName . ", patron: $patronId): "
                . $error
            );
            throw new ILSException($error);
        };

        $result = $this->registerOnlinePayment(
            $patron, $amount, $currency, $params
        );
        if ($result === true) {
            $cacheId = "blocks_$patronId";
            $this->session->cache[$cacheId] = null;
            return true;
        } elseif ($result !== false) {
            $errFun($userId, $patronId, $result);
        }
        return false;
    }

    /**
     * Get online payment configuration
     *
     * @param boolean $throwException Throw an ILSException if the
     * configuration is not valid.
     *
     * @return array config data
     */
    protected function getOnlinePaymentConfig($throwException = false)
    {
        if (empty($this->config['OnlinePayment'])) {
            return false;
        }
        return $this->config['OnlinePayment'];
    }

    /**
     * Check if online payment is supported and enabled
     *
     * @return bool
     */
    protected function supportsOnlinePayment()
    {
        $config = $this->getOnlinePaymentConfig();
        if (!$config || empty($config['enabled'])) {
            return false;
        }
        return $this->validateOnlinePaymentConfig();
    }

    /**
     * Helper method for validating online payment configuration.
     *
     * @param boolean $throwException Throw an ILSException if the
     * configuration is not valid.
     *
     * @return bool
     */
    protected function validateOnlinePaymentConfig($throwException = false)
    {
        $checkRequired = function ($config, $params, $throwException) {
            foreach ($params as $req) {
                if (!isset($params[$req]) && !empty($params[$req])) {
                    $err = "Missing online payment parameter $req";
                    $this->error($err);
                    if ($throwException) {
                        throw new ILSException($err);
                    }
                    return false;
                }

                if (empty($config[$req])) {
                    return false;
                }
            }
            return true;
        };
        if (!$config = $this->getOnlinePaymentConfig()) {
            return false;
        }
        if (!$checkRequired($config, ['currency', 'enabled'], $throwException)) {
            return false;
        }
        $registrationParams = $this->getOnlinePaymentRegistrationParams();
        if (empty($registrationParams)) {
            return true;
        }

        if (empty($config['registrationParams'])) {
            return false;
        }
        return $checkRequired(
            $config['registrationParams'], $registrationParams, $throwException
        );
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron information, if available
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null)
    {
        $result = $this->makeRequest(
            ['odata', 'CatalogueItems'],
            ['$filter' => "MarcRecordId eq $id"]
        );

        if (empty($result)) {
            return [];
        }

        $statuses = [];
        $organisationTotal = [];
        foreach ($result as $i => $item) {
            $statusCode = $this->getItemStatusCode($item);
            if ($statusCode === 'Withdrawn') {
                continue;
            }

            $unitId = $item['BelongToUnitId'];
            if (!$unit = $this->getLibraryUnit($unitId)) {
                continue;
            }
            $locationName = $this->translate(
                'location_' . $unit['name'],
                null,
                $unit['name']
            );

            $available = $item['ItemStatus'] === 'AvailableForLoan';
            $organisationTotal[$unit['branch']] = [
               'reservations' => $item['ReservationQueueLength']
            ];
            $duedate = isset($item['DueDate'])
                ? $this->formatDate(
                    $item['DueDate']
                )
                : '';
            $unit = $this->getLibraryUnit($unitId);
            $number = '';
            $shelf = $item['Shelf'];

            // Special case: detect if Shelf field has issue number information
            // (e.g. 2018:4) and put the info into number field instead
            if (preg_match('/^\d{4}:\d+$/', $shelf) === 1) {
                $number = $shelf;
                $shelf = '';
            }
            $entry = [
                'id' => $id,
                'item_id' => $item['Id'],
                'parentId' => $unit['parent'],
                'holdings_id' => $unit['organisation'],
                'location' => $locationName,
                'organisation_id' => $unit['organisation'],
                'branch_id' => $unit['branch'],
                'availability' => $available,
                'status' => $statusCode,
                'reserve' => 'N',
                'callnumber' => $shelf,
                'duedate' => $duedate,
                'barcode' => $item['Barcode'],
                'item_notes' => [$item['notes'] ?? null],
                'number' => $number,
            ];

            if (!empty($item['LocationId'])) {
                $entry['department'] = $this->getDepartment($item['LocationId']);
            }

            if ($this->itemHoldAllowed($item) && $item['PermitLoan']) {
                $entry['is_holdable'] = true;
                if ($patron) {
                    $entry['level'] = 'copy';
                    $entry['addLink'] = !empty(
                        $this->config['Holds']['ShowLinkOnCopy']
                    );
                }
            } else {
                $entry['is_holdable'] = false;
                $entry['status'] = 'On Reference Desk';
            }

            $statuses[] = $entry;
        }

        foreach ($statuses as &$status) {
            $status['availabilityInfo']
                = array_merge(
                    ['displayText' => $status['status']],
                    $organisationTotal[$status['branch_id']]
                );
        }

        usort($statuses, [$this, 'statusSortFunction']);

        $summary = $this->getHoldingsSummary($statuses);
        $statuses[] = $summary;
        return $statuses;
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
        $holdable = false;
        $titleHold = true;
        $availableTotal = $itemsTotal = $orderedTotal = $reservationsTotal = 0;
        $locations = [];
        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (isset($item['availabilityInfo']['ordered'])) {
                $orderedTotal += $item['availabilityInfo']['ordered'];
            }

            $reservationsTotal = $item['availabilityInfo']['reservations'];
            $locations[$item['location']] = true;

            if ($item['is_holdable']) {
                $holdable = true;
            }
            if (!empty($item['number'])) {
                $titleHold = false;
            }
            $itemsTotal++;
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.
        return [
           'available' => $availableTotal,
           'ordered' => $orderedTotal,
           'total' => $itemsTotal,
           'reservations' => $reservationsTotal,
           'locations' => count($locations),
           'holdable' => $holdable,
           'availability' => null,
           'callnumber' => null,
           'location' => '__HOLDINGSSUMMARYLOCATION__',
           'groupBranches' => false,
           'titleHold' => $titleHold
        ];
    }

    /**
     * Map Mikromarc status to VuFind.
     *
     * @param array $item Item from Mikromarc.
     *
     * @return string Status
     */
    protected function getItemStatusCode($item)
    {
        $map = [
           'AvailableForLoan' => 'Available',
           'InCourseOfAcquisition' => 'Ordered',
           'OnLoan' => 'Charged',
           'InProcess' => 'In Process',
           'Recalled' => 'Recall Request',
           'WaitingOnReservationShelf' => 'On Holdshelf',
           'AwaitingReplacing' => 'In Repair',
           'InTransitBetweenLibraries' => 'In Transit',
           'ClaimedReturnedOrNeverBorrowed' => 'Claims Returned',
           'Lost' => 'Lost--Library Applied',
           'MissingBeingTraced' => 'Lost--Library Applied',
           'AtBinding' => 'In Repair',
           'UnderRepair' => 'In Repair',
           'AwaitingTransfer' => 'In Transit',
           'MissingOverdue' => 'Overdue',
           'Withdrawn' => 'Withdrawn',
           'Discarded' => 'Withdrawn',
           'Other' => 'Not Available',
           'Unknown' => 'No information available',
           'OrderedFromAnotherLibrary' => 'In Transit',
           'DeletedInMikromarc1' => 'Withdrawn',
           'Reserved' => 'On Hold',
           'ReservedInTransitBetweenLibraries' => 'In Transit On Hold',
           'ToAcquisition' => 'In Process',
        ];

        return $map[$item['ItemStatus']] ?? 'No information available';
    }

    /**
     * Get the list of library units.
     *
     * @return array Associative array of library unit id => name pairs.
     */
    protected function getLibraryUnits()
    {
        $cacheKey = implode(
            '|', [
               'mikromarc', 'libraryUnits',
               $this->config['Catalog']['base'], $this->config['Catalog']['unit']
            ]
        );

        $units = $this->getCachedData($cacheKey);

        if ($units !== null) {
            return $units;
        }

        $result = $this->makeRequest(['odata', 'LibraryUnits']);

        $units = [];
        foreach ($result as $unit) {
            $id = $unit['Id'];
            $units[$id] = [
                'id' => $id,
                'name' => $unit['Name'],
                'parent' => $unit['ParentUnitId'],
                'department' => $unit['IsDepartment']
            ];
        }

        foreach ($units as $key => &$unit) {
            $parent = !empty($units[$unit['parent']])
                ? $units[$unit['parent']] : null;

            // Branch and organisation
            $unit['branch'] = $key;
            $organisationId = 1;
            $organisationName = null;
            if (!empty($this->config['Holdings']['organisationId'])) {
                $organisationId = $this->config['Holdings']['organisationId'];
                $organisationName
                    = $this->translator->translate("source_$organisationId");
            } elseif ($parent && $parent['department']) {
                $organisationId = $parent['parent'];
                $organisationName = $this->getLibraryUnit($parent['id'])['name'];
            }

            $unit['organisation'] = $organisationId;
            $unit['organisationName'] = $organisationName;

            if (!$unit['department'] || !$parent) {
                continue;
            }

            // Prepend parent name to department names
            $parentName = $parent['name'];
            $unitName = $unit['name'];
            if (strpos(trim($unitName), trim($parentName)) === 0) {
                continue;
            }
            $unit['name'] = "$parentName - $unitName";
        }
        $this->putCachedData($cacheKey, $units, 3600);
        return $units;
    }

    /**
     * Return library unit information..
     *
     * @param int $id Unit id.
     *
     * @return array|null
     */
    protected function getLibraryUnit($id)
    {
        $units = $this->getLibraryUnits();
        return $units[$id] ?? null;
    }

    /**
     * Return library unit name.
     *
     * @param int $id Unit id.
     *
     * @return string|null
     */
    protected function getLibraryUnitName($id)
    {
        $unit = $this->getLibraryUnit($id);
        return $unit ? $unit['name'] : null;
    }

    /**
     * Get patron's blocks, if any
     *
     * @param array $patron Patron
     *
     * @return mixed        A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function getPatronBlocks($patron)
    {
        return false;
    }

    /**
     * Get cache key for patron profile.
     *
     * @param array  $patron Patron
     * @param string $action Action calling
     *
     * @return string
     */
    protected function getPatronCacheKey($patron, $action)
    {
        return "mikromarc|$action|"
            . md5(implode('|', [$patron['cat_username'], $patron['cat_password']]));
    }

    /**
     * Create a HTTP client
     *
     * @param string $url Request URL
     *
     * @return \Laminas\Http\Client
     */
    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

        if (isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Laminas\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context, 'ssl', 'verify_peer_name', false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Laminas\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // Set timeout value
        $timeout = isset($this->config['Catalog']['http_timeout'])
            ? $this->config['Catalog']['http_timeout'] : 30;
        $client->setOptions(
            ['timeout' => $timeout, 'useragent' => 'VuFind', 'keepalive' => true]
        );

        // Set Accept header
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept', 'application/json'
        );

        return $client;
    }

    /**
     * Check if an item is holdable
     *
     * @param array $item Item
     *
     * @return bool
     */
    protected function itemHoldAllowed($item)
    {
        $notAllowedForHold = isset($this->config['Holds']['notAllowedForHold'])
            ? explode(':', $this->config['Holds']['notAllowedForHold'])
            : [
                'ClaimedReturnedOrNeverBorrowed', 'Lost',
                'SuppliedReturnNotRequired', 'MissingOverDue', 'Withdrawn',
                'Discarded', 'Other'
            ];
        return in_array($item['ItemStatus'], $notAllowedForHold) ? false : true;
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
     * Return a hold error message
     *
     * @param int   $code   HTTP Result Code
     * @param array $result API Response
     *
     * @return array
     */
    protected function holdError($code, $result)
    {
        $message = 'hold_error_fail';
        if (!empty($result['error']['message'])) {
            $message = $result['error']['message'];
        } elseif (!empty($result['error']['code'])) {
            $message = $result['error']['code'];
        }

        $map = [
           'BorrowerDefaulted' => 'authentication_error_account_locked',
           'DuplicateReservationExists' => 'hold_error_already_held',
           'NoItemsAvailableByTerm' => 'hold_error_denied',
           'NoItemAvailable' => 'hold_error_denied',
           'NoTermsPermitLoanOrReservation' => 'hold_error_not_holdable'
        ];

        if (isset($map[$message])) {
            $message = $map[$message];
        }

        return [
            'success' => false,
            'sysMessage' => $message
        ];
    }

    /**
     * Make Request
     *
     * Makes a request to the Mikromarc REST API
     *
     * @param array  $hierarchy  Array of values to embed in the URL path of
     * the request
     * @param array  $params     A keyed array of query data
     * @param string $method     The http request method to use (Default is GET)
     * @param bool   $returnCode If true, returns HTTP status code in addition to
     * the result
     *
     * @throws ILSException
     * @return mixed JSON response decoded to an associative array or null on
     * authentication error
     */
    protected function makeRequest($hierarchy, $params = false, $method = 'GET',
        $returnCode = false
    ) {
        // Set up the request
        $conf = $this->config['Catalog'];
        $apiUrl = $this->config['Catalog']['host'];
        $apiUrl .= '/' . urlencode($conf['base']);
        $apiUrl .= '/' . urlencode($conf['unit']);

        // Add hierarchy
        foreach ($hierarchy as $value) {
            $apiUrl .= '/' . urlencode($value);
        }

        // Create proxy request
        $client = $this->createHttpClient($apiUrl);
        $client->setAuth($conf['username'], $conf['password']);

        // Add params
        if (false !== $params) {
            if ($method == 'GET') {
                $client->setParameterGet($params);
            } else {
                if (is_string($params)) {
                    $client->getRequest()->setContent($params);
                    $client->getRequest()->getHeaders()
                        ->addHeaderLine('Content-Type', 'application/json');
                } else {
                    $client->setParameterPost($params);
                }
            }
        } else {
            $client->setHeaders(['Content-length' => 0]);
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $client->setMethod($method);

        if (false == $params) {
            $params = [];
        }

        $page = 0;
        $data = [];
        $fetch = true;
        while ($fetch) {
            $client->setUri($apiUrl);
            $response = $client->send();
            $result = $response->getBody();
            $this->debug(
                '[' . round(microtime(true) - $startTime, 4) . 's]'
                . " GET request $apiUrl" . PHP_EOL . 'response: ' . PHP_EOL
                . $result
            );
            // Handle errors as complete failures only if the API call didn't return
            // valid JSON that the caller can handle
            $decodedResult = json_decode($result, true);
            if (!$response->isSuccess()
                && (null === $decodedResult || !empty($decodedResult['error']))
                && !$returnCode
            ) {
                $params = $method == 'GET'
                   ? $client->getRequest()->getQuery()->toString()
                    : $client->getRequest()->getPost()->toString();
                $this->error(
                    "$method request for '$apiUrl' with params"
                    . "'$params' and contents '"
                    . $client->getRequest()->getContent() . "' failed: "
                    . $response->getStatusCode() . ': '
                    . $response->getReasonPhrase()
                    . ', response content: ' . $response->getBody()
                );
                throw new ILSException('Problem with Mikromarc REST API.');
            }

            // More results available?
            if ($next = !empty($decodedResult['@odata.nextLink'])
                && !strpos(
                    $decodedResult['@odata.nextLink'], 'LibraryUnits?$skip=100'
                )
            ) {
                $client->setParameterPost([]);
                $client->setParameterGet([]);
                $apiUrl = $decodedResult['@odata.nextLink'];
            }

            if (isset($decodedResult['value'])) {
                $decodedResult = $decodedResult['value'];
            }

            if ($page == 0) {
                $data = $decodedResult;
            } else {
                $data = array_merge($data, $decodedResult);
            }

            if (!$next) {
                $fetch = false;
            }
            $page++;
        }
        return $returnCode ? [$response->getStatusCode(), $data]
            : $data;
    }

    /**
     * Status item sort function
     *
     * @param array $a First status record to compare
     * @param array $b Second status record to compare
     *
     * @return int
     */
    protected function statusSortFunction($a, $b)
    {
        $key = 'parentId';

        $sortOrder = $this->holdingsOrganisationOrder;
        $orderA = in_array($a[$key], $sortOrder)
            ? array_search($a[$key], $sortOrder) : null;
        $orderB = in_array($b[$key], $sortOrder)
            ? array_search($b[$key], $sortOrder) : null;

        if ($orderA !== null) {
            if ($orderB !== null) {
                $posA = array_search($a[$key], $sortOrder);
                $posB = array_search($b[$key], $sortOrder);
                return $posA - $posB;
            }
            return -1;
        }
        if ($orderB !== null) {
            return 1;
        }
        return strcmp($a['location'], $b['location']);
    }

    /**
     * Fetch name of the department where the shelf is located
     *
     * @param int $locationId Id of the shelf
     *
     * @return string
     */
    public function getDepartment($locationId)
    {
        static $cacheDepartment = [];
        if (!isset($cacheDepartment[$locationId])) {
            $request = [
                '$filter' => "Id eq $locationId"
            ];
            $cacheDepartment[$locationId] = $this->makeRequest(
                ['odata', 'CatalogueItemLocations'],
                $request
            );
        }
        return $cacheDepartment[$locationId][0]['Name'];
    }

    /**
     * Format date
     *
     * @param string $dateString Date as a string
     *
     * @return string Formatted date
     */
    protected function formatDate($dateString)
    {
        // Ignore timezone and time, otherwise CatalogueItems
        // and BorrowerLoans api calls give different due dates for
        // the same item
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateString, $matches)) {
            return $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $matches[1]
            );
        }

        return $this->dateConverter->convertToDisplayDate('Y-m-d', $dateString);
    }

    /**
     * Change request status
     *
     * This is responsible for changing the status of a hold request
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Associative array of the results
     */
    public function changeRequestStatus($patron, $holdDetails)
    {
        if ($holdDetails['frozen'] == 1) {
            // Mikromarc doesn't have any separate 'freeze' status on reservations
            $getHold = $this->makeRequest(
                ['odata','BorrowerReservations(' . $holdDetails['requestId'] . ')']
            );
            $pausedFrom = date('Y-m-d', strtotime('today'));
            $pausedTo = date('Y-m-d', strtotime($getHold['ResValidUntil']));
        } else {
            $pausedFrom = null;
            $pausedTo = null;
        }
        $requestBody = [
            "ResPausedFrom" => $pausedFrom,
            "ResPausedTo" => $pausedTo
        ];
        list($code, $result) = $this->makeRequest(
            ['odata','BorrowerReservations(' . $holdDetails['requestId'] . ')'],
            json_encode($requestBody),
            'PATCH',
            true
        );
        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if ('title' === $data['level']) {
            $items = $this->getStatus($id);
            $summary = array_pop($items);
            if ((isset($summary['titleHold']) && $summary['titleHold'] === false)
                || !$summary['holdable']
            ) {
                return false;
            }
        }
        return true;
    }
}
