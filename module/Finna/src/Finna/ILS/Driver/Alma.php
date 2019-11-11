<?php
/**
 * Alma ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * Alma ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends \VuFind\ILS\Driver\Alma
{
    /**
     * Simple cache to avoid repeated requests
     *
     * @var array
     */
    protected $cachedRequest = [];

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $paymentConfig = $this->config['OnlinePayment'] ?? [];
        $blockedTypes = $paymentConfig['nonPayable'] ?? [];
        $xml = $this->makeRequest(
            '/users/' . $patron['id'] . '/fees'
        );
        $fineList = [];
        foreach ($xml as $fee) {
            $created = (string)$fee->creation_time;
            $checkout = (string)$fee->status_time;
            $payable = false;
            if (!empty($paymentConfig['enabled'])) {
                $type = (string)$fee->type;
                $payable = !in_array($type, $blockedTypes);
            }
            $fineList[] = [
                'id'       => (string)$fee->id,
                "title"    => (string)($fee->title ?? ''),
                "amount"   => round(floatval($fee->original_amount) * 100),
                "balance"  => round(floatval($fee->balance) * 100),
                "createdate" => $this->dateConverter->convertToDisplayDateAndTime(
                    'Y-m-d\TH:i:s.???T',
                    $created
                ),
                "checkout" => $this->dateConverter->convertToDisplayDateAndTime(
                    'Y-m-d\TH:i:s.???T',
                    $checkout
                ),
                "fine"     => (string)$fee->type['desc'],
                'payableOnline' => $payable
            ];
        }
        return $fineList;
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
        $paymentConfig = $this->config['OnlinePayment'] ?? [];
        $amount = 0;
        if (!empty($fines)) {
            foreach ($fines as $fine) {
                if ($fine['payableOnline']) {
                    $amount += $fine['balance'];
                }
            }
        }
        if ($amount > ($paymentConfig['minimumFee'] ?? 0)) {
            return [
                'payable' => true,
                'amount' => $amount
            ];
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
        $fines = $this->getMyFines($patron);
        $amountRemaining = $amount;
        // Mark payable fines as long as amount remains. If there's any left over
        // send it as a generic payment.
        foreach ($fines as $fine) {
            if ($fine['payableOnline'] && $fine['balance'] <= $amountRemaining) {
                $getParams = [
                    'op' => 'pay',
                    'amount' => sprintf('%0.02F', $fine['balance'] / 100),
                    'method' => 'ONLINE',
                    'comment' => "Finna transaction $transactionNumber",
                    'external_transaction_id' => $transactionId
                ];
                $this->makeRequest(
                    '/users/' . $patron['id'] . '/fees/' . $fine['id'],
                    $getParams,
                    [],
                    'POST'
                );

                $amountRemaining -= $fine['balance'];
            }
        }
        if ($amountRemaining) {
            $getParams = [
                'op' => 'pay',
                'amount' => sprintf('%0.02F', $amountRemaining / 100),
                'method' => 'ONLINE',
                'comment' => "Finna transaction $transactionNumber",
                'external_transaction_id' => $transactionId
            ];
            $this->makeRequest(
                '/users/' . $patron['id'] . '/fees/all',
                $getParams,
                [],
                'POST'
            );
        }

        return true;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $patronId = $patron['id'];
        $xml = $this->makeRequest('/users/' . $patronId);
        if (empty($xml)) {
            return [];
        }
        $profile = [
            'firstname'  => isset($xml->first_name)
                                ? (string)$xml->first_name
                                : null,
            'lastname'   => isset($xml->last_name)
                                ? (string)$xml->last_name
                                : null,
            'group'      => isset($xml->user_group['desc'])
                                ? (string)$xml->user_group['desc']
                                : null,
            'group_code' => isset($xml->user_group)
                                ? (string)$xml->user_group
                                : null,
            'account_type' => strtolower((string)$xml->account_type)
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {
                $address = null;
                foreach ($contact->addresses->address as $item) {
                    if ('true' === (string)$item['preferred']) {
                        $address = $item;
                        break;
                    }
                }
                if (null === $address) {
                    $address = $contact->addresses[0]->address[0];
                }
                $profile['address1'] =  isset($address->line1)
                                            ? (string)$address->line1
                                            : null;
                $profile['address2'] =  isset($address->line2)
                                            ? (string)$address->line2
                                            : null;
                $profile['address3'] =  isset($address->line3)
                                            ? (string)$address->line3
                                            : null;
                $profile['zip']      =  isset($address->postal_code)
                                            ? (string)$address->postal_code
                                            : null;
                $profile['city']     =  isset($address->city)
                                            ? (string)$address->city
                                            : null;
                if (!empty($address->country)) {
                    $profile['country'] = new \VuFind\I18n\TranslatableString(
                        (string)$address->country,
                        (string)$address->country->attributes()->desc
                    );
                } else {
                    $profile['country'] = null;
                }
            }
            if ($contact->phones) {
                $phone = null;
                foreach ($contact->phones->phone as $item) {
                    if ('true' === (string)$item['preferred']) {
                        $phone = $item;
                        break;
                    }
                }
                if (null === $phone) {
                    $phone = $contact->phones[0]->phone[0];
                }
                $profile['phone'] = isset($phone->phone_number)
                                        ? (string)$phone->phone_number
                                        : null;
            }
            if ($contact->emails) {
                $email = null;
                foreach ($contact->emails->email as $item) {
                    if ('true' === (string)$item['preferred']) {
                        $email = $item;
                        break;
                    }
                }
                if (null === $email) {
                    $email = $contact->emails[0]->email[0];
                }
                $profile['email'] = isset($email->email_address)
                                        ? (string)$email->email_address
                                        : null;
            }
        }

        if ($xml->user_identifiers && $xml->user_identifiers->user_identifier) {
            foreach ($xml->user_identifiers->user_identifier as $identifier) {
                if ('BARCODE' === (string)$identifier->id_type
                    && 'ACTIVE' === (string)$identifier->status
                ) {
                    $profile['barcode'] = (string)$identifier->value;
                    break;
                }
            }
        }

        $profile['self_service_pin'] = '****';

        if ($xml->proxy_for_users) {
            foreach ($xml->proxy_for_users->proxy_for_user as $user) {
                $profile['guarantees'][] = [
                    'lastname' => (string)$user->full_name
                ];
            }
        }

        // Cache the user group code
        $cacheId = 'alma|user|' . $patronId . '|group_code';
        $this->putCachedData($cacheId, $profile['group_code'] ?? null);

        return $profile;
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
        $addressMapping = [
            'address1' => 'line1',
            'address2' => 'line2',
            'address3' => 'line3',
            'address4' => 'line4',
            'address5' => 'line5',
            'zip' => 'postal_code',
            'city' => 'city',
            'country' => 'country'
        ];
        $phoneMapping = [
            'phone' => 'phone_number'
        ];
        $emailMapping = [
            'email' => 'email_address'
        ];
        $otherMapping = [
            'self_service_pin' => 'pin_number'
        ];
        // We need to process address fields, phone number fields and email fields
        // as separate sets, so divide them now to gategories
        $hasAddress = false;
        $hasPhone = false;
        $hasEmail = false;
        $fieldConfig = isset($this->config['updateProfile']['fields'])
            ? $this->config['updateProfile']['fields'] : [];
        foreach ($fieldConfig as $field) {
            $parts = explode(':', $field);
            if (isset($parts[1])) {
                $fieldName = $parts[1];
                if (isset($addressMapping[$fieldName])) {
                    if (isset($details[$fieldName])) {
                        $hasAddress = true;
                    }
                } elseif ('phone' === $fieldName) {
                    if (isset($details[$fieldName])) {
                        $hasPhone = true;
                    }
                } elseif ('email' === $fieldName) {
                    if (isset($details[$fieldName])) {
                        $hasEmail = true;
                    }
                }
            }
        }

        // Retrieve old data first
        $userData = $this->makeRequest('/users/' . $patron['id']);

        $contact = $userData->contact_info ?? $userData->addChild('contact_info');

        // Pick the configured fields from the request
        if ($hasAddress) {
            // Try to find an existing address to modify
            $types = null;
            if (!$contact->addresses) {
                $contact->addChild('addresses');
            }
            foreach ($contact->addresses->address as $item) {
                if ('true' === (string)$item['preferred']) {
                    // Remove the existing address
                    $types = clone $item->address_types->address_type;
                    unset($item[0]);
                    break;
                }
            }
            $address = $contact->addresses->addChild('address');
            $addressTypes = $address->addChild('address_types');
            if (null === $types) {
                $addressTypes->addChild('address_type', 'home');
            } else {
                foreach ($types as $type) {
                    $addressTypes->addChild('address_type', (string)$type);
                }
            }
            $address['preferred'] = 'true';
            foreach ($details as $key => $value) {
                if (isset($addressMapping[$key])) {
                    $address->addChild($addressMapping[$key], $value);
                }
            }
        }

        if ($hasPhone) {
            // Try to find an existing phone to modify
            $types = null;
            if (!$contact->phones) {
                $contact->addChild('phones');
            }
            foreach ($contact->phones->phone as $item) {
                if ('true' === (string)$item['preferred']) {
                    // Remove the existing phone number
                    $types = clone $item->phone_types->phone_type;
                    unset($item[0]);
                    break;
                }
            }
            $phone = $contact->phones->addChild('phone');
            $phoneTypes = $phone->addChild('phone_types');
            if (null === $types) {
                $phoneTypes->addChild('phone_type', 'mobile');
            } else {
                foreach ($types as $type) {
                    $phoneTypes->addChild('phone_type', (string)$type);
                }
            }
            $phone['preferred'] = 'true';
            foreach ($details as $key => $value) {
                if (isset($phoneMapping[$key])) {
                    $phone->addChild($phoneMapping[$key], $value);
                }
            }
        }

        if ($hasEmail) {
            // Try to find an existing email to modify
            $types = null;
            if (!$contact->emails) {
                $contact->addChild('emails');
            }
            foreach ($contact->emails->email as $item) {
                if ('true' === (string)$item['preferred']) {
                    // Remove the existing email address
                    $types = clone $item->email_types->email_type;
                    unset($item[0]);
                    break;
                }
            }
            $email = $contact->emails->addChild('email');
            $emailTypes = $email->addChild('email_types');
            if (null === $types) {
                $emailTypes->addChild('email_type', 'home');
            } else {
                foreach ($types as $type) {
                    $emailTypes->addChild('email_type', (string)$type);
                }
            }
            $email['preferred'] = 'true';
            foreach ($details as $key => $value) {
                if (isset($emailMapping[$key])) {
                    $email->addChild($emailMapping[$key], $value);
                }
            }
        }

        $overrideFields = [];
        foreach ($details as $key => $value) {
            $value = trim($value);
            if (isset($otherMapping[$key])) {
                $fieldName = $otherMapping[$key];
                if ('pin_number' === $fieldName) {
                    if (empty($value) || trim($value) === '****') {
                        continue;
                    }
                    $overrideFields[] = 'pin_number';
                }
                $field = $userData->{$fieldName};
                if ($field) {
                    $field[0] = $value;
                } else {
                    $field = $userData->addChild($fieldName, $value);
                }
            }
        }

        // Remove list-style data that we don't ever update and is handled by Alma
        // as complete entities
        unset($userData->user_identifiers);
        unset($userData->user_roles);
        unset($userData->user_blocks);
        unset($userData->user_statistics);
        unset($userData->proxy_for_users);

        // Update user in Alma
        $queryParams = '';
        if ($overrideFields) {
            $queryParams = '?override=' . implode(',', $overrideFields);
        }
        list($response, $code) = $this->makeRequest(
            '/users/' . urlencode($patron['id']) . $queryParams,
            [],
            [],
            'PUT',
            $userData->asXML(),
            ['Content-Type' => 'application/xml'],
            [400],
            true
        );
        if (200 !== $code) {
            return [
                'success' => false,
                'status' => (string)$response->errorList->error[0]->errorMessage,
                'sys_message' => ''
            ];
        }

        return [
            'success' => true,
            'status' => 'request_change_accepted',
            'sys_message' => ''
        ];
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
        if ('onlinePayment' === $function) {
            $config = $this->config['OnlinePayment'] ?? [];
            if (!empty($config) && !isset($config['exactBalanceRequired'])) {
                $config['exactBalanceRequired'] = false;
            }
            return $config;
        }
        if ('updateAddress' === $function) {
            $function = 'updateProfile';
        }
        $config = parent::getConfig($function, $params);
        if ('updateProfile' === $function && isset($config['fields'])) {
            // Allow only a limited set of fields for external users
            if (isset($params['patron'])) {
                $profile = $this->getMyProfile($params['patron']);
                if ('external' === $profile['account_type']) {
                    $fields = [];
                    foreach ($config['fields'] as &$field) {
                        list($label, $fieldId) = explode(':', $field);
                        if (in_array($fieldId, ['self_service_pin'])) {
                            $fields[] = $field;
                        }
                    }
                    if (!$fields) {
                        return false;
                    }
                    $config['fields'] = $fields;
                }
            }
            // Add code tables
            if (!empty($config['fields'])) {
                foreach ($config['fields'] as &$field) {
                    $parts = explode(':', $field);
                    $fieldId = $parts[1] ?? '';
                    if ('country' === $fieldId) {
                        $field = [
                            'field' => 'country',
                            'label' => $parts[0],
                            'type' => 'select',
                            'options' => $this->getCodeTableOptions(
                                'CountryCodes', 'description'
                            ),
                            'required' => ($parts[3] ?? '') === 'required',
                        ];
                    }
                }
            }
        }
        return $config;
    }

    /**
     * Get Default Pick Up Location
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
    public function getDefaultPickUpLocation($patron = null, $holdDetails = null)
    {
        return false;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Additional options
     *
     * @return array On success an array with the key "total" containing the total
     * number of items for the given bib id, and the key "holdings" containing an
     * array of holding information each one with these keys: id, source,
     * availability, status, location, reserve, callnumber, duedate, returnDate,
     * number, barcode, item_notes, item_id, holding_id, addLink, description
     */
    public function getHolding($id, $patron = null, array $options = [])
    {
        $results = parent::getHolding($id, $patron, $options);

        // Add holdings without items if we have a single page of holdings.
        // Otherwise we don't know all the items.
        if (!isset($options['itemLimit'])
            || $results['total'] <= $options['itemLimit']
        ) {
            $noItemsHoldings = [];
            $records = $this->makeRequest('/bibs/' . urlencode($id) . '/holdings');
            foreach ($records->holding ?? [] as $record) {
                $itemsFound = false;
                foreach ($results['holdings'] as &$holding) {
                    if ($holding['holding_id'] === (string)$record->holding_id) {
                        $holding['details_ajax'] = $holding['holding_id'];
                        $itemsFound = true;
                    }
                }
                unset($holding);
                if (!$itemsFound) {
                    $noItemsHoldings[] = $record;
                }
            }

            foreach ($noItemsHoldings as $record) {
                $entry = $this->createHoldingEntry($id, $record);
                $entry['details_ajax'] = $entry['holding_id'];
                $results['holdings'][] = $entry;
                ++$results['total'];
            }
        }

        return $results;
    }

    /**
     * Get detailed holding information for a single holdings record
     *
     * @param string $id     Bib record id
     * @param string $key    Retrieval key
     * @param array  $patron Patron data
     *
     * @return array
     */
    public function getHoldingsDetails($id, $key, $patron = null)
    {
        return $this->getHoldingsData($id, $key);
    }

    /**
     * Get holdings data from a holdings record
     *
     * @param string $id         Bib ID
     * @param array  $holdingsId Holdings record ID
     *
     * @return array
     */
    protected function getHoldingsData($id, $holdingsId)
    {
        // If the record is from the holdings list, it doesn't include MARC..
        $record = $this->makeRequest(
            '/bibs/' . urlencode($id) . '/holdings/'
            . urlencode($holdingsId)
        );
        $marc = $record->record;

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
            $marcDetails['holding_id'] = $record['holding_id'];
        }

        return $marcDetails;
    }

    /**
     * Create a holding entry
     *
     * @param string $id      Bib ID
     * @param array  $holding Holding
     *
     * @return array
     */
    protected function createHoldingEntry($id, $holding)
    {
        $location = $this->getTranslatableString($holding->library);
        $callnumber = $this->getTranslatableString($holding->call_number);

        return [
            'id' => $id,
            'item_id' => 'HLD_' . (string)$holding->holding_id,
            'location' => $location,
            'requests_placed' => 0,
            'status' => '',
            'use_unknown_message' => true,
            'availability' => false,
            'duedate' => '',
            'barcode' => '',
            'callnumber' => $callnumber,
            'holding_id' => (string)$holding->holding_id,
        ];
    }

    /**
     * Get specified fields from an MFHD MARC Record
     *
     * @param object       $record     SimpleXMLElement
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
            foreach ($record->datafield as $field) {
                if ((string)$field->attributes()->tag === $fieldCode) {
                    $line = '';
                    foreach ($field->subfield as $subfield) {
                        $code = (string)$subfield->attributes()->code;
                        if (!strstr($subfieldCodes, $code)) {
                            continue;
                        }
                        if ($line) {
                            $line .= ' ';
                        }
                        $line .= (string)$subfield;
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
        return $results;
    }

    /**
     * Get code table options for table
     *
     * @param string $codeTable Code table to fetch
     * @param string $sort      Sort order ('', 'code' or 'description)
     *
     * @return array
     */
    protected function getCodeTableOptions($codeTable, $sort)
    {
        $cacheId = 'alma|codetable|' . $codeTable . "|$sort";
        $cached = $this->getCachedData($cacheId);
        if (null !== $cached) {
            return $cached;
        }

        $table = $this->makeRequest('/conf/code-tables/' . urlencode($codeTable));
        $result = [];
        foreach ($table->rows->row as $row) {
            if ((string)$row->enabled === 'true') {
                $result[(string)$row->code] = [
                    'name' => (string)$row->description
                ];
            }
        }

        if ('code' === $sort) {
            uksort(
                $result,
                function ($a, $b) {
                    return strcmp($a, $b);
                }
            );
        } elseif ('description' === $sort) {
            uasort(
                $result,
                function ($a, $b) {
                    return strcmp($a['name'], $b['name']);
                }
            );
        }

        $this->putCachedData($cacheId, $result);

        return $result;
    }

    /**
     * Make an HTTP request against Alma
     *
     * @param string        $path          Path to retrieve from API (excluding base
     *                                     URL/API key)
     * @param array         $paramsGet     Additional GET params
     * @param array         $paramsPost    Additional POST params
     * @param string        $method        GET or POST. Default is GET.
     * @param string        $rawBody       Request body.
     * @param Headers|array $headers       Add headers to the call.
     * @param array         $allowedErrors HTTP status codes that are not treated as
     *                                     API errors.
     * @param bool          $returnStatus  Whether to return HTTP status in addition
     *                                     to the response.
     *
     * @throws ILSException
     * @return NULL|SimpleXMLElement
     */
    protected function makeRequest(
        $path,
        $paramsGet = [],
        $paramsPost = [],
        $method = 'GET',
        $rawBody = null,
        $headers = null,
        $allowedErrors = [],
        $returnStatus = false
    ) {
        // Primitive cache (mainly for getConfig())
        $cachedRequest = $this->cachedRequest['request'] ?? '';
        $reqIdParts = [
            $path,
            $paramsGet,
            $paramsPost,
            $rawBody,
            $headers,
            $allowedErrors,
            $returnStatus
        ];
        $reqId = md5(print_r($reqIdParts, true));
        if ('GET' === $method && $reqId === $cachedRequest) {
            return $this->cachedRequest['response'];
        }
        $result = parent::makeRequest(
            $path, $paramsGet, $paramsPost, $method, $rawBody, $headers,
            $allowedErrors, $returnStatus
        );
        if ('GET' === $method) {
            $this->cachedRequest = [
                'request' => $reqId,
                'response' => $result
            ];
        }
        return $result;
    }
}
