<?php

/**
 * Axiell Web Services ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
use Finna\ILS\Driver\Feature\FinnaCommonILSTrait;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface as TranslatorAwareInterface;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\ILS\Logic\OnlinePaymentTrait;

use function count;
use function in_array;
use function is_callable;
use function is_object;
use function strlen;

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
class AxiellWebServices extends \VuFind\ILS\Driver\AbstractBase implements
    TranslatorAwareInterface,
    \Psr\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFind\Cache\CacheTrait;
    use FinnaCommonILSTrait;
    use OnlinePaymentTrait {
        fineIsPayable as fineIsPayableBase;
    }

    /**
     * Date formatting object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateFormat;

    /**
     * Config file path resolver
     *
     * @var \VuFind\Config\PathResolver
     */
    protected $pathResolver;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

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
     * @var array
     */
    protected $holdingsOrganisationOrder;

    /**
     * Institution settings for the order of branches
     *
     * @var array
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
    protected $messagingFilters = [
        'pickUpNotice' => [],
        'overdueNotice' => [],
        'dueDateAlert' => [],
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
        'lastreturned' => 'showlastreturned',
    ];

    /**
     * Messaging preference type mappings
     *
     * @var array
     */
    protected array $messagingPrefTypeMap = [
        'overdueNotice' => 'overdueNotice',
        'pickUpNotice' => 'pickUpNotice',
        'dueDateAlert' => 'dueDateAlert',
        'dueDateAlertEmail' => 'dueDateAlertEmail',
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
     * SOAP Options
     *
     * @var array
     */
    protected $soapOptions = [
        'soap_version' => SOAP_1_1,
        'exceptions' => true,
        'trace' => false,
        'timeout' => 60,
        'connection_timeout' => 15,
        'typemap' => [
            [
                'type_ns' => 'http://www.w3.org/2001/XMLSchema',
                'type_name' => 'anyType',
                'from_xml' => ['\AxiellWebServices', 'anyTypeToString'],
                'to_xml' => ['\AxiellWebServices', 'stringToAnyType'],
            ],
        ],
    ];

    /**
     * Titlelist cache time mappings in minutes
     *
     * @var array
     */
    protected $titleListCacheSettings = [];

    /**
     * Pick up location block list
     *
     * @var array
     */
    protected $excludedPickUpLocations = [
        'regional' => [
            'organisation' => [],
            'unit' => [],
        ],
        'normal' => [
            'organisation' => [],
            'unit' => [],
        ],
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter      $dateConverter Date converter object
     * @param \VuFind\Config\PathResolver $pathResolver  Config file path resolver
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        \VuFind\Config\PathResolver $pathResolver
    ) {
        $this->dateFormat = $dateConverter;
        $this->pathResolver = $pathResolver;
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

        // BC for online payment configuration:
        if (empty($this->config['OnlinePayment']) && !empty($this->config['onlinePayment'])) {
            $this->config['OnlinePayment'] = $this->config['onlinePayment'];
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
            throw new ILSException('reservations_wsdl configuration needs to be set.');
        }

        if (isset($this->config['Catalog']['patronaurora_wsdl'])) {
            $this->patronaurora_wsdl
                = $this->getWsdlPath($this->config['Catalog']['patronaurora_wsdl']);
        }

        $this->defaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation'] ?? false;

        $holds = $this->config['Holds'];
        // Bc for older setting
        $excludedPickUpLocationsBc
            = isset($holds['excludePickUpLocations'])
            ? explode(':', $holds['excludePickUpLocations']) : [];

        $excludedNormalLocations
            = isset($holds['excludeLocationsFromNormalHoldPickUp'])
            ? array_merge(
                explode(':', $holds['excludeLocationsFromNormalHoldPickUp']),
                $excludedPickUpLocationsBc
            ) : $excludedPickUpLocationsBc;

        $excludedRegionalLocations
            = isset($holds['excludeLocationsFromRegionalHoldPickUp'])
            ? array_merge(
                explode(':', $holds['excludeLocationsFromRegionalHoldPickUp']),
                $excludedPickUpLocationsBc
            ) : $excludedPickUpLocationsBc;

        $excludedNormalOrganisations
            = isset($holds['excludeOrganisationsFromNormalHoldPickUp'])
            ? explode(':', $holds['excludeOrganisationsFromNormalHoldPickUp'])
            : [];

        $excludedRegionalOrganisations
            = isset($holds['excludeOrganisationsFromRegionalHoldPickUp'])
            ? explode(':', $holds['excludeOrganisationsFromRegionalHoldPickUp'])
            : [];

        $this->excludedPickUpLocations = [
            'normal' => [
                'unit' => $excludedNormalLocations,
                'organisation' => $excludedNormalOrganisations,
            ],
            'regional' => [
                'unit' => $excludedRegionalLocations,
                'organisation' => $excludedRegionalOrganisations,
            ],
        ];

        if (
            $this->defaultPickUpLocation == '0'
            || $this->defaultPickUpLocation === 'user-selected'
        ) {
            $this->defaultPickUpLocation = false;
        }

        $this->defaultRequestGroup
            = $this->config['Holds']['defaultRequestGroup'] ?? false;

        if ($this->defaultRequestGroup === 'user-selected') {
            $this->defaultRequestGroup = false;
        }

        $this->regionalHold = $this->config['Holds']['regionalHold'] ?? false;

        $this->requestGroupsEnabled
            = isset($this->config['Holds']['extraHoldFields'])
        && in_array(
            'requestGroup',
            explode(':', $this->config['Holds']['extraHoldFields'])
        );

        $this->singleReservationQueue
            = $this->config['Holds']['singleReservationQueue'] ?? false;

        $this->durationLogPrefix = $this->config['Debug']['durationLogPrefix'] ?? '';

        if (isset($this->config['Debug']['verbose'])) {
            $this->verbose = $this->config['Debug']['verbose'];
            $this->soapOptions['trace'] = true;
        }

        $this->logFile = $this->config['Debug']['log'] ?? '';

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

        // Use new settings with fallbacks for old ones:
        $this->messagingFilters['pickUpNotice']
            = explode(
                ':',
                $this->config['messagingFilters']['pickUpNotice']
                ?? $this->config['messagingBlackLists']['pickUpNotice']
                ?? ''
            );
        $this->messagingFilters['overdueNotice']
            = explode(
                ':',
                $this->config['messagingFilters']['overdueNotice']
                ?? $this->config['messagingBlackLists']['overdueNotice']
                ?? ''
            );
        $this->messagingFilters['dueDateAlert']
            = explode(
                ':',
                $this->config['messagingFilters']['dueDateAlert']
                ?? $this->config['messagingBlackLists']['dueDateAlert']
                ?? ''
            );
        $this->titleListCacheSettings
            = $this->config['titleListCacheSettings']['settings'] ??
            [
                'new' => 15,
                'lastreturned' => 15,
                'mostborrowed' => 480,
                'mostrequested' => 240,
            ];

        if (!empty($this->config['Catalog']['connection_timeout'])) {
            $this->soapOptions['connection_timeout']
                = $this->config['Catalog']['connection_timeout'];
        }
        if (!empty($this->config['Catalog']['timeout'])) {
            $this->soapOptions['timeout']
                = $this->config['Catalog']['timeout'];
        }
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
     * @return array Array of locations
     */
    public function getPickUpLocations($user, $holdDetails)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $id = !empty($holdDetails['item_id'])
            ? $holdDetails['item_id']
            : ($holdDetails['id'] ?? '');

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
            'reservationType' => $holdType,
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl,
            $function,
            $functionResult,
            $username,
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

        $keyName = 'limitPickUpLocationChangeToCurrentOrganization';
        $limitToCurrentOrganisation = ($this->config['Holds'][$keyName]
            ?? !$this->singleReservationQueue) && $holdType !== 'regional';
        foreach ($organisations as $organisation) {
            if (!isset($organisation->branches->branch)) {
                continue;
            }

            if (
                !empty($holdDetails['_organization']) && $limitToCurrentOrganisation
                && $organisation->name !== $holdDetails['_organization']
            ) {
                continue;
            }

            $organisationID = $organisation->id;
            if (
                !empty($this->excludedPickUpLocations[$holdType])
                && in_array(
                    $organisationID,
                    $this->excludedPickUpLocations[$holdType]['organisation'] ?? []
                )
            ) {
                continue;
            }

            // TODO: Make it configurable whether organisation names
            // should be included in the location name
            $branches = is_object($organisation->branches->branch)
                ? [$organisation->branches->branch]
                : $organisation->branches->branch;
            foreach ($branches as $branch) {
                $locationID
                    = $organisationID . '.' . $branch->id;
                if (
                    !empty($this->excludedPickUpLocations[$holdType])
                    && in_array(
                        $locationID,
                        $this->excludedPickUpLocations[$holdType]['unit'] ?? []
                    )
                ) {
                    continue;
                }

                $locationsList[] = [
                    'locationID' => $locationID,
                    'locationDisplay' => $branch->name ?? '',
                ];
            }
        }

        // Sort pick up locations
        usort($locationsList, [$this, 'pickUpLocationsSortFunction']);

        return $locationsList;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in the .ini file
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
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
     * placeHold, minus the patron data. May be used to limit the request group
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
                'name' => 'axiell_normal',
            ],
            [
                'id'   => 'regional',
                'name' => 'axiell_regional',
            ],
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
            $validFromDate = !empty($holdDetails['startDateTS'])
                ? date('Y-m-d', $holdDetails['startDateTS'])
                : date('Y-m-d');
            $validToDate = !empty($holdDetails['requiredByTS'])
                ? date('Y-m-d', $holdDetails['requiredByTS'])
                : date('Y-m-d', $this->getDefaultRequiredByDate());
        } catch (DateException $e) {
            // Hold Date is invalid
            throw new ILSException('hold_date_invalid');
        }

        $pickUpLocation = $holdDetails['pickUpLocation'];
        [$organisation, $branch] = explode('.', $pickUpLocation, 2);

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
            'validToDate' => $validToDate,
        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl,
            $function,
            $functionResult,
            $username,
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
               'sysMessage' => $message,
            ];
        }

        return [
            'success' => true,
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
            [$id] = explode('|', $details);
            $result = $this->doSOAPRequest(
                $this->reservations_wsdl,
                $function,
                $functionResult,
                $username,
                ['removeReservationsParam' =>
                   ['arenaMember' => $this->arenaMember,
                    'user' => $username, 'password' => $password,
                     'language' => 'en', 'id' => $id],
                ]
            );

            $statusAWS = $result->$functionResult->status;

            if ($statusAWS->type != 'ok') {
                $message
                    = $this->handleError($function, $statusAWS, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
                $results[$id] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $statusAWS->message ?? $statusAWS->type,
                ];
            } else {
                $results[$id] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                    'sysMessage' => '',
                ];
            }

            ++$succeeded;
        }
        $results['count'] = $succeeded;
        return $results;
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
        $function = 'changeReservation';
        $functionResult = 'changeReservationResult';
        foreach ($holdsDetails as $details) {
            [$requestId, $validFromDate, $validToDate, $pickupLocation]
                = explode('|', $details);
            $updateRequest = [
                'arenaMember' => $this->arenaMember,
                'user' => $patron['cat_username'],
                'password' => $patron['cat_password'],
                'language' => 'en',
                'id' => $requestId,
                'pickUpBranchId' => $pickupLocation,
                'validFromDate' => $validFromDate,
                'validToDate' => $validToDate,
            ];

            if (isset($fields['requiredByTS'])) {
                $updateRequest['validToDate']
                    = date('Y-m-d', $fields['requiredByTS']);
            }
            if (isset($fields['frozen'])) {
                if ($fields['frozen']) {
                    if (isset($fields['frozenThroughTS'])) {
                        $updateRequest['validFromDate']
                            = \DateTime::createFromFormat(
                                'U',
                                $fields['frozenThroughTS']
                            )->modify('+1 DAY')->format('Y-m-d');
                    } else {
                        $updateRequest['validFromDate']
                            = $updateRequest['validToDate'];
                    }
                } else {
                    $updateRequest['validFromDate'] = date('Y-m-d');
                }
            } elseif (
                $updateRequest['validFromDate'] > $updateRequest['validToDate']
            ) {
                $updateRequest['validFromDate'] = $updateRequest['validToDate'];
            }
            if (isset($fields['pickUpLocation'])) {
                [, $branch] = explode('.', $fields['pickUpLocation'], 2);
                $updateRequest['pickUpBranchId'] = $branch;
            }
            $result = $this->doSOAPRequest(
                $this->reservations_wsdl,
                $function,
                $functionResult,
                $patron['cat_username'],
                ['changeReservationsParam' => $updateRequest]
            );

            $statusAWS = $result->$functionResult->status;

            if ($statusAWS->type != 'ok') {
                $message = $this->handleError(
                    $function,
                    $statusAWS,
                    $patron['cat_username']
                );
                $results[$requestId] = [
                    'success' => false,
                    'status' => $message,
                ];
            } else {
                $results[$requestId] = [
                    'success' => true,
                ];
            }
        }
        return $results;
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
     * @param ?array $patron  Patron data
     * @param array  $options Extra options
     *
     * @throws \VuFind\Exception\ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, ?array $patron = null, array $options = [])
    {
        $function = 'GetHoldings';
        $functionResult = 'GetHoldingResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'id' => $id,
            'language' => $this->getLanguage(),
        ];

        $response = $this->doSOAPRequest(
            $this->catalogue_wsdl,
            $function,
            $functionResult,
            $id,
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
                        'edition' => $edition,
                    ];

                    $result = array_merge(
                        $result,
                        $this->parseHoldings(
                            $holdingsOrganisations,
                            $id,
                            $journalInfo
                        )
                    );
                }
            }
        } else {
            $result = $this->parseHoldings($holdings, $id);
        }

        if (!empty($result)) {
            usort($result, [$this, 'holdingsSortFunction']);

            $summary = $this->getHoldingsSummary($result, $id);
            $result[] = $summary;
        }

        return $result;
    }

    /**
     * This is responsible for iterating the organisation holdings
     *
     * @param array  $organisationHoldings Organisation holdings
     * @param string $id                   The record id to retrieve the holdings
     * @param ?array $journalInfo          Jornal information
     *
     * @return array
     */
    protected function parseHoldings(array $organisationHoldings, string $id, ?array $journalInfo = null)
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
                    $reservableId = $branch->reservable ?? '';
                    $holdable = $branch->reservationButtonStatus == 'reservationOk';
                    $departments = $this->objectToArray($branch->holdings->holding);

                    foreach ($departments as $department) {
                        // Get holding data
                        $dueDate = isset($department->firstLoanDueDate)
                            ? $this->formatDate($department->firstLoanDueDate) : '';
                        $departmentName = $department->department;
                        $locationName = $department->location ?? '';

                        if (!empty($locationName)) {
                            $departmentName = "{$departmentName}, $locationName";
                        }

                        $nofAvailableForLoan = $department->nofAvailableForLoan ?? 0;
                        $nofTotal = $department->nofTotal ?? 0;
                        $nofOrdered = $department->nofOrdered ?? 0;

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
                        if (
                            $status == 'nonAvailableForLoan'
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
                           'inTransfer' => 'In Transit',
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
                        if (
                            !$this->singleReservationQueue
                            && isset($branch->nofReservations)
                        ) {
                            $requests = $branch->nofReservations;
                        }
                        $availabilityInfo = [
                            'available' => $nofAvailableForLoan,
                            'displayText' => $status,
                            'reservations' => $branch->nofReservations ?? 0,
                            'ordered' => $nofOrdered,
                            'total' => $nofTotal,
                        ];
                        $callnumber = $department->shelfMark ?? '';

                        $holding = [
                            'id' => $id,
                            'barcode' => $id,
                            'item_id' => $reservableId,
                            'holdings_id' => $group,
                            'availability' => new AvailabilityStatus($available, $status),
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
                            'reserve' => null,
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
     * @param array  $holdings Parsed holdings items
     * @param string $id       Record id
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings, $id)
    {
        $holdable = false;
        $journal = isset($holdings[0]['journalInfo']);
        $availableTotal = $itemsTotal = $orderedTotal = $reservationsTotal = 0;
        $locations = [];
        foreach ($holdings as $item) {
            if ($item['availability']->isAvailable()) {
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
            if (
                $this->singleReservationQueue
                && isset($item['availabilityInfo']['reservations'])
            ) {
                $reservationsTotal
                    = max(
                        $reservationsTotal,
                        $item['availabilityInfo']['reservations']
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
            'id' => $id,
            'available' => $availableTotal,
            'ordered' => $orderedTotal,
            'total' => $itemsTotal - $orderedTotal,
            'reservations' => $reservationsTotal,
            'locations' => count($locations),
            'holdable' => $holdable,
            'availability' => null,
            'callnumber' => '',
            'location' => '__HOLDINGSSUMMARYLOCATION__',
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
            'language' => $this->getLanguage(),
        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl,
            $function,
            $functionResult,
            $username,
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

        /**
         * Request an authentication id used in certain requests e.g:
         * GetTransactionHistory
         */
        $patronId = $this->authenticatePatron($username, $password);
        $user = $this->createPatronArray(
            id: $patronId,
            cat_username: $username,
            cat_password: $password,
            firstname: $firstname,
            lastname: $lastname,
            nonDefaultFields: [
                // Non  default field for legacy support
                'patronId' => $patronId,
            ]
        );

        $email = null;
        $emailId = null;

        foreach ($this->objectToArray($info->emailAddresses->emailAddress ?? []) as $emailAddress) {
            if ($emailAddress->isActive === 'yes') {
                $email = $emailAddress->address ?? '';
                $emailId = $emailAddress->id ?? '';
                break;
            }
        }
        $address1 = null;
        $zip = null;
        $city = null;
        $country = null;
        $addressId = null;
        foreach ($this->objectToArray($info->addresses->address ?? []) as $address) {
            if ($address->isActive == 'yes') {
                $address1 = $address->streetAddress ?? '';
                $zip = $address->zipCode ?? '';
                $city = $address->city ?? '';
                $country = $address->country ?? '';
                $addressId = $address->id ?? '';
            }
        }

        $phone = null;
        $phoneLocalCode = null;
        $phoneAreaCode = null;
        $phoneId = null;
        foreach ($this->objectToArray($info->phoneNumbers->phoneNumber ?? []) as $phoneNumber) {
            if ($phoneNumber->sms->useForSms == 'yes') {
                $phoneAreaCode = $phone = $phoneNumber->areaCode ?? '';
                if (isset($phoneNumber->localCode)) {
                    $phone .= $phoneNumber->localCode;
                    $phoneLocalCode = $phoneNumber->localCode;
                }
                if (isset($phoneNumber->id)) {
                    $phoneId = $phoneNumber->id;
                }
            }
        }

        $serviceSendMethod
            = $this->config['updateMessagingSettings']['method'] ?? 'none';
        $messagingServices = [];
        // Convert users messaging services into koha style array
        if ($serviceSendMethod === 'driver') {
            $allowedMessagingServices = $this->getMessageServices($user);
            $userMessagingServices
                = $this->parseObtainedMessagingSettings(
                    $this->objectToArray($info->messageServices->messageService ?? []),
                    $allowedMessagingServices,
                );
            $messagingServices = $this->createMessagingSettingsArray(
                $userMessagingServices,
                1,
                5,
                'select'
            );
        } elseif ($serviceSendMethod === 'database') {
            $allowedMessagingServices = $this->getEmailMessagingServices();
            $messagingServices = $this->parseObtainedMessagingSettings(
                $this->objectToArray($info->messageServices->messageService ?? []),
                $allowedMessagingServices
            );
            $messagingServices = $this->createMessagingSettingsArray($messagingServices, 1, 5);
        }

        $userCached = $this->createProfileArray(
            firstname: $firstname,
            lastname: $lastname,
            address1: $address1,
            zip: $zip,
            city: $city,
            country: $country,
            phone: $phone,
            email: $email,
            messagingServices: $messagingServices,
            loan_history: $info->isLoanHistoryEnabled ?? null,
            nonDefaultFields: [
                'emailId' => $emailId,
                'addressId' => $addressId,
                'phoneId' => $phoneId,
                'phoneLocalCode' => $phoneLocalCode,
                'phoneAreaCode' => $phoneAreaCode,
                'patronId' => $patronId,
                'id' => $info->backendPatronId,
                'cat_username' => $username,
                'cat_password' => $password,
            ]
        );
        $this->putCachedData($cacheKey, $userCached);

        return $user;
    }

    /**
     * Helper function to format obtained messaging services from object into common array format
     *
     * @param ?object $userServices    User defined services and methods
     * @param array   $allowedServices Services allowed, obtained from ILS
     *
     * @return array
     */
    protected function parseObtainedMessagingSettings($userServices, $allowedServices): array
    {
        if (!$userServices || !$allowedServices) {
            return [];
        }

        // Loop through services found from users profile and parse them into a commonly understandable
        // form
        foreach ($userServices as $userService) {
            $type = (string)$userService->serviceType;
            if (!isset($allowedServices[$type])) {
                continue;
            }
            // Loop through users preferred send methods and adjust the returning array with correct values
            foreach ($this->objectToArray($userService->sendMethods) as $method) {
                $mappedMethod = $this->mapCodeToStatus($method->sendMethod->value ?? 'inactive');
                if (
                    !isset($allowedServices[$type]['transport_types'][$mappedMethod])
                ) {
                    continue;
                }
                $allowedServices[$type]['transport_types'][$mappedMethod]
                    = ($method->sendMethod->isActive ?? 'no') === 'yes';
            }
            if (isset($userService->nofDays->value)) {
                $allowedServices[$type]['days_in_advance']['value'] = (int)$userService->nofDays->value;
            }
        }
        return $allowedServices;
    }

    /**
     * Function to create an array for using email to change messaging services.
     * This is a Legacy compatibility function.
     *
     * @return array parsed services
     */
    public function getEmailMessagingServices()
    {
        $defaultEmailServices = [
            'pickUpNotice'  => [
                'transport_types' => [
                    'print' => false,
                    'email' => false,
                    'sms' => false,
                    'inactive' => false,
                ],
                'selectType' => 'select',
                'type' => 'pickUpNotice',
            ],
            'overdueNotice' => [
                'transport_types' => [
                    'print' => false,
                    'email' => false,
                    'sms' => false,
                    'inactive' => false,
                ],
                'selectType' => 'select',
                'type' => 'overdueNotice',
            ],
            'dueDateAlert' => [
                'transport_types' => [
                    'email' => false,
                    'inactive' => false,
                ],
                'selectType' => 'select',
                'type' => 'dueDateAlertEmail',
                'days_in_advance' => [
                    'configurable' => true,
                ],
            ],
        ];
        $filtered = [];
        foreach ($defaultEmailServices as $serviceType => $settings) {
            $filtered[$serviceType] = $settings;
            foreach ($settings['transport_types'] as $method => $active) {
                $oldStatus = $this->mapStatusToCode($method);
                if (in_array($oldStatus, $this->messagingFilters[$serviceType] ?? [])) {
                    unset($filtered[$serviceType]['transport_types'][$method]);
                }
            }
        }
        return $filtered;
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
            if ('OnlinePayment' === $function) {
                if (!isset($functionConfig['exactBalanceRequired'])) {
                    $functionConfig['exactBalanceRequired'] = true;
                }
                $functionConfig['creditUnsupported'] = true;
            }
        } else {
            $functionConfig = false;
        }
        if ($function === 'getTitleList') {
            if (isset($this->config['Catalog']['catalogueaurora_wsdl'])) {
                $functionConfig = [
                    'enabled' => true,
                    'cacheSettings' => $this->titleListCacheSettings,
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
                : 'mostloaned',
        ];

        $function = 'Search';
        $functionResult = 'searchResult';

        $result = $this->doSOAPRequest(
            $this->catalogueaurora_wsdl,
            $function,
            $functionResult,
            '',
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
            'pages' => $result->$functionResult->nofPages,
        ];
        // Lets get a pretty list of results
        foreach ($records as $key => $obj) {
            $record = [
                'id' => $obj->id ?? '0',
                'title' => $obj->title ?? '',
                'mediaClass' => $obj->mediaClass ?? '',
                'icon' => $obj->mediaClassIcon ?? '',
                'author' => $obj->author ?? '',
                'year' => $obj->publicationYear ?? '',
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
            'language' => $this->getLanguage(),
        ];

        $result = $this->doSOAPRequest(
            $this->loans_wsdl,
            $function,
            $functionResult,
            $username,
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

        // Create a timestamp for calculating the due / overdue status
        $now = time();

        $transList = [];
        if (!isset($result->$functionResult->loans->loan)) {
            return $transList;
        }
        $loans = $this->objectToArray($result->$functionResult->loans->loan);
        foreach ($loans as $loan) {
            $title = $loan->catalogueRecord->title;
            if (!empty($loan->note)) {
                $title .= ' (' . $loan->note . ')';
            }

            $message = isset($loan->loanStatus->status)
                ? $this->mapStatus($loan->loanStatus->status, $function) : '';

            // These are confusingly similarly named, but displayed a bit differently
            // in the UI:
            // renew/renewLimit is displayed as "x renewals remaining"
            $renew = null;
            $renewLimit = null;
            // renewals/renewalLimit is displayed as "renewed/limit"
            $renewals = null;
            $renewalLimit = null;
            if (
                isset($loan->loanStatus->status)
                && $this->isPermanentRenewalBlock($loan->loanStatus->status)
            ) {
                // No changes
            } elseif (isset($this->config['Loans']['renewalLimit'])) {
                $renewalLimit = $this->config['Loans']['renewalLimit'];
                $renewals = max(
                    [
                        0,
                        $renewalLimit - $loan->remainingRenewals,
                    ]
                );
            } elseif ($loan->remainingRenewals > 0) {
                $renew = 0;
                $renewLimit = $loan->remainingRenewals;
            }

            $dueDate = strtotime($loan->loanDueDate . ' 23:59:59');
            if ($now > $dueDate) {
                $dueStatus = 'overdue';
            } elseif (($dueDate - $now) < 86400) {
                $dueStatus = 'due';
            } else {
                $dueStatus = false;
            }

            $trans = [
                'id' => $loan->catalogueRecord->id,
                'item_id' => $loan->id,
                'title' => $title,
                'duedate' => $loan->loanDueDate,
                'dueStatus' => $dueStatus,
                'renewable' => (string)$loan->loanStatus->isRenewable == 'yes',
                'message' => $message,
                'renew' => $renew,
                'renewLimit' => $renewLimit,
                'renewalCount' => $renewals,
                'renewalLimit' => $renewalLimit,
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
                ? $params['sort'] : 'CHECK_OUT_DATE DESCENDING',
            2
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
            'sortDirection' => $sortKey,
        ];

        $result = $this->doSOAPRequest(
            $this->loansaurora_wsdl,
            $function,
            $functionResult,
            $username,
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
            $title = $obj->title;
            if (!empty($record->note)) {
                $title .= ' (' . $record->note . ')';
            }
            $trans = [
                'id' => $obj->id,
                'title' => $title,
                'checkoutDate' => $this->formatDate($record->checkOutDate),
                'returnDate' => isset($record->checkInDate)
                    ? $this->formatDate($record->checkInDate) : '',
                'publication_year' => $obj->publicationYear ?? '',
                'volume' => $obj->volume ?? '',
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
            'password' => $password,
        ];

        $result = $this->doSOAPRequest(
            $this->patron_wsdl,
            $function,
            $functionResult,
            $username,
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
            'status' => 'request_change_done',
        ];

        foreach ($params as $service => $settings) {
            $transport = $settings['settings']['transport_types'] ?? '';
            if (empty($transport)) {
                continue;
            }
            $coded = $this->mapStatusToCode($transport['value']);
            $current = [
                'serviceType' => $service,
                'sendMethod' => $coded,
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
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
        ];
        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl,
            $function,
            $functionResult,
            $username,
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
        foreach ($resultArray as $sendMethods) {
            $serviceType = $this->mapCodeToStatus($sendMethods->serviceType);
            $current = [
                'transport_types' => [],
            ];
            foreach ($sendMethods->sendMethods->sendMethod as $value) {
                $method = is_object($value) ? $value->value : $value;
                $mappedMethod = $this->mapCodeToStatus($method);
                if (in_array($method, $this->messagingFilters[$serviceType] ?? [])) {
                    continue;
                }
                $current['transport_types'][$mappedMethod] = false;
            }
            if ($serviceType === 'dueDateAlert') {
                $current['days_in_advance'] = [
                    'configurable' => true,
                ];
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
                'value' => $params['sendMethod'],
            ],
            'serviceType' => $params['serviceType'],
        ];

        if ($params['serviceType'] === 'dueDateAlert') {
            $conf['nofDays'] = [
                'value' => $params['nofDays'],
            ];
        }

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl,
            $function,
            $functionResult,
            $username,
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
                'status' => $statusAWS,
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
            'serviceType' => $params['serviceType'],
        ];

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl,
            $function,
            $functionResult,
            $username,
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
                'status' => $statusAWS,
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

        $function = 'GetDebts';
        $functionResult = 'debtsResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'fromDate' => '1699-12-31',
            'toDate' => time(),
        ];

        $result = $this->doSOAPRequest(
            $this->payments_wsdl,
            $function,
            $functionResult,
            $username,
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
            // Round the amount in case it's a weird decimal number:
            $amount = round($amount);
            $description = $debt->debtType . ' - ' . $debt->debtNote;
            $fine = [
                'debt_id' => $debt->id,
                'fineId' => $debt->id,
                'amount' => $amount,
                'checkout' => '',
                'fine' => $description,
                'balance' => $amount,
                'createdate' => $debt->debtDate,
                'organization' => trim($debt->organisation ?? ''),
            ];
            $fine['payableOnline'] = $this->fineIsPayable($fine);
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
     * Register a payment.
     *
     * This is called after a successful online payment.
     *
     * @param array   $patron                  Patron
     * @param int     $amount                  Amount to be registered as paid
     * @param string  $localPaymentIdentifier  Local payment identifier
     * @param ?string $remotePaymentIdentifier Remote payment identifier
     * @param int     $paymentId               Internal payment id
     * @param ?array  $fineIds                 Fine IDs to mark paid or null for bulk payment
     *
     * @throws ILSException
     * @return array Associative array with keys success (bool, always) and reason (string, on error)
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function registerPayment(
        array $patron,
        int $amount,
        string $localPaymentIdentifier,
        ?string $remotePaymentIdentifier,
        int $paymentId,
        ?array $fineIds = null
    ): array {
        $function = 'AddPayment';
        $functionResult = 'addPaymentResponse';
        $functionParam = 'addPaymentRequest';

        $fines = $this->getMyFines($patron);
        $payableFines = array_filter(
            $fines,
            function ($fine) {
                return $fine['payableOnline'];
            }
        );
        $total = array_reduce(
            $payableFines,
            function ($carry, $fine) {
                $carry += $fine['balance'];
                return $carry;
            }
        );

        $paymentConfig = $this->getConfig('OnlinePayment');
        if (
            $total < $amount
            || (!empty($paymentConfig['exactBalanceRequired']) && $total != $amount)
        ) {
            return [
                'success' => false,
                'reason' => 'Payment::error_fines_changed',
            ];
        }

        $debtIds = array_column($payableFines, 'fineId');
        $request = [
            'arenaMember'       => $this->arenaMember,
            'orderId'           => (string)$localPaymentIdentifier,
            'transactionNumber' => (string)$paymentId,
            'paymentAmount'     => $amount,
            // Comma-separated list of IDs since the API has it single-valued
            'debts'             => ['id' => implode(',', $debtIds)],
        ];

        $result = $this->doSOAPRequest(
            $this->payments_wsdl,
            $function,
            $functionResult,
            $patron['cat_username'],
            [$functionParam => $request]
        );

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError(
                $function,
                $statusAWS,
                $patron['cat_username']
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

        return [
            'success' => true,
        ];
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
            'language' => $this->getLanguage(),

        ];

        $result = $this->doSOAPRequest(
            $this->reservations_wsdl,
            $function,
            $functionResult,
            $username,
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
            $title = $reservation->catalogueRecord->title ?? '';
            if (isset($reservation->note)) {
                $title .= ' (' . $reservation->note . ')';
            }

            $detailsStr = $reservation->id . '|' . $reservation->validFromDate
                . '|' . $reservation->validToDate . '|'
                . $reservation->pickUpBranchId;
            $updateDetails = '';
            $cancelDetails = '';
            // Regional holds have isEditable 'no' even when they're editable, so
            // check for isDeletetable for them:
            if (
                'yes' === $reservation->isEditable
                || ('regional' === $reservation->reservationType
                && 'yes' === $reservation->isDeletable)
            ) {
                $updateDetails = $detailsStr;
            }
            if ('yes' === $reservation->isDeletable) {
                $cancelDetails = $detailsStr;
            }
            $frozen = $reservation->validFromDate > date('Y-m-d');
            if (
                $frozen && $reservation->validFromDate != $reservation->validToDate
            ) {
                $ts = $this->dateFormat->convertFromDisplayDate(
                    'U',
                    $this->formatDate($reservation->validFromDate)
                );
                $frozenThrough = $this->dateFormat->convertToDisplayDate(
                    'Y-m-d',
                    \DateTime::createFromFormat('U', $ts)
                        ->modify('-1 DAY')->format('Y-m-d')
                );
            } else {
                $frozenThrough = '';
            }
            $hold = [
                'id' => $reservation->catalogueRecord->id,
                'type' => $reservation->reservationStatus,
                'location' => $reservation->pickUpBranchId,
                'pickupnum' =>
                   $reservation->pickUpNo ?? '',
                'expire' => $this->formatDate($expireDate),
                'frozen' => $frozen,
                'frozenThrough' => $frozenThrough,
                'position' =>
                   $reservation->queueNo ?? '-',
                'available' => $reservation->reservationStatus == 'fetchable',
                'reqnum' => $reservation->id,
                'volume' =>
                   $reservation->catalogueRecord->volume ?? '',
                'publication_year' =>
                   $reservation->catalogueRecord->publicationYear ?? '',
                'requestGroup' =>
                   isset($reservation->reservationType)
                   && $this->requestGroupsEnabled
                   ? "axiell_$reservation->reservationType"
                   : '',
                'requestGroupId' =>
                   isset($reservation->reservationType)
                   && $this->requestGroupsEnabled
                   ? $reservation->reservationType
                   : '',
                'in_transit' => $reservation->reservationStatus == 'inTransit',
                'title' => $title,
                'cancel_details' => $cancelDetails,
                'updateDetails' => $updateDetails,
                '_organization' => $reservation->organisationId ?? '',
                'create' => $this->formatDate($reservation->createDate),
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
            'loans' => $renewDetails['details'],
        ];

        $result = $this->doSOAPRequest(
            $this->loans_wsdl,
            $function,
            $functionResult,
            $username,
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
                'new_time' => '',
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
            'useForSms'    => 'yes',
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
            $this->patron_wsdl,
            $function,
            $functionResult,
            $username,
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
                'sys_message' => $statusAWS->message ?? $statusAWS->type,
            ];
        }

        // Clear patron cache
        $cacheKey = $this->getPatronCacheKey($username);
        $this->putCachedData($cacheKey, null);

        return [
                'success' => true,
                'status' => 'Phone number changed',
                'sys_message' => '',
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
            'isLoanHistoryEnabled' => $state,
        ];

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl,
            $function,
            $functionResult,
            $username,
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
                'status' => 'Changing the checkout history state failed',
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

        // Workaround for AWS issue where a bare plus sign gets converted to a space
        if (
            !isset($this->config['updateEmail']['encodeEmailPlusSign'])
            || $this->config['updateEmail']['encodeEmailPlusSign']
        ) {
            $email = str_replace('+', '%2B', $email);
        }
        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'address'      => $email,
            'isActive'     => 'yes',
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
            $this->patron_wsdl,
            $function,
            $functionResult,
            $username,
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
                'sys_message' => $statusAWS->message ?? $statusAWS->type,
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
        if (isset($details['email'])) {
            $result = $this->updateEmail($patron, $details['email']);
            if (!$result['success']) {
                return $result;
            }
        }

        if (isset($details['phone'])) {
            $result = $this->updatePhone($patron, $details['phone']);
            if (!$result['success']) {
                return $result;
            }
        }

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
            'city'          => $details['city'],
        ];

        $function = 'changeAddress';
        $functionResult = 'changeAddressResponse';

        $result = $this->doSOAPRequest(
            $this->patronaurora_wsdl,
            $function,
            $functionResult,
            $username,
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

        if (
            isset($this->config['updateAddress']['needsApproval'])
            && !$this->config['updateAddress']['needsApproval']
        ) {
            $status = 'request_change_accepted';
        } else {
            $status = 'request_change_done';
        }
        return [
            'success' => true,
            'status' => $status,
            'sys_message' => '',
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
            $this->patron_wsdl,
            $function,
            $functionResult,
            $username,
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
                'status' => $statusAWS->message ?? $statusAWS->type,
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
        $this->debug("$function Request for '$this->arenaMember'.'$id'");

        $startTime = microtime(true);
        try {
            $client = new ProxySoapClient($this->httpService, $wsdl, $this->soapOptions);
            $result = $client->$function($params);
        } catch (\SoapFault | \ErrorException $e) {
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
                '* M d G:i:s e Y',
                $dateString
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
            return strnatcasecmp($editionB, $editionA);
        }
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
        [$d, $m, $y] = isset($this->config['Holds']['defaultRequiredDate'])
             ? explode(':', $this->config['Holds']['defaultRequiredDate'])
             : [0, 1, 0];
        return mktime(
            0,
            0,
            0,
            date('m') + $m,
            date('d') + $d,
            date('Y') + $y
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
        if (
            $this->requestGroupsEnabled && !empty($holdDetails['requestGroupId'])
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
            ? explode(':', $this->config['Holds']['pickUpLocationOrder']) : [];
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
                ? 'hold_error_blocked' : 'Borrowing Block Message',
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
            'copyIsReserved',
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
     * called on this driver. Required method for any smart drivers.
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
        return $this->pathResolver->getConfigPath($wsdl);
    }

    /**
     * Check if a fine is payable.
     *
     * @param array $fine Fine
     *
     * @return bool
     */
    protected function fineIsPayable(array $fine): bool
    {
        if (!$this->fineIsPayableBase($fine)) {
            return false;
        }

        $paymentConfig = $this->config['OnlinePayment'] ?? [];
        $blockedTypes = $paymentConfig['nonPayable'] ?? [];
        $payableMinDate = strtotime($paymentConfig['payableFineDateThreshold'] ?? '-5 years');

        $debtDate = $this->dateFormat->convertFromDisplayDate(
            'U',
            $this->formatDate($fine['createdate'])
        );
        if ($debtDate < $payableMinDate) {
            return false;
        }
        foreach ($blockedTypes as $blockedType) {
            if (
                $blockedType === $fine['fine']
                || (str_starts_with($blockedType, '/')
                && str_ends_with($blockedType, '/')
                && preg_match($blockedType, $fine['fine']))
            ) {
                return false;
            }
        }
    }
}
