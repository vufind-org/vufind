<?php
/**
 * Alma ILS Driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019-2020.
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
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Alma ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends \VuFind\ILS\Driver\Alma implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Simple cache to avoid repeated requests
     *
     * @var array
     */
    protected $cachedRequest = [];

    /**
     * Priority settings for the order of locations
     *
     * @var array
     */
    protected $holdingsLocationOrder = [];

    /**
     * Whether to sort items by enumchron. Default is true.
     *
     * @var bool
     */
    protected $sortItemsByEnumChron = true;

    /**
     * Process types hidden from holdings. The array is keyed by physical material
     * type, or '*' to match all types.
     *
     * @var array
     */
    protected $hiddenProcessTypes = [];

    /**
     * Mappings from fee types
     *
     * @var array
     */
    protected $feeTypeMappings = [
        'OVERDUEFINE' => 'Overdue',
        'OVERDUENOTIFICATIONFINE' => 'Overdue',
        'RECALLEDOVERDUEFINE' => 'Overdue',
        'LOSTITEMREPLACEMENTFEE' => 'Lost Item Replacement',
        'LOSTITEMPROCESSFEE' => 'Lost Item Processing',
        'LIBRARYCARDREPLACEMENT' => 'New Card',
        'CARDRENEWAL' => 'New Card',
        'ISSUELIBRARYCARD' => 'New Card',
        'SERVICEFEE' => 'Service Fee',
        'LOCKERKEY' => 'Locker Key',
        'OTHER' => 'Other',
        'DAMAGEDITEMFINE' => 'Damaged Item'
    ];

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

        if (isset($this->config['Holdings']['holdingsLocationOrder'])) {
            $values = explode(
                ':',
                $this->config['Holdings']['holdingsLocationOrder']
            );
            foreach ($values as $i => $value) {
                $parts = explode('=', $value, 2);
                $idx = $parts[1] ?? $i;
                $this->holdingsLocationOrder[$parts[0]] = $idx;
            }
        }

        $this->sortItemsByEnumChron
            = $this->config['Holdings']['sortByEnumChron'] ?? true;

        if (!empty($this->config['Holdings']['hiddenProcessTypes'])) {
            foreach ($this->config['Holdings']['hiddenProcessTypes']
                as $key => $value
            ) {
                $this->hiddenProcessTypes[$key] = explode(':', $value);
            }
        }

        if (!empty($this->config['FeeTypeMappings'])) {
            $this->feeTypeMappings = array_merge(
                $this->feeTypeMappings, $this->config['FeeTypeMappings']
            );
        }
    }

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
            $payable = false;
            if (!empty($paymentConfig['enabled'])) {
                $type = (string)$fee->type;
                $payable = !in_array($type, $blockedTypes);
            }
            $feeType = $this->feeTypeMappings[(string)$fee->type]
                ?? (string)$fee->type['desc'];
            $fineList[] = [
                'id'       => (string)$fee->id,
                "title"    => (string)($fee->title ?? ''),
                "amount"   => round(floatval($fee->original_amount) * 100),
                "balance"  => round(floatval($fee->balance) * 100),
                "createdate" => $this->parseDate($created, true),
                "fine"     => $feeType,
                'payableOnline' => $payable,
                '_create_time' => (string)$fee->creation_time,
                '_status_time' => (string)$fee->status_time,
                '_barcode'    => (string)($fee->barcode ?? ''),
                '_status'  => (string)$fee->status ?? '',
            ];
        }
        if (!empty($this->config['Catalog']['groupFees'])) {
            $finesGrouped = [];
            foreach ($fineList as $fine) {
                $key = $fine['fine'] . '||' . $fine['_status'] . '||'
                    . $fine['title'] . '||' . $fine['_barcode'] . '||'
                    . $fine['payableOnline'];
                if (isset($finesGrouped[$key])) {
                    $dateCmp = strcmp(
                        $finesGrouped[$key]['_create_time'], $fine['_create_time']
                    );
                    if ($dateCmp < 0) {
                        $finesGrouped[$key]['_create_time'] = $fine['_create_time'];
                        $finesGrouped[$key]['createdate'] = $fine['createdate'];
                    }
                    $finesGrouped[$key]['amount'] += $fine['amount'];
                    $finesGrouped[$key]['balance'] += $fine['balance'];
                } else {
                    $finesGrouped[$key] = $fine;
                }
            }
            $fineList = array_values($finesGrouped);
        }
        usort(
            $fineList,
            function ($a, $b) {
                return strnatcasecmp($a['title'], $b['title'])
                    ?: strcmp($a['_status_time'], $b['_status_time']);
            }
        );
        return $fineList;
    }

    /**
     * Get transactions of the current patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @return array Transaction information as array.
     *
     * @author Michael Birkner
     */
    public function getMyTransactions($patron, $params = [])
    {
        // Defining the return value
        $returnArray = [];

        // Get the patron id
        $patronId = $patron['id'];

        // Create a timestamp for calculating the due / overdue status
        $nowTS = time();

        $sort = explode(
            ' ', !empty($params['sort']) ? $params['sort'] : 'checkout desc', 2
        );
        if ($sort[0] == 'checkout') {
            $sortKey = 'loan_date';
        } elseif ($sort[0] == 'title') {
            $sortKey = 'title';
        } else {
            $sortKey = 'due_date';
        }
        $direction = (isset($sort[1]) && 'desc' === $sort[1]) ? 'DESC' : 'ASC';

        $pageSize = $params['limit'] ?? 50;
        $params = [
            'limit' => $pageSize,
            'offset' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'order_by' => $sortKey,
            'direction' => $direction,
            'expand' => 'renewable'
        ];

        // Get user loans from Alma API
        $apiResult = $this->makeRequest(
            '/users/' . $patronId . '/loans',
            $params
        );

        // If there is an API result, process it
        $totalCount = 0;
        if ($apiResult) {
            $totalCount = $apiResult->attributes()->total_record_count;
            // Iterate over all item loans
            foreach ($apiResult->item_loan as $itemLoan) {
                $loan['duedate'] = $this->parseDate(
                    (string)$itemLoan->due_date,
                    true
                );
                //$loan['dueTime'] = ;
                $loan['checkoutDate'] = $this->parseDate(
                    (string)$itemLoan->loan_date,
                    false
                );
                $loan['dueStatus'] = false; // Calculated below
                $loan['id'] = (string)$itemLoan->mms_id;
                //$loan['source'] = 'Solr';
                $loan['barcode'] = (string)$itemLoan->item_barcode;
                //$loan['renew'] = ;
                //$loan['renewLimit'] = ;
                //$loan['request'] = ;
                //$loan['volume'] = ;
                $loan['publication_year'] = (string)$itemLoan->publication_year;
                $loan['renewable']
                    = (strtolower((string)$itemLoan->renewable) == 'true')
                    ? true
                    : false;
                //$loan['message'] = ;
                $loan['title'] = (string)$itemLoan->title;
                $loan['item_id'] = (string)$itemLoan->loan_id;
                $loan['institution_name']
                    = $this->getTranslatableString($itemLoan->library);
                //$loan['isbn'] = ;
                //$loan['issn'] = ;
                //$loan['oclc'] = ;
                //$loan['upc'] = ;
                /*
                Apparently this is not useful for us
                $loan['borrowingLocation']
                    = $this->getTranslatableString($itemLoan->circ_desk);
                */

                // Calculate due status
                $dueDateTS = strtotime($loan['duedate']);
                if ($nowTS > $dueDateTS) {
                    // Loan is overdue
                    $loan['dueStatus'] = 'overdue';
                } elseif (($dueDateTS - $nowTS) < 86400) {
                    // Due date within one day
                    $loan['dueStatus'] = 'due';
                }

                $returnArray[] = $loan;
            }
        }

        return [
            'count' => $totalCount,
            'records' => $returnArray
        ];
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
        if ($amount >= ($paymentConfig['minimumFee'] ?? 0)) {
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
                $profile['addresses'] = [];
                foreach ($contact->addresses->address as $item) {
                    $address = [
                        'preferred' => 'true' === (string)$item['preferred'],
                        'types' => [],
                        'address1' => (string)($item->line1 ?? ''),
                        'address2' => (string)($item->line2 ?? ''),
                        'address3' => (string)($item->line3 ?? ''),
                        'zip' => (string)($item->postal_code ?? ''),
                        'city' => (string)($item->city ?? ''),
                    ];
                    foreach ($item->address_types->address_type as $type) {
                        $address['types'][] = (string)$type;
                    }
                    if (!empty($item->country)) {
                        $address['country'] = new \VuFind\I18n\TranslatableString(
                            (string)$item->country,
                            (string)$item->country->attributes()->desc
                        );
                    } else {
                        $address['country'] = '';
                    }
                    $profile['addresses'][] = $address;
                }

                // Copy preferred address to the basic fields
                foreach ($profile['addresses'] as $address) {
                    if (!empty($address['preferred'])) {
                        foreach ($address as $key => $value) {
                            $profile[$key] = $value;
                        }
                        break;
                    }
                }

                // Check if the user has a work and/or home address for hold pickup
                foreach ($contact->addresses->address as $item) {
                    foreach ($item->address_types->address_type ?? [] as $type) {
                        $parts = [
                            (string)$item->line1 ?? '',
                            ((string)$item->zip ?? '') . ' '
                            . ((string)$item->city ?? '')
                        ];
                        $parts = array_map('trim', $parts);
                        $addressLine = implode(', ', array_filter($parts));
                        if ('home' === (string)$type) {
                            $profile['homeAddress'] = $addressLine;
                        }
                        if ('work' === (string)$type) {
                            $profile['workAddress'] = $addressLine;
                        }
                    }
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

        // Display '****' as a hint that the field is available to update..
        $fieldConfig = $this->getUpdateProfileFields();
        foreach ($fieldConfig as $field) {
            $parts = explode(':', $field);
            if (($parts[1] ?? '') === 'self_service_pin') {
                $profile['self_service_pin'] = '****';
            }
        }

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
     * Check for account blocks in Alma and cache them.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array|boolean    An array of block messages or false if there are no
     *                          blocks
     * @author Michael Birkner
     */
    public function getAccountBlocks($patron)
    {
        $patronId = $patron['id'];
        $cacheId = 'alma|user|' . $patronId . '|blocks';
        $cachedBlocks = $this->getCachedData($cacheId);
        if ($cachedBlocks !== null) {
            return $cachedBlocks;
        }

        $xml = $this->makeRequest('/users/' . $patronId);
        if ($xml == null || empty($xml)) {
            return false;
        }

        $userBlocks = $xml->user_blocks->user_block;
        if ($userBlocks == null || empty($userBlocks)) {
            return false;
        }

        $blocks = [];
        foreach ($userBlocks as $block) {
            $blockStatus = (string)$block->block_status;
            if ($blockStatus === 'ACTIVE') {
                $blocks[] = 'Borrowing Block Message';
            }
        }
        $blocks = array_unique($blocks);

        if (!empty($blocks)) {
            $this->putCachedData($cacheId, $blocks);
            return $blocks;
        } else {
            $this->putCachedData($cacheId, false);
            return false;
        }
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
        $fieldConfig = $this->getUpdateProfileFields();
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
                    $address->addChild($addressMapping[$key], trim($value));
                }
            }
        } else {
            // Multiple address support
            $addresses = array_reverse(array_values($details['addresses'] ?? []));
            // Make sure we only have a single preferred address
            $hasPreferred = false;
            foreach ($addresses as &$address) {
                if (!empty($address['preferred'])) {
                    if ($hasPreferred) {
                        $address['preferred'] = false;
                    } else {
                        $hasPreferred = true;
                    }
                }
            }
            unset($address);

            $index = count($addresses);
            foreach ($addresses as $newAddress) {
                --$index;
                if (!($address = $contact->addresses->address[$index] ?? null)) {
                    $address = $contact->addresses->addChild('address');
                }
                $hasData = false;
                foreach ($newAddress as $key => $value) {
                    if (isset($addressMapping[$key])) {
                        $address->addChild($addressMapping[$key], trim($value));
                        if (!empty(trim($value))) {
                            $hasData = true;
                        }
                    }
                }
                if (!$hasData) {
                    // Empty fields, remove address
                    unset($address[0]);
                    continue;
                }
                if ($address->address_types) {
                    unset($address->address_types[0]);
                }
                $addressTypes = $address->addChild('address_types');

                foreach ($newAddress['types'] ?? ['home'] as $type) {
                    foreach ($addressTypes->address_type as $existing) {
                        if ((string)$existing === $type) {
                            continue 2;
                        }
                    }
                    $addressTypes->addChild('address_type', (string)$type);
                }
                $address['preferred'] = !empty($newAddress['preferred'])
                    ? 'true' : 'false';
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
            if (!is_array($value)) {
                $value = trim($value);
            }
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

        // Remove user roles as they are the exception that Alma handles differently.
        unset($userData->user_roles);

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
     * Register a new user
     *
     * @param array $params The data from the "create new account" form
     *
     * @throws \VuFind\Exception\Auth
     *
     * @return bool
     */
    public function registerPatron($params)
    {
        $formParams = $params['userdata'];

        // Get config for creating new Alma users from Alma.ini
        $newUserConfig = $this->config['NewUser'] ?? [];

        // Check if config params are all set
        $configParams = [
            'recordType', 'userGroup',
            'accountType', 'status', 'emailType',
        ];
        foreach ($configParams as $configParam) {
            if (empty(trim($newUserConfig[$configParam] ?? ''))) {
                $errorMessage = 'Configuration "' . $configParam . '" is not set ' .
                                'in Alma ini in the [NewUser] section!';
                $this->logError($errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // Calculate expiry date based on config in Alma.ini
        $expiryDate = new \DateTime('now');
        if (!empty(trim($newUserConfig['expiryDate'] ?? ''))) {
            try {
                $expiryDate->add(
                    new \DateInterval($newUserConfig['expiryDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "expiryDate" in Alma.ini (see ' .
                                '[NewUser] section) has the wrong format!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        } else {
            $expiryDate->add(new \DateInterval('P1Y'));
        }

        // Calculate purge date based on config in Alma.ini
        $purgeDate = null;
        if (!empty(trim($newUserConfig['purgeDate'] ?? ''))) {
            try {
                $purgeDate = new \DateTime('now');
                $purgeDate->add(
                    new \DateInterval($newUserConfig['purgeDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "purgeDate" in Alma.ini (see ' .
                                '[NewUser] section) has the wrong format!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // Create user XML for Alma API
        $xml = simplexml_load_string(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "\n\n<user/>"
        );
        $xml->addChild('record_type', $newUserConfig['recordType']);
        $xml->addChild('first_name', $formParams['firstname']);
        $xml->addChild('last_name', $formParams['lastname']);
        $xml->addChild('user_group', $newUserConfig['userGroup']);
        $xml->addChild(
            'preferred_language', $formParams['language']
        );
        $xml->addChild('account_type', $newUserConfig['accountType']);
        $xml->addChild('status', $newUserConfig['status']);
        $xml->addChild('expiry_date', $expiryDate->format('Y-m-d') . 'Z');
        if (null !== $purgeDate) {
            $xml->addChild('purge_date', $purgeDate->format('Y-m-d') . 'Z');
        }

        $contactInfo = $xml->addChild('contact_info');
        $emails = $contactInfo->addChild('emails');
        $email = $emails->addChild('email');
        $email->addAttribute('preferred', 'true');
        $email->addChild('email_address', $formParams['email']);
        $emailTypes = $email->addChild('email_types');
        $emailTypes->addChild('email_type', $newUserConfig['emailType']);

        $addresses = $contactInfo->addChild('addresses');
        $address = $addresses->addChild('address');
        $addressTypes = $address->addChild('address_types');
        $addressTypes->addChild('address_type', 'home');
        $address['preferred'] = 'true';
        $address->addChild('line1', $formParams['address']);
        $address->addChild('postal_code', $formParams['zip']);
        $address->addChild('city', $formParams['city']);

        $phones = $contactInfo->addChild('phones');
        $phone = $phones->addChild('phone');
        $phoneTypes = $phone->addChild('phone_types');
        $phoneTypes->addChild('phone_type', 'mobile');
        $phone['preferred'] = 'true';
        $phone->addChild('phone_number', $formParams['phone']);

        if (!empty($formParams['identitynumber'])) {
            $identityField = $newUserConfig['identityField'] ?? 'primary_id';
            if ('primary_id' === $identityField) {
                $xml->addChild('primary_id', $formParams['identitynumber']);
            } elseif ('inst_id' === $identityField) {
                $userIdentifiers = $xml->addChild('user_identifiers');
                $userIdentifier = $userIdentifiers->addChild('user_identifier');
                $userIdentifier->addChild('id_type', 'INST_ID');
                $userIdentifier->addChild('value', $formParams['identitynumber']);
            } elseif ('note' === $identityField) {
                $notes = $xml->addChild('user_notes');
                $note = $notes->addChild('user_note');
                $noteType = $note->addChild('note_type', 'OTHER');
                $noteType['Description'] = 'Other';
                $note->addChild('note_text', $formParams['identitynumber']);
                $note->addChild('user_viewable', 'false');
                $note->addChild('popup_note', 'false');
            }
        }

        $userXml = $xml->asXML();

        // Create user in Alma
        $this->makeRequest(
            '/users',
            [],
            [],
            'POST',
            $userXml,
            ['Content-Type' => 'application/xml']
        );

        return true;
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
        } elseif ('registerPatron' === $function) {
            $function = 'NewUser';
        }
        $config = parent::getConfig($function, $params);
        if ('updateProfile' === $function && isset($config['fields'])) {
            $config['fields'] = $this->getUpdateProfileFields();
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
            // Add code tables and choices
            if (!empty($config['fields'])) {
                foreach ($config['fields'] as &$field) {
                    $parts = explode(':', $field);
                    $fieldId = $parts[1] ?? '';
                    if ('country' === $fieldId
                        || preg_match('/^addresses\[[0-9]\]\[country\]$/', $fieldId)
                    ) {
                        $field = [
                            'field' => $fieldId,
                            'label' => $parts[0],
                            'type' => 'select',
                            'options' => $this->getCodeTableOptions(
                                'CountryCodes', 'description'
                            ),
                            'required' => ($parts[3] ?? '') === 'required',
                        ];
                    } elseif (preg_match('/^addresses\[[0-9]\]\[types\]$/', $fieldId)
                    ) {
                        // Add address types
                        $field = [
                            'field' => $fieldId,
                            'label' => $parts[0],
                            'type' => 'multiselect',
                            'options' => [
                                'home' => [
                                    'name' => 'address_type_home',
                                ],
                                'work' => [
                                    'name' => 'address_type_work',
                                ],
                                'school' => [
                                    'name' => 'address_type_school',
                                ],
                                'alternative' => [
                                    'name' => 'address_type_alternative',
                                ],
                            ],
                            'required' => ($parts[3] ?? '') === 'required',
                        ];
                    }
                }
            }
        }
        if ($config && 'Holds' === $function) {
            if (isset($config['titleHoldBibLevels'])
                && !is_array($config['titleHoldBibLevels'])
            ) {
                $config['titleHoldBibLevels']
                    = explode(':', $config['titleHoldBibLevels']);
            }
            if (!empty($params['id']) && !empty($params['patron']['id'])) {
                // Kludge to allow caching for two minutes
                $cacheLifeTime = $this->cacheLifetime;
                $this->cacheLifetime = 120;
                $cacheKey = md5(
                    'request-options-' . $params['id'] . '-'
                    . $params['patron']['id']
                );
                $requestOptions = $this->getCachedData($cacheKey);
                $this->cacheLifetime = $cacheLifeTime;
                if (null === $requestOptions) {
                    // Check if we require the part_issue (description) field
                    $requestOptionsPath = '/bibs/' . urlencode($params['id'])
                        . '/request-options?user_id='
                        . urlencode($params['patron']['id']);
                    // Make the API request
                    $requestOptions = $this->makeRequest($requestOptionsPath);
                    $this->putCachedData($cacheKey, $requestOptions->asXML());
                } else {
                    $requestOptions = simplexml_load_string($requestOptions);
                }
                // Check possible request types from the API answer
                $requestTypes = $requestOptions->xpath(
                    '/request_options/request_option//type'
                );
                $types = [];
                foreach ($requestTypes as $requestType) {
                    $types[] = (string)$requestType;
                }
                if ($types === ['PURCHASE']) {
                    $config['extraHoldFields']
                        = empty($config['extraHoldFields'])
                            ? 'part_issue'
                            : $config['extraHoldFields'] . ':part_issue';
                }

                // Add a flag so that checkRequestIsValid knows to check valid pickup
                // locations
                $config['HMACKeys']
                    = empty($config['HMACKeys'])
                        ? '__check_pickup'
                        : $config['HMACKeys'] . ':__check_pickup';
            }
        }
        return $config;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Hold details
     *
     * @return array An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickupLocations($patron, $holdDetails)
    {
        $libraries = parent::getPickupLocations($patron, $holdDetails);

        if ($patron && $holdDetails
            && !empty($this->config['Holds']['pickupLocationRules'])
        ) {
            $rules = $this->parsePickupLocationRules(
                $this->config['Holds']['pickupLocationRules']
            );
            // Filter the pickup locations using the rules

            $level = isset($holdDetails['level']) && !empty($holdDetails['level'])
                ? $holdDetails['level'] : 'copy';
            $bibId = $holdDetails['id'];
            $itemId = $holdDetails['item_id'] ?? false;

            $allItems = [];
            $availableItems = [];
            $unavailableItems = [];
            if ('copy' === $level && $itemId) {
                $item = $this->makeRequest(
                    '/bibs/' . urlencode($bibId) . '/holdings/ALL/items/'
                    . urlencode($itemId)
                );
                $items = [$item];
            } else {
                $items = $this->makeRequest(
                    '/bibs/' . urlencode($bibId) . '/holdings/ALL/items'
                );
                $items = $items->item;
            }
            foreach ($items as $item) {
                $lib = (string)$item->item_data->library;
                $loc = (string)$item->item_data->location;
                $policy = !empty($item->item_data->policy) ?
                    (string)$item->item_data->policy : '';
                $entry = [
                    'lib' => $lib,
                    'loc' => $loc,
                    'policy' => $policy
                ];
                $allItems[] = $entry;
                $status = (string)$item->item_data->base_status;
                if ('1' === $status) {
                    $availableItems[] = $entry;
                }
            }

            foreach ($allItems as $item) {
                foreach ($availableItems as $availItem) {
                    if ($item['lib'] === $availItem['lib']
                        && $item['loc'] === $availItem['loc']
                        && $item['policy'] === $availItem['policy']
                    ) {
                        continue 2;
                    }
                }
                $unavailableItems[] = $item;
            }

            $profile = $this->getMyProfile($patron);
            $patronGroup = $profile['group_code'] ?? '';
            $libraryFilter = null;
            $work = false;
            $home = false;
            foreach ($rules as $rule) {
                if (!empty($rule['level'])
                    && !$this->compareRuleWithArray($rule['level'], (array)$level)
                ) {
                    continue;
                }

                if ((!empty($rule['loc']) || !empty($rule['lib'])
                    || !empty($rule['policy']))
                    && !$this->compareItemRule(
                        $rule['lib'][0] ?? '',
                        $rule['loc'] ?? [],
                        $rule['policy'] ?? [],
                        $allItems
                    )
                ) {
                    continue;
                }
                if ((!empty($rule['avail']) || !empty($rule['availlib'])
                    || !empty($rule['availpolicy']))
                    && !$this->compareItemRule(
                        $rule['availlib'][0] ?? '',
                        $rule['avail'] ?? [],
                        $rule['availpolicy'] ?? [],
                        $availableItems
                    )
                ) {
                    continue;
                }
                if ((!empty($rule['unavail']) || !empty($rule['unavaillib'])
                    || !empty($rule['unavailpolicy']))
                    && !$this->compareItemRule(
                        $rule['unavaillib'][0] ?? '',
                        $rule['unavail'] ?? [],
                        $rule['unavailpolicy'] ?? [],
                        $unavailableItems
                    )
                ) {
                    continue;
                }

                if (!empty($rule['group'])) {
                    $match = $this->compareRuleWithArray(
                        $rule['group'], (array)$patronGroup
                    );
                    if (!$match) {
                        continue;
                    }
                }

                // We have a matching rule
                if (null === $libraryFilter) {
                    $libraryFilter = [];
                }
                $libraryFilter = array_merge($libraryFilter, $rule['pickup'] ?? []);

                if (!empty($rule['home'])) {
                    $home = !empty($profile['homeAddress'])
                        && $this->compareRuleWithArray(
                            $rule['home'], ['true']
                        );
                }
                if (!empty($rule['work'])) {
                    $work = !empty($profile['workAddress'])
                        && $this->compareRuleWithArray(
                            $rule['work'], ['true']
                        );
                }

                if (in_array('stop', $rule['match'] ?? [])) {
                    break;
                }
            }

            if (null === $libraryFilter) {
                $libraries = [];
            } else {
                $libraries = array_filter(
                    $libraries,
                    function ($library) use ($libraryFilter) {
                        return in_array($library['locationID'], $libraryFilter);
                    }
                );
            }

            if ($home) {
                $libraries[] = [
                    'locationID' => '$$HOME',
                    'locationDisplay' => $this->getTranslatableStringForCode(
                        'pickup_location_home_address',
                        'pickup_location_home_address'
                    )
                ];
            }
            if ($work) {
                if (!$home || $profile['homeAddress'] !== $profile['workAddress']) {
                    $libraries[] = [
                        'locationID' => '$$WORK',
                        'locationDisplay' => $this->getTranslatableStringForCode(
                            'pickup_location_work_address',
                            'pickup_location_work_address'
                        )
                    ];
                }
            }
        }

        return $libraries;
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
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyHolds($patron)
    {
        $holdList = [];
        $offset = 0;
        $totalCount = 1;
        while ($offset < $totalCount) {
            $xml = $this->makeRequest(
                '/users/' . $patron['id'] . '/requests',
                ['request_type' => 'HOLD', 'offset' => $offset, 'limit' => 100]
            );
            $offset += 100;
            $totalCount = (int)$xml->attributes()->{'total_record_count'};
            foreach ($xml as $request) {
                $lastInterestDate = $request->last_interest_date
                    ? $this->dateConverter->convertToDisplayDate(
                        'Y-m-dT',
                        (string)$request->last_interest_date
                    ) : null;
                $available = (string)$request->request_status === 'On Hold Shelf';
                $lastPickupDate = null;
                if ($available) {
                    $lastPickupDate = $request->expiry_date
                        ? $this->dateConverter->convertToDisplayDate(
                            'Y-m-dT',
                            (string)$request->expiry_date
                        ) : null;
                    $lastInterestDate = null;
                }
                $hold = [
                    'create' => $this->dateConverter->convertToDisplayDate(
                        'Y-m-dT',
                        (string)$request->request_date
                    ),
                    'expire' => $lastInterestDate,
                    'id' => (string)$request->request_id,
                    'available' => $available,
                    'last_pickup_date' => $lastPickupDate,
                    'item_id' => (string)$request->mms_id,
                    'location' => (string)$request->pickup_location,
                    'processed' => $request->item_policy === 'InterlibraryLoan'
                        && (string)$request->request_status !== 'Not Started',
                    'title' => (string)$request->title,
                ];
                if (!$available) {
                    if ('In Process' === (string)$request->request_status) {
                        $hold['position'] = $this->translate('status_In Process');
                    } else {
                        $hold['position'] = (int)($request->place_in_queue ?? 1);
                    }
                }

                $holdList[] = $hold;
            }
        }
        return $holdList;
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The record id
     * @param array  $data   An array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $patronId = $patron['id'];
        $level = $data['level'] ?? 'copy';
        if ('copy' === $level) {
            if (isset($this->config['Holds']['enableItemHolds'])
                && !$this->config['Holds']['enableItemHolds']
            ) {
                return false;
            }

            // Call the request-options API for the logged-in user
            $requestOptionsPath = '/bibs/' . urlencode($id)
                . '/holdings/' . urlencode($data['holding_id']) . '/items/'
                . urlencode($data['item_id']) . '/request-options?user_id='
                . urlencode($patronId);

            // Make the API request
            $requestOptions = $this->makeRequest($requestOptionsPath);
        } elseif ('title' === $level) {
            $hmac = explode(':', $this->config['Holds']['HMACKeys'] ?? '');
            if (!in_array('level', $hmac)) {
                return false;
            }
            // Call the request-options API for the logged-in user
            $requestOptionsPath = '/bibs/' . urlencode($id)
                . '/request-options?user_id=' . urlencode($patronId);

            // Make the API request
            $requestOptions = $this->makeRequest($requestOptionsPath);
        } else {
            return false;
        }

        $result = false;

        // Check possible request types from the API answer
        $requestTypes = $requestOptions->xpath(
            '/request_options/request_option//type'
        );
        foreach ($requestTypes as $requestType) {
            if (in_array((string)$requestType, ['HOLD', 'PURCHASE'])) {
                $result = true;
                break;
            }
        }

        if ($result && array_key_exists('__check_pickup', $data)) {
            // Check valid pickup locations
            if (empty($this->getPickupLocations($patron, $data))) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Place a hold request via Alma API. This could be a title level request or
     * an item level request.
     *
     * Finna: Handles the $$HOME and $$WORK pickup locations
     *
     * @param array $holdDetails An associative array w/ atleast patron and item_id
     *
     * @return array success: bool, sysMessage: string
     *
     * @link https://developers.exlibrisgroup.com/alma/apis/bibs
     */
    public function placeHold($holdDetails)
    {
        // Check for title or item level request
        $level = $holdDetails['level'] ?? 'item';

        // Get information that is valid for both, item level requests and title
        // level requests.
        $mmsId = $holdDetails['id'];
        $holId = $holdDetails['holding_id'];
        $itmId = $holdDetails['item_id'];
        $patronId = $holdDetails['patron']['id'];
        $pickupLocation = $holdDetails['pickUpLocation'] ?? null;
        $comment = $holdDetails['comment'] ?? null;
        $requiredBy = (isset($holdDetails['requiredBy']))
        ? $this->dateConverter->convertFromDisplayDate(
            'Y-m-d',
            $holdDetails['requiredBy']
        ) . 'Z'
        : null;

        // Create body for API request
        $body = [];
        $body['request_type'] = 'HOLD';
        if ('$$HOME' === $pickupLocation) {
            $body['pickup_location_type'] = 'USER_HOME_ADDRESS';
        } elseif ('$$WORK' === $pickupLocation) {
            $body['pickup_location_type'] = 'USER_WORK_ADDRESS';
        } else {
            $body['pickup_location_type'] = 'LIBRARY';
            $body['pickup_location_library'] = $pickupLocation;
        }
        $body['comment'] = $comment;
        $body['last_interest_date'] = $requiredBy;

        // Remove "null" values from body array
        $body = array_filter($body);

        // Check if we have a title level request or an item level request
        if ($level === 'title') {
            $partIssue = $holdDetails['part_issue'] ?? null;
            if ($partIssue) {
                // Alma doesn't have a way of placing an "other item" request via the
                // API (it would need to fill the manual_description field). And this
                // one will require a description. Take one, whichever, and add the
                // part or issue description in the comment field.
                $items = $this->makeRequest(
                    '/bibs/' . urlencode($mmsId) . '/holdings/ALL/items?limit=1'
                );
                $item = $items->item;
                if ($item->item_data->description) {
                    $body['description'] = (string)$item->item_data->description;
                }
                $body['comment'] = "Part or issue: $partIssue";
                if ($comment) {
                    $body['comment'] .= " -- Comment: $comment";
                }
            }

            // Create HTTP client with Alma API URL for title level requests
            $client = $this->httpService->createClient(
                $this->baseUrl . '/bibs/' . urlencode($mmsId)
                . '/requests?apikey=' . urlencode($this->apiKey)
                . '&user_id=' . urlencode($patronId)
                . '&format=json'
            );
        } else {
            // Create HTTP client with Alma API URL for item level requests
            $client = $this->httpService->createClient(
                $this->baseUrl . '/bibs/' . urlencode($mmsId)
                . '/holdings/' . urlencode($holId)
                . '/items/' . urlencode($itmId)
                . '/requests?apikey=' . urlencode($this->apiKey)
                . '&user_id=' . urlencode($patronId)
                . '&format=json'
            );
        }

        // Set headers
        $client->setHeaders(
            [
            'Content-type: application/json',
            'Accept: application/json'
            ]
        );

        // Set HTTP method
        $client->setMethod(\Zend\Http\Request::METHOD_POST);

        // Set body
        $client->setRawBody(json_encode($body));

        // Send API call and get response
        $response = $client->send();

        // Check for success
        if ($response->isSuccess()) {
            return ['success' => true];
        } else {
            $this->logError(
                'POST request for ' . $client->getUri()->toString() . ' failed: '
                . $response->getBody()
            );
        }

        // Get error message
        $error = json_decode($response->getBody());
        if (!$error) {
            $error = simplexml_load_string($response->getBody());
        }

        $errorCode = $error->errorList->error[0]->errorCode ?? null;
        switch ($errorCode) {
        case '401136':
            $errorMsg = 'hold_error_already_held';
            break;
        case '401129':
            $errorMsg = 'hold_error_cannot_fulfill';
            break;
        case '401652':
            $errorMsg = 'hold_error_fail';
            break;
        default:
            $errorMsg = $error->errorList->error[0]->errorMessage
                ?? 'hold_error_fail';
        }

        if ('Missing mandatory field: Description.' === $errorMsg) {
            $errorMsg = $this->translate('This field is required') . ': '
                . $this->translate('hold_part_issue');
        }

        return [
            'success' => false,
            'sysMessage' => $errorMsg
        ];
    }

    /**
     * Get details of a single hold request.
     *
     * @param array $holdDetails One of the item arrays returned by the
     *                           getMyHolds method
     *
     * @return string            The Alma request ID
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return (empty($holdDetails['available'])
            || !empty($this->config['Holds']['allowCancelingAvailableRequests']))
            ? $holdDetails['id'] : '';
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * Finna:
     *  - Don't use a running number as item number.
     *  - Handle suppressed holdings.
     *  - Add holdings for locations with no items.
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
        // Check if the request is for more details
        if (!empty($options['detailsGroupKey'])) {
            return $this->getHoldingsDetails(
                $id, $options['detailsGroupKey'], $patron, $options
            );
        }

        $displayRequests = !isset($this->config['Holdings']['displayTotalHoldCount'])
            || $this->config['Holdings']['displayTotalHoldCount'];

        // Two cases: if there aren't too many items, fetch everything and be done
        // with it. Otherwise fetch only summary availability first.
        $itemLimit = $this->config['Holdings']['itemLimit'] ?? 100;
        $itemsResult = $this->makeRequest(
            '/bibs/' . urlencode($id) . '/holdings/ALL/items',
            [
                'limit' => $itemLimit,
                'expand' => 'due_date',
            ]
        );
        if ($itemsResult) {
            $totalItems = (int)$itemsResult->attributes()->total_record_count;
            if ($totalItems <= $itemLimit) {
                $items = $this->getHoldingsItems(
                    $itemsResult,
                    $id,
                    $patron,
                    true,
                    true
                );

                $result = [
                    'holdings' => $items['items'],
                    'total' => $items['total'],
                ];

                // Add summary
                $externalInterfaceUrl = str_replace(
                    '%%id%%',
                    $id,
                    $this->config['Holdings']['externalInterfaceUrl'] ?? ''
                );
                $summary = [
                    'available' => $items['available'],
                    'total' => $items['total'],
                    'availability' => null,
                    'callnumber' => null,
                    'location' => '__HOLDINGSSUMMARYLOCATION__',
                    'externalInterfaceUrl' => $externalInterfaceUrl,
                ];

                if ($displayRequests) {
                    $bib = $this->makeRequest(
                        '/bibs/' . urlencode($id) . '?expand=requests'
                    );
                    $summary['reservations'] = (int)$bib->requests ?? 0;
                }
                $result['holdings'][] = $summary;

                return $result;
            }
        }

        $total = 0;
        $totalAvailable = 0;
        $requests = 0;
        $locations = [];
        $holdings = [];

        // Summary holdings
        $params = [
            'expand' => implode(',', $this->getInventoryTypes())
        ];
        if ($displayRequests) {
            $params['expand'] .= ',requests';
        }
        if ($bib = $this->makeRequest('/bibs/' . urlencode($id), $params)) {
            $requests = (int)$bib->requests ?? 0;
            $marc = new \File_MARCXML(
                $bib->record->asXML(),
                \File_MARCXML::SOURCE_STRING
            );
            $sort = 0;
            $tmpl = [
                'id' => (string)$bib->mms_id,
                'source' => 'Solr',
                'callnumber' => '',
                'reserve' => 'N',
            ];
            if ($record = $marc->next()) {
                // Physical
                $physicalItems = $record->getFields('AVA');
                foreach ($physicalItems as $field) {
                    // Filter out suggestions for other records
                    $mmsId = $this->getMarcSubfield($field, '0');
                    if ($mmsId !== (string)$bib->mms_id) {
                        continue;
                    }
                    $status = $this->getMarcSubfield($field, 'e');
                    $libraryCode = $this->getMarcSubfield($field, 'b');
                    $locationCode = $this->getMarcSubfield($field, 'j');
                    $location = $this->getMarcSubfield($field, 'c');
                    $items = $this->getMarcSubfield($field, 'f') ?: 0;
                    $unavailable = $this->getMarcSubfield($field, 'g') ?: 0;
                    $available = $items - $unavailable;
                    $holdingId = $this->getMarcSubfield($field, '8');
                    $total += $items;
                    $totalAvailable += $available;
                    $locations[$locationCode] = 1;
                    switch ($status) {
                    case 'available':
                        $status = 'Available';
                        break;
                    case 'unavailable':
                        $status = 'Not Available';
                        break;
                    case 'check_holdings':
                        $status = '';
                        break;
                    }

                    $holdings[] = [
                        'id' => $holdingId,
                        'source' => 'Solr',
                        'availability' => $available,
                        'status' => $status,
                        'location' => $this->getTranslatableStringForCode(
                            $locationCode,
                            $location
                        ),
                        'location_code' => $locationCode,
                        'reserve' => 'N',   // TODO: support reserve status
                        'callnumber' => $this->getMarcSubfield($field, 'd'),
                        'duedate' => null,
                        'returnDate' => false, // TODO: support recent returns
                        'barcode' => '',
                        'holding_id' => $holdingId,
                        'detailsGroupKey'
                            => "$holdingId||$libraryCode||$locationCode",
                        'sort' => $sort++
                    ];
                }
                // Electronic
                $electronicItems = $record->getFields('AVE');
                foreach ($electronicItems as $field) {
                    $avail = $this->getMarcSubfield($field, 'e');
                    $item = $tmpl;
                    $item['availability'] = strtolower($avail) === 'available';
                    // Use the following subfields for location:
                    // m (Collection name)
                    // i (Available for library)
                    // d (Available for library)
                    // b (Available for library)
                    $location = [
                        $this->getMarcSubfield($field, 'm') ?: 'Get full text'
                    ];
                    foreach (['i', 'd', 'b'] as $code) {
                        if ($content = $this->getMarcSubfield($field, $code)) {
                            $location[] = $content;
                        }
                    }
                    $item['location'] = implode(' - ', $location);
                    $item['callnumber'] = $this->getMarcSubfield($field, 't');
                    $url = $this->getMarcSubfield($field, 'u');
                    if (preg_match('/^https?:\/\//', $url)) {
                        $item['locationhref'] = $url;
                    }
                    $item['status'] = $this->getMarcSubfield($field, 's')
                        ?: null;
                    if ($note = $this->getMarcSubfield($field, 'n')) {
                        $item['item_notes'] = [$note];
                    }
                    $item['sort'] = $sort++;
                    $holdings[] = $item;
                }
                // Digital
                $deliveryUrl
                    = $this->config['Holdings']['digitalDeliveryUrl'] ?? '';
                $digitalItems = $record->getFields('AVD');
                if ($digitalItems && !$deliveryUrl) {
                    $this->logWarning(
                        'Digital items exist for ' . (string)$bib->mms_id
                        . ', but digitalDeliveryUrl not set -- unable to'
                        . ' generate links'
                    );
                }
                foreach ($digitalItems as $field) {
                    $item = $tmpl;
                    unset($item['callnumber']);
                    $item['availability'] = true;
                    $item['location'] = $this->getMarcSubfield($field, 'e');
                    // Using subfield 'd' ('Repository Name') as callnumber
                    $item['callnumber'] = $this->getMarcSubfield($field, 'd');
                    if ($deliveryUrl) {
                        $item['locationhref'] = str_replace(
                            '%%id%%',
                            $this->getMarcSubfield($field, 'b'),
                            $deliveryUrl
                        );
                    }
                    $item['sort'] = $sort++;
                    $holdings[] = $item;
                }
            }
            usort($holdings, [$this, 'statusSortFunction']);

            // Return locations to strings and add flags to skip items list
            foreach ($holdings as &$item) {
                $item['location'] = $this->translate($item['location']);
                $item['skipItemsList'] = true;
            }
            unset($item);
        }

        // The AVA field might be borked...
        $total = max($total, count($holdings));

        // Add summary
        $summary = [
           'available' => $totalAvailable,
           'total' => $total,
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => '__HOLDINGSSUMMARYLOCATION__'
        ];
        if ($displayRequests) {
            $summary['reservations'] = $requests;
        }
        $holdings[] = $summary;

        return compact('total', 'holdings');
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
        if ('registerPatron' === $method) {
            $config = $this->config['NewUser'] ?? [];
            $required = [
                'recordType', 'accountType', 'status', 'userGroup',
                'emailType', 'termsUrl'
            ];
            foreach ($required as $key) {
                if (empty($config[$key])) {
                    return false;
                }
            }
            return true;
        }
        return parent::supportsMethod($method, $params);
    }

    /**
     * Get detailed holding information for a single holdings record
     *
     * @param string $id       Bib record id
     * @param string $groupKey Details group key
     * @param array  $patron   Patron data
     * @param array  $params   Params (offset, itemLimit etc.)
     *
     * @return array
     */
    protected function getHoldingsDetails($id, $groupKey, $patron = null,
        $params = []
    ) {
        list($holdingId, $libraryCode, $locationCode) = explode('||', $groupKey);

        // Summary holdings
        if ($holdingId) {
            $holding = $this->makeRequest(
                '/bibs/' . urlencode($id) . '/holdings/' . urlencode($holdingId)
            );

            if ('true' === (string)$holding->suppress_from_publishing) {
                return [
                    'holdingsDetails' => [],
                    'items' => [],
                    'totalItems' => 0
                ];
            }
        }

        $marcDetails = [];
        if ($params['page'] == 1) {
            $marc = $holding->record;

            // Get Notes
            $data = $this->getHoldingsMarc(
                $marc,
                isset($this->config['Holdings']['notes'])
                ? $this->config['Holdings']['notes']
                : '852z'
            );
            if ($data) {
                $marcDetails['notes'] = $data;
            }

            // Get Summary (may be multiple lines)
            $data = $this->getHoldingsMarc(
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
                $data = $this->getHoldingsMarc(
                    $marc,
                    $this->config['Holdings']['supplements']
                );
                if ($data) {
                    $marcDetails['supplements'] = $data;
                }
            }

            // Get Indexes
            if (isset($this->config['Holdings']['indexes'])) {
                $data = $this->getHoldingsMarc(
                    $marc,
                    $this->config['Holdings']['indexes']
                );
                if ($data) {
                    $marcDetails['indexes'] = $data;
                }
            }

            // Get links
            if (isset($this->config['Holdings']['links'])) {
                $data = $this->getHoldingsMarc(
                    $marc,
                    $this->config['Holdings']['links']
                );
                if ($data) {
                    $marcDetails['links'] = $data;
                }
            }
        }

        // Make sure to return an empty array unless we have details to display
        if (!empty($marcDetails)) {
            $marcDetails['holding_id'] = $holdingId;
        }

        // Items
        $items = [
            'items' => [],
            'total' => 0
        ];
        if ($libraryCode && $locationCode) {
            $itemLimit = min(
                $params['itemLimit'] ?? 100,
                $this->config['Holdings']['itemLimit'] ?? 100
            );
            $page = ($params['page'] ?? 1) - 1;
            // NOTE: According to documentation offset is 0-based, but in reality it
            // seems to be 1-based.
            $queryParams = [
                'offset' => $page * $itemLimit + 1,
                'limit' => $itemLimit,
                'current_location' => $locationCode,
                'order_by' => 'description,enum_a,enum_b',
                'direction' => 'desc',
                'expand' => 'due_date',
            ];

            if ($holdingId) {
                $itemsReq = '/bibs/' . urlencode($id) . '/holdings/'
                    . urlencode($holdingId) . '/items';
            } else {
                $queryParams['current_library'] = $libraryCode;
                $queryParams['current_location'] = $locationCode;
                $itemsReq = '/bibs/' . urlencode($id) . '/holdings/ALL/items';
            }

            $itemsResult = $this->makeRequest($itemsReq, $queryParams);
            $items = $this->getHoldingsItems($itemsResult, $id, $patron, false);
        }

        return [
            'details' => $marcDetails,
            'holdings' => $items['items'],
            'total' => $items['total'],
            'detailsGroupKey' => "||$libraryCode||$locationCode",
        ];
    }

    /**
     * Get items as holdings entries
     *
     * @param \SimpleXMLElement $itemsResult         Items from Alma
     * @param string            $id                  Record id
     * @param array             $patron              Patron
     * @param bool              $sortItems           Whether to sort the items
     * @param bool              $addItemlessHoldings Whether to include holdings that
     * don't have items
     *
     * @return array
     */
    protected function getHoldingsItems($itemsResult, $id, $patron = null,
        $sortItems = false, $addItemlessHoldings = false
    ) {
        // For checking for suppressed holdings. This is a bit complicated,
        // but the holding information in items is missing this crucial bit.
        $almaHoldings = [];
        $records = $this->makeRequest('/bibs/' . urlencode($id) . '/holdings');
        foreach ($records->holding ?? [] as $record) {
            $almaHoldings[(string)$record->holding_id] = $record;
        }

        $sort = 0;
        $items = [];
        $totalItems = 0;
        $availableItems = 0;
        $itemHolds = $this->config['Holds']['enableItemHolds'] ?? null;
        if ($itemsResult) {
            $totalItems = (int)$itemsResult->attributes()->total_record_count;

            foreach ($itemsResult->item as $item) {
                $processType = (string)($item->item_data->process_type ?? '');
                $format = (string)($item->item_data->physical_material_type ?? '');
                if (in_array($processType, $this->hiddenProcessTypes[$format] ?? [])
                    || in_array($processType, $this->hiddenProcessTypes['*'] ?? [])
                ) {
                    continue;
                }
                $holdingId = (string)$item->holding_data->holding_id;
                if ($almaHolding = $almaHoldings[$holdingId] ?? null) {
                    if ('true' === (string)$almaHolding->suppress_from_publishing) {
                        continue;
                    }
                }

                $itemId = (string)$item->item_data->pid;
                $barcode = (string)$item->item_data->barcode;
                $status = (string)$item->item_data->base_status[0]
                    ->attributes()['desc'];
                $duedate = $item->item_data->due_date
                    ? $this->parseDate((string)$item->item_data->due_date) : null;
                if ($duedate && 'Item not in place' === $status) {
                    $status = 'Checked Out';
                }

                $itemNotes = !empty($item->item_data->public_note)
                    ? [(string)$item->item_data->public_note] : null;

                if ($processType && 'LOAN' !== $processType) {
                    $status = $this->getTranslatableStatusString(
                        $item->item_data->process_type
                    );
                }

                $description = null;
                $number = null;
                if (!empty($item->item_data->description)) {
                    $number = (string)$item->item_data->description;
                    $description = (string)$item->item_data->description;
                }

                $addLink = $patron ? 'check' : false;
                if ($addLink && null !== $itemHolds) {
                    if ('description' === $itemHolds) {
                        $addLink = null !== $description;
                    } elseif (!$itemHolds) {
                        $addLink = false;
                    }
                }
                $available = $this->getAvailabilityFromItem($item);
                if ($available) {
                    ++$availableItems;
                }

                $items[] = [
                    'id' => $id,
                    'source' => 'Solr',
                    'availability' => $available,
                    'status' => $status,
                    'location' => $this->getItemLocation($item),
                    'location_code' => (string)$item->item_data->location,
                    'reserve' => 'N',   // TODO: support reserve status
                    'callnumber' => $this->getTranslatableString(
                        $item->holding_data->call_number
                    ),
                    'duedate' => $duedate,
                    'returnDate' => false, // TODO: support recent returns
                    'number' => $number,
                    'barcode' => empty($barcode) ? 'n/a' : $barcode,
                    'item_notes' => $itemNotes ?? null,
                    'item_id' => $itemId,
                    'holding_id' => $holdingId,
                    'holdtype' => 'auto',
                    'addLink' => $addLink,
                    // For Alma title-level hold requests
                    'description' => $description ?? null,
                    'detailsGroupKey' => $holdingId ? "$holdingId||||" : '',
                    'sort' => $sort++
                ];
            }
        }

        if ($addItemlessHoldings) {
            $noItemsHoldings = [];
            foreach ($almaHoldings as $record) {
                if ('true' === (string)$record->suppress_from_publishing) {
                    continue;
                }
                foreach ($items as $item) {
                    if ($item['holding_id'] === (string)$record->holding_id) {
                        continue 2;
                    }
                }
                $noItemsHoldings[] = $record;
            }

            foreach ($noItemsHoldings as $record) {
                $entry = $this->createHoldingEntry($id, $record);
                $entry['details_ajax'] = $entry['holding_id'];
                $entry['sort'] = $sort++;
                $entry['detailsGroupKey'] = $entry['holding_id'] . '||||';
                $items[] = $entry;
                ++$totalItems;
            }
        }

        if ($sortItems) {
            usort($items, [$this, 'statusSortFunction']);
        }

        // Return locations to strings
        foreach ($items as &$item) {
            $item['location'] = $this->translate($item['location']);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $totalItems,
            'available' => $availableItems,
        ];
    }

    /**
     * Create a holding entry
     *
     * @param string $id      Bib ID
     * @param object $holding Holding
     *
     * @return array
     */
    protected function createHoldingEntry($id, $holding)
    {
        $location = $this->getTranslatableString($holding->library);
        $callnumber = $holding->call_number
            ? $this->getTranslatableString($holding->call_number) : '';

        return [
            'id' => $id,
            'item_id' => 'HLD_' . (string)$holding->holding_id,
            'location' => $location,
            'location_code' => (string)$holding->library,
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
     * Get specified fields from a Holdings MARC Record
     *
     * @param object       $record     SimpleXMLElement
     * @param array|string $fieldSpecs Array or colon-separated list of
     * field/subfield specifications (3 chars for field code and then subfields,
     * e.g. 866az)
     *
     * @return array
     */
    protected function getHoldingsMarc($record, $fieldSpecs)
    {
        if (!is_array($fieldSpecs)) {
            $fieldSpecs = explode(':', $fieldSpecs);
        }
        $results = [];
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
                        $results[] = $line;
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Get location for an item
     *
     * @param SimpleXMLElement $item Item
     *
     * @return \VuFind\I18n\TranslatableString|string
     */
    protected function getItemLocation($item)
    {
        // Yes, temporary location is in holding data while permanent location is in
        // item data.
        if ('true' === (string)$item->holding_data->in_temp_location) {
            $library = $item->holding_data->temp_library
                ?: $item->item_data->library;
            $location = $item->holding_data->temp_location
                ?: $item->item_data->location;
        } else {
            $library = $item->item_data->library;
            $location = $item->item_data->location;
        }
        $value = ($this->config['Catalog']['translationPrefix'] ?? '') . $location;
        $desc = $this->getLocationExternalName((string)$library, (string)$location);
        if (null === $desc) {
            $desc
                = (string)($item->item_data->location->attributes()->desc ?? $value);
        }
        return new \VuFind\I18n\TranslatableString($value, $desc);
    }

    /**
     * Get the external name of a location
     *
     * @param string $library  Library
     * @param string $location Location
     *
     * @return string
     */
    protected function getLocationExternalName($library, $location)
    {
        $cacheId = 'alma|locations|' . $library;
        $saveLifetime = $this->cacheLifetime;
        $this->cacheLifetime = 3600;
        $locations = $this->getCachedData($cacheId);
        $this->cacheLifetime = $saveLifetime;

        if (null === $locations) {
            $xml = $this->makeRequest(
                '/conf/libraries/' . urlencode($library) . '/locations'
            );
            $locations = [];
            foreach ($xml as $entry) {
                $locations[(string)$entry->code] = [
                    'name' => (string)$entry->name,
                    'externalName' => (string)$entry->external_name
                ];
            }
            $this->putCachedData($cacheId, $locations);
        }
        return !empty($locations[$location]['externalName'])
            ? $locations[$location]['externalName']
            : ($locations[$location]['name'] ?? null);
    }

    /**
     * Get Statuses for inventory types
     *
     * This is responsible for retrieving the status information for a
     * collection of records with specified inventory types.
     *
     * Finna:
     *  - Get location codes too, and sort results
     *
     * @param array $ids   The array of record ids to retrieve the status for
     * @param array $types Inventory types
     *
     * @return array An array of getStatus() return values on success.
     */
    protected function getStatusesForInventoryTypes($ids, $types)
    {
        $results = [];
        $params = [
            'mms_id' => implode(',', $ids),
            'expand' => implode(',', array_unique(array_merge($types, ['requests'])))
        ];
        if ($bibs = $this->makeRequest('/bibs', $params)) {
            foreach ($bibs as $bib) {
                $marc = new \File_MARCXML(
                    $bib->record->asXML(),
                    \File_MARCXML::SOURCE_STRING
                );
                $status = [];
                $tmpl = [
                    'id' => (string)$bib->mms_id,
                    'source' => 'Solr',
                    'callnumber' => '',
                    'reserve' => 'N',
                ];
                $sort = 0;
                if ($record = $marc->next()) {
                    $externalInterfaceUrl = str_replace(
                        '%%id%%',
                        (string)$bib->mms_id,
                        $this->config['Holdings']['externalInterfaceUrl'] ?? ''
                    );

                    // Physical
                    $physicalItems = $record->getFields('AVA');
                    foreach ($physicalItems as $field) {
                        // Filter out suggestions for other records
                        $mmsId = $this->getMarcSubfield($field, '0');
                        if ($mmsId !== (string)$bib->mms_id) {
                            continue;
                        }
                        $avail = $this->getMarcSubfield($field, 'e');
                        $item = $tmpl;
                        $item['availability'] = strtolower($avail) === 'available';
                        $item['location_code'] = $this->getMarcSubfield($field, 'j');
                        $item['location'] = $this->getTranslatableStringForCode(
                            $item['location_code'],
                            $this->getMarcSubfield($field, 'c')
                        );
                        $item['callnumber'] = $this->getMarcSubfield($field, 'd');
                        $item['sort'] = $sort++;
                        $item['externalInterfaceUrl'] = $externalInterfaceUrl;
                        $status[] = $item;
                    }
                    // Electronic
                    $electronicItems = $record->getFields('AVE');
                    foreach ($electronicItems as $field) {
                        $avail = $this->getMarcSubfield($field, 'e');
                        $item = $tmpl;
                        $item['availability'] = strtolower($avail) === 'available';
                        // Use the following subfields for location:
                        // m (Collection name)
                        // i (Available for library)
                        // d (Available for library)
                        // b (Available for library)
                        $location = [
                            $this->getMarcSubfield($field, 'm') ?: 'Get full text'
                        ];
                        foreach (['i', 'd', 'b'] as $code) {
                            if ($content = $this->getMarcSubfield($field, $code)) {
                                $location[] = $content;
                            }
                        }
                        $item['location'] = implode(' - ', $location);
                        $item['callnumber'] = $this->getMarcSubfield($field, 't');
                        $url = $this->getMarcSubfield($field, 'u');
                        if (preg_match('/^https?:\/\//', $url)) {
                            $item['locationhref'] = $url;
                        }
                        $item['status'] = $this->getMarcSubfield($field, 's')
                            ?: null;
                        if ($note = $this->getMarcSubfield($field, 'n')) {
                            $item['item_notes'] = [$note];
                        }
                        $item['sort'] = $sort++;
                        $item['externalInterfaceUrl'] = $externalInterfaceUrl;
                        $status[] = $item;
                    }
                    // Digital
                    $deliveryUrl
                        = $this->config['Holdings']['digitalDeliveryUrl'] ?? '';
                    $digitalItems = $record->getFields('AVD');
                    if ($digitalItems && !$deliveryUrl) {
                        $this->logWarning(
                            'Digital items exist for ' . (string)$bib->mms_id
                            . ', but digitalDeliveryUrl not set -- unable to'
                            . ' generate links'
                        );
                    }
                    foreach ($digitalItems as $field) {
                        $item = $tmpl;
                        unset($item['callnumber']);
                        $item['availability'] = true;
                        $item['location'] = $this->getMarcSubfield($field, 'e');
                        // Using subfield 'd' ('Repository Name') as callnumber
                        $item['callnumber'] = $this->getMarcSubfield($field, 'd');
                        if ($deliveryUrl) {
                            $item['locationhref'] = str_replace(
                                '%%id%%',
                                $this->getMarcSubfield($field, 'b'),
                                $deliveryUrl
                            );
                        }
                        $item['sort'] = $sort++;
                        $status[] = $item;
                    }
                }
                usort($status, [$this, 'statusSortFunction']);

                // Return locations to strings
                foreach ($status as &$item) {
                    $item['location'] = $this->translate($item['location']);
                }
                unset($item);

                $results[(string)$bib->mms_id] = $status;
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
     * Parse pickup location rules from configuration
     *
     * @param array $config Rule configuration
     *
     * @return array
     */
    protected function parsePickupLocationRules($config)
    {
        $rules = [];
        foreach ($config as $rule) {
            $items = array_map('trim', str_getcsv($rule, ':'));
            $ruleParts = [];
            foreach ($items as $item) {
                $parsed = parse_ini_string($item, false, INI_SCANNER_RAW);
                foreach ($parsed as $key => $value) {
                    if (!isset($ruleParts[$key])) {
                        $ruleParts[$key] = [];
                    }
                    $ruleParts[$key] = array_merge(
                        $ruleParts[$key],
                        array_map('trim', str_getcsv($value, ',', "'"))
                    );
                }
            }
            $rules[] = $ruleParts;
        }
        return $rules;
    }

    /**
     * Compare a rule with an array of values
     *
     * @param string|array $rule   Rule values
     * @param array        $values Values
     *
     * @return bool
     */
    protected function compareRuleWithArray($rule, $values)
    {
        $negated = false;
        $result = false;
        // First non-negated rules...
        foreach ((array)$rule as $ruleValue) {
            if (strncmp($ruleValue, '!', 1) === 0) {
                // We have negated rules, no point in continuing positive matches
                $negated = true;
                break;
            }
            $ruleValue = addcslashes($ruleValue, '\\');
            foreach ($values as $value) {
                if (preg_match("/^$ruleValue\$/i", $value)) {
                    $result = true;
                }
            }
        }
        if (!$negated) {
            return $result;
        }

        // ... then negated rules
        foreach ((array)$rule as $ruleValue) {
            if (strncmp($ruleValue, '!', 1) !== 0) {
                continue;
            }
            $ruleValue = substr($ruleValue, 1);
            $ruleValue = addcslashes($ruleValue, '\\');
            foreach ($values as $value) {
                if (preg_match("/^$ruleValue\$/i", $value)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Compare an item rule
     *
     * @param string       $lib    Library
     * @param string|array $loc    Locations
     * @param string|array $policy Item policies
     * @param array        $items  Item information
     *
     * @return bool
     */
    protected function compareItemRule($lib, $loc, $policy, $items)
    {
        foreach ($items as $item) {
            if ($lib && $item['lib'] !== $lib) {
                continue;
            }
            if ($loc && !$this->compareRuleWithArray($loc, (array)$item['loc'])) {
                continue;
            }
            if ($policy
                && !$this->compareRuleWithArray($policy, (array)$item['policy'])
            ) {
                continue;
            }

            return true;
        }
        return false;
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
        $orderA = $this->holdingsLocationOrder[$a['location_code'] ?? ''] ?? 999;
        $orderB = $this->holdingsLocationOrder[$b['location_code'] ?? ''] ?? 999;
        $result = $orderA - $orderB;

        if (0 === $result) {
            $orderA = $this->holdingsLocationOrder[(string)$a['location']] ?? 999;
            $orderB = $this->holdingsLocationOrder[(string)$b['location']] ?? 999;
            $result = $orderA - $orderB;
        }

        if (0 === $result) {
            $result = strcmp(
                ($a['location'] instanceof \VuFind\I18n\TranslatableString)
                    ? $a['location']->getDisplayString() : $a['location'],
                ($b['location'] instanceof \VuFind\I18n\TranslatableString)
                    ? $b['location']->getDisplayString() : $b['location']
            );
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

    /**
     * Gets a translatable string for description and code
     *
     * @param string $code        Code
     * @param string $description Description
     *
     * @return \VuFind\I18n\TranslatableString
     */
    protected function getTranslatableStringForCode($code, $description)
    {
        $value = ($this->config['Catalog']['translationPrefix'] ?? '')
            . (string)$code;
        return new \VuFind\I18n\TranslatableString($value, $description);
    }

    /**
     * Get fields available for profile update
     *
     * @return array
     */
    protected function getUpdateProfileFields()
    {
        $fieldConfig = isset($this->config['updateProfile']['fields'])
            ? $this->config['updateProfile']['fields'] : [];
        if ($disabled = ($this->config['updateProfile']['disabledFields'] ?? '')) {
            $result = [];
            $disabled = explode(':', $disabled);
            foreach ($fieldConfig as $field) {
                $parts = explode(':', $field);
                if (!in_array($parts[1] ?? '', $disabled)) {
                    $result[] = $field;
                }
            }
            return $result;
        }
        return $fieldConfig;
    }
}
