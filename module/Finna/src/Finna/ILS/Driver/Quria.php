<?php

/**
 * Quria ILS Driver
 *
 * PHP version 8.1
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
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
use VuFind\ILS\Logic\AvailabilityStatus;

use function count;
use function in_array;
use function is_callable;
use function is_object;
use function strlen;

/**
 * Quria ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Quria extends AxiellWebServices
{
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
              'from_xml' => ['\Quria', 'anyTypeToString'],
              'to_xml' => ['\Quria', 'stringToAnyType'],
          ],
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
                return !empty($this->loans_wsdl);
            case 'updateAddress':
                return !empty($this->patron_wsdl);
            default:
                return is_callable([$this, $method]);
        }
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
            'language' => 'fi',
            'country' => 'FI',
            'reservationEntities' => $id,
            'reservationType' => $holdType,
            'patronId' => $user['patronId'],
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
        $function = 'GetCatalogueRecordDetail';
        $functionResult = 'catalogueRecordDetailResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'id' => $id,
            'language' => $this->getLanguage(),
            'holdings' => ['enable' => 'yes'],
        ];
        $response = $this->doSOAPRequest(
            $this->catalogue_wsdl,
            $function,
            $functionResult,
            $id,
            ['catalogueRecordDetailRequest' => $conf]
        );

        $statusAWS = $response->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS, $id);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_holdings_message');
            }
            return [];
        }

        if (!isset($response->$functionResult->compositeHoldings)) {
            return [];
        }
        $holdings = $this->objectToArray(
            $response->$functionResult->compositeHoldings
        );

        $hol = $this->objectToArray($holdings[0]->compositeHolding ?? []);
        if (isset($hol[0]->type) && $hol[0]->type == 'year') {
            $result = [];
            foreach ($hol as $holding) {
                $year = $holding->value;
                $holdingsEditions = $this->objectToArray($holding->compositeHolding ?? []);
                foreach ($holdingsEditions as $holdingsEdition) {
                    $edition = $holdingsEdition->value;
                    $holdingsOrganisations
                        = $this->objectToArray($holdingsEdition->compositeHolding ?? []);
                    $journalInfo = [
                        'year' => $year,
                        'edition' => $edition,
                        'holdable' => ($holdingsEdition->reservationButtonStatus ?? '') == 'reservationOk',
                        'reservableId' => $holdingsEdition->reservable ?? '',
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
            $result = $this->parseHoldings(
                $holdings,
                $id,
                null,
                (string)($response->$functionResult->catalogueRecordDetail->reservable ?? '')
            );
        }

        if (!empty($result)) {
            usort($result, [$this, 'holdingsSortFunction']);
            $summary = $this->getHoldingsSummary($result, $id, $response->$functionResult->catalogueRecordDetail);
            $result[] = $summary;
        }

        return $result;
    }

    /**
     * This is responsible for iterating the organisation holdings
     *
     * @param array   $organisationHoldings Organisation holdings
     * @param string  $id                   The record id to retrieve the holdings
     * @param ?array  $journalInfo          Jornal information
     * @param ?string $reservable           Is the record reservable
     *
     * @return array
     */
    protected function parseHoldings(
        array $organisationHoldings,
        string $id,
        ?array $journalInfo = null,
        ?string $reservable = null
    ) {
        $result = [];
        foreach ($organisationHoldings as $organisation) {
            $holdingsBranch = $journalInfo === null
                ? $this->objectToArray($organisation->compositeHolding)
                : $this->objectToArray($organisation);
            foreach ($holdingsBranch as $branch) {
                $branchName = $branch->value ?? '';
                $branchId = $branch->id ?? '';
                if ($journalInfo) {
                    $reservableId = $journalInfo['reservableId'];
                    $holdable = $journalInfo['holdable'];
                } else {
                    $reservableId = $branch->reservable ?? '';
                    $holdable = $reservable === 'yes';
                }
                $departments = $this->objectToArray($branch->holdings->holding ?? []);
                $organisationId = $branch->id ?? '';
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
                    $group = null;
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
                        $journalInfo['location'] = '';
                    }

                    // Status & availability
                    $status = $department->status;
                    $available = $status == 'availableForLoan' || $status == 'returnedToday';

                    // Special status: On reference desk
                    if (
                        $status == 'referenceOnly'
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
                        'notAvailable' => 'Not Available',
                        'nonAvailableForLoan' => 'On Reference Desk',
                        'onRefDesk' => 'On Reference Desk',
                        'referenceOnly' => 'Not Available',
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
                            'Unhandled status ' .
                            $department->status .
                            " for '$this->arenaMember'.'$id'"
                        );
                    }
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
                        'holdings_id' => $group ?? '',
                        'availability' => new AvailabilityStatus($available, $status),
                        'availabilityInfo' => $availabilityInfo,
                        'status' => $status,
                        'location' => $group ?? $branchName,
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

        return $result;
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
        $yearA = $a['journalInfo']['year'];
        $yearB = $b['journalInfo']['year'];
        if ($yearA == $yearB) {
            return strnatcasecmp($b['journalInfo']['edition'], $a['journalInfo']['edition']);
        } else {
            return strnatcasecmp($yearB, $yearA);
        }
    }

    /**
     * Return summary of holdings items.
     *
     * @param array  $holdings      Parsed holdings items
     * @param string $id            Record id
     * @param object $recordDetails Record details
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings, $id, $recordDetails = null)
    {
        $holdable = false;
        $journal = isset($holdings[0]['journalInfo']);
        $availableTotal = $itemsTotal = $orderedTotal = 0;
        $reservationsTotal = $recordDetails->nofReservations ?? 0;
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

        $statusFunction = 'getPatronStatus';
        $statusFunctionResult = 'patronStatusResult';
        $statusConf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
        ];
        $statusResult = $this->doSOAPRequest(
            $this->patron_wsdl,
            $statusFunction,
            $statusFunctionResult,
            $username,
            ['patronStatusParam' => $statusConf]
        );
        if ($statusResult->$statusFunctionResult->status->type !== 'ok') {
            return null;
        }
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

        $email = null;
        $emails = [];
        if (!empty($info->emailAddresses->emailAddress)) {
            $emailAddresses
                =  $this->objectToArray($info->emailAddresses->emailAddress);
            $activeFound = false;
            foreach ($emailAddresses as $i => $emailAddress) {
                if (!$email || !$activeFound) {
                    $email = $emailAddress->address;
                    $activeFound = $emailAddress->isActive == 'yes';
                }
                $emails['email_' . $i] = $emailAddress->address ?? null;
                $emails['email_' . $i . '_id'] = $emailAddress->id ?? null;
                $emails['email_' . $i . '_active'] = $emailAddress->isActive == 'yes';
            }
        }

        $user = $this->createPatronArray(
            id: $info->backendPatronId,
            cat_username: $username,
            cat_password: $password,
            lastname: $lastname,
            firstname: $firstname,
            email: $email,
            nonDefaultFields: [
                'patronId' => $patronId,
            ]
        );
        $address = null;
        $zip = null;
        $city = null;
        $country = null;
        $addressId = null;
        if (isset($info->addresses->address)) {
            foreach ($this->objectToArray($info->addresses->address) as $addressFound) {
                if ($addressFound->isActive === 'yes') {
                    $address = $addressFound->streetAddress ?? '';
                    $zip = $addressFound->zipCode ?? '';
                    $city = $addressFound->city ?? '';
                    $country = $addressFound->country ?? '';
                    $addressId = $addressFound->id ?? '';
                    break;
                }
            }
        }
        $phone = null;
        $phones = [];
        if (isset($info->phoneNumbers->phoneNumber)) {
            $phoneNumbers = $this->objectToArray($info->phoneNumbers->phoneNumber);
            foreach ($phoneNumbers as $i => $phoneNumber) {
                $activeFound = false;
                if (empty($phone) || !$activeFound) {
                    $phone = ($phoneNumber->areaCode ?? '') . $phoneNumber->localCode ?? null;
                    $activeFound = $phoneNumber->sms->useForSms == 'yes';
                }
                $phones['phone_' . $i] = ($phoneNumber->areaCode ?? '') . $phoneNumber->localCode ?? null;
                $phones['phone_' . $i . '_id'] = $phoneNumber->id ?? null;
                $phones['phone_' . $i . '_active'] = ($phoneNumber->sms->useForSms ?? '') == 'yes';
            }
        }

        $userCached = $this->createProfileArray(
            firstname: $firstname,
            lastname: $lastname,
            address1: $address,
            zip: $zip,
            city: $city,
            country: $country,
            phone: $phone,
            loan_history: $info->isLoanHistoryEnabled ?? null,
            email: $email,
            nonDefaultFields: [
                'addressId' => $addressId,
                'id' => $info->backendPatronId,
                'cat_username' => $username,
                'cat_password' => $password,
                ...$emails,
                ...$phones,
                'patronId' => $patronId,
            ]
        );
        $this->putCachedData($cacheKey, $userCached);

        return $user;
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
            'patronId' => $user['patronId'],
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
            if ($this->isPermanentRenewalBlock($loan->loanStatus->status ?? '')) {
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
                'checkoutDate' => $this->formatDate($loan->loanDate),
                'borrowingLocation' => $loan->branch ?? '',
                'renewable' => (string)($loan->loanStatus->isRenewable ?? '') === 'yes',
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
        $patronId = $patron['patronId'];
        $pageSize = $params['limit'] ?? 50;
        $conf = [
            'arenaMember' => $this->arenaMember,
            'language' => $this->getLanguage(),
            'patronId' => $patronId,
            'start' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'count' => $pageSize,
            'sortField' => $sortField,
            'sortDirection' => $sortKey,
        ];

        $result = $this->doSOAPRequest(
            $this->loans_wsdl,
            $function,
            $functionResult,
            $username,
            ['loanHistoryRequest' => $conf]
        );
        $status = $result->$functionResult->status;

        if ($status->type != 'ok') {
            // Quria returns InvalidLogin error for GetLoanHistory if patron has loan history disabled.
            if ($status->message == 'InvalidLogin') {
                return ['transactions' => [], 'count' => 0];
            }
            $message = $this->handleError($function, $status, $username);
            if ($message == 'ils_connection_failed' || $status->type === 'error') {
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

        $formatted['success'] = $status->type === 'ok';
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
            'language' => 'fi',
            'patronId' => $user['patronId'],
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
                . $reservation->pickUpBranchId; // $reservation->pickUpBranchId actually
            // contains the branch name instead of the ID
            $updateDetails = '';
            $cancelDetails = '';
            // TODO: Regional holds are not yet implemented
            if ('yes' === $reservation->isEditable) {
                $cancelDetails = $updateDetails = $detailsStr;
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
        $user = $this->getMyProfile($patron);

        // Handle phones and emails
        foreach ($details as $field => $detail) {
            $parts = explode('_', $field);
            $fieldName = $parts[0];
            $ind = $parts[1] ?? null;
            $activeSuffix = $parts[2] ?? null;
            if (in_array($fieldName, ['email', 'phone']) && $activeSuffix === null) {
                $active = isset($details[$field . '_active']);
                if (
                    $user[$field] !== $detail
                    || $user[$field . '_active'] !== $active
                ) {
                    $result = $fieldName === 'email'
                        ? $this->updateEmailAddress($patron, $detail, $ind, $active)
                        : $this->updatePhoneNumber($patron, $detail, $ind, $active);
                    if (!$result['success']) {
                        return $result;
                    }
                }
            }
        }

        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $conf = [
            'arenaMember'   => $this->arenaMember,
            'language'      => $this->getLanguage(),
            'user'          => $username,
            'password'      => $password,
            'patronId'      => $user['patronId'],
            'isActive'      => 'yes',
            'id'            => $user['addressId'],
            'streetAddress' => $details['address1'],
            'zipCode'       => $details['zip'],
            'city'          => $details['city'],
        ];

        $function = 'changeAddress';
        $functionResult = 'changeAddressResponse';

        $result = $this->doSOAPRequest(
            $this->patron_wsdl,
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
            $this->patron_wsdl,
            $function,
            $functionResult,
            $username,
            ['changeLoanHistoryStatusParam' => $conf]
        );

        $status = $result->$functionResult->status;
        if ($status->type != 'ok') {
            $message = $this->handleError($function, $status, $username);
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
     * Returns translated value of a fine type.
     * Maps Quria message in to more unified version in VuFind
     * I.E reservationFeeDebt => 'fine_status_Hold Expired'
     *
     * @param string $key Key to check for mapping
     *
     * @return string
     */
    protected function mapAndTranslateFineType(string $key): string
    {
        // All of the finetypes in quria backend.
        $fines = [
            'claim1FeeDebt' => 'Lost Item',
            'claim2FeeDebt' => 'Lost Item',
            'claim3FeeDebt' => 'Lost Item',
            'claim4FeeDebt' => 'Lost Item',
            'claim5FeeDebt' => 'Lost Item',
            'deleteReservationFeeDebt' => '',
            'emailReminderFeeDebt' => '',
            'illFeeDebt' => 'Interlibrary Loan',
            'illReservationFeeDebt' => 'Hold Expired',
            'internetUsageFeeDebt' => '',
            'librarySubscriptionFeeDebt' => '',
            'loanFeeDebt' => '',
            'messageFeeDebt' => '',
            'otherFeeDebt' => 'Other',
            'overdueFeeDebt' => 'Overdue',
            'overdueFeeInvoiceDebt' => '',
            'photocopyFeeDebt' => '',
            'renewFeeDebt' => '',
            'replacementFeeDebt' => 'Lost Item Replacement',
            'reservationFeeDebt' => 'Hold Expired',
            'reservationPickupFeeDebt' => '',
            'smsIllFeeDebt' => '',
            'smsRecall1FeeDebt' => '',
            'smsRecall2FeeDebt' => '',
            'smsRecall3FeeDebt' => '',
            'smsRecall4FeeDebt' => '',
            'smsRecall5FeeDebt' => '',
            'smsReminderFeeDebt' => '',
            'smsReservationFeeDebt' => '',
            'partOfPaymentDebt' => '',
            'transferFeeDebt' => '',
        ];
        $found = $fines[$key] ?? $key;
        return $this->translateWithPrefix('fine_status_', $found, [], $key);
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

        $paymentConfig = $this->config['OnlinePayment'] ?? [];
        $blockedTypes = $paymentConfig['nonPayable'] ?? [];
        $payableMinDate
            = strtotime($paymentConfig['payableFineDateThreshold'] ?? '-5 years');

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
            $description = $this->mapAndTranslateFineType($debt->debtType) . ' - ' . $debt->debtNote;
            $debtDate = $this->dateFormat->convertFromDisplayDate(
                'U',
                $this->formatDate($debt->debtDate)
            );
            $payable = $amount > 0 && $debtDate >= $payableMinDate;
            if ($payable) {
                foreach ($blockedTypes as $blockedType) {
                    if (
                        $blockedType === $description
                        || (strncmp($blockedType, '/', 1) === 0
                        && substr_compare($blockedType, '/', -1) === 0
                        && preg_match($blockedType, $description))
                    ) {
                        $payable = false;
                        break;
                    }
                }
            }
            $fine = [
                'debt_id' => $debt->id,
                'fine_id' => $debt->id,
                'fineId' => $debt->id,
                'amount' => $amount,
                'checkout' => '',
                'fine' => $description,
                'balance' => $amount,
                'createdate' => $debt->debtDate,
                'payableOnline' => $payable,
                'organization' => trim($debt->organisation ?? ''),
            ];
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
                'patronId' => $patron['patronId'],
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
            } else {
                // Map the branch name to an actual branch ID
                $locations = $this->getPickUpLocations($patron, ['item_id' => $requestId]);
                foreach ($locations as $loc) {
                    if ($loc['locationDisplay'] === $pickupLocation) {
                        [, $branch] = explode('.', $loc['locationID'], 2);
                        $updateRequest['pickUpBranchId'] = $branch;
                    }
                }
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
        $entityId = !empty($holdDetails['item_id'])
            ? $holdDetails['item_id']
            : ($holdDetails['id'] ?? '');

        $reservationSource = 'catalogueRecordDetail';

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
        $patronId = $this->authenticatePatron($username, $password);
        // TODO: only normal implemented at this moment
        $holdType = 'normal';

        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'patronId' => $patronId,
            'language' => 'fi',
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
     * Update patron's email address
     *
     * @param array    $patron  Patron array
     * @param String   $email   Email address
     * @param int|null $emailId Email ID
     * @param bool     $active  Whether to set the email active
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    protected function updateEmailAddress($patron, $email, $emailId = null, $active = false)
    {
        if (empty($email) || $emailId === null) {
            return [
                'success' => true,
                'status' => 'No data to update',
                'sys_message' => '',
            ];
        }
        $validator = new \Laminas\Validator\EmailAddress();
        if (!$validator->isValid($email)) {
            return [
                'success' => false,
                'status' => 'Invalid email',
                'sys_message' => '',
            ];
        }

        $username = $patron['cat_username'];
        $password = $patron['cat_password'];

        $user = $this->getMyProfile($patron);

        $function = '';
        $functionResult = '';
        $functionParam = '';

        // Workaround for AWS issue where a bare plus sign gets converted to a space
        if ($this->config['updateEmail']['encodeEmailPlusSign'] ?? false) {
            $email = str_replace('+', '%2B', $email);
        }

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'patronId'     => $patron['patronId'],
            'address'      => $email,
            'isActive'     => $active ? 'yes' : 'no',
        ];
        if (!empty($user['email_0'])) {
            $conf['id'] = $user['email_' . $emailId . '_id'] ?? '';
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
     * Update patron's phone number
     *
     * @param array  $patron  Patron array
     * @param string $phone   Phone number
     * @param string $phoneId Phone ID
     * @param bool   $active  Whether to set the phone active
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    protected function updatePhoneNumber($patron, $phone, $phoneId = null, $active = false)
    {
        if (empty($phone) || $phoneId === null) {
            return [
                'success' => true,
                'status' => 'No data to update',
                'sys_message' => '',
            ];
        }
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
            'patronId'     => $user['patronId'],
            'areaCode'     => '',
            'country'      => $user['phoneCountry'] ?? 'FI',
            'localCode'    => $phone,
            'useForSms'    => $active ? 'yes' : 'no',
        ];

        if (!empty($user['phone_0'])) {
            $conf['id'] = $user['phone_' . $phoneId . '_id'] ?? '';
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
                [
                    'removeReservationsParam' =>
                    [
                        'arenaMember' => $this->arenaMember,
                        'user' => $username,
                        'password' => $password,
                        'language' => 'en',
                        'id' => $id,
                        'patronId' => $cancelDetails['patron']['patronId'],
                    ],
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

        $function = 'RenewLoans';
        $functionResult = 'renewLoansResponse';
        $patronId = $this->authenticatePatron($username, $password);

        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'patronId' => $patronId,
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
            $isRenewed = (string)($loan->loanStatus->status ?? '') === 'isRenewedToday';
            $results['details'][$id] = [
                'success' => $isRenewed,
                'status' => $isRenewed ? 'Loan renewed' : 'Renewal failed',
                'sysMessage' => $this->mapStatus($loan->loanStatus->status ?? '-', $function),
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
        $config = parent::getConfig($function);
        if ('updateAddress' === $function && isset($config['fields'])) {
            if (isset($params['patron'])) {
                $profile = $this->getMyProfile($params['patron']);
                $extraFields = [];
                foreach ($config['fields'] as $i => $field) {
                    [$label, $fieldId] = explode(':', $field);
                    if (in_array($fieldId, ['email', 'phone'])) {
                        // Create a new field for every email/phone the user has
                        foreach ($profile as $profileField => $value) {
                            $pattern = "/^{$fieldId}_\d+$/";
                            if (preg_match($pattern, $profileField)) {
                                $activation = in_array(
                                    $fieldId,
                                    $this->config['updateAddress']['contactInfoActivation'] ?? []
                                );
                                $extraField = [
                                    'field' => $profileField,
                                    'label' => $label,
                                    'type' => 'text',
                                ];
                                $extraFields[] = $extraField;
                                if ($activation) {
                                    $extraActiveField = [
                                        'field' => $profileField . '_active',
                                        'label' => $fieldId === 'email'
                                            ? $fieldId . '_active'
                                            : $fieldId . '_use_for_sms',
                                        'type' => 'boolean',
                                    ];
                                    $extraFields[] = $extraActiveField;
                                }
                            }
                        }
                        unset($config['fields'][$i]);
                    }
                }
                $config['fields'] = array_merge($config['fields'], $extraFields);
            }
        }
        return $config;
    }
}
