<?php

/**
 * VuFind Driver for Koha, using REST API
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Finna\ILS\Driver;

use Finna\ILS\Driver\Feature\FinnaCommonILSTrait;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\TranslatableString;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\Marc\MarcReader;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

/**
 * VuFind Driver for Koha, using REST API
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends \VuFind\ILS\Driver\KohaRest
{
    use FinnaCommonILSTrait;

    /**
     * Mappings from Koha messaging preferences
     *
     * @var array
     */
    protected $messagingPrefTypeMap = [
        'Advance_Notice' => 'dueDateAlert',
        'Auto_Renewals' => 'autoRenewal',
        'Hold_Filled' => 'pickUpNotice',
        'Hold_Reminder' => 'pickUpReminder',
        'Item_Check_in' => 'checkinNotice',
        'Item_Checkout' => 'checkoutNotice',
        'Item_Due' => 'dueDateNotice',
        'Ill_ready' => 'illRequestReadyForPickUp',
        'Ill_unavailable' => 'illRequestUnavailable',
        'Ill_update' => 'illRequestUpdate',
        'Patron_Expiry' => 'cardExpiry',
    ];

    /**
     * Item status rankings. The lower the value, the more important the status.
     *
     * @var array
     */
    protected $statusRankings = [
        'Lost--Library Applied' => 1,
        'Charged' => 2,
        'On Hold' => 3,
    ];

    /**
     * Whether to use location in addition to library when grouping holdings
     *
     * @param bool
     */
    protected $groupHoldingsByLocation;

    /**
     * Priority settings for the order of libraries or library/location combinations
     *
     * @var array
     */
    protected $holdingsLibraryOrder;

    /**
     * Priority settings for the order of locations (in libraries)
     *
     * @var array
     */
    protected $holdingsLocationOrder;

    /**
     * Minimum payable amount
     *
     * @var int
     */
    protected $minimumPayableAmount = 0;

    /**
     * Non-payable fine types
     *
     * @var array
     */
    protected $nonPayableTypes = [];

    /**
     * Non-payable fine statuses
     *
     * @var array
     */
    protected $nonPayableStatuses = [];

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

        // BC for online payment configuration:
        if (empty($this->config['OnlinePayment']) && !empty($this->config['onlinePayment'])) {
            $this->config['OnlinePayment'] = $this->config['onlinePayment'];
        }

        $this->patronStatusMappings['Patron::DebarredWithReason']
            = 'patron_status_restricted_with_reason';

        $this->groupHoldingsByLocation
            = $this->config['Holdings']['group_by_location']
            ?? '';

        if (isset($this->config['Holdings']['holdings_branch_order'])) {
            $values = explode(
                ':',
                $this->config['Holdings']['holdings_branch_order']
            );
            foreach ($values as $i => $value) {
                $parts = explode('=', $value, 2);
                $idx = $parts[1] ?? $i;
                $this->holdingsLibraryOrder[$parts[0]] = $idx;
            }
        }

        $this->holdingsLocationOrder
            = isset($this->config['Holdings']['holdings_location_order'])
            ? explode(':', $this->config['Holdings']['holdings_location_order'])
            : [];
        $this->holdingsLocationOrder = array_flip($this->holdingsLocationOrder);

        if ($typeMappings = (array)($this->config['MessagingPrefTypeMappings'] ?? [])) {
            $this->messagingPrefTypeMap = array_merge($this->messagingPrefTypeMap, $typeMappings);
        }
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
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, ?array $patron = null, array $options = [])
    {
        $data = parent::getHolding($id, $patron);
        // Remove request counts if necessary
        if (
            !empty($data['holdings'])
            && !($this->config['Holdings']['display_item_hold_counts'] ?? true)
        ) {
            foreach ($data['holdings'] as &$item) {
                if ('__HOLDINGSSUMMARYLOCATION__' !== $item['location']) {
                    unset($item['requests_placed']);
                }
            }
            unset($item);
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
        return $this->getItemStatusesForBiblio($id, null, true);
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
            $statuses = $this->getItemStatusesForBiblio($id, null, true);
            if (isset($statuses['holdings'])) {
                $items[] = array_merge(
                    $statuses['holdings'],
                    $statuses['electronic_holdings']
                );
            } else {
                $items[] = $statuses;
            }
        }
        return $items;
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
        // TODO: Make this use X-Koha-Embed when the endpoint allows
        $result = $this->makeRequest(['v1', 'patrons', $patron['id'], 'account']);

        $fines = [];
        foreach ($result['data']['outstanding_debits']['lines'] ?? [] as $entry) {
            $bibId = null;
            if (!empty($entry['item_id'])) {
                $item = $this->getItem($entry['item_id']);
                if (!empty($item['biblio_id'])) {
                    $bibId = $item['biblio_id'];
                }
            }
            $debitType = trim($entry['debit_type']);
            $debitStatus = trim($entry['status'] ?? '');
            $type = $this->feeTypeMappings[$debitType] ?? $debitType;
            $description = trim($entry['description']);
            $fine = [
                'fineId' => $entry['account_line_id'],
                'amount' => (int)round($entry['amount'] * 100),
                'balance' => (int)round($entry['amount_outstanding'] * 100),
                'fine' => $type,
                'description' => $description,
                'createdate' => $this->convertDate($entry['date'] ?? null),
                'checkout' => '',
                'organization' => $entry['library_id'] ?? '',
                '__status' => trim($entry['status'] ?? ''),
            ];
            $fine['payableOnline'] = $this->fineIsPayable($fine);
            $fine['taxPercent'] = $this->getFineTaxRate($fine, $entry);
            if (null !== $bibId) {
                $fine['id'] = $bibId;
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
        $result = $this->makeRequest(
            [
                'path' => ['v1', 'contrib', 'kohasuomi', 'patrons', $patron['id']],
                'query' => [
                    'query_blocks' => 1,
                    'query_relationships' => 1,
                    'query_messaging_preferences' => 1,
                    'query_messages' => 1,
                ],
            ]
        );
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }
        $result = $result['data'];

        $expirationDate = $this->convertDate($result['expiry_date'] ?? null);

        $guarantors = [];
        foreach ($result['guarantors'] ?? [] as $guarantor) {
            $guarantors[] = [
                'firstname' => $guarantor['firstname'],
                'lastname' => $guarantor['surname'],
            ];
        }
        $guarantees = [];
        foreach ($result['guarantees'] ?? [] as $guarantee) {
            $guarantees[] = [
                'firstname' => $guarantee['firstname'],
                'lastname' => $guarantee['surname'],
            ];
        }

        $messagingSettings = $this->config['Profile']['messagingSettings'] ?? true
            ? $this->createMessagingSettingsArray($result['messaging_preferences'])
            : [];

        $messages = [];
        foreach ($result['messages'] ?? [] as $message) {
            $messages[] = [
                'date' => $this->convertDate($message['date']),
                'library' => $this->getLibraryName($message['library_id']),
                'message' => $message['message'],
            ];
        }

        $phoneField = $this->config['Profile']['phoneNumberField'] ?? 'mobile';
        $smsField = $this->config['Profile']['smsNumberField'] ?? 'sms_number';
        $holdIdentifierField = $this->config['Profile']['holdIdentifierField'] ?? 'other_name';
        $callingNameField = $this->config['Profile']['callingNameField'] ?? '';

        $phone = $phoneField && !empty($result[$phoneField]) ? $result[$phoneField] : null;
        $smsnumber = $smsField && !empty($result[$smsField]) ? $result[$smsField] : null;

        return $this->createProfileArray(
            firstname: $result['firstname'],
            lastname: $result['surname'],
            phone: $phone,
            address1: $result['address'],
            address2: $result['address2'],
            zip: $result['postal_code'],
            city: $result['city'],
            country: $result['country'],
            expiration_date: $expirationDate,
            messagingServices: $messagingSettings,
            loan_history: $result['privacy'],
            email: $result['email'],
            nonDefaultFields: [
                'calling_name' => $result[$callingNameField] ?? '',
                'category' => $result['category_id'] ?? '',
                'expiration_soon' => !empty($result['expiry_date_near']),
                'expired' => !empty($result['blocks']['Patron::CardExpired']),
                'hold_identifier' => $result[$holdIdentifierField] ?? '',
                'guarantors' => $guarantors,
                'guarantees' => $guarantees,
                'notes' => $result['opac_notes'],
                'messages' => $messages,
                'full_data' => $result,
                'smsnumber' => $smsnumber,
            ]
        );
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
        return $this->updatePatron($patron, ['privacy' => (int)$state]);
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
        return $this->updatePatron($patron, ['mobile' => $phone]);
    }

    /**
     * Update patron's SMS alert number
     *
     * @param array  $patron Patron array
     * @param string $number SMS alert number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateSmsNumber($patron, $number)
    {
        $fields = !empty($this->config['updateSmsNumber']['fields'])
            ? explode(',', $this->config['updateSmsNumber']['fields'])
            : ['sms_number'];

        $update = [];
        foreach ($fields as $field) {
            $update[$field] = $number;
        }

        return $this->updatePatron($patron, $update);
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
        return $this->updatePatron($patron, ['email' => $email]);
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
        $request = [];
        $addressFields = [];
        $fieldConfig = $this->config['updateAddress']['fields'] ?? [];
        foreach ($fieldConfig as $field) {
            $parts = explode(':', $field);
            if (isset($parts[1])) {
                $addressFields[$parts[1]] = $parts[0];
            }
        }

        // Pick the configured fields from the request
        foreach ($details as $key => $value) {
            if (isset($addressFields[$key])) {
                $request[$key] = $value;
            }
        }

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'contrib', 'kohasuomi', 'patrons', $patron['id']],
                'json' => $request,
                'method' => 'PATCH',
                'errors' => true,
            ]
        );
        if ($result['code'] >= 300) {
            if (409 === $result['code'] && !empty($result['data']['conflict'])) {
                $keys = array_keys($result['data']['conflict']);
                $key = reset($keys);
                $fieldName = isset($addressFields[$key])
                    ? $this->translate($addressFields[$key])
                    : '???';
                $status = $this->translate(
                    'request_change_value_already_in_use',
                    ['%%field%%' => $fieldName]
                );
            } else {
                $status = 'Updating of patron information failed';
            }
            return [
                'success' => false,
                'status' => $status,
                'sys_message' => $result['data']['error'] ?? $result['code'],
            ];
        }

        return [
            'success' => true,
            'status' => 202 === $result['code']
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => '',
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
        $messagingSettings = [];
        foreach ($details as $prefId => $pref) {
            $result = [];
            foreach ($pref['settings'] as $settingId => $setting) {
                if (!empty($setting['readonly'])) {
                    continue;
                }
                if ('boolean' === $setting['type']) {
                    $result[$settingId] = [
                        'value' => $setting['active'],
                    ];
                } elseif ('select' === $setting['type']) {
                    $result[$settingId] = [
                        'value' => ctype_digit($setting['value'])
                            ? (int)$setting['value'] : $setting['value'],
                    ];
                } else {
                    foreach ($setting['options'] as $optionId => $option) {
                        $result[$settingId][$optionId] = $option['active'];
                    }
                }
            }
            $messagingSettings[$prefId] = $result;
        }

        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    'messaging_preferences',
                ],
                'json' => $messagingSettings,
                'method' => 'PUT',
            ]
        );
        if ($result['code'] >= 300) {
            return  [
                'success' => false,
                'status' => 'Updating of patron information failed',
                'sys_message' => $result['error'] ?? $result['code'],
            ];
        }

        return [
            'success' => true,
            'status' => $result['code'] == 202
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => '',
        ];
    }

    /**
     * Check if patron belongs to staff.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return bool True if patron is staff, false if not
     */
    public function getPatronStaffAuthorizationStatus($patron)
    {
        $result = $this->makeRequest(
            [
                'path' => ['v1', 'contrib', 'kohasuomi', 'patrons', $patron['id']],
                'query' => ['query_permissions' => 1],
            ]
        );

        return !empty(
            array_intersect(
                ['superlibrarian', 'catalogue'],
                $result['data']['permissions']
            )
        );
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
        $bibId = $holdDetails['id'] ?? null;
        $itemId = $holdDetails['item_id'] ?? false;
        $requestId = $holdDetails['reqnum'] ?? false;
        $requestType
            = array_key_exists('StorageRetrievalRequest', $holdDetails ?? [])
                ? 'StorageRetrievalRequests' : 'Holds';
        $included = null;
        if ($bibId && 'Holds' === $requestType) {
            // Collect library codes that are to be included
            $level = !empty($holdDetails['level']) ? $holdDetails['level'] : 'title';
            if ('copy' === $level && false === $itemId) {
                return [];
            }
            if ('copy' === $level) {
                $result = $this->makeRequest(
                    [
                        'path' => [
                            'v1', 'contrib', 'kohasuomi', 'availability', 'items',
                            $itemId, 'hold',
                        ],
                        'query' => [
                            'patron_id' => (int)$patron['id'],
                            'query_pickup_locations' => 1,
                        ],
                    ]
                );
                if (empty($result['data'])) {
                    return [];
                }
                $notes = $result['data']['availability']['notes'] ?? [];
                $included = $notes['Item::PickupLocations']['to_libraries'] ?? [];
            } else {
                $result = $this->makeRequest(
                    [
                        'path' => [
                            'v1', 'contrib', 'kohasuomi', 'availability', 'biblios',
                            $bibId, 'hold',
                        ],
                        'query' => [
                            'patron_id' => (int)$patron['id'],
                            'query_pickup_locations' => 1,
                            'ignore_patron_holds' => $requestId ? 1 : 0,
                        ],
                    ]
                );
                if (empty($result['data'])) {
                    return [];
                }
                $notes = $result['data']['availability']['notes'] ?? [];
                $included = $notes['Biblio::PickupLocations']['to_libraries'] ?? [];
            }
        }

        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];
        $locations = [];
        foreach ($this->getLibraries() as $library) {
            $code = $library['library_id'];
            if (
                (null === $included && !$library['pickup_location'])
                || in_array($code, $excluded)
                || (null !== $included && !in_array($code, $included))
            ) {
                continue;
            }
            $locations[] = [
                'locationID' => $code,
                'locationDisplay' => $library['name'],
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = $this->config['Holds']['pickUpLocationOrder'] ?? 'default';
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
        if ('getPatronStaffAuthorizationStatus' === $function) {
            return ['enabled' => true];
        }
        $functionConfig = parent::getConfig($function, $params);
        if ($functionConfig && 'OnlinePayment' === $function) {
            if (!isset($functionConfig['exactBalanceRequired'])) {
                $functionConfig['exactBalanceRequired'] = false;
            }
        }

        return $functionConfig;
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron information, if available
     * @param bool   $brief  Whether to return brief information only (getStatus)
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null, $brief = false)
    {
        $holdings = [];
        if (!empty($this->config['Holdings']['use_holding_records'])) {
            $holdingsResult = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'biblios', $id, 'holdings',
                    ],
                    'errors' => true,
                ]
            );
            if (404 === $holdingsResult['code']) {
                return [];
            }
            if (200 !== $holdingsResult['code']) {
                throw new ILSException('Problem with Koha REST API.');
            }

            // Turn the results into a keyed array
            foreach ($holdingsResult['data']['holdings'] ?? [] as $holding) {
                $holdings[$holding['holding_id']] = $holding;
            }
        }

        $requestParams = [
            'path' => [
                'v1', 'contrib', 'kohasuomi', 'availability', 'biblios', $id,
                'search',
            ],
            'errors' => true,
            'query' => [],
        ];
        if ($this->includeSuspendedHoldsInQueueLength) {
            $requestParams['query']['include_suspended_in_hold_queue'] = '1';
        }

        $result = $this->makeRequest($requestParams);
        if (404 === $result['code']) {
            return [];
        }
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $statuses = [];
        $itemsTotal = 0;
        $orderedTotal = 0;
        $availableTotal = 0;
        $requestsTotal = 0;
        foreach ($result['data']['item_availabilities'] ?? [] as $i => $item) {
            // $holding is a reference!
            unset($holding);
            if (
                !empty($item['holding_id'])
                && isset($holdings[$item['holding_id']])
            ) {
                $holding = &$holdings[$item['holding_id']];
                if ($holding['suppressed']) {
                    continue;
                }
            }
            $avail = $item['availability'];
            $available = $avail['available'];
            $statusCodes = $this->getItemStatusCodes($item);
            $status = $this->pickStatus($statusCodes);
            if (
                isset($avail['unavailabilities']['Item::CheckedOut']['due_date'])
                && !isset($avail['unavailabilities']['Item::Lost'])
            ) {
                $duedate = $this->convertDate(
                    $avail['unavailabilities']['Item::CheckedOut']['due_date'],
                    true
                );
            } else {
                $duedate = null;
            }

            $location = $this->getItemLocationName($item);
            $callnumber = $this->getItemCallNumber($item);

            if (!$this->useHomeLibrary && null !== $item['holding_library_id']) {
                $libraryId = $item['holding_library_id'];
            } else {
                $libraryId = $item['home_library_id'];
            }
            // Check holding library and modify status if not in home library:
            if (
                $this->useHomeLibrary && null !== $item['holding_library_id']
                && $item['home_library_id'] !== $item['holding_library_id']
                && 'On Shelf' === $status
            ) {
                $available = false;
                $status = 'Not Available';
                array_unshift($statusCodes, 'Not Available');
            }
            $locationId = $item['location'];

            $number = $item['serial_issue_number'];
            if (!$number) {
                $number = $this->getItemSpecificLocation($item);
            } else {
                $number .= ' ' . $this->getItemSpecificLocation($item);
            }

            $requests = max(
                [$item['hold_queue_length'],
                $result['data']['hold_queue_length']]
            );

            if (-1 === ($avail['unavailabilities']['Item::NotForLoan']['status'] ?? null)) {
                ++$orderedTotal;
            } else {
                ++$itemsTotal;
            }
            if ($available) {
                ++$availableTotal;
            }
            $requestsTotal = max($requestsTotal, $requests);

            $extraStatusInformation = [];
            if ($transit = $avail['unavailabilities']['Item::Transfer'] ?? null) {
                if (null !== ($toLibrary = $transit['to_library'] ?? null)) {
                    $extraStatusInformation['location'] = $this->getLibraryName($toLibrary);
                    if ($status == 'HoldingStatus::transit_to_date') {
                        $extraStatusInformation['date'] = $this->convertDate(
                            $transit['datesent'],
                            true
                        );
                    }
                }
            }

            $entry = [
                'id' => $id,
                'item_id' => $item['item_id'],
                'location' => $location,
                'availability' => new AvailabilityStatus($available, $status, $extraStatusInformation),
                'status' => $status,
                'status_array' => $statusCodes,
                'reserve' => 'N',
                'callnumber' => $callnumber,
                'duedate' => $duedate,
                'number' => $number,
                'barcode' => $item['external_id'],
                'sort' => $i,
                'requests_placed' => $requests,
                'libraryId' => $libraryId,
                'locationId' => $locationId,
            ];
            if (!empty($item['public_notes'])) {
                $entry['item_notes'] = [$item['public_notes']];
            }

            if ($patron && $this->itemHoldAllowed($item)) {
                $entry['is_holdable'] = true;
                $entry['level'] = 'copy';
                $entry['addLink'] = 'check';
            } else {
                $entry['is_holdable'] = false;
            }

            if ($patron && $this->itemArticleRequestAllowed($item)) {
                $entry['storageRetrievalRequest'] = 'auto';
                $entry['addStorageRetrievalRequestLink'] = 'check';
            }

            if (isset($holding)) {
                $entry += $this->getHoldingData($holding);
                $holding['_hasItems'] = true;
            }

            $statuses[] = $entry;
        }
        // $holding is a reference!
        unset($holding);

        if (!isset($i)) {
            $i = 0;
        }

        // Add holdings that don't have items
        foreach ($holdings as $holding) {
            if ($holding['suppressed'] || !empty($holding['_hasItems'])) {
                continue;
            }
            $holdingData = $this->getHoldingData($holding);
            $i++;
            $entry = $this->createHoldingsEntry($id, $holding, $i);
            $entry += $holdingData;

            $statuses[] = $entry;
        }

        // Add serial purchase information
        if (!$brief && !empty($this->config['Holdings']['use_serial_subscriptions'])) {
            $serialsResult = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'biblios', $id,
                        'serialsubscriptions',
                    ],
                    'errors' => true,
                ]
            );
            if (404 === $serialsResult['code']) {
                return [];
            }
            if (200 !== $serialsResult['code']) {
                throw new ILSException('Problem with Koha REST API.');
            }

            // Turn the results into a keyed array
            if (!empty($serialsResult['data']['subscriptions'])) {
                $currentYear = date('Y');
                $lastYear = $currentYear - 1;
                $filter = $this->config['Holdings']['serial_subscription_filter']
                    ?? '';
                $yearFilter = 'current+1' === $filter;
                foreach ($serialsResult['data']['subscriptions'] as $subscription) {
                    $i++;
                    $seqs = [];
                    $latestReceived = 0;
                    if ('last year' === $filter) {
                        foreach ($subscription['issues'] as $issue) {
                            if (!$issue['received']) {
                                continue;
                            }
                            [$year] = explode('-', $issue['publisheddate'] ?? '');
                            if ($year > $latestReceived) {
                                $latestReceived = $year;
                            }
                        }
                    }
                    foreach ($subscription['issues'] as $issue) {
                        if (!$issue['received']) {
                            continue;
                        }
                        [$year] = explode('-', $issue['publisheddate'] ?? '');
                        if ($yearFilter) {
                            // Limit to current and last year
                            if (
                                $year && $year != $currentYear
                                && $year != $lastYear
                            ) {
                                continue;
                            }
                        } elseif ($latestReceived && $year != $latestReceived) {
                            continue;
                        }
                        $seq = $issue['serialseq'];
                        if ($issue['notes']) {
                            $seq .= ' ' . $issue['notes'];
                        }
                        $seqs[] = $seq;
                    }
                    $seqs = array_unique($seqs);
                    natsort($seqs);
                    $issues = [];
                    foreach (array_reverse($seqs) as $seq) {
                        $issues[] = [
                            'issue' => $seq,
                        ];
                    }

                    $entry = $this->createSerialEntry($subscription, $i);

                    foreach ($statuses as &$status) {
                        if (
                            $status['callnumber'] === $entry['callnumber']
                            && $status['location'] === $entry['location']
                        ) {
                            $status['purchase_history'] = $issues;
                            $entry = null;
                            break;
                        }
                    }
                    unset($status);
                    if (null === $entry) {
                        continue;
                    }
                    $entry['purchase_history'] = $issues;
                    $statuses[] = $entry;
                }
            }
        }

        // See if there are links in holdings
        $electronic = [];
        foreach ($holdings as $holding) {
            if ($holding['suppressed']) {
                continue;
            }
            $marc = $this->getHoldingMarc($holding);
            if (null === $marc) {
                continue;
            }

            $notes = [];
            if ($fields = $marc->getFields('852')) {
                foreach ($fields as $field) {
                    if ($subfield = $marc->getSubfield($field, 'z')) {
                        $notes[] = $subfield;
                    }
                }
            }
            if ($fields = $marc->getFields('856')) {
                foreach ($fields as $field) {
                    if ($subfields = $field['subfields'] ?? []) {
                        $urls = [];
                        $desc = [];
                        $parts = [];
                        foreach ($subfields as $subfield) {
                            if ('u' === $subfield['code']) {
                                $urls[] = $subfield['data'];
                            } elseif ('3' === $subfield['code']) {
                                $parts[] = $subfield['data'];
                            } elseif (in_array($subfield['code'], ['y', 'z'])) {
                                $desc[] = $subfield['data'];
                            }
                        }
                        foreach ($urls as $url) {
                            ++$i;
                            $entry
                                = $this->createHoldingsEntry($id, $holding, $i);
                            $entry['availability'] = true;
                            $entry['location'] = implode('. ', $desc);
                            $entry['locationhref'] = $url;
                            $entry['use_unknown_message'] = false;
                            $entry['status']
                                = implode('. ', array_merge($parts, $notes));
                            $electronic[] = $entry;
                        }
                    }
                }
            }
        }

        usort($statuses, [$this, 'statusSortFunction']);
        usort($electronic, [$this, 'statusSortFunction']);

        // Add summary
        $summary = [
            'id' => $id,
            'available' => $availableTotal,
            'total' => $itemsTotal,
            'ordered' => $orderedTotal,
            'locations' => count(array_unique(array_column($statuses, 'location'))),
            'availability' => null,
            'callnumber' => '',
            'location' => '__HOLDINGSSUMMARYLOCATION__',
        ];
        if (!empty($this->config['Holdings']['display_total_hold_count'])) {
            $summary['reservations'] = $requestsTotal;
        }
        $statuses[] = $summary;

        return [
            'holdings' => $statuses,
            'electronic_holdings' => $electronic,
        ];
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
        $aKey = $a['libraryId'] . '/' . $a['locationId'];
        $orderA = $this->holdingsLibraryOrder[$aKey]
            ?? $this->holdingsLibraryOrder[$a['libraryId']]
            ?? 999;
        $bKey = $b['libraryId'] . '/' . $b['locationId'];
        $orderB = $this->holdingsLibraryOrder[$bKey]
            ?? $this->holdingsLibraryOrder[$b['libraryId']]
            ?? 999;
        $result = $orderA - $orderB;

        if (0 === $result) {
            $orderA = $this->holdingsLocationOrder[$a['locationId']] ?? 999;
            $orderB = $this->holdingsLocationOrder[$b['locationId']] ?? 999;
            $result = $orderA - $orderB;
        }

        if (0 === $result) {
            $result = strcmp($a['location'], $b['location']);
        }

        if (0 === $result && $this->sortItemsBySerialIssue) {
            // Reverse chronological order
            $result = strnatcmp($b['number'] ?? '', $a['number'] ?? '');
        }

        if (0 === $result) {
            $result = $a['sort'] - $b['sort'];
        }

        return $result;
    }

    /**
     * Update a patron in Koha with the data in $fields
     *
     * @param array $patron The patron array from patronLogin
     * @param array $fields Patron fields to update
     *
     * @return array ILS driver response
     */
    protected function updatePatron($patron, $fields)
    {
        $result = $this->makeRequest(['v1', 'patrons', $patron['id']]);

        $request = $result['data'];
        // Unset read-only fields
        unset($request['anonymized']);
        unset($request['restricted']);
        unset($request['expired']);

        $request = array_merge($request, $fields);

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $patron['id']],
                'json' => $request,
                'method' => 'PUT',
                'errors' => true,
            ]
        );
        if ($result['code'] >= 300) {
            return [
                'success' => false,
                'status' => 'Updating of patron information failed',
                'sys_message' => $result['data']['error'] ?? $result['code'],
            ];
        }

        return [
            'success' => true,
            'status' => 202 === $result['code']
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => '',
        ];
    }

    /**
     * Return a location for a Koha item
     *
     * @param array $item Item
     *
     * @return string
     */
    protected function getItemLocationName($item)
    {
        $result = parent::getItemLocationName($item);

        if ($this->groupHoldingsByLocation) {
            $location = $this->translateLocation(
                $item['location'],
                !empty($item['location_description'])
                    ? $item['location_description'] : $item['location']
            );
            if ($location) {
                // Empty translation will result in &#x200C
                $emptyChar = html_entity_decode('&#x200C;', ENT_NOQUOTES, 'UTF-8');
                if ($result && $result !== $emptyChar) {
                    $result .= ', ';
                }
                $result .= $location;
            }
        }
        return $result;
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
                case 'collection_code':
                    if (!empty($item['collection_code'])) {
                        $collection = $this->translateCollection(
                            $item['collection_code'],
                            $item['collection_code_description']
                                ?? $item['collection_code']
                        );
                        if ($collection) {
                            $result[] = $collection;
                        }
                    }
                    break;
                case 'location':
                    if (!empty($item['location'])) {
                        $location = $this->translateLocation(
                            $item['location'],
                            !empty($item['location_description'])
                                ? $item['location_description'] : $item['location']
                        );
                        if ($location) {
                            $result[] = $location;
                        }
                    }
                    break;
                case 'sub_location':
                    if (!empty($item['sub_location'])) {
                        $subLocations = $this->getSubLocations();
                        // Before Koha 23.11, authorized values contained authorised_value and lib_opac.
                        // From 23.11, they are 'value' and 'opac_description' (see Koha bug 32981):
                        $subLocation = $subLocations[$item['sub_location']] ?? [];
                        $result[] = $this->translateSubLocation(
                            $item['sub_location'],
                            $subLocation['opac_description'] ?? $subLocation['lib_opac'] ?? null
                        );
                    }
                    break;
                case 'callnumber':
                    if (!empty($item['callnumber'])) {
                        $result[] = $item['callnumber'];
                    }
                    break;
            }
        }

        return implode(', ', $result);
    }

    /**
     * Translate sub-location name
     *
     * @param string $location Location code
     * @param string $default  Default value if translation is not available
     *
     * @return string
     */
    protected function translateSubLocation($location, $default = null)
    {
        if (empty($location)) {
            return $default ?? '';
        }
        $prefix = !empty($this->config['Catalog']['id'])
            ? ($this->config['Catalog']['id'] . '_')
            : '';
        $prefix .= 'sub_location_';

        return $this->translate(
            "$prefix$location",
            null,
            $default ?? $location
        );
    }

    /**
     * Get sub-locations from cache or from the API
     *
     * @return array
     */
    protected function getSubLocations()
    {
        $cacheKey = 'sublocations';
        $locations = $this->getCachedData($cacheKey);
        if (null === $locations) {
            $result = $this->makeRequest(
                'v1/contrib/kohasuomi/authorised_values/authorised_value_categories/'
                . '?authorised_value_category=SUBLOC'
            );
            $locations = [];
            foreach ($result['data'] as $location) {
                // Before Koha 23.11, authorized values contained authorised_value field.
                // From 23.11, it is 'value' (see Koha bug 32981):
                $locations[$location['value'] ?? $location['authorised_value']] = $location;
            }
            $this->putCachedData($cacheKey, $locations, 3600);
        }
        return $locations;
    }

    /**
     * Create a holdings entry
     *
     * @param string $id       Bib ID
     * @param array  $holdings Holdings record
     * @param int    $sortKey  Sort key
     *
     * @return array
     */
    protected function createHoldingsEntry($id, $holdings, $sortKey)
    {
        $location = $this->getLibraryName($holdings['holding_library_id']);

        if ($this->groupHoldingsByLocation) {
            $holdingLoc = $this->translateLocation(
                $holdings['location'],
                !empty($holdings['location_description'])
                    ? $holdings['location_description'] : $holdings['location']
            );
            if ($holdingLoc) {
                if ($location) {
                    $location .= ', ';
                }
                $location .= $holdingLoc;
            }
        }

        $callnumber = $this->getItemCallNumber($holdings);
        $libraryId = $holdings['holding_library_id'];
        $locationId = $holdings['location'];

        return [
            'id' => $id,
            'item_id' => 'HLD_' . $holdings['biblionumber'],
            'location' => $location,
            'requests_placed' => 0,
            'status' => '',
            'use_unknown_message' => true,
            'availability' => false,
            'duedate' => '',
            'barcode' => '',
            'callnumber' => $callnumber,
            'sort' => $sortKey,
            'libraryId' => $libraryId,
            'locationId' => $locationId,
        ];
    }

    /**
     * Create a serial entry
     *
     * @param array $subscription Subscription record
     * @param int   $sortKey      Sort key
     *
     * @return array
     */
    protected function createSerialEntry($subscription, $sortKey)
    {
        $item = [
            'home_library_id' => $subscription['library_id'],
            'holding_library_id' => $subscription['library_id'],
            'location' => $subscription['location'],
            'location_description' => $subscription['location_description'] ?? null,
            'callnumber' => $subscription['callnumber'] ?? null,
        ];
        $location = $this->getItemLocationName($item);
        $callnumber = $this->getItemCallNumber($item);

        return [
            'item_id' => "SERIAL_$sortKey",
            'location' => $location,
            'callnumber' => $callnumber,
            'libraryId' => $subscription['library_id'],
            'locationId' => $subscription['location'],
            'requests_placed' => 0,
            'availability' => false,
            'use_unknown_message' => true,
            'sort' => $sortKey,
            'duedate' => null,
            'status' => '',
            'barcode' => null,
        ];
    }

    /**
     * Return a call number for a Koha item
     *
     * @param array $item Item
     *
     * @return string
     */
    protected function getItemCallNumber($item)
    {
        $result = [];
        if (
            !empty($item['collection_code'])
            && !empty($this->config['Holdings']['display_ccode'])
        ) {
            $result[] = $this->translateCollection(
                $item['collection_code'],
                $item['collection_code_description'] ?? $item['collection_code']
            );
        }
        if (!$this->groupHoldingsByLocation) {
            $loc = $this->translateLocation(
                $item['location'],
                !empty($item['location_description'])
                    ? $item['location_description'] : $item['location']
            );
            if ($loc) {
                $result[] = $loc;
            }
        }
        if (
            (!empty($item['callnumber']) || !empty($item['callnumber_display']))
            && !empty($this->config['Holdings']['display_full_call_number'])
        ) {
            $result[] = $item['callnumber'];
        }
        return implode(', ', $result);
    }

    /**
     * Get a MARC record for the given holding or null if not available
     *
     * @param array $holding Holding
     *
     * @return MarcReader
     */
    protected function getHoldingMarc(&$holding)
    {
        if (!isset($holding['_marcRecord'])) {
            foreach ($holding['holdings_metadata'] ?? [$holding['metadata']] as $metadata) {
                if (
                    'marcxml' === $metadata['format']
                    && 'MARC21' === $metadata['schema']
                ) {
                    $holding['_marcRecord'] = new MarcReader($metadata['metadata']);
                    return $holding['_marcRecord'];
                }
            }
            $holding['_marcRecord'] = null;
        }
        return $holding['_marcRecord'];
    }

    /**
     * Get holding data from a holding record
     *
     * @param array $holding Holding record from Koha
     *
     * @return array
     */
    protected function getHoldingData(&$holding)
    {
        $marc = $this->getHoldingMarc($holding);
        if (null === $marc) {
            return [];
        }

        $marcDetails = [];

        // Get Notes
        $data = $this->getMARCData(
            $marc,
            $this->config['Holdings']['notes']
            ?? '852z'
        );
        if ($data) {
            $marcDetails['notes'] = $data;
        }

        // Get Summary (may be multiple lines)
        $data = $this->getMARCData(
            $marc,
            $this->config['Holdings']['summary']
            ?? '866a'
        );
        if ($data) {
            $marcDetails['summary'] = $data;
        }

        // Get Supplements
        if (isset($this->config['Holdings']['supplements'])) {
            $data = $this->getMARCData(
                $marc,
                $this->config['Holdings']['supplements']
            );
            if ($data) {
                $marcDetails['supplements'] = $data;
            }
        }

        // Get Indexes
        if (isset($this->config['Holdings']['indexes'])) {
            $data = $this->getMARCData(
                $marc,
                $this->config['Holdings']['indexes']
            );
            if ($data) {
                $marcDetails['indexes'] = $data;
            }
        }

        // Get links
        if (isset($this->config['Holdings']['links'])) {
            $data = $this->getMARCData(
                $marc,
                $this->config['Holdings']['links']
            );
            if ($data) {
                $marcDetails['links'] = $data;
            }
        }

        // Make sure to return an empty array unless we have details to display
        if (!empty($marcDetails)) {
            $marcDetails['holdings_id'] = $holding['holding_id'];
        }

        return $marcDetails;
    }

    /**
     * Get specified fields from a MARC Record
     *
     * @param MarcReader   $record     Marc reader
     * @param array|string $fieldSpecs Array or colon-separated list of
     * field/subfield specifications (3 chars for field code and then subfields,
     * e.g. 866az)
     *
     * @return string|array Results as a string if single, array if multiple
     */
    protected function getMARCData(MarcReader $record, $fieldSpecs)
    {
        if (!is_array($fieldSpecs)) {
            $fieldSpecs = explode(':', $fieldSpecs);
        }
        $results = '';
        foreach ($fieldSpecs as $fieldSpec) {
            $fieldCode = substr($fieldSpec, 0, 3);
            $subfieldCodes = substr($fieldSpec, 3);
            if ($fields = $record->getFields($fieldCode)) {
                foreach ($fields as $field) {
                    if ($subfields = $field['subfields'] ?? []) {
                        $line = '';
                        foreach ($subfields as $subfield) {
                            if (!strstr($subfieldCodes, $subfield['code'])) {
                                continue;
                            }
                            if ($line) {
                                $line .= ' ';
                            }
                            $line .= $subfield['data'];
                        }
                        if ($line) {
                            if (!$results) {
                                $results = $line;
                            } else {
                                if (!is_array($results)) {
                                    $results = [$results];
                                }
                                $results[] = $line;
                            }
                        }
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Translate collection name
     *
     * @param string $code        Collection code
     * @param string $description Collection description
     *
     * @return string
     */
    protected function translateCollection($code, $description)
    {
        $prefix = 'collection_';
        if (!empty($this->config['Catalog']['id'])) {
            $prefix .= $this->config['Catalog']['id'] . '_';
        }
        return $this->translate(
            "$prefix$code",
            null,
            $description
        );
    }

    /**
     * Translate location name
     *
     * @param string $location Location code
     * @param string $default  Default value if translation is not available
     *
     * @return string
     */
    protected function translateLocation($location, $default = null)
    {
        $defaultTranslation = parent::translateLocation($location, $default);
        if (empty($this->config['Catalog']['id'])) {
            return $defaultTranslation;
        }

        // Try first with location_ prefix:
        $prefix = 'location_' . $this->config['Catalog']['id'] . '_';
        $key = "$prefix$location";
        $translated = $this->translate($key);
        if ($translated !== $key) {
            return $translated;
        }
        // Fall back to just catalog id:
        $prefix = $this->config['Catalog']['id'] . '_';
        return $this->translate("$prefix$location", [], $defaultTranslation);
    }

    /**
     * Get a description for a block
     *
     * @param string $reason  Koha block reason
     * @param array  $details Any details related to the reason
     *
     * @return string
     */
    protected function getPatronBlockReason($reason, $details)
    {
        $params = [];
        switch ($reason) {
            case 'Hold::MaximumHoldsReached':
                $params = [
                    '%%blockCount%%' => $details['current_hold_count'],
                    '%%blockLimit%%' => $details['max_holds_allowed'],
                ];
                break;
            case 'Patron::Debt':
            case 'Patron::DebtGuarantees':
                $count = isset($details['current_outstanding'])
                    ? $this->formatMoney($details['current_outstanding'])
                    : '-';
                $limit = isset($details['max_outstanding'])
                    ? $this->formatMoney($details['max_outstanding'])
                    : '-';
                $params = [
                    '%%blockCount%%' => $count,
                    '%%blockLimit%%' => $limit,
                ];
                break;
            case 'Patron::Debarred':
                if (!empty($details['comment'])) {
                    $params = [
                        '%%reason%%' => $details['comment'],
                    ];
                    $reason = 'Patron::DebarredWithReason';
                }
                break;
            case 'Patron::CardExpired':
                $params = [
                    '%%expirationDate%%'
                        => $this->convertDate($details['expiration_date']),
                ];
                break;
        }
        return $this->translate($this->patronStatusMappings[$reason] ?? '', $params);
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked-out items
     * or checked-in items) by a specific patron.
     *
     * Finna: adds materialType
     *
     * @param array $patron    The patron array from patronLogin
     * @param array $params    Parameters
     * @param bool  $checkedIn Whether to list checked-in items
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    protected function getTransactions($patron, $params, $checkedIn)
    {
        $pageSize = $params['limit'] ?? 50;
        $sort = match ($params['sort'] ?? null) {
            '-checkout_date',
            '+checkout_date',
            '-checkin_date',
            '+checkin_date',
            '-due_date',
            '+due_date' => $params['sort'],
            '+title' => '+title,+subtitle',
            default => $checkedIn ? '-checkout_date' : '+due_date',
        };
        $queryParams = [
            '_order_by' => $sort,
            '_page' => $params['page'] ?? 1,
            '_per_page' => $pageSize,
        ];
        if ($checkedIn) {
            $queryParams['checked_in'] = '1';
            $arrayKey = 'transactions';
        } else {
            $arrayKey = 'records';
        }
        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    'checkouts',
                ],
                'query' => $queryParams,
            ]
        );

        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        if (empty($result['data'])) {
            return [
                'count' => 0,
                $arrayKey => [],
            ];
        }
        $transactions = [];
        foreach ($result['data'] as $entry) {
            $dueStatus = false;
            $now = time();
            $dueTimeStamp = strtotime($entry['due_date']);
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = 'overdue';
                } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                    $dueStatus = 'due';
                }
            }

            $renewable = $entry['renewable'];
            // Koha 22.11 introduced a backward compatibility break by renaming
            // renewals to renewals_count (bug 30275), so check both:
            $renewals = $entry['renewals_count'] ?? $entry['renewals'];
            $renewLimit = $entry['max_renewals'];
            $message = '';
            if (!$renewable && !$checkedIn) {
                $message = $this->mapRenewalBlockReason(
                    $entry['renewability_blocks'],
                    $entry['item_itype']
                );
                $permanent = in_array(
                    $entry['renewability_blocks'],
                    $this->permanentRenewalBlocks
                );
                if ($permanent) {
                    $renewals = null;
                    $renewLimit = null;
                }
            }

            $materialType = '';
            if (!empty($this->config['Loans']['displayItemType'])) {
                $materialType = ($entry['item_itype'] ?? null)
                    ?: ($entry['biblio_itype'] ?? null) ?: '';
                if ($materialType) {
                    $prefix = 'material_type_';
                    if (!empty($this->config['Catalog']['id'])) {
                        $prefix .= $this->config['Catalog']['id'] . '_';
                    }
                    $materialType = new TranslatableString(
                        $prefix . $materialType,
                        $materialType
                    );
                }
            }

            $transaction = [
                'id' => $entry['biblio_id'],
                'checkout_id' => $entry['checkout_id'],
                'item_id' => $entry['item_id'],
                'barcode' => $entry['external_id'] ?? null,
                'title' => $this->getBiblioTitle($entry),
                // enumchron should have been mapped to serial_issue_number, but the
                // mapping is missing from all plugin versions up to v22.05.02:
                'volume' => $entry['serial_issue_number'] ?? $entry['enumchron']
                    ?? '',
                'publication_year' => $entry['copyright_date']
                    ?? $entry['publication_year'] ?? '',
                'borrowingLocation' => $this->getLibraryName($entry['library_id']),
                'checkoutDate' => $this->convertDate($entry['checkout_date']),
                'duedate' => $this->convertDate($entry['due_date'], true),
                'returnDate' => $this->convertDate($entry['checkin_date']),
                'dueStatus' => $dueStatus,
                'renew' => $renewals,
                'renewLimit' => $renewLimit,
                'renewable' => $renewable,
                'message' => $message,
                'materialType' => $materialType,
            ];

            $transactions[] = $transaction;
        }

        return [
            'count' => $result['headers']['X-Total-Count'] ?? count($transactions),
            $arrayKey => $transactions,
        ];
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
        $client = parent::createHttpClient($url);
        $client->setOptions(['keepalive' => false]);
        return $client;
    }
}
