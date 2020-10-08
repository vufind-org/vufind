<?php
/**
 * VuFind Driver for Koha, using REST API
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2020.
 * Copyright (C) Moravian Library 2019.
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
 * @author   Bohdan Inhliziian <bohdan.inhliziian@gmail.com.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\View\Helper\Root\SafeMoneyFormat;

/**
 * VuFind Driver for Koha, using REST API
 *
 * Minimum Koha Version: 20.05 + koha-plugin-rest-di REST API plugin from
 * https://github.com/natlibfi/koha-plugin-rest-di
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bohdan Inhliziian <bohdan.inhliziian@gmail.com.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends \VuFind\ILS\Driver\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFind\ILS\Driver\CacheTrait;

    /**
     * Library prefix
     *
     * @var string
     */
    protected $source = '';

    /**
     * Date converter object
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
     * Money formatting view helper
     *
     * @var SafeMoneyFormat
     */
    protected $safeMoneyFormat;

    /**
     * Session cache
     *
     * @var \Laminas\Session\Container
     */
    protected $sessionCache;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Item status rankings. The lower the value, the more important the status.
     *
     * @var array
     */
    protected $statusRankings = [
        'Charged' => 1,
        'On Hold' => 2
    ];

    /**
     * Mappings from fee (account line) types
     *
     * @var array
     */
    protected $feeTypeMappings = [
        'A' => 'Account',
        'C' => 'Credit',
        'Copie' => 'Copier Fee',
        'F' => 'Overdue',
        'FU' => 'Accrued Fine',
        'L' => 'Lost Item Replacement',
        'M' => 'Sundry',
        'N' => 'New Card',
        'ODUE' => 'Overdue',
        'Res' => 'Hold Fee',
        'HE' => 'Hold Expired',
        'RENT' => 'Rental'
    ];

    /**
     * Mappings from renewal block reasons
     *
     * @var array
     */
    protected $renewalBlockMappings = [
        'too_soon' => 'Cannot renew yet',
        'onsite_checkout' => 'Copy has special circulation',
        'on_reserve' => 'renew_item_requested',
        'too_many' => 'renew_item_limit',
        'restriction' => 'Borrowing Block Message',
        'overdue' => 'renew_item_overdue',
        'cardlost' => 'renew_card_lost',
        'gonenoaddress' => 'patron_status_address_missing',
        'debarred' => 'patron_status_card_blocked',
        'debt' => 'renew_debt'
    ];

    /**
     * Permanent renewal blocks
     *
     * @var array
     */
    protected $permanentRenewalBlocks = [
        'onsite_checkout',
        'on_reserve',
        'too_many'
    ];

    /**
     * Patron status mappings
     *
     * @var array
     */
    protected $patronStatusMappings = [
        'Hold::MaximumHoldsReached' => 'patron_status_maximum_requests',
        'Patron::CardExpired' => 'patron_status_card_expired',
        'Patron::DebarredOverdue' => 'patron_status_debarred_overdue',
        'Patron::Debt' => 'patron_status_debt_limit_reached',
        'Patron::DebtGuarantees' => 'patron_status_guarantees_debt_limit_reached',
        'Patron::GoneNoAddress' => 'patron_status_address_missing',
    ];

    /**
     * Item status mappings
     *
     * @var array
     */
    protected $itemStatusMappings = [];

    /**
     * Whether to display home library instead of holding library
     *
     * @var bool
     */
    protected $useHomeLibrary = false;

    /**
     * Whether to sort items by serial issue. Default is true.
     *
     * @var bool
     */
    protected $sortItemsBySerialIssue;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter   Date converter object
     * @param Callable               $sessionFactory  Factory function returning
     * SessionContainer object
     * @param SafeMoneyFormat        $safeMoneyFormat Money formatting view helper
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        $sessionFactory, SafeMoneyFormat $safeMoneyFormat
    ) {
        $this->dateConverter = $dateConverter;
        $this->sessionFactory = $sessionFactory;
        $this->safeMoneyFormat = $safeMoneyFormat;
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
        $required = ['host'];
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation']
            : '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }

        if (!empty($this->config['StatusRankings'])) {
            $this->statusRankings = array_merge(
                $this->statusRankings, $this->config['StatusRankings']
            );
        }

        if (!empty($this->config['FeeTypeMappings'])) {
            $this->feeTypeMappings = array_merge(
                $this->feeTypeMappings, $this->config['FeeTypeMappings']
            );
        }

        if (!empty($this->config['PatronStatusMappings'])) {
            $this->patronStatusMappings = array_merge(
                $this->patronStatusMappings, $this->config['PatronStatusMappings']
            );
        }

        if (!empty($this->config['ItemStatusMappings'])) {
            $this->itemStatusMappings = array_merge(
                $this->itemStatusMappings, $this->config['ItemStatusMappings']
            );
        }

        $this->useHomeLibrary = !empty($this->config['Holdings']['useHomeLibrary']);

        $this->sortItemsBySerialIssue
            = $this->config['Holdings']['sortBySerialIssue'] ?? true;

        // Init session cache for session-specific data
        $namespace = md5($this->config['Catalog']['host']);
        $factory = $this->sessionFactory;
        $this->sessionCache = $factory($namespace);
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
        return 'KohaRest' . '-' . md5($this->config['Catalog']['host'] . $suffix);
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
        return $this->getItemStatusesForBiblio($id);
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
            $items[] = $this->getItemStatusesForBiblio($id);
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
        return $this->getItemStatusesForBiblio($id, $patron);
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
        if (empty($username) || empty($password)) {
            return null;
        }

        $result = $this->makeRequest(
            [
                'path' => 'v1/contrib/kohasuomi/auth/patrons/validation',
                'json' => ['userid' => $username, 'password' => $password],
                'method' => 'POST',
                'errors' => true,
            ]
        );

        if (401 === $result['code'] || 403 === $result['code']) {
            return null;
        }
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $result = $result['data'];
        return [
            'id' => $result['patron_id'],
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => $result['email'],
            'major' => null,
            'college' => null,
            'home_library' => $result['library_id']
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
        $result = $this->makeRequest(['v1', 'patrons', $patron['id']]);

        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $result = $result['data'];
        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $result['phone'],
            'mobile_phone' => $result['mobile'],
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['postal_code'],
            'city' => $result['city'],
            'country' => $result['country'],
            'expiration_date' => $this->convertDate($result['expiry_date'] ?? null)
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
        return $this->getTransactions($patron, $params, false);
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
        $finalResult = ['details' => []];

        foreach ($renewDetails['details'] as $details) {
            list($checkoutId, $itemId) = explode('|', $details);
            $result = $this->makeRequest(
                [
                    'path' => ['v1', 'checkouts', $checkoutId, 'renewal'],
                    'method' => 'POST'
                ]
            );
            if (201 === $result['code']) {
                $newDate
                    = $this->convertDate($result['data']['due_date'] ?? null, true);
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => true,
                    'new_date' => $newDate
                ];
            } else {
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => false
                ];
            }
        }
        return $finalResult;
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
        return $this->getTransactions($patron, $params, true);
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
            [
                'path' => 'v1/holds',
                'query' => [
                    'patron_id' => $patron['id'],
                    '_match' => 'exact'
                ]
            ]
        );

        $holds = [];
        foreach ($result['data'] as $entry) {
            $biblio = $this->getBiblio($entry['biblio_id']);
            $frozen = false;
            if (!empty($entry['suspended'])) {
                $frozen = !empty($entry['suspend_until']) ? $entry['suspend_until']
                    : true;
            }
            $volume = '';
            if ($entry['item_id'] ?? null) {
                $item = $this->getItem($entry['item_id']);
                $volume = $item['serial_issue_number'];
            }
            $holds[] = [
                'id' => $entry['biblio_id'],
                'item_id' => $entry['hold_id'],
                'requestId' => $entry['hold_id'],
                'location' => $this->getLibraryName(
                    $entry['pickup_library_id'] ?? null
                ),
                'create' => $this->convertDate($entry['hold_date'] ?? null),
                'expire' => $this->convertDate($entry['expiration_date'] ?? null),
                'position' => $entry['priority'],
                'available' => !empty($entry['waiting_date']),
                'frozen' => $frozen,
                'in_transit' => !empty($entry['status']) && $entry['status'] == 'T',
                'title' => $this->getBiblioTitle($biblio),
                'isbn' => $biblio['isbn'] ?? '',
                'issn' => $biblio['issn'] ?? '',
                'publication_year' => $biblio['copyright_date']
                    ?? $biblio['publication_year'] ?? '',
                'volume' => $volume,
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
        $count = 0;
        $response = [];

        foreach ($details as $holdId) {
            $result = $this->makeRequest(
                [
                    'path' => ['v1', 'holds', $holdId],
                    'method' => 'DELETE',
                    'errors' => true
                ]
            );

            if (200 === $result['code'] || 204 === $result['code']) {
                $response[$holdId] = [
                    'success' => true,
                    'status' => 'hold_cancel_success'
                ];
                ++$count;
            } else {
                $response[$holdId] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false
                ];
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
        $bibId = $holdDetails['id'] ?? null;
        $itemId = $holdDetails['item_id'] ?? false;
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
                            $itemId, 'hold'
                        ],
                        'query' => [
                            'patron_id' => (int)$patron['id'],
                            'query_pickup_locations' => 1
                        ]
                    ]
                );
                if (empty($result['data'])) {
                    return [];
                }
                $notes = $result['data']['availability']['notes'];
                $included = $notes['Item::PickupLocations']['to_libraries'];
            } else {
                $result = $this->makeRequest(
                    [
                        'path' => [
                            'v1', 'contrib', 'kohasuomi', 'availability', 'biblios',
                            $bibId, 'hold'
                        ],
                        'query' => [
                            'patron_id' => (int)$patron['id'],
                            'query_pickup_locations' => 1
                        ]
                    ]
                );
                if (empty($result['data'])) {
                    return [];
                }
                $notes = $result['data']['availability']['notes'];
                $included = $notes['Biblio::PickupLocations']['to_libraries'];
            }
        }

        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];
        $locations = [];
        foreach ($this->getLibraries() as $library) {
            $code = $library['library_id'];
            if ((null === $included && !$library['pickup_location'])
                || in_array($code, $excluded)
                || (null !== $included && !in_array($code, $included))
            ) {
                continue;
            }
            $locations[] = [
                'locationID' => $code,
                'locationDisplay' => $library['name']
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = isset($this->config['Holds']['pickUpLocationOrder'])
            ? $this->config['Holds']['pickUpLocationOrder'] : 'default';
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
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if ($this->getPatronBlocks($patron)) {
            return false;
        }
        $level = $data['level'] ?? 'copy';
        if ('title' === $level) {
            $result = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'availability', 'biblios', $id,
                        'hold'
                    ],
                    'query' => ['patron_id' => $patron['id']]
                ]
            );
            if (!empty($result['data']['availability']['available'])) {
                return [
                    'valid' => true,
                    'status' => 'title_hold_place'
                ];
            }
            return [
                'valid' => false,
                'status' => $this->getHoldBlockReason($result['data'])
            ];
        }

        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'availability', 'items',
                    $data['item_id'], 'hold'
                ],
                'query' => ['patron_id' => $patron['id']]
            ]
        );
        if (!empty($result['data']['availability']['available'])) {
            return [
                'valid' => true,
                'status' => 'hold_place'
            ];
        }
        return [
            'valid' => false,
            'status' => $this->getHoldBlockReason($result['data'])
        ];
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

        if ($level == 'copy' && empty($itemId)) {
            throw new ILSException("Hold level is 'copy', but item ID is empty");
        }

        // Convert last interest date from Display Format to Koha's required format
        try {
            $lastInterestDate = $this->dateConverter->convertFromDisplayDate(
                'Y-m-d', $holdDetails['requiredBy']
            );
        } catch (DateException $e) {
            // Hold Date is invalid
            return $this->holdError('hold_date_invalid');
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
            'biblio_id' => (int)$bibId,
            'patron_id' => (int)$patron['id'],
            'pickup_library_id' => $pickUpLocation,
            'notes' => $comment,
            'expiration_date' => $lastInterestDate,
        ];
        if ($level == 'copy') {
            $request['item_id'] = (int)$itemId;
        }

        $result = $this->makeRequest(
            [
                'path' => 'v1/holds',
                'json' => $request,
                'method' => 'POST',
                'errors' => true
            ]
        );

        if ($result['code'] >= 300) {
            return $this->holdError($result['data']['error'] ?? 'hold_error_fail');
        }
        return ['success' => true];
    }

    /**
     * Get Patron Storage Retrieval Requests
     *
     * This is responsible for retrieving all article requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return array        Array of the patron's storage retrieval requests.
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        $result = $this->makeRequest(
            [
                'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                'articlerequests'
            ]
        );
        if (empty($result)) {
            return [];
        }
        $requests = [];
        foreach ($result['data'] as $entry) {
            // Article requests don't yet have a unified API mapping in Koha.
            // Try to take into account existing and predicted field names.
            $bibId = $entry['biblio_id'] ?? $entry['biblionumber'] ?? null;
            $itemId = $entry['item_id'] ?? $entry['itemnumber'] ?? null;
            $location = $entry['library_id'] ?? $entry['branchcode'] ?? null;
            $title = '';
            $volume = '';
            if ($itemId) {
                $item = $this->getItem($itemId);
                $bibId = $item['biblio_id'];
                $volume = $item['serial_issue_number'];
            }
            if (!empty($bibId)) {
                $bib = $this->getBiblio($bibId);
                $title = $this->getBiblioTitle($bib);
            }
            $requests[] = [
                'id' => $bibId,
                'item_id' => $entry['id'],
                'location' => $location,
                'create' => $this->convertDate($entry['created_on']),
                'available' => $entry['status'] === 'COMPLETED',
                'title' => $title,
                'volume' => $volume,
            ];
        }
        return $requests;
    }

    /**
     * Get Cancel Storage Retrieval Request (article request) Details
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        return $details['item_id'];
    }

    /**
     * Cancel Storage Retrieval Requests (article requests)
     *
     * Attempts to Cancel an article request on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelStorageRetrievalRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelStorageRetrievalRequests($cancelDetails)
    {
        $details = $cancelDetails['details'];
        $patron = $cancelDetails['patron'];
        $count = 0;
        $response = [];

        foreach ($details as $id) {
            $result = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                        'articlerequests', $id
                    ],
                    'method' => 'DELETE',
                    'errors' => true
                ]
            );

            if (200 !== $result['code']) {
                $response[$id] = [
                    'success' => false,
                    'status' => 'storage_retrieval_request_cancel_fail',
                    'sysMessage' => false
                ];
            } else {
                $response[$id] = [
                    'success' => true,
                    'status' => 'storage_retrieval_request_cancel_success'
                ];
                ++$count;
            }
        }
        return ['count' => $count, 'items' => $response];
    }

    /**
     * Check if storage retrieval request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        if (!isset($this->config['StorageRetrievalRequests'])
            || $this->getPatronBlocks($patron)
        ) {
            return false;
        }

        $level = $data['level'] ?? 'copy';

        if ('title' === $level) {
            $result = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'availability', 'biblios', $id,
                        'articlerequest'
                    ],
                    'query' => ['patron_id' => $patron['id']]
                ]
            );
        } else {
            $result = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'availability', 'items',
                        $data['item_id'], 'articlerequest'
                    ],
                    'query' => ['patron_id' => $patron['id']]
                ]
            );
        }
        return !empty($result['data']['availability']['available']);
    }

    /**
     * Place Storage Retrieval Request (Call Slip)
     *
     * Attempts to place a call slip request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeStorageRetrievalRequest($details)
    {
        $patron = $details['patron'];
        $level = $details['level'] ?? 'copy';
        $pickUpLocation = $details['pickUpLocation'] ?? null;
        $itemId = $details['item_id'] ?? false;
        $comment = $details['comment'] ?? '';
        $bibId = $details['id'];

        if ('copy' === $level && empty($itemId)) {
            throw new ILSException("Request level is 'copy', but item ID is empty");
        }

        // Make sure pickup location is valid
        if (null !== $pickUpLocation
            && !$this->pickUpLocationIsValid($pickUpLocation, $patron, $details)
        ) {
            return [
                'success' => false,
                'sysMessage' => 'storage_retrieval_request_invalid_pickup'
            ];
        }

        $request = [
            'biblio_id' => (int)$bibId,
            'pickup_library_id' => $pickUpLocation,
            'notes' => $comment,
            'volume' => $details['volume'] ?? '',
            'issue' => $details['issue'] ?? '',
            'date' => $details['year'] ?? '',
        ];
        if ($level == 'copy') {
            $request['item_id'] = (int)$itemId;
        }

        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    'articlerequests'
                ],
                'json' => $request,
                'method' => 'POST',
                'errors' => true
            ]
        );

        if ($result['code'] >= 300) {
            $message = $result['data']['error']
                ?? 'storage_retrieval_request_error_fail';
            return [
                'success' => false,
                'sysMessage' => $message
            ];
        }
        return [
            'success' => true,
            'status' => 'storage_retrieval_request_place_success'
        ];
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
            $type = $entry['debit_type'];
            $type = $this->translate($this->feeTypeMappings[$type] ?? $type);
            if ($entry['description'] !== $type) {
                $type .= ' - ' . $entry['description'];
            }
            $fine = [
                'amount' => $entry['amount'] * 100,
                'balance' => $entry['amount_outstanding'] * 100,
                'fine' => $type,
                'createdate' => $this->convertDate($entry['date'] ?? null),
                'checkout' => '',
            ];
            if (null !== $bibId) {
                $fine['id'] = $bibId;
            }
            $fines[] = $fine;
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
        $patron = $details['patron'];
        $request = [
            'password' => $details['newPassword'],
            'password_2' => $details['newPassword']
        ];

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $patron['id'], 'password'],
                'json' => $request,
                'method' => 'POST',
                'errors' => true
            ]
        );

        if (200 !== $result['code']) {
            if (400 === $result['code']) {
                $message = 'password_error_invalid';
            } else {
                $message = 'An error has occurred';
            }
            return [
                'success' => false, 'status' => $message
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
        if ('getMyTransactionHistory' === $function) {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }
            $limit = $this->config['TransactionHistory']['max_page_size'] ?? 100;
            return [
                'max_results' => $limit,
                'sort' => [
                    '-checkout_date' => 'sort_checkout_date_desc',
                    '+checkout_date' => 'sort_checkout_date_asc',
                    '-checkin_date' => 'sort_return_date_desc',
                    '+checkin_date' => 'sort_return_date_asc',
                    '-due_date' => 'sort_due_date_desc',
                    '+due_date' => 'sort_due_date_asc',
                    '+title' => 'sort_title'
                ],
                'default_sort' => '-checkout_date'
            ];
        } elseif ('getMyTransactions' === $function) {
            $limit = $this->config['Loans']['max_page_size'] ?? 100;
            return [
                'max_results' => $limit,
                'sort' => [
                    '-checkout_date' => 'sort_checkout_date_desc',
                    '+checkout_date' => 'sort_checkout_date_asc',
                    '-due_date' => 'sort_due_date_desc',
                    '+due_date' => 'sort_due_date_asc',
                    '+title' => 'sort_title'
                ],
                'default_sort' => '+due_date'
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
        // Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
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
        $client = $this->httpService->createClient($url);

        if (isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Laminas\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context, 'ssl', 'verify_peer_name', false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Laminas\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

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
     * Make Request
     *
     * Makes a request to the Koha REST API
     *
     * @param array $request Either a path as string or non-keyed array of path
     *                       elements, or a keyed array of request parameters:
     *
     * path     String or array of values to embed in the URL path. String is taken
     *          as is, array elements are url-encoded.
     * query    URL parameters (optional)
     * method   HTTP method (default is GET)
     * form     Form request params (optional)
     * json     JSON request as a PHP array (optional, only when form is not
     *          specified)
     * headers  Headers
     * errors   If true, return errors instead of raising an exception
     *
     * @return array
     * @throws ILSException
     */
    protected function makeRequest($request)
    {
        // Set up the request
        $apiUrl = $this->config['Catalog']['host'] . '/';

        // Handle the simple case of just a path in $request
        if (is_string($request) || !isset($request['path'])) {
            $request = [
                'path' => $request
            ];
        }

        if (is_array($request['path'])) {
            $apiUrl .= implode('/', array_map('urlencode', $request['path']));
        } else {
            $apiUrl .= $request['path'];
        }

        $client = $this->createHttpClient($apiUrl);
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Authorization', $this->getOAuth2Token());

        // Add params
        if (!empty($request['query'])) {
            $client->setParameterGet($request['query']);
        }
        if (!empty($request['form'])) {
            $client->setParameterPost($request['form']);
        } elseif (!empty($request['json'])) {
            $client->getRequest()->setContent(json_encode($request['json']));
            $client->getRequest()->getHeaders()->addHeaderLine(
                'Content-Type', 'application/json'
            );
        }

        if (!empty($request['headers'])) {
            $requestHeaders = $client->getRequest()->getHeaders();
            foreach ($request['headers'] as $name => $value) {
                $requestHeaders->addHeaderLine($name, [$value]);
            }
        }

        // Send request and retrieve response
        $method = $request['method'] ?? 'GET';
        $startTime = microtime(true);
        $client->setMethod($method);

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError(
                "$method request for '$apiUrl' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        // If we get a 401, we need to renew the access token and try again
        if ($response->getStatusCode() == 401) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Authorization', $this->getOAuth2Token(true));

            try {
                $response = $client->send();
            } catch (\Exception $e) {
                $this->logError(
                    "$method request for '$apiUrl' failed: " . $e->getMessage()
                );
                throw new ILSException('Problem with Koha REST API.');
            }
        }

        $result = $response->getBody();

        $fullUrl = $apiUrl;
        if ($method == 'GET') {
            $fullUrl .= '?' . $client->getRequest()->getQuery()->toString();
        }
        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $method request $fullUrl" . PHP_EOL . 'response: ' . PHP_EOL
            . $result
        );

        // Handle errors as complete failures only if the API call didn't return
        // valid JSON that the caller can handle
        $decodedResult = json_decode($result, true);
        if (empty($request['errors']) && !$response->isSuccess()
            && (null === $decodedResult || !empty($decodedResult['error']))
        ) {
            $params = $method == 'GET'
                ? $client->getRequest()->getQuery()->toString()
                : $client->getRequest()->getPost()->toString();
            $this->logError(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        return [
            'data' => $decodedResult,
            'code' => (int)$response->getStatusCode(),
            'headers' => $response->getHeaders()->toArray(),
        ];
    }

    /**
     * Get a new or cached OAuth2 token (type + token)
     *
     * @param bool $renew Force renewal of token
     *
     * @return string
     */
    protected function getOAuth2Token($renew = false)
    {
        $cacheKey = 'oauth';

        if (!$renew) {
            $token = $this->getCachedData($cacheKey);
            if ($token) {
                return $token;
            }
        }

        $url = $this->config['Catalog']['host'] . '/v1/oauth/token';
        $client = $this->createHttpClient($url);
        $client->setMethod('POST');
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Content-Type', 'application/x-www-form-urlencoded'
        );

        $client->setParameterPost(
            [
                'client_id' => $this->config['Catalog']['clientId'],
                'client_secret' => $this->config['Catalog']['clientSecret'],
                'grant_type' => $this->config['Catalog']['grantType']
                    ?? 'client_credentials'
            ]
        );

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError(
                "POST request for '$url' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        if ($response->getStatusCode() != 200) {
            $errorMessage = 'Error while getting OAuth2 access token (status code '
                . $response->getStatusCode() . '): ' . $response->getContent();
            $this->logError($errorMessage);
            throw new ILSException('Problem with Koha REST API.');
        }
        $responseData = json_decode($response->getContent(), true);

        if (empty($responseData['token_type'])
            || empty($responseData['access_token'])
        ) {
            $this->logError(
                'Did not receive OAuth2 token, response: '
                . $response->getContent()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        $token = $responseData['token_type'] . ' '
            . $responseData['access_token'];

        $this->putCachedData($cacheKey, $token, $responseData['expires_in'] ?? null);

        return $token;
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron information, if available
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null)
    {
        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'availability', 'biblios', $id,
                    'search'
                ],
                'errors' => true
            ]
        );
        if (404 == $result['code']) {
            return [];
        }
        if (200 != $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        if (empty($result['data']['item_availabilities'])) {
            return [];
        }

        $statuses = [];
        foreach ($result['data']['item_availabilities'] as $i => $item) {
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

            $entry = [
                'id' => $id,
                'item_id' => $item['item_id'],
                'location' => $this->getItemLocationName($item),
                'availability' => $available,
                'status' => $status,
                'status_array' => $statusCodes,
                'reserve' => 'N',
                'callnumber' => $this->getItemCallNumber($item),
                'duedate' => $duedate,
                'number' => $item['serial_issue_number'],
                'barcode' => $item['external_id'],
                'sort' => $i,
                'requests_placed' => max(
                    [$item['hold_queue_length'],
                    $result['data']['hold_queue_length']]
                )
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

            $statuses[] = $entry;
        }

        usort($statuses, [$this, 'statusSortFunction']);
        return $statuses;
    }

    /**
     * Get statuses for an item
     *
     * @param array $item Item from Koha
     *
     * @return array Status array and possible due date
     */
    protected function getItemStatusCodes($item)
    {
        $statuses = [];
        if ($item['availability']['available']) {
            $statuses[] = 'On Shelf';
        } elseif (isset($item['availability']['unavailabilities'])) {
            foreach ($item['availability']['unavailabilities'] as $key => $reason) {
                if (isset($this->itemStatusMappings[$key])) {
                    $statuses[] = $this->itemStatusMappings[$key];
                    continue;
                }
                $parts = explode('::', $key, 2);
                $statusType = $parts[0];
                $status = $parts[1] ?? '';

                if ('Item' === $statusType || 'ItemType' === $statusType) {
                    switch ($status) {
                    case 'CheckedOut':
                        $overdue = false;
                        if (!empty($reason['due_date'])) {
                            $duedate = $this->dateConverter->convert(
                                'Y-m-d',
                                'U',
                                $reason['due_date']
                            );
                            $overdue = $duedate < time();
                        }
                        $statuses[] = $overdue ? 'Overdue' : 'Charged';
                        break;
                    case 'Lost':
                        $statuses[] = 'Lost--Library Applied';
                        break;
                    case 'NotForLoan':
                    case 'NotForLoanForcing':
                        // NotForLoan is special: status has a library-specific
                        // status number. Allow mapping of different status numbers
                        // separately (e.g. Item::NotForLoan with status number 4
                        // is mapped with key Item::NotForLoan4):
                        $statusKey = $key . ($reason['status'] ?? '-');
                        // Replace ':' in status key if used as status since ':' is
                        // the namespace separator in translatable strings:
                        $statuses[] = $this->itemStatusMappings[$statusKey]
                            ?? $reason['code'] ?? str_replace(':', '_', $statusKey);
                        break;
                    case 'Transfer':
                        $onHold = false;
                        if (!empty($item['availability']['notes'])) {
                            foreach (array_keys($item['availability']['notes'])
                                as $noteKey
                            ) {
                                if ('Item::Held' === $noteKey) {
                                    $onHold = true;
                                    break;
                                }
                            }
                        }
                        $statuses[] = $onHold ? 'In Transit On Hold' : 'In Transit';
                        break;
                    case 'Held':
                        $statuses[] = 'On Hold';
                        break;
                    case 'Waiting':
                        $statuses[] = 'On Holdshelf';
                        break;
                    default:
                        $statuses[] = !empty($reason['code'])
                            ? $reason['code'] : $status;
                    }
                }
            }
            if (empty($statuses)) {
                $statuses[] = 'Not Available';
            }
        } else {
            $this->error(
                "Unable to determine status for item: " . print_r($item, true)
            );
        }

        if (empty($statuses)) {
            $statuses[] = 'No information available';
        }
        return array_unique($statuses);
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

        if (0 === $result && $this->sortItemsBySerialIssue) {
            $result = strnatcmp($a['number'], $b['number']);
        }

        if (0 === $result) {
            $result = $a['sort'] - $b['sort'];
        }
        return $result;
    }

    /**
     * Check if an item is holdable
     *
     * @param array $item Item from Koha
     *
     * @return bool
     */
    protected function itemHoldAllowed($item)
    {
        $unavail = $item['availability']['unavailabilities'] ?? [];
        if (!isset($unavail['Hold::NotHoldable'])) {
            return true;
        }
        return false;
    }

    /**
     * Check if an article request can be placed on the item
     *
     * @param array $item Item from Koha
     *
     * @return bool
     */
    protected function itemArticleRequestAllowed($item)
    {
        $unavail = $item['availability']['unavailabilities'] ?? [];
        if (isset($unavail['ArticleRequest::NotAllowed'])) {
            return false;
        }
        if (empty($this->config['StorageRetrievalRequests']['allow_checked_out'])
            && isset($unavail['Item::CheckedOut'])
        ) {
            return false;
        }
        return true;
    }

    /**
     * Protected support method to pick which status message to display when multiple
     * options are present.
     *
     * @param array $statusArray Array of status messages to choose from.
     *
     * @throws ILSException
     * @return string            The best status message to display.
     */
    protected function pickStatus($statusArray)
    {
        // Pick the first entry by default, then see if we can find a better match:
        $status = $statusArray[0];
        $rank = $this->getStatusRanking($status);
        for ($x = 1; $x < count($statusArray); $x++) {
            if ($this->getStatusRanking($statusArray[$x]) < $rank) {
                $status = $statusArray[$x];
            }
        }

        return $status;
    }

    /**
     * Support method for pickStatus() -- get the ranking value of the specified
     * status message.
     *
     * @param string $status Status message to look up
     *
     * @return int
     */
    protected function getStatusRanking($status)
    {
        return isset($this->statusRankings[$status])
            ? $this->statusRankings[$status] : 32000;
    }

    /**
     * Get libraries from cache or from the API
     *
     * @return array
     */
    protected function getLibraries()
    {
        $cacheKey = 'libraries';
        $libraries = $this->getCachedData($cacheKey);
        if (null === $libraries) {
            $result = $this->makeRequest('v1/libraries');
            $libraries = [];
            foreach ($result['data'] as $library) {
                $libraries[$library['library_id']] = $library;
            }
            $this->putCachedData($cacheKey, $libraries, 3600);
        }
        return $libraries;
    }

    /**
     * Get library name
     *
     * @param string $library Library ID
     *
     * @return string
     */
    protected function getLibraryName($library)
    {
        $libraries = $this->getLibraries();
        return $libraries[$library]['name'] ?? '';
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
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id']
                    ],
                    'query' => ['query_blocks' => 1]
                ]
            );
            $blockReason = [];
            if (!empty($result['data']['blocks'])) {
                $nonHoldBlock = false;
                foreach ($result['data']['blocks'] as $reason => $details) {
                    if ($reason !== 'Hold::MaximumHoldsReached') {
                        $nonHoldBlock = true;
                    }
                    $description = $this->getPatronBlockReason($reason, $details);
                    if ($description) {
                        $blockReason[] = $description;
                    }
                }
                // Add the generic block message to the beginning if we have blocks
                // other than hold block
                if ($nonHoldBlock) {
                    array_unshift(
                        $blockReason, $this->translate('patron_status_card_blocked')
                    );
                }
            }
            $this->putCachedData($cacheId, $blockReason);
        }
        return empty($blockReason) ? false : $blockReason;
    }

    /**
     * Fetch an item record from Koha
     *
     * @param int $id Item id
     *
     * @return array|null
     */
    protected function getItem($id)
    {
        $cacheId = "items|$id";
        $item = $this->getCachedData($cacheId);
        if (null === $item) {
            $result = $this->makeRequest(['v1', 'items', $id]);
            $item = $result['data'] ?? false;
            $this->putCachedData($cacheId, $item, 300);
        }
        return $item ?: null;
    }

    /**
     * Fetch a biblio record from Koha
     *
     * @param int $id Bib record id
     *
     * @return array|null
     */
    protected function getBiblio($id)
    {
        static $cachedRecords = [];
        if (!isset($cachedRecords[$id])) {
            $result = $this->makeRequest(['v1', 'biblios', $id]);
            $cachedRecords[$id] = $result['data'] ?? false;
        }
        return $cachedRecords[$id];
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
     * Return a hold error message
     *
     * @param string $error Error message
     *
     * @return array
     */
    protected function holdError($error)
    {
        switch ($error) {
        case 'Hold cannot be placed. Reason: tooManyReserves':
        case 'Hold cannot be placed. Reason: tooManyHoldsForThisRecord':
            $error = 'hold_error_too_many_holds';
            break;
        case 'Hold cannot be placed. Reason: ageRestricted':
            $error = 'hold_error_age_restricted';
            break;
        }
        return [
            'success' => false,
            'sysMessage' => $error
        ];
    }

    /**
     * Map a Koha renewal block reason code to a VuFind translation string
     *
     * @param string $reason Koha block code
     *
     * @return string
     */
    protected function mapRenewalBlockReason($reason)
    {
        return isset($this->renewalBlockMappings[$reason])
            ? $this->renewalBlockMappings[$reason] : 'renew_item_no';
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
        $libraryId = (!$this->useHomeLibrary && null !== $item['holding_library_id'])
            ? $item['holding_library_id'] : $item['home_library_id'];
        $name = $this->translateLocation($libraryId);
        if ($name === $libraryId) {
            $libraries = $this->getLibraries();
            $name = isset($libraries[$libraryId])
                ? $libraries[$libraryId]['name'] : $libraryId;
        }
        return $name;
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
        $prefix = 'location_';
        return $this->translate(
            "$prefix$location",
            null,
            null !== $default ? $default : $location
        );
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
        return $item['callnumber'];
    }

    /**
     * Get a reason for why a hold cannot be placed
     *
     * @param array $result Hold check result
     *
     * @return string
     */
    protected function getHoldBlockReason($result)
    {
        if (!empty($result['availability']['unavailabilities'])) {
            foreach (array_keys($result['availability']['unavailabilities']) as $key
            ) {
                switch ($key) {
                case 'Biblio::NoAvailableItems':
                    return 'hold_error_not_holdable';
                case 'Item::NotForLoan':
                case 'Hold::NotAllowedInOPAC':
                case 'Hold::ZeroHoldsAllowed':
                case 'Hold::NotAllowedByLibrary':
                case 'Hold::NotAllowedFromOtherLibraries':
                case 'Item::Restricted':
                case 'Hold::ItemLevelHoldNotAllowed':
                    return 'hold_error_item_not_holdable';
                case 'Hold::MaximumHoldsForRecordReached':
                case 'Hold::MaximumHoldsReached':
                    return 'hold_error_too_many_holds';
                case 'Item::AlreadyHeldForThisPatron':
                    return 'hold_error_already_held';
                case 'Hold::OnShelfNotAllowed':
                    return 'hold_error_on_shelf_blocked';
                }
            }
        }
        return 'hold_error_blocked';
    }

    /**
     * Converts given key to corresponding parameter
     *
     * @param string $key     to convert
     * @param string $default value to return
     *
     * @return string
     */
    protected function getSortParamValue($key, $default = '')
    {
        $params = [
            'checkout' => 'issuedate',
            'return' => 'returndate',
            'lastrenewed' => 'lastreneweddate',
            'title' => 'title'
        ];

        return $params[$key] ?? $default;
    }

    /**
     * Get a complete title from all the title-related fields
     *
     * @param array $biblio Biblio record (or something with the correct fields)
     *
     * @return string
     */
    protected function getBiblioTitle($biblio)
    {
        $title = [];
        foreach (['title', 'subtitle', 'part_number', 'part_name'] as $field) {
            $content = $biblio[$field] ?? '';
            if ($content) {
                $title[] = $content;
            }
        }
        return implode(' ', $title);
    }

    /**
     * Convert a date to display format
     *
     * @param string $date     Date
     * @param bool   $withTime Whether the date includes time
     *
     * @return string
     */
    protected function convertDate($date, $withTime = false)
    {
        if (!$date) {
            return '';
        }
        $createFormat = $withTime ? 'Y-m-d\TH:i:sP' : 'Y-m-d';
        return $this->dateConverter->convertToDisplayDate($createFormat, $date);
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked-out items
     * or checked-in items) by a specific patron.
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
        $sort = $params['sort'] ?? '+due_date';
        if ('+title' === $sort) {
            $sort = '+title|+subtitle';
        } elseif ('-title' === $sort) {
            $sort = '-title|-subtitle';
        }
        $queryParams = [
            '_order_by' => $sort,
            '_page' => $params['page'] ?? 1,
            '_per_page' => $pageSize
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
                    'checkouts'
                ],
                'query' => $queryParams
            ]
        );

        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        if (empty($result['data'])) {
            return [
                'count' => 0,
                $arrayKey => []
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
            $renewals = $entry['renewals'];
            $renewLimit = $entry['max_renewals'];
            $message = '';
            if (!$renewable && !$checkedIn) {
                $message = $this->mapRenewalBlockReason(
                    $entry['renewability_blocks']
                );
                $permanent = in_array(
                    $entry['renewability_blocks'], $this->permanentRenewalBlocks
                );
                if ($permanent) {
                    $renewals = null;
                    $renewLimit = null;
                }
            }

            $transaction = [
                'id' => $entry['biblio_id'],
                'checkout_id' => $entry['checkout_id'],
                'item_id' => $entry['item_id'],
                'barcode' => $entry['external_id'] ?? null,
                'title' => $this->getBiblioTitle($entry),
                'volume' => $entry['serial_issue_number'] ?? '',
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
                'message' => $message
            ];

            $transactions[] = $transaction;
        }

        return [
            'count' => $result['headers']['X-Total-Count'] ?? count($transactions),
            $arrayKey => $transactions
        ];
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
                '%%blockLimit%%' => $details['max_holds_allowed']
            ];
            break;
        case 'Patron::Debt':
        case 'Patron::DebtGuarantees':
            $count = isset($details['current_outstanding'])
                ? $this->safeMoneyFormat->__invoke($details['current_outstanding'])
                : '-';
            $limit = isset($details['max_outstanding'])
                ? $this->safeMoneyFormat->__invoke($details['max_outstanding'])
                : '-';
            $params = [
                '%%blockCount%%' => $count,
                '%%blockLimit%%' => $limit,
            ];
            break;
        }
        return $this->translate($this->patronStatusMappings[$reason] ?? '', $params);
    }
}
