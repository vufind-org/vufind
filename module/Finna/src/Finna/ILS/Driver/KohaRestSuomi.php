<?php
/**
 * KohaRest ILS Driver for KohaSuomi
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
 * KohaRest ILS Driver for KohaSuomi
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRestSuomi extends KohaRestSuomiVuFind
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
     * Whether to use location in addition to branch when grouping holdings
     *
     * @param bool
     */
    protected $groupHoldingsByLocation;

    /**
     * Priority settings for the order of branches or branch/location combinations
     *
     * @var array
     */
    protected $holdingsBranchOrder;

    /**
     * Priority settings for the order of locations (in branches)
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
                $this->holdingsBranchOrder[$parts[0]] = $idx;
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
        $data = parent::getStatus($id);
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
            ['v1', 'patrons', $patron['id']], false, 'GET', $patron
        );

        $expirationDate = !empty($result['dateexpiry'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $result['dateexpiry']
            ) : '';

        $guarantor = [];
        $guarantees = [];
        if (!empty($result['guarantorid'])) {
            $guarantorRecord = $this->makeRequest(
                ['v1', 'patrons', $result['guarantorid']], false, 'GET', $patron
            );
            if ($guarantorRecord) {
                $guarantor['firstname'] = $guarantorRecord['firstname'];
                $guarantor['lastname'] = $guarantorRecord['surname'];
            }
        } else {
            // Assume patron can have guarantees only if there is no guarantor
            $guaranteeRecords = $this->makeRequest(
                ['v1', 'patrons'], ['guarantorid' => $patron['id']], 'GET',
                $patron
            );
            foreach ($guaranteeRecords as $guarantee) {
                $guarantees[] = [
                    'firstname' => $guarantee['firstname'],
                    'lastname' => $guarantee['surname']
                ];
            }
        }

        list($resultCode, $messagingPrefs) = $this->makeRequest(
            ['v1', 'messaging_preferences'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron,
            true
        );

        $messagingSettings = [];
        if (200 === $resultCode) {
            foreach ($messagingPrefs as $type => $prefs) {
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
        }

        $phoneField = isset($this->config['Profile']['phoneNumberField'])
            ? $this->config['Profile']['phoneNumberField']
            : 'mobile';

        $smsField = isset($this->config['Profile']['smsNumberField'])
            ? $this->config['Profile']['smsNumberField']
            : 'smsalertnumber';

        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $phoneField && !empty($result[$phoneField])
                ? $result[$phoneField] : '',
            'smsnumber' => $smsField ? $result[$smsField] : '',
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['zipcode'],
            'city' => $result['city'],
            'country' => $result['country'],
            'category' => $result['categorycode'] ?? '',
            'expiration_date' => $expirationDate,
            'hold_identifier' => $result['othernames'],
            'guarantor' => $guarantor,
            'guarantees' => $guarantees,
            'loan_history' => $result['privacy'],
            'messagingServices' => $messagingSettings,
            'notes' => $result['opacnote'],
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
        list($code, $result) = $this->makeRequest(
            ['v1', 'checkouts', 'history'],
            ['borrowernumber' => $patron['id']],
            'DELETE',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Purging the loan history failed',
                'sys_message' => $result['error'] ?? $code
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
        $request = [
            'privacy' => (int)$state
        ];

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
                'status' => 'Changing the checkout history state failed',
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
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
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

        $request = [];
        foreach ($fields as $field) {
            $request[$field] = $number;
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
                'status' => 'Changing the phone number failed',
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
            'PATCH',
            $patron,
            true
        );
        if (!in_array($code, [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
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
            if (409 === $code && !empty($result['conflict'])) {
                $keys = array_keys($result['conflict']);
                $key = reset($keys);
                $fieldName = isset($addressFields[$key])
                    ? $this->translate($addressFields[$key])
                    : '???';
                $status = $this->translate(
                    'request_change_value_already_in_use',
                    ['%%field%%' => $fieldName]
                );
            } else {
                $status = 'Changing the contact information failed';
            }
            return [
                'success' => false,
                'status' => $status,
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
        $messagingPrefs = $this->makeRequest(
            ['v1', 'messaging_preferences'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );

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

        list($code, $result) = $this->makeRequest(
            ['v1', 'messaging_preferences'],
            [
                'borrowernumber' => $patron['id'],
                '##body##' => json_encode($messagingSettings)
            ],
            'PUT',
            $patron,
            true
        );
        if ($code >= 300) {
            return  [
                'success' => false,
                'status' => 'Changing the preferences failed',
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
            'suspend' => $frozen
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
                . " {$patron['id']}: $code: " . print_r($result, true);
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
        $request = [
            'cardnumber' => $params['cat_username'],
            'email' => $params['email'],
            'skip_email' => true
        ];
        $operator = [];
        if (!empty($this->config['PasswordRecovery']['userId'])
            && !empty($this->config['PasswordRecovery']['userPassword'])
        ) {
            $operator = [
                'cat_username' => $this->config['PasswordRecovery']['userId'],
                'cat_password' => $this->config['PasswordRecovery']['userPassword']
            ];
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', 'password', 'recovery'],
            json_encode($request),
            'POST',
            $operator,
            true
        );
        if (201 != $code) {
            if (404 != $code) {
                throw new ILSException("Failed to get a recovery token: $code");
            }
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
        return [
            'success' => true,
            'token' => $result['uuid']
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
            'uuid' => $params['token'],
            'new_password' => $params['password'],
            'confirm_new_password' => $params['password']
        ];
        $operator = [];
        if (!empty($this->config['passwordRecovery']['userId'])
            && !empty($this->config['passwordRecovery']['userPassword'])
        ) {
            $operator = [
                'cat_username' => $this->config['passwordRecovery']['userId'],
                'cat_password' => $this->config['passwordRecovery']['userPassword']
            ];
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', 'password', 'recovery', 'complete'],
            json_encode($request),
            'POST',
            $operator,
            true
        );
        if (200 != $code) {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
        return [
            'success' => true
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
     */
    public function getMyHolds($patron)
    {
        $result = $this->makeRequest(
            ['v1', 'holds'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );
        if (!isset($result)) {
            return [];
        }
        $holds = [];
        foreach ($result as $entry) {
            $bibId = $entry['biblionumber'] ?? null;
            $itemId = $entry['itemnumber'] ?? null;
            $title = '';
            $volume = '';
            if ($itemId) {
                $item = $this->getItem($itemId);
                $bibId = $item['biblionumber'] ?? null;
                $volume = $item['enumchron'] ?? '';
            }
            if (!empty($bibId)) {
                $bib = $this->getBibRecord($bibId);
                $title = $bib['title'] ?? '';
                if (!empty($bib['title_remainder'])) {
                    $title .= ' ' . $bib['title_remainder'];
                    $title = trim($title);
                }
            }
            $frozen = false;
            if (!empty($entry['suspend'])) {
                $frozen = !empty($entry['suspend_until']) ? $entry['suspend_until']
                    : true;
            }
            $available = !empty($entry['waitingdate']);
            $inTransit = isset($entry['found'])
                && strtolower($entry['found']) == 't';
            $holds[] = [
                'id' => $bibId,
                'item_id' => $itemId ? $itemId : $entry['reserve_id'],
                'location' => $entry['branchcode'],
                'create' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['reservedate']
                ),
                'expire' => !empty($entry['expirationdate'])
                    ? $this->dateConverter->convertToDisplayDate(
                        'Y-m-d', $entry['expirationdate']
                    ) : '',
                'position' => $entry['priority'],
                'available' => $available,
                'in_transit' => $inTransit,
                'requestId' => $entry['reserve_id'],
                'title' => $title,
                'volume' => $volume,
                'frozen' => $frozen,
                'is_editable' => !$available && !$inTransit
            ];
        }
        return $holds;
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
     * Check if patron belongs to staff.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return bool True if patron is staff, false if not
     */
    public function getPatronStaffAuthorizationStatus($patron)
    {
        $username = $patron['cat_username'];
        if ($this->sessionCache->patron != $username) {
            if (!$this->renewPatronCookie($patron)) {
                return false;
            }
        }

        return !empty(
            array_intersect(
                ['superlibrarian', 'catalogue'],
                $this->sessionCache->patronPermissions
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
        $locations = [];
        $section = array_key_exists('StorageRetrievalRequest', $holdDetails ?? [])
            ? 'StorageRetrievalRequests' : 'Holds';
        $excluded = isset($this->config[$section]['excludePickupLocations'])
            ? explode(':', $this->config[$section]['excludePickupLocations']) : [];
        $included = null;

        if (!empty($this->config['Catalog']['availabilitySupportsPickupLocations'])
        ) {
            $included = [];
            $level = isset($holdDetails['level']) && !empty($holdDetails['level'])
                ? $holdDetails['level'] : 'copy';
            $bibId = $holdDetails['id'];
            $itemId = $holdDetails['item_id'] ?? false;
            if ('copy' === $level && false === $itemId) {
                return [];
            }
            // Collect branch codes that are to be included
            if ('copy' === $level) {
                $result = $this->makeRequest(
                    ['v1', 'availability', 'item', 'hold'],
                    [
                        'itemnumber' => $itemId,
                        'borrowernumber' => (int)$patron['id'],
                        'query_pickup_locations' => 1
                    ],
                    'GET',
                    $patron
                );
                if (empty($result)) {
                    return [];
                }
                $pickupLocs
                    = $result[0]['availability']['notes']['Item::PickupLocations']
                    ?? [];
            } else {
                $result = $this->makeRequest(
                    ['v1', 'availability', 'biblio', 'hold'],
                    [
                        'biblionumber' => $bibId,
                        'borrowernumber' => (int)$patron['id'],
                        'query_pickup_locations' => 1
                    ],
                    'GET',
                    $patron
                );
                if (empty($result)) {
                    return [];
                }
                $pickupLocs
                    = $result[0]['availability']['notes']['Biblio::PickupLocations']
                    ?? [];
            }
            foreach ($pickupLocs['to_libraries'] ?? [] as $code) {
                $included[] = $code;
            }
        }

        $result = $this->getBranches();
        if (empty($result)) {
            return [];
        }
        foreach ($result as $location) {
            $code = $location['branchcode'];
            if ((null === $included && !$location['pickup_location'])
                || in_array($code, $excluded)
                || (null !== $included && !in_array($code, $included))
            ) {
                continue;
            }
            $locations[] = [
                'locationID' => $code,
                'locationDisplay' => $location['branchname']
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = isset($this->config[$section]['pickUpLocationOrder'])
            ? $this->config[$section]['pickUpLocationOrder'] : 'default';
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
     * Return a call number for a Koha item
     *
     * @param array $item Item
     *
     * @return string
     */
    protected function getItemCallNumber($item)
    {
        $result = [];
        if (!empty($item['ccode'])
            && !empty($this->config['Holdings']['display_ccode'])
        ) {
            $result[] = $this->translateCollection(
                $item['ccode'],
                $item['ccode_description'] ?? $item['ccode']
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
        if ((!empty($item['itemcallnumber'])
            || !empty($item['itemcallnumber_display']))
            && !empty($this->config['Holdings']['display_full_call_number'])
        ) {
            if (!empty($this->config['Holdings']['use_non_display_call_number'])) {
                $result[] = $item['itemcallnumber'];
            } else {
                $result[] = !empty($item['itemcallnumber_display'])
                    ? $item['itemcallnumber_display'] : $item['itemcallnumber'];
            }
        }
        $str = implode(', ', $result);
        return $str;
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
        $level = isset($holdDetails['level']) && !empty($holdDetails['level'])
            ? $holdDetails['level'] : 'copy';
        $pickUpLocation = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->defaultPickUpLocation;
        $itemId = $holdDetails['item_id'] ?? false;
        $comment = $holdDetails['comment'] ?? '';
        $bibId = $holdDetails['id'];

        // Convert last interest date from Display Format to Koha's required format
        try {
            $lastInterestDate = $this->dateConverter->convertFromDisplayDate(
                'Y-m-d', $holdDetails['requiredBy']
            );
        } catch (DateException $e) {
            // Hold Date is invalid
            return $this->holdError('hold_date_invalid');
        }

        if ($level == 'copy' && empty($itemId)) {
            throw new ILSException("Hold level is 'copy', but item ID is empty");
        }

        try {
            $checkTime = $this->dateConverter->convertFromDisplayDate(
                'U', $holdDetails['requiredBy']
            );
            if (!is_numeric($checkTime)) {
                throw new DateException('Result should be numeric');
            }
        } catch (DateException $e) {
            throw new ILSException('Problem parsing required by date.');
        }

        if (time() > $checkTime) {
            // Hold Date is in the past
            return $this->holdError('hold_date_past');
        }

        // Make sure pickup location is valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $request = [
            'biblionumber' => (int)$bibId,
            'borrowernumber' => (int)$patron['id'],
            'branchcode' => $pickUpLocation,
            'reservenotes' => $comment,
            'expirationdate' => $this->dateConverter->convertFromDisplayDate(
                'Y-m-d', $holdDetails['requiredBy']
            )
        ];
        if ($level == 'copy') {
            $request['itemnumber'] = (int)$itemId;
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'holds'],
            json_encode($request),
            'POST',
            $patron,
            true
        );

        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
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
            list($code, $holdingsResult) = $this->makeRequest(
                ['v1', 'biblios', $id, 'holdings'],
                [],
                'GET',
                $patron,
                true
            );
            if (404 === $code) {
                return [];
            }
            if ($code !== 200) {
                throw new ILSException('Problem with Koha REST API.');
            }

            // Turn the results into a keyed array
            if (!empty($holdingsResult['holdings'])) {
                foreach ($holdingsResult['holdings'] as $holding) {
                    $holdings[$holding['holding_id']] = $holding;
                }
            }
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'availability', 'biblio', 'search'],
            ['biblionumber' => $id],
            'GET',
            $patron,
            true
        );
        if (404 === $code) {
            return [];
        }
        if ($code !== 200) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $statuses = [];
        foreach ($result[0]['item_availabilities'] ?? [] as $i => $item) {
            // $holding is a reference!
            unset($holding);
            if (!empty($item['holding_id'])
                && isset($holdings[$item['holding_id']])
            ) {
                $holding = &$holdings[$item['holding_id']];
                if ($holding['suppress']) {
                    continue;
                }
            }
            $avail = $item['availability'];
            $available = $avail['available'];
            $statusCodes = $this->getItemStatusCodes($item);
            $status = $this->pickStatus($statusCodes);
            if (isset($avail['unavailabilities']['Item::CheckedOut']['date_due'])
                && !isset($avail['unavailabilities']['Item::Lost'])
            ) {
                $duedate = $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP',
                    $avail['unavailabilities']['Item::CheckedOut']['date_due']
                );
            } else {
                $duedate = null;
            }

            $location = $this->getItemLocationName($item);
            $callnumber = $this->getItemCallNumber($item);
            $sublocation = $item['sub_description'] ?? '';
            $branchId = (!$this->useHomeBranch && null !== $item['holdingbranch'])
                ? $item['holdingbranch'] : $item['homebranch'];
            $locationId = $item['location'];

            $entry = [
                'id' => $id,
                'item_id' => $item['itemnumber'],
                'location' => $location,
                'department' => $sublocation,
                'availability' => $available,
                'status' => $status,
                'status_array' => $statusCodes,
                'reserve' => 'N',
                'callnumber' => $callnumber,
                'duedate' => $duedate,
                'number' => $item['enumchron'],
                'barcode' => $item['barcode'],
                'sort' => $i,
                'requests_placed' => max(
                    [$item['hold_queue_length'], $result[0]['hold_queue_length']]
                ),
                'branchId' => $branchId,
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
                if ($holding['suppress'] || !empty($holding['_hasItems'])) {
                    continue;
                }
                $holdingData = $this->getHoldingData($holding, true);
                $i++;
                $entry = $this->createHoldingEntry($id, $holding, $i);
                $entry += $holdingData;

                $statuses[] = $entry;
            }
        }

        // Add serial purchase information
        if (!$brief
            && !empty($this->config['Holdings']['use_serial_subscriptions'])
        ) {
            list($code, $serialsResult) = $this->makeRequest(
                ['v1', 'biblios', $id, 'serialsubscriptions'],
                [],
                'GET',
                $patron,
                true
            );
            if (404 === $code) {
                return [];
            }
            if ($code !== 200) {
                throw new ILSException('Problem with Koha REST API.');
            }

            // Turn the results into a keyed array
            if (!empty($serialsResult['subscriptions'])) {
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
                                    = $this->createHoldingEntry($id, $holding, $i);
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
     * Create a holding entry
     *
     * @param string $id      Bib ID
     * @param array  $holding Holding
     * @param int    $sortKey Sort key
     *
     * @return array
     */
    protected function createHoldingEntry($id, $holding, $sortKey)
    {
        $location = $this->getBranchName($holding['holdingbranch']);
        $callnumber = '';
        if (!empty($holding['ccode'])
            && !empty($this->config['Holdings']['display_ccode'])
        ) {
            $callnumber = $this->translateCollection(
                $holding['ccode'],
                $holding['ccode_description'] ?? $holding['ccode']
            );
        }

        if ($this->groupHoldingsByLocation) {
            $holdingLoc = $this->translateLocation(
                $holding['location'],
                !empty($holding['location_description'])
                    ? $holding['location_description'] : $holding['location']
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
                $holding['location'],
                !empty($holding['location_description'])
                    ? $holding['location_description']
                    : $holding['location']
            );
        }
        if ($holding['callnumber']) {
            $callnumber .= ' ' . $holding['callnumber'];
        }
        $callnumber = trim($callnumber);
        $branchId = $holding['holdingbranch'];
        $locationId = $holding['location'];

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
            'branchId' => $branchId,
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
            'homebranch' => $subscription['branchcode'],
            'holdingbranch' => $subscription['branchcode'],
            'location' => $subscription['location'],
            'location_description' => $subscription['location_description'] ?? null,
            'itemcallnumber' => $subscription['callnumber'] ?? null,
            'itemcallnumber_display' => $subscription['callnumber'] ?? null,
        ];
        $location = $this->getItemLocationName($item);
        $callnumber = $this->getItemCallNumber($item);

        return [
            'item_id' => "SERIAL_$sortKey",
            'location' => $location,
            'callnumber' => $callnumber,
            'branchId' => $subscription['branchcode'],
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
     * Return a location for a Koha branch ID
     *
     * @param string $branchId Branch ID
     *
     * @return string
     */
    protected function getBranchName($branchId)
    {
        $name = $this->translate("location_$branchId");
        if ($name === "location_$branchId") {
            $branches = $this->getBranches();
            $name = isset($branches[$branchId])
                ? $branches[$branchId]['branchname'] : $branchId;
        }
        return $name;
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
                    && 'MARC21' === $metadata['marcflavour']
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
        $data = $this->getMFHDData(
            $marc,
            isset($this->config['Holdings']['notes'])
            ? $this->config['Holdings']['notes']
            : '852z'
        );
        if ($data) {
            $marcDetails['notes'] = $data;
        }

        // Get Summary (may be multiple lines)
        $data = $this->getMFHDData(
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
            $data = $this->getMFHDData(
                $marc,
                $this->config['Holdings']['supplements']
            );
            if ($data) {
                $marcDetails['supplements'] = $data;
            }
        }

        // Get Indexes
        if (isset($this->config['Holdings']['indexes'])) {
            $data = $this->getMFHDData(
                $marc,
                $this->config['Holdings']['indexes']
            );
            if ($data) {
                $marcDetails['indexes'] = $data;
            }
        }

        // Get links
        if (isset($this->config['Holdings']['links'])) {
            $data = $this->getMFHDData(
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
     * Get specified fields from an MFHD MARC Record
     *
     * @param object       $record     File_MARC object
     * @param array|string $fieldSpecs Array or colon-separated list of
     * field/subfield specifications (3 chars for field code and then subfields,
     * e.g. 866az)
     *
     * @return string|string[] Results as a string if single, array if multiple
     */
    protected function getMFHDData($record, $fieldSpecs)
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
     * Translate location name
     *
     * @param string $location Location code
     * @param string $default  Default value if translation is not available
     *
     * @return string
     */
    protected function translateLocation($location, $default = null)
    {
        if (empty($location)) {
            return null !== $default ? $default : '';
        }
        $prefix = $catPrefix = 'location_';
        if (!empty($this->config['Catalog']['id'])) {
            $catPrefix .= $this->config['Catalog']['id'] . '_';
        }
        return $this->translate(
            "$catPrefix$location",
            null,
            $this->translate(
                "$prefix$location",
                null,
                null !== $default ? $default : $location
            )
        );
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
     * Status item sort function
     *
     * @param array $a First status record to compare
     * @param array $b Second status record to compare
     *
     * @return int
     */
    protected function statusSortFunction($a, $b)
    {
        $orderA = $this->holdingsBranchOrder[$a['branchId'] . '/' . $a['locationId']]
            ?? $this->holdingsBranchOrder[$a['branchId']]
            ?? 999;
        $orderB = $this->holdingsBranchOrder[$b['branchId'] . '/' . $b['locationId']]
            ?? $this->holdingsBranchOrder[$b['branchId']]
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

        if (0 === $result && $this->sortItemsByEnumChron) {
            // Reverse chronological order
            $result = strnatcmp($b['number'] ?? '', $a['number'] ?? '');
        }

        if (0 === $result) {
            $result = $a['sort'] - $b['sort'];
        }

        return $result;
    }
}
