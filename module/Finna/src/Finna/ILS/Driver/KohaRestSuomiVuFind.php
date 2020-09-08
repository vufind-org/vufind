<?php
/**
 * KohaRest ILS Driver for KohaSuomi (the VuFind base implementation part)
 *
 * PHP version 5
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

/**
 * KohaRest ILS Driver for KohaSuomi (the VuFind base implementation part)
 *
 * Minimum Koha Version: work in progress as of 23 Jan 2017
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRestSuomiVuFind extends \VuFind\ILS\Driver\AbstractBase implements
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
        'HE' => 'Hold Expired'
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
        'gonenoaddress' => 'Borrowing Block Koha Reason Patron_GoneNoAddress',
        'debarred' => 'Borrowing Block Koha Reason Patron_DebarredOverdue',
        'debt' => 'renew_debt'
    ];

    /**
     * Permanent renewal blocks
     */
    protected $permanentRenewalBlocks = [
        'onsite_checkout',
        'on_reserve',
        'too_many'
    ];

    /**
     * Whether to display home branch instead of holding branch
     *
     * @var bool
     */
    protected $useHomeBranch = false;

    /**
     * Whether to sort items by enumchron. Default is true.
     *
     * @var bool
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

        $this->useHomeBranch = !empty($this->config['Holdings']['use_home_branch']);

        $this->sortItemsByEnumChron
            = $this->config['Holdings']['sort_by_enum_chron'] ?? true;

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
        $patron = ['cat_username' => $username, 'cat_password' => $password];

        if ($this->sessionCache->patron != $username) {
            if (!$this->renewPatronCookie($patron)) {
                return null;
            }
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $this->sessionCache->patronId],
            false,
            'GET',
            $patron,
            true
        );

        if ($code == 401 || $code == 403) {
            return null;
        }
        if ($code != 200) {
            throw new ILSException('Problem with Koha REST API.');
        }

        return [
            'id' => $result['borrowernumber'],
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => $result['email'],
            'major' => null,
            'college' => null,
            'home_library' => $result['branchcode']
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
            ['v1', 'patrons', $patron['id']], false, 'GET', $patron
        );

        $expirationDate = !empty($result['dateexpiry'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $result['dateexpiry']
            ) : '';
        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $result['mobile'],
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['zipcode'],
            'city' => $result['city'],
            'country' => $result['country'],
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
        if (!empty($this->config['Catalog']['checkoutsSupportPaging'])) {
            $sort = explode(
                ' ', !empty($params['sort']) ? $params['sort'] : 'checkout desc', 2
            );
            $sortKey = $this->getSortParamValue($sort[0], 'date_due');
            $direction = (isset($sort[1]) && 'desc' === $sort[1]) ? 'desc' : 'asc';

            $pageSize = $params['limit'] ?? 50;
            $queryParams = [
                'borrowernumber' => $patron['id'],
                'sort' => $sortKey,
                'order' => $direction,
                'offset' => isset($params['page'])
                    ? ($params['page'] - 1) * $pageSize : 0,
                'limit' => $pageSize
            ];
            $result = $this->makeRequest(
                ['v1', 'checkouts', 'expanded', 'paged'],
                $queryParams,
                'GET',
                $patron
            );
        } else {
            $records = $this->makeRequest(
                ['v1', 'checkouts', 'expanded'],
                ['borrowernumber' => $patron['id']],
                'GET',
                $patron
            );
            $result = [
                'total' => count($records),
                'records' => $records
            ];
        }
        if (empty($result['records'])) {
            return [
                'count' => 0,
                'records' => []
            ];
        }
        $transactions = [];
        foreach ($result['records'] as $entry) {
            list($biblionumber, $title, $volume)
                = $this->getCheckoutInformation($entry);

            $dueStatus = false;
            $now = time();
            $dueTimeStamp = strtotime($entry['date_due']);
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
            if (!$renewable) {
                $message = $this->mapRenewalBlockReason(
                    $entry['renewability_error']
                );
                $permanent = in_array(
                    $entry['renewability_error'], $this->permanentRenewalBlocks
                );
                if ($permanent) {
                    $renewals = null;
                    $renewLimit = null;
                }
            }
            $onsite = !empty($entry['onsite_checkout']);

            $transaction = [
                'id' => $biblionumber,
                'checkout_id' => $entry['issue_id'],
                'item_id' => $entry['itemnumber'],
                'title' => $title,
                'volume' => $volume,
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['date_due']
                ),
                'dueStatus' => $dueStatus,
                'renew' => $renewals,
                'renewLimit' => $renewLimit,
                'renewable' => $renewable,
                'message' => $message,
                'onsite' => $onsite,
            ];

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
            list($code, $result) = $this->makeRequest(
                ['v1', 'checkouts', $checkoutId], false, 'PUT', $patron, true
            );
            if ($code == 403) {
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => false
                ];
            } else {
                $newDate = !empty($result['date_due'])
                    ? $this->dateConverter->convertToDisplayDate(
                        'Y-m-d\TH:i:sP', $result['date_due']
                    ) : '-';
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
            ' ', !empty($params['sort']) ? $params['sort'] : 'checkout desc', 2
        );
        $sortKey = $this->getSortParamValue($sort[0], 'date_due');
        $direction = (isset($sort[1]) && 'desc' === $sort[1]) ? 'desc' : 'asc';

        $pageSize = $params['limit'] ?? 50;
        $queryParams = [
            'borrowernumber' => $patron['id'],
            'sort' => $sortKey,
            'order' => $direction,
            'offset' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'limit' => $pageSize
        ];

        $transactions = $this->makeRequest(
            ['v1', 'checkouts', 'history'],
            $queryParams,
            'GET',
            $patron
        );

        $result = [
            'count' => $transactions['total'],
            'transactions' => []
        ];

        foreach ($transactions['records'] as $entry) {
            list($biblionumber, $title, $volume)
                = $this->getCheckoutInformation($entry);

            $dueStatus = false;
            $now = time();
            $dueTimeStamp = strtotime($entry['date_due']);
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = 'overdue';
                } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                    $dueStatus = 'due';
                }
            }

            $transaction = [
                'id' => $biblionumber,
                'checkout_id' => $entry['issue_id'],
                'item_id' => $entry['itemnumber'],
                'title' => $title,
                'volume' => $volume,
                'checkoutdate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['issuedate']
                ),
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['date_due']
                ),
                'dueStatus' => $dueStatus,
                'returndate' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP', $entry['returndate']
                ),
                'renew' => $entry['renewals']
            ];

            $result['transactions'][] = $transaction;
        }

        return $result;
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
            $publicationYear = '';
            if ($itemId) {
                $item = $this->getItem($itemId);
                $bibId = $item['biblionumber'];
                $volume = $item['enumchron'];
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
                'available' => !empty($entry['waitingdate']),
                'in_transit' => isset($entry['found'])
                    && strtolower($entry['found']) == 't',
                'requestId' => $entry['reserve_id'],
                'title' => $title,
                'volume' => $volume,
                'frozen' => $frozen
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
            : $holdDetails['requestId'] . '|' . $holdDetails['item_id'];
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

        foreach ($details as $detail) {
            list($holdId, $itemId) = explode('|', $detail, 2);
            list($resultCode) = $this->makeRequest(
                ['v1', 'holds', $holdId], [], 'DELETE', $patron, true
            );

            if ($resultCode != 200) {
                $response[$itemId] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false
                ];
            } else {
                $response[$itemId] = [
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
        $locations = [];
        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];
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
                foreach ($result['availability']['notes']['Item::PickupLocations']
                    as $code
                ) {
                    $included[] = $code;
                }
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
                foreach ($result['availability']['notes']['Biblio::PickupLocations']
                    as $code
                ) {
                    $included[] = $code;
                }
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
        if ('title' == $data['level']) {
            $result = $this->makeRequest(
                ['v1', 'availability', 'biblio', 'hold'],
                ['biblionumber' => $id, 'borrowernumber' => $patron['id']],
                'GET',
                $patron
            );
            if (!empty($result[0]['availability']['available'])) {
                return [
                    'valid' => true,
                    'status' => 'title_hold_place'
                ];
            }
            return [
                'valid' => false,
                'status' => $this->getHoldBlockReason($result)
            ];
        }
        $result = $this->makeRequest(
            ['v1', 'availability', 'item', 'hold'],
            ['itemnumber' => $data['item_id'], 'borrowernumber' => $patron['id']],
            'GET',
            $patron
        );
        if (!empty($result[0]['availability']['available'])) {
            return [
                'valid' => true,
                'status' => 'hold_place'
            ];
        }
        return [
            'valid' => false,
            'status' => $this->getHoldBlockReason($result)
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
            ['v1', 'articlerequests'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );
        if (empty($result['records'])) {
            return [];
        }
        $requests = [];
        foreach ($result['records'] as $entry) {
            $bibId = $entry['biblionumber'] ?? null;
            $itemId = $entry['itemnumber'] ?? null;
            $title = '';
            $volume = '';
            $publicationYear = '';
            if ($itemId) {
                $item = $this->getItem($itemId);
                $bibId = $item['biblionumber'];
                $volume = $item['enumchron'];
            }
            if (!empty($bibId)) {
                $bib = $this->getBibRecord($bibId);
                $title = $bib['title'] ?? '';
                if (!empty($bib['title_remainder'])) {
                    $title .= ' ' . $bib['title_remainder'];
                    $title = trim($title);
                }
            }
            $requests[] = [
                'id' => $bibId,
                'item_id' => $entry['id'],
                'location' => $entry['branchcode'],
                'create' => $this->dateConverter->convertToDisplayDate(
                    'Y-m-d', $entry['created_on']
                ),
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
            list($resultCode) = $this->makeRequest(
                ['v1', 'articlerequests', $id], [], 'DELETE', $patron, true
            );

            if ($resultCode != 200) {
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
                ['v1', 'availability', 'biblio', 'articlerequest'],
                ['biblionumber' => $id, 'borrowernumber' => $patron['id']],
                'GET',
                $patron
            );
        } else {
            $result = $this->makeRequest(
                ['v1', 'availability', 'item', 'articlerequest'],
                [
                    'itemnumber' => $data['item_id'],
                    'borrowernumber' => $patron['id']
                ],
                'GET',
                $patron
            );
        }
        return !empty($result[0]['availability']['available']);
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
            'biblionumber' => (int)$bibId,
            'borrowernumber' => (int)$patron['id'],
            'branchcode' => $pickUpLocation,
            'patron_notes' => $comment,
        ];
        if ($level == 'copy') {
            $request['itemnumber'] = (int)$itemId;
        }

        $request['volume'] = $details['volume'] ?? '';
        $request['issue'] = $details['issue'] ?? '';
        $request['date'] = $details['year'] ?? '';

        list($code, $result) = $this->makeRequest(
            ['v1', 'articlerequests'],
            json_encode($request),
            'POST',
            $patron,
            true
        );

        if ($code >= 300) {
            $message = $result['error'] ?? 'storage_retrieval_request_error_fail';
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
        $result = $this->makeRequest(
            ['v1', 'accountlines'],
            ['borrowernumber' => $patron['id']],
            'GET',
            $patron
        );

        if (empty($result)) {
            return [];
        }
        $fines = [];
        foreach ($result as $entry) {
            if ($entry['accounttype'] == 'Pay'
                || $entry['amountoutstanding'] == 0
            ) {
                continue;
            }
            $bibId = null;
            $title = null;
            if (!empty($entry['itemnumber'])) {
                $item = $this->getItem($entry['itemnumber']);
                if (!empty($item['biblionumber'])) {
                    $bibId = $item['biblionumber'];
                }
            }
            $createDate = !empty($entry['date'])
                ? $this->dateConverter->convertToDisplayDate('Y-m-d', $entry['date'])
                : '';
            $type = $entry['accounttype'];
            if (isset($this->feeTypeMappings[$type])) {
                $type = $this->feeTypeMappings[$type];
            }
            $fine = [
                'amount' => $entry['amount'] * 100,
                'balance' => $entry['amountoutstanding'] * 100,
                'fine' => $type,
                'createdate' => $createDate,
                'checkout' => '',
                'title' => $entry['description']
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
            'new_password' => $details['newPassword'],
            'current_password' => $details['oldPassword']
        ];

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'password'],
            json_encode($request),
            'PATCH',
            $patron,
            true
        );

        if ($code != 200) {
            if ($code == 403 || (isset($result['error'])
                && $result['error'] == 'Wrong current password.')
            ) {
                $message = 'authentication_error_invalid';
            } elseif ($code == 404) {
                $message = 'An error has occurred';
            } else {
                $message = 'password_error_invalid';
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
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'lastrenewed desc' => 'sort_lastrenewed_date_desc',
                    'lastrenewed asc' => 'sort_lastrenewed_date_asc',
                    'return desc' => 'sort_return_date_desc',
                    'return asc' => 'sort_return_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc'
                ],
                'default_sort' => 'checkout desc'
            ];
        } elseif ('getMyTransactions' === $function) {
            if (empty($this->config['Catalog']['checkoutsSupportPaging'])) {
                return [];
            }
            $limit = $this->config['Loans']['max_page_size'] ?? 100;
            return [
                'max_results' => $limit,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                    'title asc' => 'sort_title'
                ],
                'default_sort' => 'due asc'
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
     * @param array  $hierarchy  Array of values to embed in the URL path of
     * the request
     * @param array  $params     A keyed array of query data
     * @param string $method     The http request method to use (Default is GET)
     * @param array  $patron     Patron information when using patron APIs
     * @param bool   $returnCode If true, returns HTTP status code in addition to
     * the result
     *
     * @throws ILSException
     * @return mixed JSON response decoded to an associative array or null on
     * authentication error
     */
    protected function makeRequest($hierarchy, $params = false, $method = 'GET',
        $patron = null, $returnCode = false
    ) {
        if ($patron) {
            // Clear current patron cookie if it's not specific to the given patron
            if ($this->sessionCache->patron != $patron['cat_username']) {
                $this->sessionCache->patronCookie = null;
            }

            // Renew authentication token as necessary
            if (null === $this->sessionCache->patronCookie) {
                if (!$this->renewPatronCookie($patron)) {
                    return $returnCode ? [403, null] : null;
                }
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
        if (false !== $params) {
            if ('GET' === $method || 'DELETE' === $method) {
                $client->setParameterGet($params);
            } else {
                $body = '';
                if (is_string($params)) {
                    $body = $params;
                } else {
                    if (isset($params['##body##'])) {
                        $body = $params['##body##'];
                        unset($params['##body##']);
                        $client->setParameterGet($params);
                    } else {
                        $client->setParameterPost($params);
                    }
                }
                if ('' !== $body) {
                    $client->getRequest()->setContent($body);
                    $client->getRequest()->getHeaders()
                        ->addHeaderLine('Content-Type', 'application/json');
                }
            }
        }

        // Set authorization header
        if ($patron) {
            $client->addCookie($this->sessionCache->patronCookie);
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $client->setMethod($method);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->error(
                "$method request for '$apiUrl' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        // If we get a 401, we need to renew the access token and try again
        if ($response->getStatusCode() == 401) {
            if (!$this->renewPatronCookie($patron)) {
                return $returnCode ? [401, null] : null;
            }
            $client->clearCookies();
            $client->addCookie($this->sessionCache->patronCookie);
            $this->debug('Session renewed');
            try {
                $response = $client->send();
            } catch (\Exception $e) {
                $this->error(
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
        if (!$response->isSuccess()
            && (null === $decodedResult || !empty($decodedResult['error']))
            && !$returnCode
        ) {
            $params = $method == 'GET'
                ? $client->getRequest()->getQuery()->toString()
                : $client->getRequest()->getPost()->toString();
            $this->error(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        return $returnCode ? [$response->getStatusCode(), $decodedResult]
            : $decodedResult;
    }

    /**
     * Renew the patron cookie and store it in the cache.
     * Throw an exception if there is an error.
     *
     * @param array $patron Patron information
     *
     * @return bool
     * @throws ILSException
     */
    protected function renewPatronCookie($patron)
    {
        $apiUrl = $this->config['Catalog']['host'] . '/v1/auth/session';

        // Create proxy request
        $client = $this->createHttpClient($apiUrl);

        $client->setParameterPost(
            [
                'cardnumber' => $patron['cat_username'],
                'password' => $patron['cat_password']
            ]
        );

        try {
            $response = $client->setMethod('POST')->send();
        } catch (\Exception $e) {
            $this->error(
                "POST request for '$apiUrl' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }
        if (!$response->isSuccess()) {
            if (in_array((int)$response->getStatusCode(), [401, 403])) {
                return false;
            }
            $this->error(
                "POST request for '" . $client->getRequest()->getUriString()
                . "' did not succeed: "
                . $response->getStatusCode() . ': '
                . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Koha authentication.');
        }

        $this->sessionCache->patron = $patron['cat_username'];
        $this->sessionCache->patronCookie = $response->getCookie();
        $result = json_decode($response->getBody(), true);
        $this->sessionCache->patronId = $result['borrowernumber'];
        $this->sessionCache->patronPermissions = $result['permissions'];
        return true;
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

        if (empty($result[0]['item_availabilities'])) {
            return [];
        }

        $statuses = [];
        foreach ($result[0]['item_availabilities'] as $i => $item) {
            $avail = $item['availability'];
            $available = $avail['available'];
            $statusCodes = $this->getItemStatusCodes($item);
            $status = $this->pickStatus($statusCodes);
            if (isset($avail['unavailabilities']['Item::CheckedOut']['date_due'])) {
                $duedate = $this->dateConverter->convertToDisplayDate(
                    'Y-m-d\TH:i:sP',
                    $avail['unavailabilities']['Item::CheckedOut']['date_due']
                );
            } else {
                $duedate = null;
            }

            $entry = [
                'id' => $id,
                'item_id' => $item['itemnumber'],
                'location' => $this->getItemLocationName($item),
                'availability' => $available,
                'status' => $status,
                'status_array' => $statusCodes,
                'reserve' => 'N',
                'callnumber' => $this->getItemCallNumber($item),
                'duedate' => $duedate,
                'number' => $item['enumchron'],
                'barcode' => $item['barcode'],
                'sort' => $i,
                'requests_placed' => max(
                    [$item['hold_queue_length'], $result[0]['hold_queue_length']]
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
                if (isset($this->config['ItemStatusMappings'][$key])) {
                    $statuses[] = $this->config['ItemStatusMappings'][$key];
                } elseif (strncmp($key, 'Item::', 6) == 0) {
                    $status = substr($key, 6);
                    switch ($status) {
                    case 'CheckedOut':
                        $overdue = false;
                        if (!empty($reason['date_due'])) {
                            $duedate = $this->dateConverter->convert(
                                'Y-m-d',
                                'U',
                                $reason['date_due']
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
                        if (isset($reason['code'])) {
                            switch ($reason['code']) {
                            case 'Not For Loan':
                                $statuses[] = 'On Reference Desk';
                                break;
                            default:
                                $statuses[] = $reason['code'];
                                break;
                            }
                        } else {
                            $statuses[] = 'On Reference Desk';
                        }
                        break;
                    case 'Transfer':
                        $onHold = false;
                        if (!empty($item['availability']['notes'])) {
                            foreach ($item['availability']['notes'] as $noteKey
                                => $note
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
                } elseif (strncmp($key, 'ItemType::', 10) == 0) {
                    $status = substr($key, 10);
                    switch ($status) {
                    case 'NotForLoan':
                        $statuses[] = 'On Reference Desk';
                        break;
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

        if (0 === $result && $this->sortItemsByEnumChron) {
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
     * Get branches from cache or from the API
     *
     * @return array
     */
    protected function getBranches()
    {
        $cacheKey = 'branches';
        $branches = $this->getCachedData($cacheKey);
        if (null === $branches) {
            $result = $this->makeRequest(
                ['v1', 'libraries'], false, 'GET'
            );
            $branches = [];
            foreach ($result as $branch) {
                $branches[$branch['branchcode']] = $branch;
            }
            $this->putCachedData($cacheKey, $branches);
        }
        return $branches;
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
                ['v1', 'patrons', $patron['id'], 'status'],
                [],
                'GET',
                $patron
            );
            $blockReason = [];
            if (!empty($result['blocks'])) {
                $holdBlock = false;
                $nonHoldBlock = false;
                foreach ($result['blocks'] as $reason => $details) {
                    $params = [];
                    if ($reason === 'Hold::MaximumHoldsReached') {
                        $holdBlock = true;
                        $params = [
                            '%%blockCount%%' => $details['current_hold_count'],
                            '%%blockLimit%%' => $details['max_holds_allowed']
                        ];
                    } else {
                        $nonHoldBlock = true;
                    }
                    if (($reason == 'Patron::Debt'
                        || $reason == 'Patron::DebtGuarantees')
                        && !empty($details['current_outstanding'])
                        && !empty($details['max_outstanding'])
                    ) {
                        $params = [
                            '%%blockCount%%' => $details['current_outstanding'],
                            '%%blockLimit%%' => $details['max_outstanding']
                        ];
                    }
                    $reason = 'Borrowing Block Koha Reason '
                        . str_replace('::', '_', $reason);
                    $translated = $this->translate($reason, $params);
                    if ($reason !== $translated) {
                        $reason = $translated;
                        $blockReason[] = $reason;
                    }
                }
                // Add the generic block message to the beginning if we have blocks
                // other than hold block
                if ($nonHoldBlock) {
                    array_unshift(
                        $blockReason, $this->translate('Borrowing Block Message')
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
        static $cachedRecords = [];
        if (!isset($cachedRecords[$id])) {
            $cachedRecords[$id] = $this->makeRequest(['v1', 'items', $id]);
        }
        return $cachedRecords[$id];
    }

    /**
     * Fetch a bib record from Koha
     *
     * @param int $id Bib record id
     *
     * @return array|null
     */
    protected function getBibRecord($id)
    {
        static $cachedRecords = [];
        if (!isset($cachedRecords[$id])) {
            $cachedRecords[$id] = $this->makeRequest(['v1', 'biblios', $id]);
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
     * @param int   $code   HTTP Result Code
     * @param array $result API Response
     *
     * @return array
     */
    protected function holdError($code, $result = null)
    {
        $message = $result['error'] ?? 'hold_error_fail';
        switch ($message) {
        case 'Reserve cannot be placed. Reason: tooManyReserves':
        case 'Reserve cannot be placed. Reason: tooManyHoldsForThisRecord':
            $message = 'hold_error_too_many_holds';
            break;
        case 'Reserve cannot be placed. Reason: ageRestricted':
            $message = 'hold_error_age_restricted';
            break;
        }
        return [
            'success' => false,
            'sysMessage' => $message
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
            ? $this->renewalBlockMappings[$reason] : 'renew_denied';
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
        $branchId = (!$this->useHomeBranch && null !== $item['holdingbranch'])
            ? $item['holdingbranch'] : $item['homebranch'];
        $name = $this->translateLocation($branchId);
        if ($name === $branchId) {
            $branches = $this->getBranches();
            $name = isset($branches[$branchId])
                ? $branches[$branchId]['branchname'] : $branchId;
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
        return $item['itemcallnumber'];
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
        if (!empty($result[0]['availability']['unavailabilities'])) {
            foreach ($result[0]['availability']['unavailabilities']
                as $key => $reason
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
     * Get item and title information for a checkout
     *
     * @param array $entry Checkout entry
     *
     * @return array biblionumber, title and volume
     */
    protected function getCheckoutInformation($entry)
    {
        if (isset($entry['biblionumber'])) {
            // New fields available
            $biblionumber = $entry['biblionumber'];
            $title = $entry['title'];
            if (!empty($entry['title_remainder'])) {
                $title .= ' ' . $entry['title_remainder'];
                $title = trim($title);
            }
            $volume = $entry['enumchron'];
        } else {
            // TODO remove when no longer needed
            try {
                $item = $this->getItem($entry['itemnumber']);
                $volume = $item['enumchron'] ?? '';
                $title = '';
                $biblionumber = '';
                if (!empty($item['biblionumber'])) {
                    $biblionumber = $item['biblionumber'];
                    $bib = $this->getBibRecord($biblionumber);
                    if (!empty($bib['title'])) {
                        $title = $bib['title'];
                    }
                    if (!empty($bib['title_remainder'])) {
                        $title .= ' ' . $bib['title_remainder'];
                        $title = trim($title);
                    }
                }
            } catch (ILSException $e) {
                // Not a fatal error, but we can't display the loan properly
                $biblionumber = '';
                $volume = '';
                $title = '[item ' . $entry['itemnumber']
                    . ' cannot be displayed]';
            }
        }
        return [$biblionumber, $title, $volume];
    }

    /**
     * Converts given key to corresponding parameter
     *
     * @param string $key     to convert
     * @param string $default value to return
     *
     * @return string
     */
    public function getSortParamValue($key, $default = '')
    {
        $params = [
            'checkout' => 'issuedate',
            'return' => 'returndate',
            'lastrenewed' => 'lastreneweddate',
            'title' => 'title'
        ];

        return $params[$key] ?? $default;
    }
}
