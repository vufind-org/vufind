<?php
/**
 * III Sierra REST API driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\VuFind\Exception;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindHttp\HttpServiceAwareInterface;
use Zend\Log\LoggerAwareInterface;

/**
 * III Sierra REST API driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class SierraRest extends AbstractBase implements TranslatorAwareInterface,
    HttpServiceAwareInterface, LoggerAwareInterface
{
    use CacheTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Driver configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Factory function for constructing the SessionContainer.
     *
     * @var Callable
     */
    protected $sessionFactory;

    /**
     * Session cache
     *
     * @var \Zend\Session\Container
     */
    protected $sessionCache;

    /**
     * Whether item holds are enabled
     *
     * @var bool
     */
    protected $itemHoldsEnabled;

    /**
     * Item codes for which item level hold is not allowed
     *
     * @var array
     */
    protected $itemHoldExcludedItemCodes;

    /**
     * Bib levels for which title level hold is allowed
     *
     * @var array
     */
    protected $titleHoldBibLevels;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Whether to check that items exist when placing a hold
     *
     * @var bool
     */
    protected $checkItemsExist;

    /**
     * Item statuses that allow placing a hold
     *
     * @var array
     */
    protected $validHoldStatuses;

    /**
     * Mappings from item status codes to VuFind strings
     *
     * @var array
     */
    protected $itemStatusMappings = [
        '!' => 'On Holdshelf',
        't' => 'In Transit',
        'o' => 'On Reference Desk',
        'k' => 'In Repair',
        'm' => 'Missing',
        'n' => 'Long Overdue',
        '$' => 'Lost--Library Applied',
        'p' => '',
        'z' => 'Claims Returned',
        's' => 'On Search',
        'd' => 'In Process',
        '-' => 'On Shelf',
        'Charged' => 'Charged',
    ];

    /**
     * Available API version
     *
     * Functionality requiring a specific minimum version:
     *
     * v5:
     *   - last pickup date for holds
     * v5.1 (technically still v5 but added in a later revision):
     *   - summary holdings information (especially for serials)
     *
     * @var int
     */
    protected $apiVersion = 5;

    /**
     * Whether to sort items by enumchron. Default is true.
     *
     * @var array
     */
    protected $sortItemsByEnumChron;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter  Date converter object
     * @param Callable               $sessionFactory Factory function returning
     * SessionContainer object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        $sessionFactory
    ) {
        $this->dateConverter = $dateConverter;
        $this->sessionFactory = $sessionFactory;
    }

    /**
     * Set configuration.
     *
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
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
        // Validate config
        $required = ['host', 'client_key', 'client_secret'];
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }

        $this->validHoldStatuses
            = !empty($this->config['Holds']['valid_hold_statuses'])
            ? explode(':', $this->config['Holds']['valid_hold_statuses'])
            : [];

        $this->itemHoldsEnabled
            = isset($this->config['Holds']['enableItemHolds'])
            ? $this->config['Holds']['enableItemHolds'] : true;

        $this->itemHoldExcludedItemCodes
            = !empty($this->config['Holds']['item_hold_excluded_item_codes'])
            ? explode(':', $this->config['Holds']['item_hold_excluded_item_codes'])
            : [];

        $this->titleHoldBibLevels
            = !empty($this->config['Holds']['title_hold_bib_levels'])
            ? explode(':', $this->config['Holds']['title_hold_bib_levels'])
            : ['a', 'b', 'm', 'd'];

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation']
            : '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }

        if (!empty($this->config['ItemStatusMappings'])) {
            $this->itemStatusMappings = array_merge(
                $this->itemStatusMappings, $this->config['ItemStatusMappings']
            );
        }

        if (isset($this->config['Catalog']['api_version'])) {
            $this->apiVersion = $this->config['Catalog']['api_version'];
        }

        $this->sortItemsByEnumChron
            = $this->config['Holdings']['sort_by_enum_chron'] ?? true;

        // Init session cache for session-specific data
        $namespace = md5(
            $this->config['Catalog']['host'] . '|'
            . $this->config['Catalog']['client_key']
        );
        $factory = $this->sessionFactory;
        $this->sessionCache = $factory($namespace);
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
        return $this->getItemStatusesForBib($id);
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
            $items[] = $this->getItemStatusesForBib($id);
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
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null)
    {
        return $this->getItemStatusesForBib($id, true);
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
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        return ['count' => 0, 'results' => []];
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @return mixed An array of associative arrays representing reserve items.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        return [];
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        // We could get the access token and use the token info API, but since we
        // already know the barcode, we can avoid one API call and get the patron
        // information right away (makeRequest renews the access token as necessary
        // which verifies the PIN code).

        $result = $this->makeRequest(
            ['v3', 'info', 'token'],
            [],
            'GET',
            ['cat_username' => $username, 'cat_password' => $password]
        );
        if (null === $result) {
            return null;
        }
        if (empty($result['patronId'])) {
            throw new ILSException('No patronId in token response');
        }
        $patronId = $result['patronId'];

        $result = $this->makeRequest(
            ['v3', 'patrons', $patronId],
            ['fields' => 'names,emails'],
            'GET',
            ['cat_username' => $username, 'cat_password' => $password]
        );

        if (null === $result || !empty($result['code'])) {
            return null;
        }
        $firstname = '';
        $lastname = '';
        if (!empty($result['names'])) {
            $name = $result['names'][0];
            $parts = explode(', ', $name, 2);
            $lastname = $parts[0];
            $firstname = $parts[1] ?? '';
        }
        return [
            'id' => $result['id'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => !empty($result['emails']) ? $result['emails'][0] : '',
            'major' => null,
            'college' => null
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
            ['v3', 'patrons', $patron['id']],
            [
                'fields' => 'names,emails,phones,addresses,expirationDate'
            ],
            'GET',
            $patron
        );

        if (empty($result)) {
            return [];
        }
        $firstname = '';
        $lastname = '';
        $address = '';
        $zip = '';
        $city = '';
        if (!empty($result['names'])) {
            $nameParts = explode(', ', $result['names'][0], 2);
            $lastname = $nameParts[0];
            $firstname = $nameParts[1] ?? '';
        }
        if (!empty($result['addresses'][0]['lines'][1])) {
            $address = $result['addresses'][0]['lines'][0];
            $postalParts = explode(' ', $result['addresses'][0]['lines'][1], 2);
            if (isset($postalParts[1])) {
                $zip = $postalParts[0];
                $city = $postalParts[1];
            } else {
                $city = $postalParts[0];
            }
        }
        $expirationDate = !empty($result['expirationDate'])
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $result['expirationDate']
                ) : '';
        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'phone' => !empty($result['phones'][0]['number'])
                ? $result['phones'][0]['number'] : '',
            'email' => !empty($result['emails']) ? $result['emails'][0] : '',
            'address1' => $address,
            'zip' => $zip,
            'city' => $city,
            'expiration_date' => $expirationDate
        ];
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron, $params = [])
    {
        $pageSize = $params['limit'] ?? 50;
        $offset = isset($params['page']) ? ($params['page'] - 1) * $pageSize : 0;

        $result = $this->makeRequest(
            ['v3', 'patrons', $patron['id'], 'checkouts'],
            [
                'limit' => $pageSize,
                'offset' => $offset,
                'fields' => 'item,dueDate,numberOfRenewals,outDate,recallDate'
                    . ',callNumber,barcode'
            ],
            'GET',
            $patron
        );
        if (empty($result['entries'])) {
            return [
                'count' => $result['total'],
                'records' => []
            ];
        }
        $transactions = [];
        foreach ($result['entries'] as $entry) {
            $transaction = [
                'id' => '',
                'checkout_id' => $this->extractId($entry['id']),
                'item_id' => $this->extractId($entry['item']),
                'barcode' => $entry['barcode'],
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['dueDate']
                ),
                'renew' => $entry['numberOfRenewals'],
                'renewable' => true // assumption, who knows?
            ];
            if (!empty($entry['recallDate'])) {
                $date = $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['recallDate']
                );
                $transaction['message']
                    = $this->translate('item_recalled', ['%%date%%' => $date]);
            }
            // Fetch item information
            $item = $this->makeRequest(
                ['v3', 'items', $transaction['item_id']],
                ['fields' => 'bibIds,varFields'],
                'GET',
                $patron
            );
            $transaction['volume'] = $this->extractVolume($item);
            if (!empty($item['bibIds'])) {
                $transaction['id'] = $this->formatBibId($item['bibIds'][0]);

                // Fetch bib information
                $bib = $this->getBibRecord(
                    $transaction['id'], 'title,publishYear', $patron
                );
                if (!empty($bib['title'])) {
                    $transaction['title'] = $bib['title'];
                }
                if (!empty($bib['publishYear'])) {
                    $transaction['publication_year'] = $bib['publishYear'];
                }
            }
            $transactions[] = $transaction;
        }

        return [
            'count' => $result['total'],
            'records' => $transactions
        ];
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
        return $checkOutDetails['checkout_id'] . '|' . $checkOutDetails['item_id'];
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
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $patron = $renewDetails['patron'];
        $finalResult = ['details' => []];

        foreach ($renewDetails['details'] as $details) {
            list($checkoutId, $itemId) = explode('|', $details);
            $result = $this->makeRequest(
                ['v3', 'patrons', 'checkouts', $checkoutId, 'renewal'], [], 'POST',
                $patron
            );
            if (!empty($result['code'])) {
                $msg = $this->formatErrorMessage(
                    $result['description'] ?? $result['name']
                );
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => false,
                    'sysMessage' => $msg
                ];
            } else {
                $newDate = $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $result['dueDate']
                );
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => true,
                    'new_date' => $newDate
                ];
            }
        }
        return $finalResult;
    }

    /**
     * Get Patron Transaction History
     *
     * This is responsible for retrieving all historic transactions (i.e. checked
     * out items) by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's historic transactions on success.
     */
    public function getMyTransactionHistory($patron, $params)
    {
        $pageSize = $params['limit'] ?? 50;
        $offset = isset($params['page']) ? ($params['page'] - 1) * $pageSize : 0;
        $sortOrder = isset($params['sort']) && 'checkout asc' === $params['sort']
            ? 'asc' : 'desc';
        $result = $this->makeRequest(
            ['v3', 'patrons', $patron['id'], 'checkouts', 'history'],
            [
                'limit' => $pageSize,
                'offset' => $offset,
                'sortField' => 'outDate',
                'sortOrder' => $sortOrder,
                'fields' => 'item,outDate'
            ],
            'GET',
            $patron
        );
        if (!empty($result['code'])) {
            return [
                'success' => false,
                'status' => 146 === $result['code']
                    ? 'ils_transaction_history_disabled'
                    : 'ils_connection_failed'
            ];
        }
        $transactions = [];
        foreach ($result['entries'] as $entry) {
            $transaction = [
                'id' => '',
                'item_id' => $this->extractId($entry['item']),
                'checkoutDate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['outDate']
                )
            ];
            // Fetch item information
            $item = $this->makeRequest(
                ['v3', 'items', $transaction['item_id']],
                ['fields' => 'bibIds,varFields'],
                'GET',
                $patron
            );
            $transaction['volume'] = $this->extractVolume($item);
            if (!empty($item['bibIds'])) {
                $transaction['id'] = $this->formatBibId($item['bibIds'][0]);

                // Fetch bib information
                $bib = $this->getBibRecord(
                    $transaction['id'], 'title,publishYear', $patron
                );
                if (!empty($bib['title'])) {
                    $transaction['title'] = $bib['title'];
                }
                if (!empty($bib['publishYear'])) {
                    $transaction['publication_year'] = $bib['publishYear'];
                }
            }
            $transactions[] = $transaction;
        }

        return [
            'count' => $result['total'] ?? 0,
            'transactions' => $transactions
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
     * @todo   Support for handling frozen and pickup location change
     */
    public function getMyHolds($patron)
    {
        $fields = 'id,record,frozen,placed,location,pickupLocation,status'
            . ',recordType,priority,priorityQueueLength';
        if ($this->apiVersion >= 5) {
            $fields .= ',pickupByDate';
        }
        $result = $this->makeRequest(
            ['v3', 'patrons', $patron['id'], 'holds'],
            [
                'limit' => 10000,
                'fields' => $fields
            ],
            'GET',
            $patron
        );
        if (!isset($result['entries'])) {
            return [];
        }
        $holds = [];
        foreach ($result['entries'] as $entry) {
            $bibId = null;
            $itemId = null;
            $title = '';
            $volume = '';
            $publicationYear = '';
            if ($entry['recordType'] == 'i') {
                $itemId = $this->extractId($entry['record']);
                // Fetch bib ID from item
                $item = $this->makeRequest(
                    ['v3', 'items', $itemId],
                    ['fields' => 'bibIds,varFields'],
                    'GET',
                    $patron
                );
                if (!empty($item['bibIds'])) {
                    $bibId = $item['bibIds'][0];
                }
                $volume = $this->extractVolume($item);
            } elseif ($entry['recordType'] == 'b') {
                $bibId = $this->extractId($entry['record']);
            }
            if (!empty($bibId)) {
                // Fetch bib information
                $bib = $this->getBibRecord($bibId, 'title,publishYear', $patron);
                $title = $bib['title'] ?? '';
                $publicationYear = $bib['publishYear'] ?? '';
            }
            $available = in_array($entry['status']['code'], ['b', 'j', 'i']);
            if ($entry['priority'] >= $entry['priorityQueueLength']) {
                // This can happen, no idea why
                $position = $entry['priorityQueueLength'] . ' / '
                    . $entry['priorityQueueLength'];
            } else {
                $position = $entry['priority'] . ' / '
                    . $entry['priorityQueueLength'];
            }
            $lastPickup = !empty($entry['pickupByDate'])
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['pickupByDate']
                ) : '';
            $holds[] = [
                'id' => $this->formatBibId($bibId),
                'requestId' => $this->extractId($entry['id']),
                'item_id' => $itemId ? $itemId : $this->extractId($entry['id']),
                // note that $entry['pickupLocation']['name'] may contain misleading
                // text, so we instead use the code here:
                'location' => $entry['pickupLocation']['code'],
                'create' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['placed']
                ),
                'last_pickup_date' => $lastPickup,
                'position' => $position,
                'available' => $available,
                'in_transit' => $entry['status']['code'] == 't',
                'volume' => $volume,
                'publication_year' => $publicationYear,
                'title' => $title,
                'frozen' => !empty($entry['frozen'])
            ];
        }
        return $holds;
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
        return $holdDetails['available'] || $holdDetails['in_transit'] ? ''
            : $holdDetails['requestId'];
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
        $patron = $cancelDetails['patron'];
        $count = 0;
        $response = [];

        foreach ($details as $holdId) {
            $result = $this->makeRequest(
                ['v5', 'patrons', 'holds', $holdId], '', 'DELETE', $patron
            );

            if (!empty($result['code'])) {
                $msg = $this->formatErrorMessage(
                    $result['description'] ?? $result['name']
                );
                $response[$holdId] = [
                    'item_id' => $holdId,
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $msg
                ];
            } else {
                $response[$holdId] = [
                    'item_id' => $holdId,
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
        if (!empty($this->config['pickUpLocations'])) {
            $locations = [];
            foreach ($this->config['pickUpLocations'] as $id => $location) {
                $locations[] = [
                    'locationID' => $id,
                    'locationDisplay' => $this->translateLocation(
                        ['code' => $id, 'name' => $location]
                    )
                ];
            }
            return $locations;
        }

        $result = $this->makeRequest(
            ['v4', 'branches', 'pickupLocations'],
            [
                'limit' => 10000,
                'offset' => 0,
                'fields' => 'code,name',
                'language' => $this->getTranslatorLocale()
            ],
            'GET',
            $patron
        );
        if (!empty($result['code'])) {
            // An error was returned
            $this->error(
                "Request for pickup locations returned error code: {$result['code']}"
                . ", HTTP status: {$result['httpStatus']}, name: {$result['name']}"
            );
            throw new ILSException('Problem with Sierra REST API.');
        }
        if (empty($result)) {
            return [];
        }

        $locations = [];
        foreach ($result as $entry) {
            $locations[] = [
                'locationID' => $entry['code'],
                'locationDisplay' => $this->translateLocation(
                    ['code' => $entry['code'], 'name' => $entry['name']]
                )
            ];
        }

        usort($locations, [$this, 'pickupLocationSortFunction']);
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
        if ($this->getPatronBlocks($patron)) {
            return false;
        }
        $level = $data['level'] ?? 'copy';
        if ('title' === $level) {
            $bib = $this->getBibRecord($id, 'bibLevel', $patron);
            if (!isset($bib['bibLevel']['code'])
                || !in_array($bib['bibLevel']['code'], $this->titleHoldBibLevels)
            ) {
                return false;
            }
        }
        return true;
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
        $bibId = $this->extractBibId($holdDetails['id']);

        // Convert last interest date from Display Format to Sierra's required format
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
            'recordType' => $level == 'copy' ? 'i' : 'b',
            'recordNumber' => (int)($level == 'copy' ? $itemId : $bibId),
            'pickupLocation' => $pickUpLocation,
            'neededBy' => $lastInterestDate
        ];
        if ($comment) {
            $request['note'] = $comment;
        }

        $result = $this->makeRequest(
            [$comment ? 'v4' : 'v3', 'patrons', $patron['id'], 'holds', 'requests'],
            json_encode($request),
            'POST',
            $patron
        );

        if (!empty($result['code'])) {
            return $this->holdError($result['description'] ?? $result['name']);
        }
        return ['success' => true];
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
        $result = $this->makeRequest(
            ['v3', 'patrons', $patron['id'], 'fines'],
            [
                'fields' => 'item,assessedDate,description,chargeType,itemCharge'
                    . ',processingFee,billingFee,paidAmount'
            ],
            'GET',
            $patron
        );

        if (!isset($result['entries'])) {
            return [];
        }
        $fines = [];
        foreach ($result['entries'] as $entry) {
            $amount = $entry['itemCharge'] + $entry['processingFee']
                + $entry['billingFee'];
            $balance = $amount - $entry['paidAmount'];
            $description = '';
            // Display charge type if it's not manual (code=1)
            if (!empty($entry['chargeType'])
                && $entry['chargeType']['code'] != '1'
            ) {
                $description = $entry['chargeType']['display'];
            }
            if (!empty($entry['description'])) {
                if ($description) {
                    $description .= ' - ';
                }
                $description .= $entry['description'];
            }
            switch ($description) {
            case 'Overdue Renewal':
                $description = 'Overdue';
                break;
            }
            $bibId = null;
            $title = null;
            if (!empty($entry['item'])) {
                $itemId = $this->extractId($entry['item']);
                // Fetch bib ID from item
                $item = $this->makeRequest(
                    ['v3', 'items', $itemId],
                    ['fields' => 'bibIds'],
                    'GET',
                    $patron
                );
                if (!empty($item['bibIds'])) {
                    $bibId = $item['bibIds'][0];
                    // Fetch bib information
                    $bib = $this->getBibRecord($bibId, 'title,publishYear', $patron);
                    $title = $bib['title'] ?? '';
                }
            }

            $fines[] = [
                'amount' => $amount * 100,
                'fine' => $description,
                'balance' => $balance * 100,
                'createdate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['assessedDate']
                ),
                'checkout' => '',
                'id' => $this->formatBibId($bibId),
                'title' => $title
            ];
        }
        return $fines;
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
        // Force new login
        $this->sessionCache->accessTokenPatron = '';
        $patron = $this->patronLogin(
            $details['patron']['cat_username'], $details['oldPassword']
        );
        if (null === $patron) {
            return [
                'success' => false, 'status' => 'authentication_error_invalid'
            ];
        }

        $newPIN = preg_replace('/[^\d]/', '', trim($details['newPassword']));
        if (strlen($newPIN) != 4) {
            return [
                'success' => false, 'status' => 'password_error_invalid'
            ];
        }

        $request = ['pin' => $newPIN];

        $result = $this->makeRequest(
            ['v3', 'patrons', $patron['id']],
            json_encode($request),
            'PUT',
            $patron
        );

        if (!empty($result['code'])) {
            return [
                'success' => false,
                'status' => $this->formatErrorMessage(
                    $result['description'] ?? $result['name']
                )
            ];
        }
        return ['success' => true, 'status' => 'change_password_ok'];
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
        if ('getMyTransactions' === $function) {
            return [
                'max_results' => 100
            ];
        }
        if ('getMyTransactionHistory' === $function) {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }
            return [
                'max_results' => 100,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc'
                ],
                'default_sort' => 'checkout desc'
            ];
        }
        return isset($this->config[$function])
            ? $this->config[$function] : false;
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
        // Changing password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        // Loan history is only available if properly configured
        if ($method == 'getMyTransactionHistory') {
            return !empty($this->config['TransactionHistory']['enabled']);
        }
        return is_callable([$this, $method]);
    }

    /**
     * Extract an ID from a URL (last number)
     *
     * @param string $url URL containing the ID
     *
     * @return string ID
     */
    protected function extractId($url)
    {
        $parts = explode('/', $url);
        return end($parts);
    }

    /**
     * Extract volume from item record's varFields
     *
     * @param array $item Item record from Sierra
     *
     * @return string
     */
    protected function extractVolume($item)
    {
        foreach ($item['varFields'] as $varField) {
            if ($varField['fieldTag'] == 'v') {
                return trim($varField['content']);
            }
        }
        return '';
    }

    /**
     * Make Request
     *
     * Makes a request to the Sierra REST API
     *
     * @param array  $hierarchy Array of values to embed in the URL path of
     * the request
     * @param array  $params    A keyed array of query data
     * @param string $method    The http request method to use (Default is GET)
     * @param array  $patron    Patron information, if available
     *
     * @throws ILSException
     * @return mixed JSON response decoded to an associative array or null on
     * authentication error
     */
    protected function makeRequest($hierarchy, $params = false, $method = 'GET',
        $patron = false
    ) {
        // Clear current access token if it's not specific to the given patron
        if ($patron
            && $this->sessionCache->accessTokenPatron != $patron['cat_username']
        ) {
            $this->sessionCache->accessToken = null;
        }

        // Renew authentication token as necessary
        if (null === $this->sessionCache->accessToken) {
            if (!$this->renewAccessToken($patron)) {
                return null;
            }
        }

        // Set up the request
        $apiUrl = $this->config['Catalog']['host'];

        // Add hierarchy
        foreach ($hierarchy as $value) {
            $apiUrl .= '/' . urlencode($value);
        }

        // Create proxy request
        $client = $this->createHttpClient($apiUrl);

        // Add params
        if ($method == 'GET') {
            $client->setParameterGet($params);
        } else {
            if (is_string($params)) {
                $client->getRequest()->setContent($params);
            } else {
                $client->setParameterPost($params);
            }
        }

        // Set authorization header
        $headers = $client->getRequest()->getHeaders();
        $headers->addHeaderLine(
            'Authorization', "Bearer {$this->sessionCache->accessToken}"
        );
        if (is_string($params)) {
            $headers->addHeaderLine('Content-Type', 'application/json');
        }

        $locale = $this->getTranslatorLocale();
        if ($locale != 'en') {
            $locale .= ', en;q=0.8';
        }
        $headers->addHeaderLine('Accept-Language', $locale);

        // Send request and retrieve response
        $startTime = microtime(true);
        $response = $client->setMethod($method)->send();
        // If we get a 401, we need to renew the access token and try again
        if ($response->getStatusCode() == 401) {
            if (!$this->renewAccessToken($patron)) {
                return null;
            }
            $client->getRequest()->getHeaders()->addHeaderLine(
                'Authorization', "Bearer {$this->sessionCache->accessToken}"
            );
            $response = $client->send();
        }
        $result = $response->getBody();

        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $method request $apiUrl" . PHP_EOL . 'response: ' . PHP_EOL
            . $result
        );

        // Handle errors as complete failures only if the API call didn't return
        // valid JSON that the caller can handle
        $decodedResult = json_decode($result, true);
        if (!$response->isSuccess() && null === $decodedResult) {
            $params = $method == 'GET'
                ? $client->getRequest()->getQuery()->toString()
                : $client->getRequest()->getPost()->toString();
            $this->error(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Sierra REST API.');
        }

        return $decodedResult;
    }

    /**
     * Renew the API access token and store it in the cache.
     * Throw an exception if there is an error.
     *
     * @param array $patron Patron information, if available
     *
     * @return bool True on success, false on patron login failure
     * @throws ILSException
     */
    protected function renewAccessToken($patron = false)
    {
        $patronCode = false;
        if ($patron && !empty($this->config['Catalog']['redirect_uri'])) {
            // Do a patron login and then perform an authorization grant request
            $params = [
                'client_id' => $this->config['Catalog']['client_key'],
                'redirect_uri' => $this->config['Catalog']['redirect_uri'],
                'state' => 'auth',
                'response_type' => 'code'
            ];
            $apiUrl = $this->config['Catalog']['host'] . '/authorize'
                . '?' . http_build_query($params);

            // First request the login form to get the hidden fields and cookies
            $client = $this->createHttpClient($apiUrl);
            $response = $client->send();
            $doc = new \DOMDocument();
            if (!@$doc->loadHTML($response->getBody())) {
                $this->error('Could not parse the III CAS login form');
                throw new ILSException('Problem with Sierra login.');
            }
            $usernameField = $this->config['Authentication']['username_field']
                ?? 'code';
            $passwordField = $this->config['Authentication']['password_field']
                ?? 'pin';
            $postParams = [
                $usernameField => $patron['cat_username'],
                $passwordField => $patron['cat_password'],
            ];
            foreach ($doc->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('type') == 'hidden') {
                    $postParams[$input->getAttribute('name')]
                        = $input->getAttribute('value');
                }
            }

            $postUrl = $client->getUri();
            $cookies = $client->getCookies();

            // Reset client
            $client = $this->createHttpClient($postUrl);
            $client->addCookie($cookies);

            // Allow two redirects so that we get back from CAS token verification
            // to the authorize API address.
            $client->setOptions(['maxredirects' => 2]);
            $client->setParameterPost($postParams);
            $response = $client->setMethod('POST')->send();
            if (!$response->isSuccess() && !$response->isRedirect()) {
                $this->error(
                    "POST request for '" . $client->getRequest()->getUriString()
                    . "' did not return 302 redirect: "
                    . $response->getStatusCode() . ': '
                    . $response->getReasonPhrase()
                    . ', response content: ' . $response->getBody()
                );
                throw new ILSException('Problem with Sierra login.');
            }
            if ($response->isRedirect()) {
                $location = $response->getHeaders()->get('Location')->getUri();
                // Don't try to parse the URI since Sierra creates it wrong if the
                // redirect_uri sent to it already contains a question mark.
                if (!preg_match('/code=([^&\?]+)/', $location, $matches)) {
                    $this->error(
                        "Could not parse patron authentication code from '$location'"
                    );
                    throw new ILSException('Problem with Sierra login.');
                }
                $patronCode = $matches[1];
            } else {
                // Did not get a redirect, assume the login failed
                return false;
            }
        }

        // Set up the request
        $apiUrl = $this->config['Catalog']['host'] . '/token';

        // Create proxy request
        $client = $this->createHttpClient($apiUrl);

        // Set headers
        $headers = $client->getRequest()->getHeaders();
        $authorization = $this->config['Catalog']['client_key'] . ':' .
            $this->config['Catalog']['client_secret'];
        $headers->addHeaderLine(
            'Authorization',
            'Basic ' . base64_encode($authorization)
        );
        $params = [];
        if ($patronCode) {
            $params['grant_type'] = 'authorization_code';
            $params['code'] = $patronCode;
            $params['redirect_uri'] = $this->config['Catalog']['redirect_uri'];
        } else {
            $params['grant_type'] = 'client_credentials';
        }
        $client->setParameterPost($params);

        // Send request and retrieve response
        $startTime = microtime(true);
        $response = $client->setMethod('POST')->send();
        if (!$response->isSuccess()) {
            $this->error(
                "POST request for '$apiUrl' with contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Sierra REST API.');
        }
        $result = $response->getBody();

        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " GET request $apiUrl" . PHP_EOL . 'response: ' . PHP_EOL
            . $result
        );

        $json = json_decode($result, true);
        $this->sessionCache->accessToken = $json['access_token'];
        $this->sessionCache->accessTokenPatron = $patronCode
            ? $patron['cat_username'] : null;
        return true;
    }

    /**
     * Create a HTTP client
     *
     * @param string $url Request URL
     *
     * @return \Zend\Http\Client
     */
    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

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
     * Add instance-specific context to a cache key suffix to ensure that
     * multiple drivers don't accidentally share values in the cache.
     *
     * @param string $key Cache key suffix
     *
     * @return string
     */
    protected function formatCacheKey($key)
    {
        return 'SierraRest-' . md5($this->config['Catalog']['host'] . "|$key");
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBib($id)
    {
        $bib = $this->getBibRecord($id, 'bibLevel');
        $holdingsData = [];
        if ($this->apiVersion >= 5.1) {
            $holdingsResult = $this->makeRequest(
                ['v5', 'holdings'],
                [
                    'bibIds' => $this->extractBibId($id),
                    //'deleted' => 'false',
                    //'suppressed' => 'false',
                    'fields' => 'fixedFields,varFields'
                ],
                'GET'
            );
            if (!empty($holdingsResult['entries'])) {
                foreach ($holdingsResult['entries'] as $entry) {
                    $location = '';
                    foreach ($entry['fixedFields'] as $field) {
                        if ('LOCATION' === $field['label']) {
                            $location = $field['value'];
                            break;
                        }
                    }
                    if ('' === $location) {
                        continue;
                    }
                    $holdingsData[$location][] = $entry;
                }
            }
        }

        $offset = 0;
        $limit = 50;
        $fields = 'location,status,barcode,callNumber,fixedFields';
        if ('m' !== ($bib['bibLevel']['code'] ?? null)) {
            // Fetch varFields for volume information
            $fields .= ',varFields';
        }
        $statuses = [];
        $sort = 0;
        while (!isset($result) || $limit === $result['total']) {
            $result = $this->makeRequest(
                ['v3', 'items'],
                [
                    'bibIds' => $this->extractBibId($id),
                    'deleted' => 'false',
                    'suppressed' => 'false',
                    'fields' => $fields,
                    'limit' => $limit,
                    'offset' => $offset
                ],
                'GET'
            );
            if (empty($result['entries'])) {
                if (!empty($result['httpStatus']) && 404 !== $result['httpStatus']) {
                    $msg = "Item status request failed: {$result['httpStatus']}";
                    if (!empty($result['description'])) {
                        $msg .= " ({$result['description']})";
                    }
                    throw new ILSException($msg);
                }
                return $statuses;
            }

            foreach ($result['entries'] as $i => $item) {
                $location = $this->translateLocation($item['location']);
                list($status, $duedate, $notes) = $this->getItemStatus($item);
                $available = $status == $this->mapStatusCode('-');
                // OPAC message
                if (isset($item['fixedFields']['108'])) {
                    $opacMsg = $item['fixedFields']['108'];
                    $trimmedMsg = trim($opacMsg['value']);
                    if (strlen($trimmedMsg) && $trimmedMsg != '-') {
                        $notes[] = $this->translateOpacMessage(
                            trim($opacMsg['value'])
                        );
                    }
                }
                $volume = isset($item['varFields']) ? $this->extractVolume($item)
                    : '';

                $entry = [
                    'id' => $id,
                    'item_id' => $item['id'],
                    'location' => $location,
                    'availability' => $available,
                    'status' => $status,
                    'reserve' => 'N',
                    'callnumber' => isset($item['callNumber'])
                        ? preg_replace('/^\|a/', '', $item['callNumber']) : '',
                    'duedate' => $duedate,
                    'number' => $volume,
                    'barcode' => $item['barcode'],
                    'sort' => $sort--
                ];
                if ($notes) {
                    $entry['item_notes'] = $notes;
                }

                if ($this->isHoldable($item) && $this->itemHoldAllowed($item, $bib)
                ) {
                    $entry['is_holdable'] = true;
                    $entry['level'] = 'copy';
                    $entry['addLink'] = true;
                } else {
                    $entry['is_holdable'] = false;
                }

                $locationCode = $item['location']['code'] ?? '';
                if (!empty($holdingsData[$locationCode])) {
                    $entry += $this->getHoldingsData($holdingsData[$locationCode]);
                    $holdingsData[$locationCode]['_hasItems'] = true;
                }

                $statuses[] = $entry;
            }
            $offset += $limit;
        }

        // Add holdings that don't have items
        foreach ($holdingsData as $locationCode => $holdings) {
            if (!empty($holdings['_hasItems'])) {
                continue;
            }

            $location = $this->translateLocation(
                ['code' => $locationCode, 'name' => '']
            );
            $code = $locationCode;
            while ('' === $location && $code) {
                $location = $this->getLocationName($code);
                $code = substr($code, 0, -1);
            }
            $entry = [
                'id' => $id,
                'item_id' => 'HLD_' . $holdings[0]['id'],
                'location' => $location,
                'requests_placed' => 0,
                'status' => '',
                'use_unknown_message' => true,
                'availability' => false,
                'duedate' => '',
                'barcode' => '',
                'sort' => $sort--
            ];
            $entry += $this->getHoldingsData($holdings);

            $statuses[] = $entry;
        }

        usort($statuses, [$this, 'statusSortFunction']);
        return $statuses;
    }

    /**
     * Get holdings fields according to configuration
     *
     * @param array $holdings Holdings records
     *
     * @return array
     */
    protected function getHoldingsData($holdings)
    {
        $result = [];
        // Get Notes
        if (isset($this->config['Holdings']['notes'])) {
            $data = $this->getHoldingFields(
                $holdings,
                $this->config['Holdings']['notes']
            );
            if ($data) {
                $result['notes'] = $data;
            }
        }

        // Get Summary (may be multiple lines)
        $data = $this->getHoldingFields(
            $holdings,
            isset($this->config['Holdings']['summary'])
            ? $this->config['Holdings']['summary']
            : 'h'
        );
        if ($data) {
            $result['summary'] = $data;
        }

        // Get Supplements
        if (isset($this->config['Holdings']['supplements'])) {
            $data = $this->getHoldingFields(
                $holdings,
                $this->config['Holdings']['supplements']
            );
            if ($data) {
                $result['supplements'] = $data;
            }
        }

        // Get Indexes
        if (isset($this->config['Holdings']['indexes'])) {
            $data = $this->getHoldingFields(
                $holdings,
                $this->config['Holdings']['indexes']
            );
            if ($data) {
                $result['indexes'] = $data;
            }
        }
        return $result;
    }

    /**
     * Get fields from holdings according to the field spec.
     *
     * @param array        $holdings   Holdings records
     * @param array|string $fieldSpecs Array or colon-separated list of
     * field/subfield specifications (3 chars for field code and then subfields,
     * e.g. 866az)
     *
     * @return string|string[] Results as a string if single, array if multiple
     */
    protected function getHoldingFields($holdings, $fieldSpecs)
    {
        if (!is_array($fieldSpecs)) {
            $fieldSpecs = explode(':', $fieldSpecs);
        }
        $result = [];
        foreach ($holdings as $holding) {
            foreach ($fieldSpecs as $fieldSpec) {
                $fieldCode = substr($fieldSpec, 0, 3);
                $subfieldCodes = substr($fieldSpec, 3);
                $fields = $holding['varFields'] ?? [];
                foreach ($fields as $field) {
                    if (($field['marcTag'] ?? '') !== $fieldCode
                        && ($field['fieldTag'] ?? '') !== $fieldCode
                    ) {
                        continue;
                    }
                    $subfields = $field['subfields'] ?? [
                        [
                            'tag' => '',
                            'content' => $field['content'] ?? ''
                        ]
                    ];
                    $line = [];
                    foreach ($subfields as $subfield) {
                        if ($subfieldCodes
                            && false === strpos($subfieldCodes, $subfield['tag'])
                        ) {
                            continue;
                        }
                        $line[] = $subfield['content'];
                    }
                    if ($line) {
                        $result[] = implode(' ', $line);
                    }
                }
            }
        }
        if (!$result) {
            return '';
        }
        return isset($result[1]) ? $result : $result[0];
    }

    /**
     * Get name for a location code
     *
     * @param string $locationCode Location code
     *
     * @return string
     */
    protected function getLocationName($locationCode)
    {
        $locations = $this->getCachedData('locations');
        if (null === $locations) {
            $locations = [];
            $result = $this->makeRequest(
                ['v4', 'branches'],
                [
                    'limit' => 10000,
                    'fields' => 'locations'
                ],
                'GET'
            );
            if (!empty($result['code'])) {
                // An error was returned
                $this->error(
                    "Request for branches returned error code: {$result['code']}, "
                    . "HTTP status: {$result['httpStatus']}, name: {$result['name']}"
                );
                throw new ILSException('Problem with Sierra REST API.');
            }
            foreach (($result['entries'] ?? []) as $branch) {
                foreach (($branch['locations'] ?? []) as $location) {
                    $locations[$location['code']] = $this->translateLocation(
                        $location
                    );
                }
            }
            $this->putCachedData('locations', $locations);
        }
        return $locations[$locationCode] ?? '';
    }

    /**
     * Translate location name
     *
     * @param array $location Location
     *
     * @return string
     */
    protected function translateLocation($location)
    {
        $prefix = 'location_';
        if (!empty($this->config['Catalog']['id'])) {
            $prefix .= $this->config['Catalog']['id'] . '_';
        }
        return $this->translate(
            $prefix . trim($location['code']),
            null,
            $location['name']
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
        $result = strcmp($a['location'], $b['location']);
        if ($result === 0 && $this->sortItemsByEnumChron) {
            $result = strnatcmp($b['number'] ?? '', $a['number'] ?? '');
        }
        if ($result === 0) {
            $result = $a['sort'] - $b['sort'];
        }
        return $result;
    }

    /**
     * Translate OPAC message
     *
     * @param string $code OPAC message code
     *
     * @return string
     */
    protected function translateOpacMessage($code)
    {
        $prefix = 'opacmsg_';
        if (!empty($this->config['Catalog']['id'])) {
            $prefix .= $this->config['Catalog']['id'] . '_';
        }
        return $this->translate("$prefix$code", null, $code);
    }

    /**
     * Get the human-readable equivalent of a status code.
     *
     * @param string $code    Code to map
     * @param string $default Default value if no mapping found
     *
     * @return string
     */
    protected function mapStatusCode($code, $default = null)
    {
        return trim($this->itemStatusMappings[$code] ?? $default ?? $code);
    }

    /**
     * Get status for an item
     *
     * @param array $item Item from Sierra
     *
     * @return array Status string, possible due date and any notes
     */
    protected function getItemStatus($item)
    {
        $duedate = '';
        $notes = [];
        $status = $this->mapStatusCode(
            trim($item['status']['code']),
            isset($item['status']['display'])
                ? ucwords(strtolower($item['status']['display']))
                : '-'
        );
        // For some reason at least API v2.0 returns "ON SHELF" even when the
        // item is out. Use duedate to check if it's actually checked out.
        if (isset($item['status']['duedate'])) {
            $duedate = $this->dateConverter->convertToDisplayDate(
                \DateTime::ISO8601,
                $item['status']['duedate']
            );
            $status = $this->mapStatusCode('Charged');
        } else {
            switch ($status) {
            case '-':
                $status = $this->mapStatusCode('-');
                break;
            case 'Lib Use Only':
                $status = $this->mapStatusCode('o');
                break;
            }
        }
        if ($status == $this->mapStatusCode('-')) {
            // Check for checkin date
            $today = $this->dateConverter->convertToDisplayDate('U', time());
            if (isset($item['fixedFields']['68'])) {
                $checkedIn = $this->dateConverter->convertToDisplayDate(
                    \DateTime::ISO8601, $item['fixedFields']['68']['value']
                );
                if ($checkedIn == $today) {
                    $notes[] = $this->translate('Returned today');
                }
            }
        }
        return [$status, $duedate, $notes];
    }

    /**
     * Determine whether an item is holdable
     *
     * @param array $item Item from Sierra
     *
     * @return bool
     */
    protected function isHoldable($item)
    {
        if (!empty($this->validHoldStatuses)) {
            list($status) = $this->getItemStatus($item);
            if (!in_array($status, $this->validHoldStatuses)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if an item is holdable
     *
     * @param array $item Item from Sierra
     * @param array $bib  Bib record from Sierra
     *
     * @return bool
     */
    protected function itemHoldAllowed($item, $bib)
    {
        if (!$this->itemHoldsEnabled) {
            return false;
        }
        if (!empty($this->itemHoldExcludedItemCodes)
            && isset($item['fixedFields']['60'])
        ) {
            $code = $item['fixedFields']['60']['value'];
            if (in_array($code, $this->itemHoldExcludedItemCodes)) {
                return false;
            }
        }
        if (!empty($this->titleHoldBibLevels)) {
            if (in_array($bib['bibLevel']['code'], $this->titleHoldBibLevels)) {
                return false;
            }
        }
        return true;
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
        $patronId = $patron['id'];
        $cacheId = "blocks|$patronId";
        $blockReason = $this->getCachedData($cacheId);
        if (null === $blockReason) {
            $result = $this->makeRequest(
                ['v3', 'patrons', $patronId],
                ['fields' => 'blockInfo'],
                'GET',
                $patron
            );
            if (!empty($result['blockInfo'])
                && trim($result['blockInfo']['code']) != '-'
            ) {
                $blockReason = [trim($result['blockInfo']['code'])];
            } else {
                $blockReason = [];
            }
            $this->putCachedData($cacheId, $blockReason);
        }
        return empty($blockReason) ? false : $blockReason;
    }

    /**
     * Pickup location sort function
     *
     * @param array $a First pickup location record to compare
     * @param array $b Second pickup location record to compare
     *
     * @return int
     */
    protected function pickupLocationSortFunction($a, $b)
    {
        $result = strcmp($a['locationDisplay'], $b['locationDisplay']);
        if ($result == 0) {
            $result = $a['locationID'] - $b['locationID'];
        }
        return $result;
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
        $msg = $this->formatErrorMessage($msg);
        return [
            'success' => false,
            'sysMessage' => $msg
        ];
    }

    /**
     * Format an error message received from Sierra
     *
     * @param string $msg An error message string
     *
     * @return string
     */
    protected function formatErrorMessage($msg)
    {
        // Remove prefix like "WebPAC Error" or "XCirc error"
        $msg = preg_replace('/.* [eE]rror\s*:\s*/', '', $msg);
        // Handle non-ascii characters that are returned in a wrongly encoded format
        // (e.g. {u00E4} instead of \u00E4)
        $msg = preg_replace_callback(
            '/\{u([0-9a-fA-F]{4})\}/',
            function ($matches) {
                return mb_convert_encoding(
                    pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE'
                );
            },
            $msg
        );
        return $msg;
    }

    /**
     * Fetch a bib record from Sierra
     *
     * @param int    $id     Bib record id
     * @param string $fields Fields to request
     * @param array  $patron Patron information, if available
     *
     * @return array|null
     */
    protected function getBibRecord($id, $fields, $patron = false)
    {
        return $this->makeRequest(
            ['v3', 'bibs', $this->extractBibId($id)],
            ['fields' => $fields],
            'GET',
            $patron
        );
    }

    /**
     * Extract a numeric bib ID value from a string that may be prefixed.
     *
     * @param string $id Bib record id (with or without .b prefix)
     *
     * @return int
     */
    protected function extractBibId($id)
    {
        // If the .b prefix is found, strip it and the trailing checksum:
        return substr($id, 0, 2) === '.b'
            ? substr($id, 2, strlen($id) - 3) : $id;
    }

    /**
     * If the system is configured to use full prefixed bib IDs, add the prefix
     * and checksum.
     *
     * @param int $id Bib ID that may need to be prefixed.
     *
     * @return string
     */
    protected function formatBibId($id)
    {
        // Simple case: prefixing is disabled, so return ID unmodified:
        if (!($this->config['Catalog']['use_prefixed_ids'] ?? false)) {
            return $id;
        }

        // If we got this far, we need to generate a check digit:
        $multiplier = 2;
        $sum = 0;
        for ($x = strlen($id) - 1; $x >= 0; $x--) {
            $current = substr($id, $x, 1);
            $sum += $multiplier * intval($current);
            $multiplier++;
        }
        $checksum = $sum % 11;
        $finalChecksum = $checksum === 10 ? 'x' : $checksum;
        return '.b' . $id . $finalChecksum;
    }
}
