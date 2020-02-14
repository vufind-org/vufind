<?php
/**
 * VuFind Driver for Koha, using REST API
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017-2020.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

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
    /**
     * Mappings from Koha messaging preferences
     *
     * @var array
     */
    protected $messagingPrefTypeMap = [
        'Advance_Notice' => 'dueDateAlert',
        'Hold_Filled' => 'pickUpNotice',
        'Item_Check_in' => 'checkinNotice',
        'Item_Checkout' => 'checkoutNotice',
        'Item_Due' => 'dueDateNotice'
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

        $this->groupHoldingsByLocation
            = isset($this->config['Holdings']['group_by_location'])
            ? $this->config['Holdings']['group_by_location']
            : '';

        if (isset($this->config['Holdings']['holdings_branch_order'])) {
            $values = explode(
                ':', $this->config['Holdings']['holdings_branch_order']
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
        $data = parent::getHolding($id, $patron);
        if (!empty($data['holdings'])) {
            $summary = $this->getHoldingsSummary($data['holdings']);

            // Remove request counts before adding the summary if necessary
            if (isset($this->config['Holdings']['display_item_hold_counts'])
                && !$this->config['Holdings']['display_item_hold_counts']
            ) {
                foreach ($data['holdings'] as &$item) {
                    unset($item['requests_placed']);
                }
            }

            $data['holdings'][] = $summary;
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
        $data = $this->getItemStatusesForBiblio($id, null, true);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
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
            [
                'path' => ['v1', 'contrib', 'kohasuomi', 'patrons', $patron['id']],
                'query' => [
                    'query_blocks' => 1,
                    'query_relationships' => 1,
                    'query_messaging_preferences' => 1,
                ]
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
                'lastname' => $guarantor['surname']
            ];
        }
        $guarantees = [];
        foreach ($result['guarantees'] ?? [] as $guarantee) {
            $guarantees[] = [
                'firstname' => $guarantee['firstname'],
                'lastname' => $guarantee['surname']
            ];
        }

        foreach ($result['messaging_preferences'] as $type => $prefs) {
            $typeName = isset($this->messagingPrefTypeMap[$type])
                ? $this->messagingPrefTypeMap[$type] : $type;
            $settings = [
                'type' => $typeName
            ];
            if (isset($prefs['transport_types'])) {
                $settings['settings']['transport_types'] = [
                    'type' => 'multiselect'
                ];
                foreach ($prefs['transport_types'] as $key => $active) {
                    $settings['settings']['transport_types']['options'][$key] = [
                        'active' => $active
                    ];
                }
            }
            if (isset($prefs['digest'])) {
                $settings['settings']['digest'] = [
                    'type' => 'boolean',
                    'name' => '',
                    'active' => $prefs['digest']['value'],
                    'readonly' => !$prefs['digest']['configurable']
                ];
            }
            if (isset($prefs['days_in_advance'])
                && ($prefs['days_in_advance']['configurable']
                || null !== $prefs['days_in_advance']['value'])
            ) {
                $options = [];
                for ($i = 0; $i <= 30; $i++) {
                    $options[$i] = [
                        'name' => $this->translate(
                            1 === $i ? 'messaging_settings_num_of_days'
                            : 'messaging_settings_num_of_days_plural',
                            ['%%days%%' => $i]
                        ),
                        'active' => $i == $prefs['days_in_advance']['value']
                    ];
                }
                $settings['settings']['days_in_advance'] = [
                    'type' => 'select',
                    'value' => $prefs['days_in_advance']['value'],
                    'options' => $options,
                    'readonly' => !$prefs['days_in_advance']['configurable']
                ];
            }
            $messagingSettings[$type] = $settings;
        }

        $phoneField = isset($this->config['Profile']['phoneNumberField'])
            ? $this->config['Profile']['phoneNumberField']
            : 'mobile';

        $smsField = isset($this->config['Profile']['smsNumberField'])
            ? $this->config['Profile']['smsNumberField']
            : 'sms_number';

        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $phoneField && !empty($result[$phoneField])
                ? $result[$phoneField] : '',
            'smsnumber' => $smsField ? $result[$smsField] : '',
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['postal_code'],
            'city' => $result['city'],
            'country' => $result['country'],
            'category' => $result['category_id'] ?? '',
            'expiration_date' => $expirationDate,
            'hold_identifier' => $result['other_name'],
            'guarantors' => $guarantors,
            'guarantees' => $guarantees,
            'loan_history' => $result['privacy'],
            'messagingServices' => $messagingSettings,
            'notes' => $result['opac_notes'],
            'full_data' => $result
        ];
    }

    /**
     * Purge Patron Transaction History
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return array Associative array of the results
     */
    public function purgeTransactionHistory($patron)
    {
        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    'checkouts', 'history'
                ],
                'method' => 'DELETE',
                'errors' => true
            ]
        );
        if (!in_array($result['code'], [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Purging the loan history failed',
                'sys_message' => $result['data']['error'] ?? $result['code']
            ];
        }

        return [
            'success' => true,
            'status' => 'loan_history_purged',
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
            : ['smsalertnumber'];

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
        $result = $this->makeRequest(
            ['v1', 'patrons', $patron['id']]
        );

        $request = $result['data'];
        // Unset read-only fields
        unset($request['anonymized']);
        unset($request['restricted']);

        $addressFields = [];
        $fieldConfig = isset($this->config['updateAddress']['fields'])
            ? $this->config['updateAddress']['fields'] : [];
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
                'path' => ['v1', 'patrons', $patron['id']],
                'json' >= $request,
                'method' => 'PUT',
                'errors' => true
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
                'sys_message' => $result['data']['error'] ?? $result['code']
            ];
        }

        return [
            'success' => true,
            'status' => 202 === $result['code']
                ? 'request_change_done' : 'request_change_accepted',
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
        $messagingSettings = [];
        foreach ($details as $prefId => $pref) {
            $result = [];
            foreach ($pref['settings'] as $settingId => $setting) {
                if (!empty($setting['readonly'])) {
                    continue;
                }
                if ('boolean' === $setting['type']) {
                    $result[$settingId] = [
                        'value' => $setting['active']
                    ];
                } elseif ('select' === $setting['type']) {
                    $result[$settingId] = [
                        'value' => ctype_digit($setting['value'])
                            ? (int)$setting['value'] : $setting['value']
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
                    'messaging_preferences'
                ],
                'json' => $messagingSettings,
                'method' => 'PUT'
            ]
        );
        if ($result['code'] >= 300) {
            return  [
                'success' => false,
                'status' => 'Updating of patron information failed',
                'sys_message' => $result['error'] ?? $code
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
            'pickup_library_id' => $pickUpLocation
        ];

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'holds', $requestId],
                'json' => $request,
                'method' => 'PUT',
                'errors' => true
            ]
        );

        if ($result['code'] >= 300) {
            return $this->holdError($result['data']['error'] ?? 'hold_error_fail');
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

        if ($frozen) {
            $result = $this->makeRequest(
                [
                    'path' => ['v1', 'holds', $requestId, 'suspension'],
                    'method' => 'POST',
                    'errors' => true
                ]
            );
        } else {
            $result = $this->makeRequest(
                [
                    'path' => ['v1', 'holds', $requestId, 'suspension'],
                    'method' => 'DELETE',
                    'errors' => true
                ]
            );
        }

        if ($result['code'] >= 300) {
            return $this->holdError($result['data']['error'] ?? 'hold_error_fail');
        }
        return ['success' => true];
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
        $request = [
            'credit_type' => 'PAYMENT',
            'amount' => $amount / 100,
            'note' => "Online transaction $transactionId"
        ];

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $patron['id'], 'account', 'credits'],
                'json' => $request,
                'method' => 'POST',
                'errors' => true
            ]
        );
        if ($result['code'] >= 300) {
            $error = "Failed to mark payment of $amount paid for patron"
                . " {$patron['id']}: {$result['code']}: " . print_r($result, true);
            $this->error($error);
            throw new ILSException($error);
        }
        // Clear patron's block cache
        $cacheId = 'blocks|' . $patron['id'];
        $this->removeCachedData($cacheId);
        return true;
    }

    /**
     * Get a password recovery token for a user
     *
     * @param array $params Required params such as cat_username and email
     *
     * @return array Associative array of the results
     */
    public function getPasswordRecoveryToken($params)
    {
        $result = $this->makeRequest(
            [
                'path' => 'v1/patrons',
                'query' => [
                    '_match' => 'exact',
                    'cardnumber' => $params['cat_username'],
                    'email' => $params['email']
                ],
                'errors' => true
            ]
        );

        if (200 === $result['code'] && !empty($result['data'][0])) {
            return [
                'success' => true,
                'token' => $result['data'][0]['patron_id']
            ];
        }

        if (404 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }
        return [
            'success' => false,
            'error' => 'Patron not found'
        ];
    }

    /**
     * Recover user's password with a token from getPasswordRecoveryToken
     *
     * @param array $params Required params such as cat_username, token and new
     * password
     *
     * @return array Associative array of the results
     */
    public function recoverPassword($params)
    {
        $request = [
            'password' => $params['password'],
            'password_2' => $params['password']
        ];

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $params['token'], 'password'],
                'json' => $request,
                'method' => 'POST',
                'errors' => true
            ]
        );
        if ($result['code'] >= 300) {
            return [
                'success' => false,
                'error' => $result['data']['error'] ?? $result['code']
            ];
        }
        return [
            'success' => true
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
                'query' => ['query_permissions' => 1]
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
        if ('getPasswordRecoveryToken' === $function
            || 'recoverPassword' === $function
        ) {
            return !empty($this->config['PasswordRecovery']['enabled'])
                ? $this->config['PasswordRecovery'] : false;
        } elseif ('getPatronStaffAuthorizationStatus' === $function) {
            return ['enabled' => true];
        }
        $functionConfig = parent::getConfig($function, $params);
        if ($functionConfig && 'onlinePayment' === $function) {
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
                        'v1', 'contrib', 'kohasuomi', 'biblios', $id, 'holdings'
                    ],
                    'errors' => true
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

        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'availability', 'biblios', $id,
                    'search'
                ],
                'errors' => true
            ]
        );
        if (404 === $result['code']) {
            return [];
        }
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $statuses = [];
        foreach ($result['data']['item_availabilities'] ?? [] as $i => $item) {
            // $holding is a reference!
            unset($holding);
            if (!empty($item['holding_id'])
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
            if (isset($avail['unavailabilities']['Item::CheckedOut']['due_date'])) {
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
            $locationId = $item['location'];

            $entry = [
                'id' => $id,
                'item_id' => $item['item_id'],
                'location' => $location,
                'availability' => $available,
                'status' => $status,
                'status_array' => $statusCodes,
                'reserve' => 'N',
                'callnumber' => $callnumber,
                'duedate' => $duedate,
                'number' => $item['serial_issue_number'],
                'barcode' => $item['external_id'],
                'sort' => $i,
                'requests_placed' => max(
                    [$item['hold_queue_length'],
                    $result['data']['hold_queue_length']]
                ),
                'libraryId' => $libraryId,
                'locationId' => $locationId
            ];
            if (!empty($item['itemnotes'])) {
                $entry['item_notes'] = [$item['itemnotes']];
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
        if (!empty($holdings)) {
            foreach ($holdings as $holding) {
                if ($holding['suppressed'] || !empty($holding['_hasItems'])) {
                    continue;
                }
                $holdingData = $this->getHoldingData($holding, true);
                $i++;
                $entry = $this->createHoldingsEntry($id, $holding, $i);
                $entry += $holdingData;

                $statuses[] = $entry;
            }
        }

        // Add serial purchase information
        if (!$brief && !empty($this->config['Holdings']['use_serial_subscriptions'])
        ) {
            $serialsResult = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'biblios', $id,
                        'serialsubscriptions'
                    ],
                    'errors' => true
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
                foreach ($serialsResult['subscriptions'] as $subscription) {
                    $i++;
                    $seqs = [];
                    $latestReceived = 0;
                    if ('last year' === $filter) {
                        foreach ($subscription['issues'] as $issue) {
                            if (!$issue['received']) {
                                continue;
                            }
                            list($year) = explode('-', $issue['publisheddate']);
                            if ($year > $latestReceived) {
                                $latestReceived = $year;
                            }
                        }
                    }
                    foreach ($subscription['issues'] as $issue) {
                        if (!$issue['received']) {
                            continue;
                        }
                        list($year) = explode('-', $issue['publisheddate']);
                        if ($yearFilter) {
                            // Limit to current and last year
                            if ($year && $year != $currentYear
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
                            'issue' => $seq
                        ];
                    }

                    $entry = $this->createSerialEntry($subscription, $i);

                    foreach ($statuses as &$status) {
                        if ($status['location'] === $entry['location']) {
                            $status['purchase_history'] = $issues;
                            continue 2;
                        }
                    }
                    unset($status);
                    $entry['purchase_history'] = $issues;
                    $statuses[] = $entry;
                }
            }
        }

        // See if there are links in holdings
        $electronic = [];
        if (!empty($holdings)) {
            foreach ($holdings as $holding) {
                $marc = $this->getHoldingMarc($holding);
                if (null === $marc) {
                    continue;
                }

                $notes = [];
                if ($fields = $marc->getFields('852')) {
                    foreach ($fields as $field) {
                        if ($subfield = $field->getSubfield('z')) {
                            $notes[] = $subfield->getData();
                        }
                    }
                }
                if ($fields = $marc->getFields('856')) {
                    foreach ($fields as $field) {
                        if ($subfields = $field->getSubfields()) {
                            $urls = [];
                            $desc = [];
                            $parts = [];
                            foreach ($subfields as $code => $subfield) {
                                if ('u' === $code) {
                                    $urls[] = $subfield->getData();
                                } elseif ('3' === $code) {
                                    $parts[] = $subfield->getData();
                                } elseif (in_array($code, ['y', 'z'])) {
                                    $desc[] = $subfield->getData();
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
        }

        usort($statuses, [$this, 'statusSortFunction']);
        usort($electronic, [$this, 'statusSortFunction']);
        return [
            'holdings' => $statuses,
            'electronic_holdings' => $electronic
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

        $request = array_merge($request, $fields);

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $patron['id']],
                'json' => $request,
                'method' => 'PUT',
                'errors' => true
            ]
        );
        if ($result['code'] >= 300) {
            return [
                'success' => false,
                'status' => 'Updating of patron information failed',
                'sys_message' => $result['data']['error'] ?? $result['code']
            ];
        }

        return [
            'success' => true,
            'status' => 202 === $result['code']
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
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
        $callnumber = '';
        if (!empty($holdings['ccode'])
            && !empty($this->config['Holdings']['display_ccode'])
        ) {
            $callnumber = $this->translateCollection(
                $holdings['collection_code'],
                $holdings['collection_code_description']
                ?? $holdings['collection_code']
            );
        }

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
        } else {
            if ($callnumber) {
                $callnumber .= ', ';
            }
            $callnumber .= $this->translateLocation(
                $holdings['location'],
                !empty($holdings['location_description'])
                    ? $holdings['location_description']
                    : $holdings['location']
            );
        }
        if ($holdings['callnumber']) {
            $callnumber .= ' ' . $holdings['callnumber'];
        }
        $callnumber = trim($callnumber);
        $libraryId = $holdings['holding_library_id'];
        $locationId = $holdings['location'];

        return [
            'id' => $id,
            'item_id' => 'HLD_' . $holding['biblionumber'],
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
            'locationId' => $locationId
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
            'callnumber' => $subscription['callnumber'] ?? null
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
     * Get a MARC record for the given holding or null if not available
     *
     * @param array $holding Holding
     *
     * @return \File_MARCXML
     */
    protected function getHoldingMarc(&$holding)
    {
        if (!isset($holding['_marcRecord'])) {
            foreach ($holding['holdings_metadata'] ?? [$holding['metadata']]
                as $metadata
            ) {
                if ('marcxml' === $metadata['format']
                    && 'MARC21' === $metadata['schema']
                ) {
                    $marc = new \File_MARCXML(
                        $metadata['metadata'],
                        \File_MARCXML::SOURCE_STRING
                    );
                    $holding['_marcRecord'] = $marc->next();
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
            isset($this->config['Holdings']['notes'])
            ? $this->config['Holdings']['notes']
            : '852z'
        );
        if ($data) {
            $marcDetails['notes'] = $data;
        }

        // Get Summary (may be multiple lines)
        $data = $this->getMARCData(
            $marc,
            isset($this->config['Holdings']['summary'])
            ? $this->config['Holdings']['summary']
            : '866a'
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
     * @param object       $record     File_MARC object
     * @param array|string $fieldSpecs Array or colon-separated list of
     * field/subfield specifications (3 chars for field code and then subfields,
     * e.g. 866az)
     *
     * @return string|array Results as a string if single, array if multiple
     */
    protected function getMARCData($record, $fieldSpecs)
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
                    if ($subfields = $field->getSubfields()) {
                        $line = '';
                        foreach ($subfields as $code => $subfield) {
                            if (!strstr($subfieldCodes, $code)) {
                                continue;
                            }
                            if ($line) {
                                $line .= ' ';
                            }
                            $line .= $subfield->getData();
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
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = $reservationsTotal = 0;
        $requests = 0;
        $locations = [];

        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (strncmp($item['item_id'], 'HLD_', 4) !== 0) {
                $itemsTotal++;
            }
            $locations[$item['location']] = true;
            if ($item['requests_placed'] > $requests) {
                $requests = $item['requests_placed'];
            }
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.

        // Use a stupid location name to make sure this doesn't get mixed with
        // real items that don't have a proper location.
        $result = [
           'available' => $availableTotal,
           'total' => $itemsTotal,
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => '__HOLDINGSSUMMARYLOCATION__'
        ];
        if (!empty($this->config['Holdings']['display_total_hold_count'])) {
            $result['reservations'] = $requests;
        }
        return $result;
    }
}
