<?php
/**
 * Gemini REST API Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016-2017.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;
use SoapClient, SoapFault, SoapHeader, File_MARC, PDO, PDOException, DOMDocument,
    VuFind\Exception\Date as DateException,
    VuFind\Exception\ILS as ILSException,
    VuFind\I18n\Translator\TranslatorAwareInterface as TranslatorAwareInterface;
use VuFind\Exception\Date;

/**
 * Gemini REST API Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Gemini extends \VuFind\ILS\Driver\AbstractBase
    implements \Zend\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Web services host
     *
     * @var string
     */
    protected $wsHost;

    /**
     * Web services database key
     *
     * @var string
     */
    protected $wsApiKey;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Web Services cookies.
     *
     * @var \Zend\Http\Response\Header\SetCookie[]
     */
    protected $cookies = false;

    /**
     * Default request group
     *
     * @var bool|string
     */
    protected $defaultRequestGroup;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter
    ) {
        $this->dateFormat = $dateConverter;
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
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['host'])) {
            $this->wsHost = $this->config['Catalog']['host'];
        } else {
            throw new ILSException('host configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['apikey'])) {
            $this->wsApiKey = $this->config['Catalog']['apikey'];
        } else {
            throw new ILSException('apikey configuration needs to be set.');
        }

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation']
            : '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }
        $this->defaultRequestGroup
            = isset($this->config['Holds']['defaultRequestGroup'])
            ? $this->config['Holds']['defaultRequestGroup'] : false;
        if ($this->defaultRequestGroup === 'user-selected') {
            $this->defaultRequestGroup = false;
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
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }

        return $functionConfig;
    }

    /**
     * Add instance-specific context to a cache key suffix (to ensure that
     * multiple drivers don't accidentally share values in the cache.
     *
     * @param string $key Cache key suffix
     *
     * @return string
     */
    protected function formatCacheKey($key)
    {
        // Override the base class formatting with Gemini-specific details
        // to ensure proper caching in a MultiBackend environment.
        return 'Gemini-'
            . md5("{$this->wsHost}|{$this->wsApiKey}|$key");
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getHolding($id);
        }
        return $items;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws DateException
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        // Add Required Params
        $params = [
            'id'   => $id,
            'lang' => $this->getLanguage()
        ];
        $response = $this->makeRequest('GetItem', $params);

        $itemsTotal =  (int) $response->MarcRecord->Overview->TotalCount;
        $orderedTotal = (int) $response->MarcRecord->Overview->Ordered;
        $reservationsTotal =  (int) $response->MarcRecord->Overview->QueueLength;
        $availability = (string) $response->MarcRecord->Overview->IsAvailable
            == 'true' ? true : false;
        $availableTotal = 0;
        $holdableTotal = false;
        $locations = [];
        $holdings = [];

        // Build Holdings Array
        foreach ($response->MarcRecord->Item as $item) {
            $department = (string) $item->Department;
            $branch = (string) $item->PlacedAtUnit;
            $branchId = (int) $item->PlacedAtUnitId;
            $shelf = (string) $item->Shelf;
            $journalInfo = null;

            if (!$item->PermitLoan) {
                $status = 'status_On Reference Desk';
                $available = true;
            } else {
                $status = (string) $item->StatusCode;
                $status = $this->mapStatusCode($status);
                $available = $status == 'Available';

                if ($status == 'Charged') {
                    $dueDate = $this->dateFormat->convertToDisplayDate(
                        'Y-m-d', (string) $item->DueDate
                    );
                } else {
                    $dueDate = '';
                }
            }

            if ($available) {
                $availableTotal++;
            }

            $holdable = (int) $item->PermitReservation == 0 ? true : false;

            if ($holdable) {
                $holdableTotal = true;
            }

            if (isset($item->MagazineIssue)) {
                $journal = explode(':', (string)$item->MagazineIssue);

                $journalInfo = [
                    'year' => $journal[0],
                    'edition' => $journal[1],
                    'location' => $branch
                ];
            }

            $holding = [
               'id' => $id,
               'barcode' => (string) $item->BarCode,
               'callnumber' =>  (string) $item->Shelf,
               'item_id' => (string) $item->ItemId,
               'holdings_id' => $shelf,
               'availability' => $available,
               'availabilityInfo' => [
                   'displayText' => $status
               ],
               'branch' => $branch,
               'department' => $department,
               'department_id' => $branchId,
               'duedate' => $dueDate,
               'location' =>  $branch,
               'reserve' => null,
               'status' => $status,
               'is_holdable' => $holdable
            ];

            if ($journalInfo) {
                $holding['journalInfo'] = $journalInfo;
            }
            // Add to the holdings array
            $holdings[] = $holding;
        }

        if (!empty($holdings)) {
            // Summary as a fake item
            $summary = [
                'available' => $availableTotal,
                'ordered' => $orderedTotal,
                'total' => $itemsTotal,
                'reservations' => $reservationsTotal,
                'locations' => null,
                'holdable' => $holdableTotal,
                'availability' => null,
                'callnumber' => null,
                'location' => null
            ];
            $holdings[] = $summary;
        }

        return $holdings;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron barcode
     * @param string $password The patron's last name or PIN (depending on config)
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        // Add Required Params
        $params = [
            'barcode' => $username,
            'pincode' => $password,
            'lang'    => $this->getLanguage()
        ];
        $response = $this->makeRequest('LoginPatron', $params);

        $statusId = (string) $response->Result['id'];

        if ($statusId != '0') {
            return null;
        }

        $response = $this->makeRequest('GetPatronInformation', $params);

        $info = $response->Patron;

        $names = explode(', ', (string) $info->Name, 2);
        $lastname = $names[0];
        $firstname = isset($names[1]) ? $names[1] : '';

        $activatedServices = [
            'pickUpNotice' => (int) $info->SendRes,
            'overdueNotice' => (int) $info->SendRemind,
            'dueDateNotice' => (int) $info->SendRecall
        ];

        $messagingSettings = $this->processMessagingSettings($activatedServices);

        $user = [
            'id' => (string) $info->PatronId,
            'cat_username' => $username,
            'cat_password' => $password,
            'lastname' => $lastname,
            'firstname' => $firstname,
            'email' => (string) $info->MainEmail,
            'address1' => (string) $info->MainAddrLine1,
            'zip' => (int) $info->MainZip,
            'city' => (string) $info->MainPlace,
            'country' => (string) $info->MainCountry,
            'phone' => (int) $info->MainPhone,
            'phoneLocalCode' => null,
            'phoneAreaCode' => null,
            'major' => null,
            'college' => null,
            'messagingServices' => $messagingSettings
        ];

        return $user;
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
        // $patron already contains all fields, but we need to fetch
        // up-to-date patron information
        $profile
            = $this->patronLogin($patron['cat_username'], $patron['cat_password']);

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
     * @throws ILSException
     * @return mixed        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        // Add Required Params
        $params = [
            'barcode' => $username,
            'pincode' => $password,
            'lang'    => $this->getLanguage()
        ];
        $response = $this->makeRequest('GetPatronLoans', $params);

        if (!isset($response->Loans->Loan)) {
            return [];
        }

        foreach ($response->Loans->Loan as $loan) {
            $itemId = (string) $loan->ItemId;

            $transactions[] = [
                'id' => (string) $loan->MarcRecordId,
                'item_id' => (string) $loan->LoanId,
                'barcode' => (string) $loan->ItemId,
                'duedate' =>  $this->dateFormat->convertToDisplayDate(
                    'Y-m-d', (string) $loan->DueTime
                ),
                'title' => (string) $loan->WorkTitle,
                'author' => (string) $loan->WorkAuthor,
                'renewable' => (string) $loan->CanBeRenewed == 'true' ? true : false,
                'renewalCount' => (int) $loan->Renewals,
                'renewalLimit' => $this->config['Loans']['renewalLimit']
            ];

        }
        return $transactions;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        // Add Required Params
        $params = [
            'barcode' => $username,
            'pincode' => $password,
            'lang'    => $this->getLanguage()
        ];

        // Gemini does not provide specific information on fines

        $response = $this->makeRequest('GetPatronInformation', $params);

        $balance = (float)$response->Patron->AccountSaldo[0];

        if ($balance == 0) {
            return [];
        } else {
            $balance = $balance * 100;
        }

        $fine = [
            'fine' => $this->translate('Total Balance Due'),
            'balance' => $balance
        ];
        $finesList[] = $fine;

        return $finesList;
    }

    /**
     * Renew Items
     *
     * This is responsible for renewing items.
     *
     * @param string $renewDetails The request details
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function renewMyItems($renewDetails)
    {
        $results = ['blocks' => [], 'details' => []];

        $username = $renewDetails['patron']['cat_username'];
        $password = $renewDetails['patron']['cat_password'];

        foreach ($renewDetails['details'] as $loanId) {
            // Add Required Params
            $params = [
                'barcode' => $username,
                'pincode' => $password,
                'loanId'  => $loanId
            ];
            $response = $this->makeRequest('renewloan', $params);

            $statusId = (string) $response->Result['id'];

            if ($statusId == '0') {
                $success = true;
                $status = 'Loan renewed';
                $sysMessage = '';
            } else {
                $success = false;
                $status = 'Renewal failed';
                $sysMessage =  $this->mapErrorCodeRenewLoan($statusId);
            }

            $results['details'][$loanId] = [
                'success' => $success,
                'status' => $status,
                'sysMessage' => $sysMessage,
                'item_id' => $loanId,
                'new_date' => '',
                'new_time' => ''
            ];
        }
        return $results;
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        // Add Required Params
        $params = [
            'barcode' => $username,
            'pincode' => $password,
            'lang'    => $this->getLanguage()
        ];
        $response = $this->makeRequest('GetPatronReservations', $params);

        if (!isset($response->Reservations->Reservation)) {
            return [];
        }

        foreach ($response->Reservations->Reservation as $reservation) {
            $hold = [
                'id' => (string) $reservation->MarcRecordId,
                'type' => (string) $reservation->Status,
                'location' => (string) $reservation->DeliverAt,
                'reqnum' => (string) $reservation->ReservationId,
                'expire' => $this->dateFormat->convertToDisplayDate(
                    'Y-m-d', (string) $reservation->ValidUntil
                ),
                'create' => $this->dateFormat->convertToDisplayDate(
                    'Y-m-d', (string) $reservation->Activation
                ),
                'position' => (string) $reservation->NumberInQueue,
                'available'
                    => (string) $reservation->reservationStatus == 'fetchable',
                'modifiable' => (string) $reservation->reservationStatus == 'active',
                'item_id' => (string) $reservation->ItemId,
                'requestId' => (string) $reservation->ReservationId,
                'title' => (string) $reservation->WorkTitle
            ];
            $holdsList[] = $hold;
        }
        return $holdsList;
    }

    /**
     * Get the language to be used in the interface
     *
     * @return string Language as string
     */
    protected function getLanguage()
    {
        $language = $this->getTranslatorLocale();

        //Only swedish and finnish are supported
        if (!in_array($language, ['sv', 'fi'])) {
            $language = 'fi';
        }
        return $language;
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
        return true;
    }

     /**
     * Get request groups
     *
     * @param integer $bibId       BIB ID
     * @param array   $patronId    Patron information returned by the patronLogin
     * method.
     * @param array   $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     */
    public function getRequestGroups($bibId, $patronId, $holdDetails = null)
    {
        $interfaceLanguage = $this->getLanguage();
        $results = [];

        // Add Required Params
        $params['unitId'] = 'ALL';

        $response = $this->makeRequest('getUnits', $params);

        if (!isset($response->Unit)) {
            return [];
        }

        foreach ($response->Unit as $unit) {
            foreach ($unit->description as $description) {
                $lang = (string) $description->attributes()['lang'];

                if ($lang == $interfaceLanguage) {
                    $unitName = (string) $description;
                }
            }

            $unit = [
                'id'   => (string) $unit->UnitId,
                'name' => $unitName
            ];
            $results[] = $unit;
        }
        return $results;
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
    public function getPickUpLocations($patron, $holdDetails)
    {
        $interfaceLanguage = $this->getLanguage();

        if (isset($holdDetails['requestGroupId'])) {
            $unitId = $holdDetails['requestGroupId'];
        } else {
            $unitId = 'ALL';
        }

        $results = [];

        // Add Required Params
        $params['unitId'] = $unitId;

        $response = $this->makeRequest('getUnits', $params);

        if (!isset($response->Unit)) {
            return [];
        }

        foreach ($response->Unit as $unit) {
            foreach ($unit->description as $description) {
                $lang = (string) $description->attributes()['lang'];

                if ($lang == $interfaceLanguage) {
                    $unitName = (string) $description;
                }
            }

            $unit = [
                'locationID'   => (string) $unit->UnitId,
                'locationDisplay' => $unitName
            ];
            $results[] = $unit;
        }
        return $results;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
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
     * Get Default Request Group
     *
     * Returns the default request group set in VoyagerRestful.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the request group
     * options or may be ignored.
     *
     * @return false|string      The default request group for the patron or false if
     * the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        return $this->defaultRequestGroup;
    }

    /**
     * Encode a string for XML
     *
     * @param string $string String to be encoded
     *
     * @return string Encoded string
     */
    protected function encodeXML($string)
    {
        return htmlspecialchars($string, ENT_COMPAT, 'UTF-8');
    }

    /**
     * Hold Error
     *
     * Returns a Hold Error Message
     *
     * @param string $msg An error message string
     *
     * @return array An array with a success (boolean) and sysMessage key
     */
    protected function holdError($msg)
    {
        return [
            'success' => false,
            'sysMessage' => $msg
        ];
    }

    /**
     * Place Hold
     *
     * This is responsible for both placing holds as well as placing recalls.
     *
     * @param string $holdDetails The request details
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function placeHold($holdDetails)
    {
        $username = $holdDetails['patron']['cat_username'];
        $password = $holdDetails['patron']['cat_password'];

        $pickUpLocation = $holdDetails['pickUpLocation'];
        $requestGroupId = $holdDetails['requestGroupId'];
        $workId = $holdDetails['id'];

        // Add Required Params
        $params = [
            'barcode'         => $username,
            'pincode'         => $password,
            'workid'          => $workId,
            'deliverAtUnitId' => $pickUpLocation,
            'resOwnerUnitId'  => $requestGroupId
        ];
        $response = $this->makeRequest('addreservation', $params);

        $statusId = (string) $response->Result['id'];

        if ($statusId == '0') {
            $success = true;
            $status = 'hold_success';
        } else {
            $success = false;
            $status = $this->mapErrorCodePlaceHold($statusId);
        }

        $results = [
            'success' => $success,
            'status' => $status,
        ];

        return $results;
    }

    /**
     * Cancel Holds
     *
     * This is responsible for canceling holds.
     *
     * @param string $cancelDetails The request details
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function cancelHolds($cancelDetails)
    {
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $succeeded = 0;
        $results = [];

        foreach ($cancelDetails['details'] as $details) {
            $params = [
                'barcode'         => $username,
                'pincode'         => $password,
                'reservationId'   => $details
            ];
            $response = $this->makeRequest('removereservation', $params);

            $statusId = (string) $response->Result['id'];

            if ($statusId == '0') {
                $results[$details] = [
                    'success' => true,
                    'status' => 'hold_cancel_success'
                ];
                ++$succeeded;
            } else {
                $results[$details] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => (string) $response->Result->Message
                ];
            }
        }
        $results['count'] = $succeeded;
        return $results;
    }

    /**
     * Cancel Hold Details
     *
     * This is responsible for getting the details required for canceling holds.
     *
     * @param string $holdDetails The request details
     *
     * @return string           Required details passed to cancelHold
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['reqnum'];
    }

    /**
     * Renew Details
     *
     * This is responsible for getting the details required for renewing loans.
     *
     * @param string $checkoutDetails The request details
     *
     * @throws ILSException
     *
     * @return string           Required details passed to renewMyItems
     */
    public function getRenewDetails($checkoutDetails)
    {
        return $checkoutDetails['item_id'];
    }

    /**
     * Change Password
     *
     * @param String $cardDetails Patron card data
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function changePassword($cardDetails)
    {
        $username = $cardDetails['patron']['cat_username'];
        $message = '';

        // Add Required Params
        $params = [
            'barcode'    => $username,
            'oldpincode' => $cardDetails['oldPassword'],
            'newpincode' => $cardDetails['newPassword']
        ];
        $response = $this->makeRequest('changepatronpincode', $params);

        $statusId = (string) $response->Result['id'];

        if ($statusId == '0') {
            return  [
                'success' => true,
                'status' => 'change_password_ok',
            ];
        } else {
            return  [
               'success' => false,
               'status' => $this->mapErrorCodeChangePassword($statusId)
            ];

        }
    }

    /**
     * Process messaging settings
     *
     * @param array $activatedServices Array of IDs for actived services
     *
     * @throws ILSException
     * @return array The messaging settings of the patron
     */
    protected function processMessagingSettings($activatedServices)
    {
        $messagingSettings = [];
        // Get the choice of messaging settings from the backend
        $response = $this->makeRequest('GetMessTypeChoices', null);

        // Mappings from Gemini messaging services
        $messagingServiceMap = [
            'sendres' => 'pickUpNotice',
            'sendremind' => 'overdueNotice',
            'sendrecall' => 'dueDateNotice'
        ];

        $messagingOptions = ['letter', 'email', 'sms'];

        foreach ($response->MessTypeChoices as $messTypeChoice) {
            $messagingService
                = $messagingServiceMap[(string) $messTypeChoice['MessageType']];
            $messagingServiceLabel
                = $this->translate("messaging_settings_type_$messagingService");

            $sendMethods = [];

            $messagingSettings[$messagingService] =  [
                'active' => true,
                'type' => $messagingServiceLabel,
                'sendMethods' => []
            ];

            for ($i = 0; $i <= 3; $i++) {
                if ('1' == substr((string) $messTypeChoice->Choices, $i, 1)) {
                    // dueDateNotice supports messaging option 'none'
                    // instead of 'letter'
                    if (!$messagingService == 'dueDateNotice' && $i == 0) {
                        $messagingMethod = 'none';
                    } else {
                        $messagingMethod = $messagingOptions[$i];
                    }
                    $messagingSettings[$messagingService]['sendMethods'] += [
                        "$messagingMethod" => [
                            'active' => $i == $activatedServices[$messagingService],
                            'type' => $messagingMethod,
                            'method' => $this->translate(
                                "messaging_settings_method_$messagingMethod"
                            )
                        ]
                    ];
                }
            }
        }
        return $messagingSettings;
    }

    /**
     * Make Request
     *
     * Makes a request to the Gemini Restful API
     *
     * @param string $service Name of the service used
     * @param array  $params  A keyed array of query data
     * @param string $mode    The http request method to use (Default of GET)
     *
     * @throws ILSException
     * @return obj  A Simple XML Object loaded with the xml data returned by the API
     */
    protected function makeRequest($service, $params = false, $mode = 'GET')
    {
        // Build Url Base
        $urlParams = "{$this->wsHost}/$service";

        // Add web services database key
        $params['apikey'] = $this->wsApiKey;

        // Add Params
        $queryString = [];
        foreach ($params as $key => $param) {
            $queryString[] = urlencode($key) . '=' . urlencode($param);
        }

        // Build Params
        $urlParams .= '?' . implode('&', $queryString);

        // Create Proxy Request
        $client = $this->httpService->createClient($urlParams);

        // Add any cookies
        if ($this->cookies) {
            $client->addCookie($this->cookies);
        }

        // Set timeout value
        $timeout = isset($this->config['Catalog']['http_timeout'])
        ? $this->config['Catalog']['http_timeout'] : 30;
        $client->setOptions(['timeout' => $timeout]);
        $client->setOptions(['sslcapath' => '/etc/ssl/certs']);

        // Send Request and Retrieve Response
        $startTime = microtime(true);
        $result = $client->setMethod($mode)->send();
        if (!$result->isSuccess()) {
            $this->error(
                "$mode request for '$urlParams' with contents '$xml' failed: "
                . $result->getStatusCode() . ': ' . $result->getReasonPhrase()
            );
            throw new ILSException('Problem with RESTful API.');
        }

        // Store cookies
        $cookie = $result->getCookie();
        if ($cookie) {
            $this->cookies = $cookie;
        }

        // Process response
        $xmlResponse = $result->getBody();
        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $mode request $urlParams, contents:"
            . PHP_EOL . 'response: ' . PHP_EOL
            . $xmlResponse
        );
        $oldLibXML = libxml_use_internal_errors();
        libxml_use_internal_errors(true);
        $simpleXML = simplexml_load_string($xmlResponse);
        libxml_use_internal_errors($oldLibXML);

        if ($simpleXML === false) {
            throw new ILSException('ils_connection_failed');
        }
        return $simpleXML;
    }

    /**
     * Map statuses
     *
     * @param string $status as a string
     *
     * @return string Mapped statusCode
     */
    protected function mapStatusCode($status)
    {
        // Status table
        $statusArray = [
            '00' => 'Available',
            '01' => 'Charged',
            '02' => 'Charged',
            '03' => 'Available',
            '04' => 'Charged',
            '05' => 'Charged',
            '06' => 'In Process',
            '07' => 'At Bindery',
            '08' => 'Missing',
            '09' => 'Circulation Review',
            '10' => 'Charged',
            '11' => 'Available',
            '12' => 'Charged',
            '13' => 'Charged',
            '14' => 'Not Available',
            '15' => 'On Holdshelf',
            '16' => 'On Holdshelf',
            '17' => 'Not Charged',
            '18' => 'Not Available',
            '19' => 'Damaged',
            '20' => 'In Transit'
        ];

        // Convert status text
        if (isset($statusArray[$status])) {
            $status = $statusArray[$status];
        } else {
            $this->debug(
                "Unhandled status for Gemini: '$status'"
            );
        }
        return $status;
    }

    /**
     * Map error codes of renewals
     *
     * @param string $errorCode as a string
     *
     * @return string Mapped error code
     */
    protected function mapErrorCodeRenewLoan($errorCode)
    {
        $errorCodes =  [
            '-1'  => 'Renewed today',
            '-2'  => 'renew_denied',
            '-5'  => 'renew_item_requested',
            '-6'  => 'renew_fail',
            '-8'  => 'renew_item_overdue',
            '-9'  => 'renew_item_limit',
            '-10' => 'renew_item_no',
            '-11' => 'renew_item_no',
            '-12' => 'renew_fail',
            '-99' => 'renew_fail'
        ];

        if (isset($errorCodes[$errorCode])) {
            return $errorCodes[$errorCode];
        }
        return 'renew_denied';
    }

     /**
     * Map error codes of place hold
     *
     * @param string $errorCode as a string
     *
     * @return string Mapped error code
     */
    protected function mapErrorCodePlaceHold($errorCode)
    {
        $errorCodes =  [
            '-2'  => 'hold_error_item_not_holdable',
            '-7'  => 'hold_error_already_held',
            '-99' => 'hold_error_fail'
        ];

        if (isset($errorCodes[$errorCode])) {
            return $errorCodes[$errorCode];
        }
        return 'hold_error_fail';
    }

    /**
     * Map error codes of change password
     *
     * @param string $errorCode as a string
     *
     * @return string Mapped error code
     */
    protected function mapErrorCodeChangePassword($errorCode)
    {
        $errorCodes =  [
            '-3'  => 'authentication_error_invalid',
            '-4'  => 'password_error_invalid'
        ];

        if (isset($errorCodes[$errorCode])) {
            return $errorCodes[$errorCode];
        }
        return 'Changing password failed';
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
        // Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
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
        return false;
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
        return false;
    }
}
