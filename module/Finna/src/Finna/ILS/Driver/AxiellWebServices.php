<?php
/**
 * Axiell Web Services ILS Driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use DOMDocument;
use VuFind\Config\Locator;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface as TranslatorAwareInterface;

/**
 * Axiell Web Services ILS Driver
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
class AxiellWebServices extends \VuFind\ILS\Driver\AbstractBase
    implements TranslatorAwareInterface, \Laminas\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFind\ILS\Driver\CacheTrait;

    /**
     * Date formatting object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateFormat;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Excluded pickup locations
     *
     * @var array
     */
    protected $excludePickUpLocations;

    /**
     * Default request group
     *
     * @var bool|string
     */
    protected $defaultRequestGroup;

    /**
     * Whether request groups are enabled
     *
     * @var bool
     */
    protected $requestGroupsEnabled;

    /**
     * Regional hold
     *
     * @var Boolean
     */
    protected $regionalHold = false;

    /**
     * Arena Member code of the institution
     *
     * @var string
     */
    protected $arenaMember = '';

    /**
     * Wsdl file name or url for accessing the catalogue section of AWS
     *
     * @var string
     */
    protected $catalogue_wsdl = '';

    /**
     * Wsdl file name or url for accessing the patron section of AWS
     *
     * @var string
     */
    protected $patron_wsdl = '';

    /**
     * Wsdl file name or url for accessing the patronaurora section of AWS
     *
     * @var string
     */
    protected $patronaurora_wsdl = '';

    /**
     * Wsdl file name or url for accessing the loans section of AWS
     *
     * @var string
     */
    protected $loans_wsdl = '';

    /**
     * Wsdl file name or url for accessing the loansaurora section of aws
     *
     * @var string
     */
    protected $loansaurora_wsdl = '';

    /**
     * Wsdl file name or url for accessing the payment section of AWS
     *
     * @var string
     */
    protected $payments_wsdl = '';

    /**
     * Wsdl file name or url for accessing the reservation section of AWS
     *
     * @var string
     */
    protected $reservations_wsdl = '';

    /**
     * Wsdl file name or url for accessing the catalogue aurora section of AWS
     *
     * @var string
     */
    protected $catalogueaurora_wsdl = '';

    /**
     * Path of the AWS debug log-file
     *
     * @var string
     */
    protected $logFile = '';

    /**
     * Pathname with prefix for logging the duration of AWS calls
     *
     * @var string
     */
    protected $durationLogPrefix = '';

    /**
     * Verbose debug-mode
     *
     * @var Boolean
     */
    protected $verbose = false;

    /**
     * Institution settings for the order of organisations
     *
     * @var string
     */
    protected $holdingsOrganisationOrder;

    /**
     * Institution settings for the order of branches
     *
     * @var string
     */
    protected $holdingsBranchOrder;

    /**
     * Institution settings for single reservation queue
     *
     * @var Boolean
     */
    protected $singleReservationQueue = false;

    /**
     * Messaging methods excluded from a service
     *
     * @var array
     */
    protected $messagingBlackLists = [
        'pickUpNotice' => [],
        'overdueNotice' => [],
        'dueDateAlert' => []
    ];

    /**
     * Title list mappings
     *
     * @var array
     */
    protected $titleListMapping = [
        'new' => 'shownovelty',
        'mostrequested' => 'mostreserved',
        'mostborrowed' => 'mostloaned',
        'lastreturned' => 'showlastreturned'
    ];

    /**
     * Messaging settings status code mappings
     *
     * @var array
     */
    protected $statuses = [
        'snailMail'             => 'print',
        'ilsDefined'            => 'inactive',
    ];

    /**
     * Backwards compatibility for messagingSettings
     *
     * @var array
     */
    protected $oldStatuses = [
        'snailMail' => 'letter',
        'ilsDefined' => 'none'
    ];

    /**
     * SOAP Options
     *
     * @var array
     */
    protected $soapOptions = [
        'soap_version' => SOAP_1_1,
        'exceptions' => true,
        'trace' => false,
        'connection_timeout' => 60,
        'typemap' => [
            [
                'type_ns' => 'http://www.w3.org/2001/XMLSchema',
                'type_name' => 'anyType',
                'from_xml' => ['\AxiellWebServices', 'anyTypeToString'],
                'to_xml' => ['\AxiellWebServices', 'stringToAnyType']
            ]
        ]
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
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

        if (isset($this->config['Catalog']['arena_member'])) {
            $this->arenaMember = $this->config['Catalog']['arena_member'];
        } else {
            throw new ILSException('arena_member configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['catalogue_wsdl'])) {
            $this->catalogue_wsdl
                = $this->getWsdlPath($this->config['Catalog']['catalogue_wsdl']);
        } else {
            throw new ILSException('catalogue_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['patron_wsdl'])) {
            $this->patron_wsdl
                = $this->getWsdlPath($this->config['Catalog']['patron_wsdl']);
        } else {
            throw new ILSException('patron_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['loans_wsdl'])) {
            $this->loans_wsdl
                = $this->getWsdlPath($this->config['Catalog']['loans_wsdl']);
        } else {
            throw new ILSException('loans_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['loansaurora_wsdl'])) {
            $this->loansaurora_wsdl
                = $this->getWsdlPath($this->config['Catalog']['loansaurora_wsdl']);
        }

        if (isset($this->config['Catalog']['catalogueaurora_wsdl'])) {
            $this->catalogueaurora_wsdl
                = $this->getWsdlPath(
                    $this->config['Catalog']['catalogueaurora_wsdl']
                );
        }

        if (isset($this->config['Catalog']['payments_wsdl'])) {
            $this->payments_wsdl
                = $this->getWsdlPath($this->config['Catalog']['payments_wsdl']);
        } else {
            throw new ILSException('payments_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['reservations_wsdl'])) {
            $this->reservations_wsdl
                = $this->getWsdlPath($this->config['Catalog']['reservations_wsdl']);
        } else {
            throw new
                ILSException('reservations_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['patronaurora_wsdl'])) {
            $this->patronaurora_wsdl
                = $this->getWsdlPath($this->config['Catalog']['patronaurora_wsdl']);
        }

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation'] : false;
        if ($this->defaultPickUpLocation == '0') {
            $this->defaultPickUpLocation = false;
        }

        $this->excludePickUpLocations
            = isset($this->config['Holds']['excludePickUpLocations'])
            ? explode(':', $this->config['Holds']['excludePickUpLocations']) : [];

        $this->defaultRequestGroup
            = isset($this->config['Holds']['defaultRequestGroup'])
            ? $this->config['Holds']['defaultRequestGroup'] : false;
        if ($this->defaultRequestGroup === 'user-selected') {
            $this->defaultRequestGroup = false;
        }

        $this->regionalHold = isset($this->config['Holds']['regionalHold'])
          ? $this->config['Holds']['regionalHold'] : false;

        $this->requestGroupsEnabled
            = isset($this->config['Holds']['extraHoldFields'])
        && in_array(
            'requestGroup',
            explode(':', $this->config['Holds']['extraHoldFields'])
        );

        $this->singleReservationQueue
            = isset($this->config['Holds']['singleReservationQueue'])
            ? $this->config['Holds']['singleReservationQueue'] : false;

        if (isset($this->config['Debug']['durationLogPrefix'])) {
            $this->durationLogPrefix = $this->config['Debug']['durationLogPrefix'];
        }

        if (isset($this->config['Debug']['verbose'])) {
            $this->verbose = $this->config['Debug']['verbose'];
            $this->soapOptions['trace'] = true;
        }

        if (isset($this->config['Debug']['log'])) {
            $this->logFile = $this->config['Debug']['log'];
        }
        $this->holdingsOrganisationOrder
            = isset($this->config['Holdings']['holdingsOrganisationOrder'])
            ? explode(':', $this->config['Holdings']['holdingsOrganisationOrder'])
            : [];
        $this->holdingsOrganisationOrder
            = array_flip($this->holdingsOrganisationOrder);
        $this->holdingsBranchOrder
            = isset($this->config['Holdings']['holdingsBranchOrder'])
            ? explode(':', $this->config['Holdings']['holdingsBranchOrder'])
            : [];
        $this->holdingsBranchOrder = array_flip($this->holdingsBranchOrder);

        $this->messagingBlackLists['pickUpNotice']
            = isset($this->config['messagingBlackLists']['pickUpNotice'])
            ? explode(':', $this->config['messagingBlackLists']['pickUpNotice'])
            : [];

        $this->messagingBlackLists['overdueNotice']
            = isset($this->config['messagingBlackLists']['overdueNotice'])
            ? explode(':', $this->config['messagingBlackLists']['overdueNotice'])
            : [];

        $this->messagingBlackLists['dueDateAlert']
            = isset($this->config['messagingBlackLists']['dueDateAlert'])
            ? explode(':', $this->config['messagingBlackLists']['dueDateAlert'])
            : [];
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
        $username = $patron['cat_username'];
        $cacheKey = $this->getPatronCacheKey($username);
        $profile = $this->getCachedData($cacheKey);

        if (null === $profile) {
            $this->patronLogin($username, $patron['cat_password']);
            $profile = $this->getCachedData($cacheKey);
        }

        return $profile;
    }

    /**
     * Get Pickup Locations
     *
     * This is responsible for retrieving pickup locations.
     *
     * @param array $user        The patron array from patronLogin
     * @param array $holdDetails Hold details
     *
     * @throws ILSException
     *
     * @return array Array of the patron's fines on success
     */
    public function getPickUpLocations($user, $holdDetails)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $id = !empty($holdDetails['item_id'])
            ? $holdDetails['item_id'] : $holdDetails['id'];

        $holdType = $this->getHoldType($holdDetails);

        $function = 'getReservationBranches';
        $functionResult = 'getReservationBranchesResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'country' => 'FI',
            'reservationEntities' => $id,
            'reservationType' => $holdType
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            ['getReservationBranchesParam' => $conf]
        );

        $locationsList = [];
        if (!isset($result->$functionResult->organisations->organisation)) {
            // If we didn't get any pickup locations for item_id, fall back to id
            // and try again... This seems to happen when there are only ordered
            // items in the branch
            if (!empty($holdDetails['item_id'])) {
                unset($holdDetails['item_id']);
                return $this->getPickUpLocations($user, $holdDetails);
            }
            return $locationsList;
        }
        $organisations
            =  $this->objectToArray(
                $result->$functionResult->organisations->organisation
            );

        foreach ($organisations as $organisation) {
            if (!isset($organisation->branches->branch)) {
                continue;
            }

            $organisationID = $organisation->id;

            // TODO: Make it configurable whether organisation names
            // should be included in the location name

            if (is_object($organisation->branches->branch)) {
                $locationID
                    = $organisationID . '.' . $organisation->branches->branch->id;
                if (in_array($locationID, $this->excludePickUpLocations)) {
                    continue;
                }

                $locationsList[] = [
                    'locationID' => $locationID,
                    'locationDisplay' => $organisation->branches->branch->name
                ];
            } else {
                foreach ($organisation->branches->branch as $branch) {
                    $locationID
                        = $organisationID . '.' . $branch->id;
                    if (in_array($locationID, $this->excludePickUpLocations)) {
                        continue;
                    }

                    $locationsList[] = [
                        'locationID' => $locationID,
                        'locationDisplay' => $branch->name
                    ];
                }
            }
        }

        // Sort pick up locations
        usort($locationsList, [$this, 'pickUpLocationsSortFunction']);

        return $locationsList;
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
     * @return string       The default pickup location for the patron.
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.
     * May be used to limit the request group options or may be ignored.
     *
     * @return string       The default request group for the patron.
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        return $this->defaultRequestGroup;
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
        if (!$this->requestGroupsEnabled) {
            return false;
        }
        $requestGroups = [
            [
                'id'   => 'normal',
                'name' => 'axiell_normal'
            ],
            [
                'id'   => 'regional',
                'name' => 'axiell_regional'
            ]
        ];
        return $requestGroups;
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
        if (isset($holdDetails['item_id']) && $holdDetails['item_id']) {
            $entityId = $holdDetails['item_id'];
            $reservationSource = 'holdings';
        } else {
            $entityId = $holdDetails['id'];
            $reservationSource = 'catalogueRecordDetail';
        }

        $username = $holdDetails['patron']['cat_username'];
        $password = $holdDetails['patron']['cat_password'];

        try {
            $validFromDate = date('Y-m-d');

            $validToDate = isset($holdDetails['requiredBy'])
                ? $this->dateFormat->convertFromDisplayDate(
                    'Y-m-d', $holdDetails['requiredBy']
                )
                : date('Y-m-d', $this->getDefaultRequiredByDate());
        } catch (DateException $e) {
            // Hold Date is invalid
            throw new ILSException('hold_date_invalid');
        }

        $pickUpLocation = $holdDetails['pickUpLocation'];
        list($organisation, $branch) = explode('.', $pickUpLocation, 2);

        $function = 'addReservation';
        $functionResult = 'addReservationResult';
        $functionParam = 'addReservationParam';

        $holdType = $this->getHoldType($holdDetails);

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'user'         => $username,
            'password'     => $password,
            'language'     => 'en',
            'reservationEntities' => $entityId,
            'reservationSource' => $reservationSource,
            'reservationType' => $holdType,
            'organisationId' => $organisation,
            'pickUpBranchId' => $branch,
            'validFromDate' => $validFromDate,
            'validToDate' => $validToDate
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            [$functionParam => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message
                = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return [
               'success' => false,
               'sysMessage' => $message
            ];
        }

        return [
            'success' => true
        ];
    }

    /**
     * Cancel Holds
     *
     * This is responsible for canceling holds.
     *
     * @param array $cancelDetails The request details
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

        $function = 'removeReservation';
        $functionResult = 'removeReservationResult';

        foreach ($cancelDetails['details'] as $details) {
            $result = $this->doSOAPRequest(
                $this->reservations_wsdl, $function, $functionResult, $username,
                ['removeReservationsParam' =>
                   ['arenaMember' => $this->arenaMember,
                    'user' => $username, 'password' => $password,
                     'language' => 'en', 'id' => $details]
                ]
            );

            $statusAWS = $result->$functionResult->status;

            if ($statusAWS->type != 'ok') {
                $message
                    = $this->handleError($function, $statusAWS, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
                $results[$details] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $statusAWS->message ?? $statusAWS->type
                ];
            } else {
                $results[$details] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                    'sysMessage' => ''
                ];
            }

            ++$succeeded;
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];
        $pickupLocationId = $holdDetails['pickupLocationId'];

        try {
            $validFromDate = date('Y-m-d');

            $validToDate = isset($holdDetails['requiredBy'])
                ? $this->dateFormat->convertFromDisplayDate(
                    'Y-m-d', $holdDetails['requiredBy']
                )
                : date('Y-m-d', $this->getDefaultRequiredByDate());
        } catch (DateException $e) {
            // Hold Date is invalid
            throw new ILSException('hold_date_invalid');
        }

        $requestId = $holdDetails['requestId'];
        list($organisation, $branch) = explode('.', $pickupLocationId, 2);

        $function = 'changeReservation';
        $functionResult = 'changeReservationResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => 'en',
            'id' => $requestId,
            'pickUpBranchId' => $branch,
            'validFromDate' => $validFromDate,
            'validToDate' => $validToDate
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            ['changeReservationsParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return [
                'success' => false,
                'sysMessage' => $message
            ];
        }

        return [
            'success' => true
        ];
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
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        $items = [];
        foreach ($idList as $id) {
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
        $function = 'GetHoldings';
        $functionResult = 'GetHoldingResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'id' => $id,
            'language' => $this->getLanguage()
        ];

        $response = $this->doSOAPRequest(
            $this->catalogue_wsdl, $function, $functionResult, $id,
            ['GetHoldingsRequest' => $conf]
        );

        $statusAWS = $response->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $id);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_holdings_message');
            }
            return [];
        }

        if (!isset($response->$functionResult->catalogueRecord->compositeHolding)) {
            return [];
        }

        $holdings = $this->objectToArray(
            $response->$functionResult->catalogueRecord->compositeHolding
        );

        if (isset($holdings[0]->type) && $holdings[0]->type == 'year') {
            $result = [];
            foreach ($holdings as $holding) {
                $year = $holding->value;
                $holdingsEditions = $this->objectToArray($holding->compositeHolding);
                foreach ($holdingsEditions as $holdingsEdition) {
                    $edition = $holdingsEdition->value;
                    $holdingsOrganisations
                        = $this->objectToArray($holdingsEdition->compositeHolding);
                    $journalInfo = [
                        'year' => $year,
                        'edition' => $edition
                    ];

                    $result = array_merge(
                        $result,
                        $this->parseHoldings(
                            $holdingsOrganisations, $id, $journalInfo
                        )
                    );
                }
            }
        } else {
            $result = $this->parseHoldings($holdings, $id, '', '');
        }

        if (!empty($result)) {
            usort($result, [$this, 'holdingsSortFunction']);

            $summary = $this->getHoldingsSummary($result);
            $result[] = $summary;
        }

        return $result;
    }

    /**
     * This is responsible for iterating the organisation holdings
     *
     * @param array  $organisationHoldings Organisation holdings
     * @param string $id                   The record id to retrieve the holdings
     * @param array  $journalInfo          Jornal information
     *
     * @return array
     */
    protected function parseHoldings($organisationHoldings, $id, $journalInfo = null)
    {
        if ($organisationHoldings[0]->status == 'noHolding') {
            return [];
        }
        if ($organisationHoldings[0]->type != 'organisation') {
            return [];
        }

        $result = [];
        foreach ($organisationHoldings as $organisation) {
            $organisationName = $group = $organisation->value;
            $organisationId = $organisation->id;

            $holdingsBranch = $this->objectToArray($organisation->compositeHolding);
            if ($holdingsBranch[0]->type == 'branch') {
                foreach ($holdingsBranch as $branch) {
                    $branchName = $branch->value;
                    $branchId = $branch->id;
                    $reservableId = isset($branch->reservable)
                        ? $branch->reservable : '';
                    $holdable = $branch->reservationButtonStatus == 'reservationOk';
                    $departments = $this->objectToArray($branch->holdings->holding);

                    foreach ($departments as $department) {
                        // Get holding data
                        $dueDate = isset($department->firstLoanDueDate)
                            ? $this->formatDate($department->firstLoanDueDate) : '';
                        $departmentName = $department->department;
                        $locationName = isset($department->location)
                            ? $department->location : '';

                        if (!empty($locationName)) {
                            $departmentName = "{$departmentName}, $locationName";
                        }

                        $nofAvailableForLoan
                            = isset($department->nofAvailableForLoan)
                            ? $department->nofAvailableForLoan : 0;
                        $nofTotal = isset($department->nofTotal)
                            ? $department->nofTotal : 0;
                        $nofOrdered = isset($department->nofOrdered)
                            ? $department->nofOrdered : 0;

                        // Group journals by issue number
                        if ($journalInfo) {
                            $year = $journalInfo['year'] ?? '';
                            $edition = $journalInfo['edition'] ?? '';
                            if ($year !== '' && $edition !== '') {
                                if (strncmp($year, $edition, strlen($year)) == 0) {
                                    $group = $edition;
                                } else {
                                    $group = "$year, $edition";
                                }
                            } else {
                                $group = $year . $edition;
                            }
                            $journalInfo['location'] = $organisationName;
                        }

                        // Status & availability
                        $status = $department->status;
                        $available
                            = $status == 'availableForLoan'
                                || $status == 'returnedToday';

                        // Special status: On reference desk
                        if ($status == 'nonAvailableForLoan'
                            && isset($department->nofReference)
                            && $department->nofReference != 0
                        ) {
                            $status = 'onRefDesk';
                            $available = true;
                        }

                        // Status table
                        $statusArray = [
                           'availableForLoan' => 'Available',
                           'fetchnoteSent' => 'On Hold',
                           'onLoan' => 'Charged',
                           //'nonAvailableForLoan' => 'Not Available',
                           'nonAvailableForLoan' => 'On Reference Desk',
                           'onRefDesk' => 'On Reference Desk',
                           'overdueLoan' => 'overdueLoan',
                           'ordered' => 'Ordered',
                           'returnedToday' => 'Returned today',
                           'inTransfer' => 'In Transit'
                        ];

                        // Convert status text
                        if (isset($statusArray[$status])) {
                            $status = $statusArray[$status];
                        } else {
                            $this->debug(
                                'Unhandled status ' +
                                $department->status +
                                " for '$this->arenaMember'.'$id'"
                            );
                        }

                        $holdable
                            = $branch->reservationButtonStatus == 'reservationOk';
                        $requests = 0;
                        if (!$this->singleReservationQueue
                            && isset($branch->nofReservations)
                        ) {
                            $requests = $branch->nofReservations;
                        }
                        $availabilityInfo = [
                            'available' => $nofAvailableForLoan,
                            'displayText' => $status,
                            'reservations' => isset($branch->nofReservations)
                                ? $branch->nofReservations : 0,
                            'ordered' => $nofOrdered,
                            'total' => $nofTotal,
                        ];
                        $callnumber = isset($department->shelfMark)
                            ? ($department->shelfMark) : '';

                        $holding = [
                            'id' => $id,
                            'barcode' => $id,
                            'item_id' => $reservableId,
                            'holdings_id' => $group,
                            'availability' => $available,
                            'availabilityInfo' => $availabilityInfo,
                            'status' => $status,
                            'location' => $group,
                            'organisation_id' => $organisationId,
                            'branch' => $branchName,
                            'branch_id' => $branchId,
                            'department' => $departmentName,
                            'duedate' => $dueDate,
                            'addLink' => $journalInfo,
                            'callnumber' => $callnumber,
                            'is_holdable' => $holdable,
                            'collapsed' => true,
                            'requests_placed' => $requests,
                            'reserve' => null
                        ];
                        if ($journalInfo) {
                            $holding['journalInfo'] = $journalInfo;
                        }
                        $result[] = $holding;
                    }
                }
            }
        }

        return $result;
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
        $journal = isset($holdings[0]['journalInfo']);
        $availableTotal = $itemsTotal = $orderedTotal = $reservationsTotal = 0;
        $locations = [];
        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (isset($item['availabilityInfo']['total'])) {
                $itemsTotal += $item['availabilityInfo']['total'];
            } else {
                $itemsTotal++;
            }
            if (isset($item['availabilityInfo']['ordered'])) {
                $orderedTotal += $item['availabilityInfo']['ordered'];
            }
            if ($this->singleReservationQueue
                && isset($item['availabilityInfo']['reservations'])
            ) {
                $reservationsTotal
                    = max(
                        $reservationsTotal, $item['availabilityInfo']['reservations']
                    );
            }
            $locations[$item['location']] = true;
            if (!$journal && $item['is_holdable']) {
                $holdable = true;
            }
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
           'location' => '__HOLDINGSSUMMARYLOCATION__'
        ];
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
        $cacheKey = $this->getPatronCacheKey($username);
        $function = 'getPatronInformation';
        $functionResult = 'patronInformationResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()
        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            ['patronInformationParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_login_message');
            }
            return null;
        }

        $info = $result->$functionResult->patronInformation;

        $names = explode(' ', $info->patronName);
        $lastname = array_pop($names);
        $firstname = implode(' ', $names);

        $loanHistoryEnabled = $info->isLoanHistoryEnabled ?? false;

        /**
         * Request an authentication id used in certain requests e.g:
         * GetTransactionHistory
         */
        $patronId = $this->authenticatePatron($username, $password);

        $user = [
            'id' => $info->backendPatronId,
            'cat_username' => $username,
            'cat_password' => $password,
            'lastname' => $lastname,
            'firstname' => $firstname,
            'major' => null,
            'college' => null,
            'patronId' => $patronId
        ];

        $userCached = [
            'id' => $info->backendPatronId,
            'cat_username' => $username,
            'cat_password' => $password,
            'lastname' => $lastname,
            'firstname' => $firstname,
            'email' => '',
            'emailId' => '',
            'address1' => '',
            'addressId' => '',
            'zip' => '',
            'city' => '',
            'country' => '',
            'phone' => '',
            'phoneId' => '',
            'phoneLocalCode' => '',
            'phoneAreaCode' => '',
            'major' => null,
            'college' => null,
            'patronId' => $patronId,
            'loan_history' => (bool)$loanHistoryEnabled
        ];

        if (!empty($info->emailAddresses->emailAddress)) {
            $emailAddresses
                =  $this->objectToArray($info->emailAddresses->emailAddress);

            foreach ($emailAddresses as $emailAddress) {
                if ($emailAddress->isActive == 'yes') {
                    $userCached['email'] = $emailAddress->address ?? '';
                    $userCached['emailId'] = $emailAddress->id ?? '';
                }
            }
        }

        if (isset($info->addresses->address)) {
            $addresses = $this->objectToArray($info->addresses->address);
            foreach ($addresses as $address) {
                if ($address->isActive == 'yes') {
                    $userCached['address1'] = $address->streetAddress ?? '';
                    $userCached['zip'] = $address->zipCode ?? '';
                    $userCached['city'] = $address->city ?? '';
                    $userCached['country'] = $address->country ?? '';
                    $userCached['addressId'] = $address->id ?? '';
                }
            }
        }

        if (isset($info->phoneNumbers->phoneNumber)) {
            $phoneNumbers = $this->objectToArray($info->phoneNumbers->phoneNumber);
            foreach ($phoneNumbers as $phoneNumber) {
                if ($phoneNumber->sms->useForSms == 'yes') {
                    $userCached['phone'] = $phoneNumber->areaCode ?? '';
                    $userCached['phoneAreaCode'] = $userCached['phone'];
                    if (isset($phoneNumber->localCode)) {
                        $userCached['phone'] .= $phoneNumber->localCode;
                        $userCached['phoneLocalCode'] = $phoneNumber->localCode;
                    }
                    if (isset($phoneNumber->id)) {
                        $userCached['phoneId'] = $phoneNumber->id;
                    }
                }
            }
        }

        $serviceSendMethod
            = $this->config['updateMessagingSettings']['method'] ?? 'none';
        $infoServices = $info->messageServices->messageService ?? [];

        switch ($serviceSendMethod) {
        case 'database':
            $userCached['messagingServices']
                = $this->parseEmailMessagingSettings(
                    $info->messageServices->messageService ?? []
                );
            break;
        case 'driver':
            $userCached['messagingServices']
                = $this->parseDriverMessagingSettings(
                    $info->messageServices->messageService ?? [],
                    $user
                );
            break;
        default:
            $userCached['messagingServices'] = [];
            break;
        }

        $this->putCachedData($cacheKey, $userCached);

        return $user;
    }

    /**
     * Function to create an array for using email to change messaging services
     *
     * @param object $infoServices to parse
     *
     * @return array parsed services
     */
    public function parseEmailMessagingSettings($infoServices)
    {
        $validServices = [
            'pickUpNotice'  => [
                'letter', 'email', 'sms', 'none'
            ],
            'overdueNotice' => [
                'letter', 'email', 'sms', 'none'
            ],
            'dueDateAlert' => [
                'email', 'none'
            ]
         ];

        $services = [];
        foreach ($validServices as $service => $validMethods) {
            $typeLabel = 'dueDateAlert' === $service
                ? $this->translate(
                    "messaging_settings_type_dueDateAlertEmail"
                )
                : $this->translate("messaging_settings_type_$service");
            $data = [
                'active' => false,
                'type' => $typeLabel,
                'sendMethods' => []
            ];

            foreach ($validMethods as $methodKey) {
                if (in_array(
                    $this->mapOldStatusToCode($methodKey),
                    $this->messagingBlackLists[$service]
                )
                ) {
                    continue;
                }

                $data['sendMethods'] += [
                    "$methodKey" => [
                        'active' => false,
                        'type' => $methodKey
                    ]
                ];
            }
            $services[$service] = $data;
        }

        if (!empty($infoServices)) {
            foreach ($infoServices as $service) {
                $methods = [];
                $serviceType = $service->serviceType;
                $numOfDays = isset($service->nofDays->value)
                    ? $service->nofDays->value : 'none';
                $active = $service->isActive === 'yes';

                $sendMethods = $this->objectToArray($service->sendMethods);

                foreach ($sendMethods as $method) {
                    $type = isset($method->sendMethod->value)
                        ? $this->mapOldCodeToStatus($method->sendMethod->value)
                        : 'none';
                    if (!isset($services[$serviceType]['sendMethods'][$type])) {
                        continue;
                    }
                    $services[$serviceType]['sendMethods'][$type]['active']
                        = isset($method->sendMethod->isActive)
                            && $method->sendMethod->isActive === 'yes';
                }

                foreach ($services[$serviceType]['sendMethods'] as $key => &$data) {
                    $methodLabel
                        = $this->translate("messaging_settings_method_$key");

                    if ($numOfDays > 0 && $key == 'email') {
                        $methodLabel =  $this->translate(
                            $numOfDays == 1
                            ? 'messaging_settings_num_of_days'
                            : 'messaging_settings_num_of_days_plural',
                            ['%%days%%' => $numOfDays]
                        );
                    }

                    if (!$active) {
                        $methodLabel
                            =  $this->translate("messaging_settings_method_none");
                    }
                    $data['method'] = $methodLabel;
                }

                if (isset($services[$serviceType])) {
                    $services[$serviceType]['active'] = $active;
                    $services[$serviceType]['numOfDays'] = $numOfDays;
                }
            }
        }

        return $services;
    }

    /**
     * Function to create an array for using driver to change messaging services
     *
     * @param object $infoServices to parse
     * @param array  $user         data
     *
     * @return array parsed services
     */
    public function parseDriverMessagingSettings($infoServices, $user)
    {
        $services = [];
        $messagingSettings = [];

        foreach ($infoServices as $service => $options) {
            $current = [
                'transport_type' =>
                    (string)$options->sendMethods->sendMethod->value,
            ];
            if (isset($options->nofDays)) {
                $current['nofDays'] = $options->nofDays->value;
            }
            $services[$options->serviceType] = $current;
        }

        // We need to find proper options for current service
        foreach ($this->getMessageServices($user) as $service => $methods) {
            $settings = [
                'type' => $service,
                'settings' => [
                    'transport_types' => [
                        'type' => 'select',
                        'options' => [],
                        'value' => $this->mapCodeToStatus(
                            $services[$service]['transport_type']
                        )
                    ],
                ]
            ];
            if ($service === 'dueDateAlert') {
                $options = [];
                $hasActive = false;
                for ($i = 1; $i <= 5; $i++) {
                    if ($i === $services[$service]['nofDays']) {
                        $hasActive = true;
                    }
                    $options[$i] = [
                        'name' => $this->translate(
                            1 === $i ? 'messaging_settings_num_of_days'
                            : 'messaging_settings_num_of_days_plural',
                            ['%%days%%' => $i]
                        ),
                        'active' => $i === $services[$service]['nofDays']
                    ];
                }
                if (!$hasActive) {
                    $options[1]['active'] = true;
                }
                $settings['settings']['days_in_advance'] = [
                    'type' => 'select',
                    'value' => $services[$service]['nofDays'],
                    'options' => $options,
                    'readonly' => false
                ];
            }
            foreach ($methods as $methodId => $method) {
                $coded = $this->mapCodeToStatus($method);
                $settings['settings']['transport_types']['options'][$coded] = [
                        'active' => $services[$service]['transport_type']
                            === $method
                    ];
            }
            $messagingSettings[$service] = $settings;
        }

        return $messagingSettings;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
            if ('onlinePayment' === $function) {
                $functionConfig['exactBalanceRequired'] = true;
            }
        } else {
            $functionConfig = false;
        }
        if ($function === 'getTitleList') {
            if (isset($this->config['Catalog']['catalogueaurora_wsdl'])) {
                $functionConfig = [
                    'enabled' => true
                ];
            }
        }
        return $functionConfig;
    }

    /**
     * Function to fetch dynamic lists from Aurora
     *
     * @param array $params To fetch
     *
     * @throws ILSException
     * @return array
     */
    public function getTitleList($params)
    {
        $conf = [
            'arenaMember' => $this->arenaMember,
            'pageSize' => $params['pageSize'] ?? 20,
            'page' => isset($params['page']) ? $params['page'] - 1 : 0,
            'query' => isset($params['query'])
                ? $this->getDynamicMappedValue($params['query'])
                : 'mostloaned'
        ];

        $function = 'Search';
        $functionResult = 'searchResult';

        $result = $this->doSOAPRequest(
            $this->catalogueaurora_wsdl, $function, $functionResult, '',
            ['searchRequest' => $conf]
        );
        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, '');
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $records = $this->objectToArray(
            $result->$functionResult->catalogueRecords->catalogueRecord ?? []
        );

        $formatted = [
            'records' => [],
            'count' => $result->$functionResult->nofRecordsTotal,
            'countPage' => $result->$functionResult->nofRecordsPage,
            'pages' => $result->$functionResult->nofPages
        ];
        // Lets get a pretty list of results
        foreach ($records as $key => $obj) {
            $record = [
                'id' => $obj->id ?? '0',
                'title' => $obj->title ?? '',
                'mediaClass' => $obj->mediaClass ?? '',
                'icon' => $obj->mediaClassIcon ?? '',
                'author' => $obj->author ?? '',
                'year' => $obj->publicationYear ?? ''
            ];
            $formatted['records'][] = $record;
        }

        return $formatted;
    }

    /**
     * Checks if key has a value in mapped list and returns it
     *
     * @param string $key to map
     *
     * @return string found value or key if does not exist
     */
    public function getDynamicMappedValue($key)
    {
        return $this->titleListMapping[$key] ?? $key;
    }

    /**
     * Checks if value has a key in mapped list and returns it
     *
     * @param string $value to map
     *
     * @return string found key or value if does not exist
     */
    public function getDynamicMappedKey($value)
    {
        $found = array_search($value, $this->titleListMapping);
        return $found ?: $value;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'GetLoans';
        $functionResult = 'loansResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()
        ];

        $result = $this->doSOAPRequest(
            $this->loans_wsdl, $function, $functionResult, $username,
            ['loansRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $transList = [];
        if (!isset($result->$functionResult->loans->loan)) {
            return $transList;
        }
        $loans =  $this->objectToArray($result->$functionResult->loans->loan);

        foreach ($loans as $loan) {
            $title = $loan->catalogueRecord->title;
            if (!empty($loan->note)) {
                $title .= ' (' . $loan->note . ')';
            }

            $message = isset($loan->loanStatus->status)
                ? $this->mapStatus($loan->loanStatus->status, $function) : '';

            if (!isset($this->config['Loans']['renewalLimit'])
                || (isset($loan->loanStatus->status)
                && $this->isPermanentRenewalBlock($loan->loanStatus->status))
            ) {
                $renewLimit = null;
                $renewals = null;
            } else {
                $renewLimit = $this->config['Loans']['renewalLimit'];
                $renewals = max(
                    [
                        0,
                        $renewLimit - $loan->remainingRenewals
                    ]
                );
            }

            $trans = [
                'id' => $loan->catalogueRecord->id,
                'item_id' => $loan->id,
                'title' => $title,
                'duedate' => $loan->loanDueDate,
                'renewable' => (string)$loan->loanStatus->isRenewable == 'yes',
                'message' => $message,
                'renewalCount' => $renewals,
                'renewalLimit' => $renewLimit,
            ];

            $transList[] = $trans;
        }

        // Sort the Loans
        $date = [];
        foreach ($transList as $key => $row) {
            $date[$key] = $row['duedate'];
        }
        array_multisort($date, SORT_ASC, $transList);

        // Convert Axiell format to display date format
        foreach ($transList as &$row) {
            $row['duedate'] = $this->formatDate($row['duedate']);
        }

        return $transList;
    }

    /**
     * Get Patron Transaction History
     *
     * This is responsible for retrieving all historical transactions
     * (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactionHistory($patron, $params)
    {
        $sort = explode(
            ' ',
            !empty($params['sort'])
                ? $params['sort'] : 'CHECK_OUT_DATE DESCENDING', 2
        );

        $sortField = $sort[0] ?? 'CHECK_OUT_DATE';
        $sortKey = $sort[1] ?? 'DESCENDING';

        $username = $patron['cat_username'];

        $function = 'GetLoanHistory';
        $functionResult = 'loanHistoryResponse';
        $pageSize = $params['limit'] ?? 50;
        $conf = [
            'arenaMember' => $this->arenaMember,
            'language' => $this->getLanguage(),
            'patronId' => $patron['patronId'],
            'start' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'count' => $pageSize,
            'sortField' => $sortField,
            'sortDirection' => $sortKey
        ];

        $result = $this->doSOAPRequest(
            $this->loansaurora_wsdl, $function, $functionResult, $username,
            ['loanHistoryRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $formatted = [];
        $transList = [];
        $transactions = $this->objectToArray(
            $result->loanHistoryResponse->loanHistoryItems->loanHistoryItem ?? []
        );
        foreach ($transactions as $transaction => $record) {
            $obj = $record->catalogueRecord;
            $trans = [
                'id' => $obj->id,
                'title' => $obj->title,
                'checkoutdate' => $this->formatDate($record->checkOutDate),
                'returndate' => isset($record->checkInDate)
                    ? $this->formatDate($record->checkInDate) : ''
            ];
            $transList[] = $trans;
        }

        $formatted['success'] = $statusAWS->type === 'ok';
        $formatted['transactions'] = $transList;
        $formatted['count'] = $result->loanHistoryResponse
            ->loanHistoryItems->totalCount;

        return $formatted;
    }

    /**
     * Returns an id which is used to authenticate current session in SOAP API
     *
     * @param string $username patron username
     * @param string $password patron password
     *
     * @return mixed id as string if succesfull, null if failed
     */
    public function authenticatePatron($username, $password)
    {
        $function = 'authenticatePatron';
        $functionResult = 'authenticatePatronResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password
        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            ['authenticatePatronParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;
        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return null;
        }

        return $result->authenticatePatronResult->patronId;
    }

    /**
     * Update patron messaging settings
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @return array        Status of request and if it was successful.
     */
    public function updateMessagingSettings($patron, $params)
    {
        $result = [
            'success' => true,
            'status' => 'request_change_done'
        ];

        foreach ($params as $service => $settings) {
            $transport = $settings['settings']['transport_types'] ?? '';
            if (empty($transport)) {
                continue;
            }
            $coded = $this->mapStatusToCode($transport['value']);
            $current = [
                'serviceType' => $service,
                'sendMethod' => $coded
            ];
            if ($coded === 'ilsDefined') {
                $status = $this->removeMessageService($patron, $current);
            } else {
                if (isset($settings['settings']['days_in_advance'])) {
                    $current['nofDays']
                        = $settings['settings']['days_in_advance']['value'];
                }
                $status = $this->changeMessageService($patron, $current);
            }
            if (!$status['success']) {
                $result = $status;
            }
        }
        return $result;
    }

    /**
     * Get message services available
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of used message services and methods.
     */
    protected function getMessageServices($patron)
    {
        if (empty($this->patronaurora_wsdl)) {
            return [];
        }

        $function = 'getMessageServices';
        $functionResult = 'messageServicesResponse';

        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $conf = [
            'arenaMember' => $this->arenaMember,
            'language' => $this->getLanguage(),
            'user' => $username,
            'password' => $password,
        ];

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl, $function, $functionResult, $username,
            ['messageServicesRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $resultArray = $this->objectToArray(
            $result->$functionResult->messageServices->messageService
        );
        $returnable = [];
        foreach ($resultArray as $service => $sendMethods) {
            $current = [];
            $currentMethods = $sendMethods->sendMethods->sendMethod;
            $serviceType = $sendMethods->serviceType;
            foreach ($currentMethods as $key => $value) {
                $method = is_object($value) ? $value->value : $value;
                if (in_array($method, $this->messagingBlackLists[$serviceType])) {
                    continue;
                }
                $current[] = $method;
            }
            $returnable[$serviceType] = $current;
        }

        return $returnable;
    }

    /**
     * Function to change message service in SOAP API
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Status of request and if it was successful.
     */
    protected function changeMessageService($patron, $params)
    {
        $function = 'changeMessageService';
        $functionResult = 'changeMessageServiceResponse';

        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $conf = [
            'arenaMember' => $this->arenaMember,
            'language' => $this->getLanguage(),
            'user' => $username,
            'password' => $password,
            'sendMethod' => [
                'value' => $params['sendMethod']
            ],
            'serviceType' => $params['serviceType']
        ];

        if ($params['serviceType'] === 'dueDateAlert') {
            $conf['nofDays'] = [
                'value' => $params['nofDays']
            ];
        }

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl, $function, $functionResult, $username,
            ['changeMessageServiceRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [
                'success' => false,
                'status' => $statusAWS
            ];
        }

        return ['success' => true];
    }

    /**
     * Function to remove message service from use in SOAP API
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Status of request and if it was successful.
     */
    protected function removeMessageService($patron, $params)
    {
        $function = 'removeMessageService';
        $functionResult = 'removeMessageServiceResponse';

        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $conf = [
            'arenaMember' => $this->arenaMember,
            'language' => $this->getLanguage(),
            'user' => $username,
            'password' => $password,
            'serviceType' => $params['serviceType']
        ];

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl, $function, $functionResult, $username,
            ['removeMessageServiceRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [
                'success' => false,
                'status' => $statusAWS
            ];
        }

        return ['success' => true];
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $paymentConfig = $this->config['onlinePayment'] ?? [];
        $blockedTypes = $paymentConfig['nonPayable'] ?? [];

        $function = 'GetDebts';
        $functionResult = 'debtsResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'fromDate' => '1699-12-31',
            'toDate' => time()
        ];

        $result = $this->doSOAPRequest(
            $this->payments_wsdl, $function, $functionResult, $username,
            ['debtsRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }
        if (!isset($result->$functionResult->debts->debt)) {
            return [];
        }

        $finesList = [];
        $debts = $this->objectToArray($result->$functionResult->debts->debt);
        foreach ($debts as $debt) {
            // Have to use debtAmountFormatted, because debtAmount shows negative
            // values as positive. Try to extract the numeric part from the formatted
            // amount.
            if (preg_match('/([\d\.\,-]+)/', $debt->debtAmountFormatted, $matches)) {
                $amount = str_replace(',', '.', $matches[1]) * 100;
            } else {
                $amount = str_replace(',', '.', $debt->debtAmountFormatted) * 100;
            }
            $description = $debt->debtType . ' - ' . $debt->debtNote;
            $payable = true;
            foreach ($blockedTypes as $blockedType) {
                if (strncmp($blockedType, '/', 1) === 0
                    && substr_compare($blockedType, '/', -1) === 0
                ) {
                    if (preg_match($blockedType, $description)) {
                        $payable = false;
                        break;
                    }
                } else {
                    if ($blockedType === $description) {
                        $payable = false;
                        break;
                    }
                }
            }
            $fine = [
                'debt_id' => $debt->id,
                'amount' => $amount,
                'checkout' => '',
                'fine' => $description,
                'balance' => $amount,
                'createdate' => $debt->debtDate,
                'payableOnline' => $payable,
                'organization' => $debt->organisation ?? ''
            ];
            if (!empty($debt->organisation)) {
                $debt->organisation = $debt->organisation;
            }
            $finesList[] = $fine;
        }

        // Sort the Fines
        $date = [];
        foreach ($finesList as $key => $row) {
            $date[$key] = $row['createdate'];
        }

        array_multisort($date, SORT_DESC, $finesList);

        // Convert Axiell format to display date format
        foreach ($finesList as &$row) {
            $row['createdate'] = $this->formatDate($row['createdate']);
        }

        return $finesList;
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
            $amount = 0;
            foreach ($fines as $fine) {
                if ($fine['payableOnline']) {
                    $amount += $fine['balance'];
                }
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
        $function = 'AddPayment';
        $functionResult = 'addPaymentResponse';
        $functionParam = 'addPaymentRequest';

        $debtIds = [];
        $fines = $this->getMyFines($patron);
        foreach ($fines as $fine) {
            if ($fine['payableOnline']) {
                $debtIds[] = $fine['debt_id'];
            }
        }
        $request = [
            'arenaMember'       => $this->arenaMember,
            'orderId'           => (string)$transactionNumber,
            'transactionNumber' => (string)$transactionId,
            'paymentAmount'     => $amount,
            // Comma-separated list of IDs since the API has it single-valued
            'debts'             => ['id' => implode(',', $debtIds)]
        ];

        $result = $this->doSOAPRequest(
            $this->payments_wsdl, $function, $functionResult,
            $patron['cat_username'],
            [$functionParam => $request]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError(
                $function, $statusAWS, $patron['cat_username']
            );
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            // Dump full response since $statusAWS->message seems to be empty
            $error = "Failed to mark payment of $amount paid for patron"
                . " {$patron['id']}: " . print_r($result->$functionResult, true);

            $this->error($error);
            throw new ILSException($error);
        }

        // Clear patron cache
        $cacheKey = $this->getPatronCacheKey($patron['cat_username']);
        $this->putCachedData($cacheKey, null);

        return true;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'getReservations';
        $functionResult =  'getReservationsResult';

        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()

        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl, $function, $functionResult, $username,
            ['getReservationsParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $holdsList = [];
        if (!isset($result->$functionResult->reservations->reservation)) {
            return $holdsList;
        }
        $reservations
            = $this->objectToArray(
                $result->$functionResult->reservations->reservation
            );

        foreach ($reservations as $reservation) {
            $expireDate = $reservation->reservationStatus == 'fetchable'
                ? $reservation->pickUpExpireDate : $reservation->validToDate;
            $title = isset($reservation->catalogueRecord->title)
                ? $reservation->catalogueRecord->title : '';
            if (isset($reservation->note)) {
                $title .= ' (' . $reservation->note . ')';
            }

            $hold = [
                'id' => $reservation->catalogueRecord->id,
                'type' => $reservation->reservationStatus,
                'location' => $reservation->pickUpBranchId,
                'reqnum' =>
                   ($reservation->isDeletable == 'yes' &&
                       isset($reservation->id)) ? $reservation->id : '',
                'pickupnum' =>
                   isset($reservation->pickUpNo) ? $reservation->pickUpNo : '',
                'expire' => $this->formatDate($expireDate),
                'create' => $this->formatDate($reservation->validFromDate),
                'position' =>
                   isset($reservation->queueNo) ? $reservation->queueNo : '-',
                'available' => $reservation->reservationStatus == 'fetchable',
                'is_editable' => $reservation->isEditable == 'yes',
                'item_id' => '',
                'requestId' => $reservation->id,
                'volume' =>
                   isset($reservation->catalogueRecord->volume)
                       ? $reservation->catalogueRecord->volume : '',
                'publication_year' =>
                   isset($reservation->catalogueRecord->publicationYear)
                       ? $reservation->catalogueRecord->publicationYear : '',
                'requestGroup' =>
                   isset($reservation->reservationType)
                   && $this->requestGroupsEnabled
                   ? "axiell_$reservation->reservationType"
                   : '',
                'in_transit' => $reservation->reservationStatus == 'inTransit',
                'title' => $title
            ];
            $holdsList[] = $hold;
        }

        // Sort the holds
        $sortArray = [];
        foreach ($holdsList as $key => $row) {
            $sortArray[$key] = $row['title'];
        }
        array_multisort($sortArray, SORT_ASC, $holdsList);

        return $holdsList;
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
        $succeeded = 0;
        $results = ['blocks' => [], 'details' => []];

        $username = $renewDetails['patron']['cat_username'];
        $password = $renewDetails['patron']['cat_password'];

        $function = 'RenewLoans';
        $functionResult = 'renewLoansResponse';

        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => 'en',
            'loans' => $renewDetails['details']
        ];

        $result = $this->doSOAPRequest(
            $this->loans_wsdl, $function, $functionResult, $username,
            ['renewLoansRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message
                = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
        }

        $loans = isset($result->$functionResult->loans->loan)
            ? $this->objectToArray($result->$functionResult->loans->loan)
            : [];

        foreach ($loans as $loan) {
            $id = $loan->id;
            $status = $loan->loanStatus->status;
            $success = $status === 'isRenewedToday';

            $results['details'][$id] = [
                'success' => $success,
                'status' => $success ? 'Loan renewed' : 'Renewal failed',
                'sysMessage' => $this->mapStatus($status, $function),
                'item_id' => $id,
                'new_date' => $this->formatDate(
                    $loan->loanDueDate
                ),
                'new_time' => ''
            ];
        }
        return $results;
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $user = $this->getMyProfile($patron);

        $function = '';
        $functionResult = '';
        $functionParam = '';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'areaCode'     => '',
            'country'      => $user['phoneCountry'] ?? 'FI',
            'localCode'    => $phone,
            'useForSms'    => 'yes'
        ];

        if (!empty($user['phoneId'])) {
            $conf['id'] = $user['phoneId'];
            $function = 'changePhone';
            $functionResult = 'changePhoneNumberResult';
            $functionParam = 'changePhoneNumberParam';
        } else {
            $function = 'addPhone';
            $functionResult = 'addPhoneNumberResult';
            $functionParam = 'addPhoneNumberParam';
        }

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            [$functionParam => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
                'sys_message' => $statusAWS->message ?? $statusAWS->type
            ];
        }

        // Clear patron cache
        $cacheKey = $this->getPatronCacheKey($username);
        $this->putCachedData($cacheKey, null);

        return [
                'success' => true,
                'status' => 'Phone number changed',
                'sys_message' => ''
            ];
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
        $username = $patron['cat_username'];
        $function = 'changeLoanHistoryStatus';
        $functionResult = 'changeLoanHistoryStatusResult';

        $conf = [
            'arenaMember' => $this->arenaMember,
            'patronId' => $patron['patronId'],
            'isLoanHistoryEnabled' => $state
        ];

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl, $function, $functionResult, $username,
            ['changeLoanHistoryStatusParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException($message);
            }
            return [
                'success' => false,
                'status' => 'Changing the checkout history state failed'
            ];
        }

        return [
            'success' => true,
            'status' => 'request_change_done',
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
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $user = $this->getMyProfile($patron);

        $function = '';
        $functionResult = '';
        $functionParam = '';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'address'      => $email,
            'isActive'     => 'yes'
        ];

        if (!empty($user['emailId'])) {
            $conf['id'] = $user['emailId'];
            $function = 'changeEmail';
            $functionResult = 'changeEmailAddressResult';
            $functionParam = 'changeEmailAddressParam';
        } else {
            $function = 'addEmail';
            $functionResult = 'addEmailAddressResult';
            $functionParam = 'addEmailAddressParam';
        }

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            [$functionParam => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
                'sys_message' => $statusAWS->message ?? $statusAWS->type
            ];
        }

        // Clear patron cache
        $cacheKey = $this->getPatronCacheKey($username);
        $this->putCachedData($cacheKey, null);

        return [
                'success' => true,
                'status' => 'Email address changed',
                'sys_message' => '',
            ];
    }

    /**
     * Update patron contact information
     *
     * @param array  $patron  Patron array
     * @param String $details Associative array of patron contact information
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateAddress($patron, $details)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $user = $this->getMyProfile($patron);

        $function = '';
        $functionResult = '';

        $conf = [
            'arenaMember'   => $this->arenaMember,
            'language'      => $this->getLanguage(),
            'user'          => $username,
            'password'      => $password,
            'patronId'      => $patron['id'],
            'isActive'      => 'yes',
            'id'            => $user['addressId'],
            'streetAddress' => $details['address1'],
            'zipCode'       => $details['zip'],
            'city'          => $details['city']
        ];

        $function = 'changeAddress';
        $functionResult = 'changeAddressResponse';

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl, $function, $functionResult, $username,
            ['changeAddressRequest' => $conf]
        );

        $statusAWS = $result->$functionResult->status;
        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => $statusAWS->message ?? $statusAWS->type,
            ];
        }

        // Clear patron cache
        $cacheKey = $this->getPatronCacheKey($username);
        $this->putCachedData($cacheKey, null);

        if (isset($this->config['updateAddress']['needsApproval'])
            && !$this->config['updateAddress']['needsApproval']
        ) {
            $status = 'request_change_accepted';
        } else {
            $status = 'request_change_done';
        }
        return [
            'success' => true,
            'status' => $status,
            'sys_message' => ''
        ];
    }

    /**
     * Change pin code
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

        $function = 'changeCardPin';
        $functionResult = 'changeCardPinResult';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'cardNumber'   => $username,
            'cardPin'      => $cardDetails['oldPassword'],
            'newCardPin'   => $cardDetails['newPassword'],
        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl, $function, $functionResult, $username,
            ['changeCardPinParam' => $conf]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => $statusAWS->message ?? $statusAWS->type
            ];
        }

        // Clear patron cache
        $cacheKey = $this->getPatronCacheKey($username);
        $this->putCachedData($cacheKey, null);

        return  [
                'success' => true,
                'status' => 'change_password_ok',
            ];
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

    /**
     * Send a SOAP request
     *
     * @param string $wsdl           Name of the wsdl file
     * @param string $function       Name of the function
     * @param string $functionResult Name of the Result tag
     * @param string $id             Username or record id
     * @param array  $params         Parameters needed for the SOAP call
     *
     * @return object SOAP response
     */
    protected function doSOAPRequest($wsdl, $function, $functionResult, $id, $params)
    {
        $client = new ProxySoapClient($this->httpService, $wsdl, $this->soapOptions);

        $this->debug("$function Request for '$this->arenaMember'.'$id'");

        $startTime = microtime(true);
        try {
            $result = $client->$function($params);
        } catch (\SoapFault $e) {
            $this->error(
                "$function Request for '$this->arenaMember'.'$id' failed: "
                . $e->getMessage()
            );
            throw new ILSException($e->getMessage());
        }

        if ($this->durationLogPrefix) {
            file_put_contents(
                $this->durationLogPrefix . '_' . $function . '.log',
                date('Y-m-d H:i:s ') . round(microtime(true) - $startTime, 4) . "\n",
                FILE_APPEND
            );
        }

        if ($this->verbose) {
            $this->debug(
                "$function Request: " . $this->formatXML($client->__getLastRequest())
            );
            $this->debug(
                "$function Response: "
                . $this->formatXML($client->__getLastResponse())
            );
        }

        if (!isset($result->$functionResult->status)) {
            throw new ILSException('ils_offline_status');
        }

        return $result;
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
        // Support also the more complex date format of the old AWS version
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateString, $matches)) {
            return $this->dateFormat->convertToDisplayDate(
                '* M d G:i:s e Y', $dateString
            );
        }
        // remove timezone from Axiell obscure dateformat
        $date = $matches[1];
        return $this->dateFormat->convertToDisplayDate('Y-m-d', $date);
    }

    /**
     * Pretty-print an XML string
     *
     * @param string $xml XML string
     *
     * @return string Pretty XML string
     */
    protected function formatXML($xml)
    {
        if (!$xml) {
            return $xml;
        }
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        return $dom->saveXML();
    }

    /**
     * Get the language to be used in the interface
     *
     * @return string Language as string
     */
    protected function getLanguage()
    {
        global $interface;
        $language = $this->getTranslatorLocale();

        if (!in_array($language, ['en', 'sv', 'fi'])) {
            $language = 'en';
        }
        return $language;
    }

    /**
     * Handle system status error messages from Axiell Web Services
     *
     * @param string $function  Function name
     * @param object $statusAWS AWS status object
     * @param string $id        Holding Id or patron barcode
     *
     * @return string Error message as string
     */
    protected function handleError($function, $statusAWS, $id)
    {
        $message = $statusAWS->message ?? $statusAWS->type;
        $status =  [
            // Axiell system status error messages
            'BackendError'           => 'ils_connection_failed',
            'LocalServiceTimeout'    => 'ils_connection_failed',
            'DatabaseError'          => 'ils_connection_failed',
        ];

        if (isset($status[$message])) {
            $this->debug("$function Request failed for '$this->arenaMember'.'$id'");
            $this->debug("AWS error: '$message'");

            $this->error("$function Request failed for '$this->arenaMember'.'$id'");
            $this->error("AWS error: '$message'");
            return $status[$message];
        }
        return $this->mapStatus($message, $function);
    }

    /**
     * Sort function for sorting holdings locations
     * according to organisation and branch
     *
     * @param array $a Holding info
     * @param array $b Holding info
     *
     * @return int
     */
    protected function holdingsSortFunction($a, $b)
    {
        if (isset($a['journalInfo']) && isset($b['journalInfo'])) {
            return $this->journalHoldingsSortFunction($a, $b);
        } else {
            return $this->defaultHoldingsSortFunction($a, $b);
        }
    }

    /**
     * Sort function for sorting journal holdings.
     *
     * @param array $a Holding info
     * @param array $b Holding info
     *
     * @return int
     */
    protected function journalHoldingsSortFunction($a, $b)
    {
        $editionA = $a['journalInfo']['edition'];
        $editionB = $b['journalInfo']['edition'];
        if ($editionA == $editionB) {
            $a['location'] = $a['journalInfo']['location'];
            $b['location'] = $b['journalInfo']['location'];
            return $this->defaultHoldingsSortFunction($a, $b);
        } else {
            $a = $this->parseJournalIssue($editionA);
            $b = $this->parseJournalIssue($editionB);

            if (empty($a)) {
                return 1;
            }
            if (empty($b)) {
                return -1;
            }

            if ($a === $b) {
                return 0;
            }

            $cnt = min(count($a), count($b));
            $a = array_slice($a, 0, $cnt);
            $b = array_slice($b, 0, $cnt);

            $f = function ($str) {
                $parts = explode('-', $str);
                return reset($parts);
            };

            $a = array_map($f, $a);
            $b = array_map($f, $b);

            return $a > $b ? -1 : 1;
        }
    }

    /**
     * Utility function for parsing journal issue.
     *
     * @param string $issue Journal issue.
     *
     * @return array
     */
    protected function parseJournalIssue($issue)
    {
        $parts = explode(':', $issue);
        return array_map('trim', $parts);
    }

    /**
     * Function for sorting holdings (non-journal)
     * according to organisation and branch.
     *
     * @param array $a Holding info
     * @param array $b Holding info
     *
     * @return int
     */
    protected function defaultHoldingsSortFunction($a, $b)
    {
        if ($a['organisation_id'] !== $b['organisation_id']) {
            $key = 'organisation_id';
            $sortOrder = $this->holdingsOrganisationOrder;
            $locationA = $a['location'];
            $locationB = $b['location'];
        } else {
            $key = 'branch_id';
            $sortOrder = $this->holdingsBranchOrder;
            $locationA
                = $a['location'] . ' ' . $a['branch'] . ' ' . $a['department'];
            $locationB
                = $b['location'] . ' ' . $b['branch'] . ' ' . $b['department'];
        }

        $orderA = $sortOrder[$a[$key]] ?? null;
        $orderB = $sortOrder[$b[$key]] ?? null;

        if ($orderA !== null) {
            if ($orderB !== null) {
                $order = $orderA - $orderB;
                return $order != 0
                    ? $order : strcasecmp($locationA, $locationB);
            }
            return -1;
        }
        if ($orderB !== null) {
            return 1;
        }
        return strcasecmp($locationA, $locationB);
    }

    /**
     * Get default required by date.
     *
     * @return int timestamp
     */
    protected function getDefaultRequiredByDate()
    {
        list($d, $m, $y) = isset($this->config['Holds']['defaultRequiredDate'])
             ? explode(':', $this->config['Holds']['defaultRequiredDate'])
             : [0, 1, 0];
        return mktime(
            0, 0, 0, date('m') + $m, date('d') + $d, date('Y') + $y
        );
    }

    /**
     * Function for determining the type of Hold
     *
     * @param array $holdDetails Hold details
     *
     * @return string
     */
    protected function getHoldType($holdDetails)
    {
        if ($this->requestGroupsEnabled && !empty($holdDetails['requestGroupId'])
        ) {
            $holdType = $holdDetails['requestGroupId'];
        } else {
            $holdType = $this->regionalHold ? 'regional' : 'normal';
        }
        return $holdType;
    }

    /**
     * Sort function for sorting pickup locations
     *
     * @param array $a Pickup location
     * @param array $b Pickup location
     *
     * @return number
     */
    protected function pickUpLocationsSortFunction($a, $b)
    {
        $pickUpLocationOrder = isset($this->config['Holds']['pickUpLocationOrder'])
            ? explode(":", $this->config['Holds']['pickUpLocationOrder']) : [];
        $pickUpLocationOrder = array_flip($pickUpLocationOrder);
        if (isset($pickUpLocationOrder[$a['locationID']])) {
            if (isset($pickUpLocationOrder[$b['locationID']])) {
                return
                    $pickUpLocationOrder[$a['locationID']]
                    - $pickUpLocationOrder[$b['locationID']];
            }
            return -1;
        }
        if (isset($pickUpLocationOrder[$b['locationID']])) {
            return 1;
        }
        return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
    }

    /**
     * Map statuses
     *
     * @param string $status   Status as a string
     * @param string $function AWS function that returned the status
     *
     * @return string Mapped status
     */
    protected function mapStatus($status, $function)
    {
        $statuses =  [
            'copyHasSpecialCircCat' => 'Copy has special circulation',
            'copyIsReserved'        => 'renew_item_requested',
            'isLoanedToday'         => 'Borrowed today',
            'isRenewedToday'        => 'Renewed today',
            'isOverdue'             => 'renew_item_overdue',
            'maxNofRenewals'        => 'renew_item_limit',
            'patronIsDeniedLoan'    => 'renew_denied',
            'patronHasDebt'         => 'renew_debt',
            'patronIsInvoiced'      => 'renew_item_patron_is_invoiced',
            'renewalIsDenied'       => 'renew_denied',
            'ReservationDenied'     => 'hold_error_denied',
            'BlockedBorrCard'       => 'addReservation' === $function
                ? 'hold_error_blocked' : 'Borrowing Block Message'
        ];

        if (isset($statuses[$status])) {
            return $statuses[$status];
        }
        return $status;
    }

    /**
     * Check if renewal is permanently blocked
     *
     * @param string $status Status as a string
     *
     * @return bool
     */
    protected function isPermanentRenewalBlock($status)
    {
        $blocks = [
            'copyHasSpecialCircCat',
            'copyIsReserved'
        ];

        return in_array($status, $blocks);
    }

    /**
     * Code to status
     *
     * @param string $code as a string
     *
     * @return string Mapped code
     */
    protected function mapCodeToStatus($code)
    {
        return $this->statuses[$code] ?? $code;
    }

    /**
     * Status to code
     *
     * @param string $status as a string
     *
     * @return string Mapped status
     */
    protected function mapStatusToCode($status)
    {
        $found = array_search($status, $this->statuses);
        return $found !== false ? $found : $status;
    }

    /**
     * Map old code to status
     *
     * @param string $code as a string
     *
     * @return string Mapped code
     */
    protected function mapOldCodeToStatus($code)
    {
        return $this->oldStatuses[$code] ?? $code;
    }

    /**
     * Map old status to code
     *
     * @param string $status as a string
     *
     * @return string Mapped status
     */
    protected function mapOldStatusToCode($status)
    {
        $found = array_search($status, $this->oldStatuses);
        return $found !== false ? $found : $status;
    }

    /**
     * Wrap the given object to an array if needed.
     *
     * @param mixed $object Object
     *
     * @return array
     */
    protected function objectToArray($object)
    {
        if (is_object($object)) {
            $object = [$object];
        }
        return $object;
    }

    /**
     * Method to ensure uniform cache keys for cached VuFind objects.
     *
     * @param string|null $suffix Optional suffix that will get appended to the
     * object class name calling getCacheKey()
     *
     * @return string
     */
    protected function getCacheKey($suffix = null)
    {
        return 'AxiellWebServices' . '-' . md5($this->arenaMember . "|$suffix");
    }

    /**
     * Get a cache key for patron information
     *
     * @param string $username Unique username
     *
     * @return string
     */
    protected function getPatronCacheKey($username)
    {
        return "patron|$username|" . $this->getTranslatorLocale();
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
        switch ($method) {
        case 'changePassword':
            return isset($this->config['changePassword']);
        case 'getMyTransactionHistory':
            return !empty($this->loansaurora_wsdl);
        case 'updateAddress':
            return !empty($this->patronaurora_wsdl);
        default:
            return is_callable([$this, $method]);
        }
    }

    /**
     * Get path to a WSDL file taking inheritance into account
     *
     * @param string $wsdl WSDL file name
     *
     * @return string
     */
    protected function getWsdlPath($wsdl)
    {
        if (preg_match('/^https?:/', $wsdl)) {
            // Don't mangle a URL
            return $wsdl;
        }
        $file = Locator::getConfigPath($wsdl);
        if (!file_exists($file)) {
            $file = Locator::getConfigPath($wsdl, 'config/finna');
        }
        return $file;
    }
}
