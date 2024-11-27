<?php

/**
 * VuFind Driver for Koha, using REST API
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2023.
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
use VuFind\Exception\AuthToken as AuthTokenException;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\Service\CurrencyFormatter;

use function array_key_exists;
use function call_user_func;
use function count;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;

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
    \Laminas\Log\LoggerAwareInterface,
    \VuFind\I18n\HasSorterInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Cache\CacheTrait;
    use \VuFind\ILS\Driver\OAuth2TokenTrait;
    use \VuFind\I18n\HasSorterTrait;

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
     * @var callable
     */
    protected $sessionFactory;

    /**
     * Currency formatter
     *
     * @var CurrencyFormatter
     */
    protected $currencyFormatter;

    /**
     * Session cache
     *
     * @var \Laminas\Session\Container
     */
    protected $sessionCache;

    /**
     * Validate passwords
     *
     * @var bool
     */
    protected $dontValidatePasswords = false;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Whether to allow canceling holds in transit. Default is false.
     *
     * @var bool
     */
    protected $allowCancelInTransit = false;

    /**
     * Item status rankings. The lower the value, the more important the status.
     *
     * @var array
     */
    protected $statusRankings = [
        'Charged'                        => 1,
        'On Hold'                        => 2,
        'HoldingStatus::transit_to'      => 3,
        'HoldingStatus::transit_to_date' => 4,
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
        'RENT' => 'Rental',
    ];

    /**
     * Mappings from renewal block reasons
     *
     * @var array
     */
    protected $renewalBlockMappings = [
        'too_soon' => 'ILSMessages::renewal_too_soon',
        'auto_too_soon' => 'ILSMessages::will_auto_renew',
        'onsite_checkout' => 'ILSMessages::special_circulation',
        'on_reserve' => 'renew_item_requested',
        'too_many' => 'renew_item_limit',
        'restriction' => 'ILSMessages::renewal_block',
        'overdue' => 'renew_item_overdue',
        'cardlost' => 'ILSMessages::lost_card',
        'gonenoaddress' => 'patron_status_address_missing',
        'debarred' => 'patron_status_card_blocked',
        'debt' => 'ILSMessages::too_much_debt',
        'recalled' => 'ILSMessages::renewal_recalled',
    ];

    /**
     * Permanent renewal blocks
     *
     * @var array
     */
    protected $permanentRenewalBlocks = [
        'onsite_checkout',
        'on_reserve',
        'too_many',
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
    protected $itemStatusMappings = [
        'Item::Held' => 'On Hold',
        'Item::Waiting' => 'On Holdshelf',
        'Item::Recalled' => 'Recalled',
    ];

    /**
     * Item status mapping methods used when the item status mappings above
     * (or in the configuration file) don't contain a direct mapping.
     *
     * @var array
     */
    protected $itemStatusMappingMethods = [
        'Item::CheckedOut' => 'getStatusCodeItemCheckedOut',
        'Item::Lost' => 'getStatusCodeItemNotForLoanOrLost',
        'Item::NotForLoan' => 'getStatusCodeItemNotForLoanOrLost',
        'Item::NotForLoanForcing' => 'getStatusCodeItemNotForLoanOrLost',
        'Item::Transfer' => 'getStatusCodeItemTransfer',
        'ItemType::NotForLoan' => 'getStatusCodeItemNotForLoanOrLost',
    ];

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
    protected $sortItemsBySerialIssue = true;

    /**
     * Whether the location field in holdings/status results is populated from
     * - branch (Koha library branch/physical location)
     * or
     * - shelving (Koha permanent shelving location of an item)
     * Default is 'branch'.
     *
     * @var string
     */
    protected $locationField = 'branch';

    /**
     * Whether to include suspended holds in hold queue length calculation.
     *
     * @var bool
     */
    protected $includeSuspendedHoldsInQueueLength = false;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter     Date converter object
     * @param callable               $sessionFactory    Factory function returning
     * SessionContainer object
     * @param CurrencyFormatter      $currencyFormatter Currency formatter
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        $sessionFactory,
        currencyFormatter $currencyFormatter
    ) {
        $this->dateConverter = $dateConverter;
        $this->sessionFactory = $sessionFactory;
        $this->currencyFormatter = $currencyFormatter;
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

        $this->dontValidatePasswords
            = !empty($this->config['Catalog']['dontValidatePasswords']);

        $this->defaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation'] ?? '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }

        $this->allowCancelInTransit
            = !empty($this->config['Holds']['allowCancelInTransit']);

        if (!empty($this->config['StatusRankings'])) {
            $this->statusRankings = array_merge(
                $this->statusRankings,
                $this->config['StatusRankings']
            );
        }

        if (!empty($this->config['FeeTypeMappings'])) {
            $this->feeTypeMappings = array_merge(
                $this->feeTypeMappings,
                $this->config['FeeTypeMappings']
            );
        }

        if (!empty($this->config['PatronStatusMappings'])) {
            $this->patronStatusMappings = array_merge(
                $this->patronStatusMappings,
                $this->config['PatronStatusMappings']
            );
        }

        if (!empty($this->config['ItemStatusMappings'])) {
            $this->itemStatusMappings = array_merge(
                $this->itemStatusMappings,
                $this->config['ItemStatusMappings']
            );
        }

        $this->useHomeLibrary = !empty($this->config['Holdings']['useHomeLibrary']);

        $this->sortItemsBySerialIssue
            = $this->config['Holdings']['sortBySerialIssue'] ?? true;

        $this->locationField
            = strtolower(trim($this->config['Holdings']['locationField'] ?? 'branch'));

        $this->includeSuspendedHoldsInQueueLength
            = $this->config['Holdings']['includeSuspendedHoldsInQueueLength'] ?? false;

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
        $holdings = $this->getItemStatusesForBiblio($id);
        return $holdings['holdings'];
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
            $holdings = $this->getItemStatusesForBiblio($id);
            $items[] = $holdings['holdings'];
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
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        return $this->getItemStatusesForBiblio($id, $patron, $options);
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
     * Get Departments
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = dept. name.
     */
    public function getDepartments()
    {
        $result = $this->makeRequest('v1/contrib/kohasuomi/departments');
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }
        $departments = [];
        foreach ($result['data'] as $department) {
            // Before Koha 23.11, authorized values contained authorised_value and lib_opac.
            // From 23.11, they are 'value' and 'opac_description' (see Koha bug 32981):
            $code = $department['value'] ?? $department['authorised_value'];
            $description = $department['opac_description'] ?? $department['lib_opac'];
            $departments[$code] = $description;
        }
        return $departments;
    }

    /**
     * Get Instructors
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        $result = $this->makeRequest('v1/contrib/kohasuomi/instructors');
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }
        $instructors = [];
        foreach ($result['data'] as $instructor) {
            $name = trim(
                ($instructor['firstname'] ?? '') . ' '
                . ($instructor['surname'] ?? '')
            );
            $instructors[$instructor['patron_id']] = $name;
        }
        return $instructors;
    }

    /**
     * Get Courses
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        $result = $this->makeRequest('v1/contrib/kohasuomi/courses');
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }
        $courses = [];
        foreach ($result['data'] as $course) {
            $courses[$course['course_id']] = $course['course_name'];
        }
        return $courses;
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
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     */
    public function findReserves($course, $inst, $dept)
    {
        $params = [];
        if ('' !== $course) {
            $params['course_id'] = $course;
        }
        if ('' !== $inst) {
            $params['patron_id'] = $inst;
        }
        if ('' !== $dept) {
            $params['department'] = $dept;
        }

        $result = $this->makeRequest(
            [
                'path' => 'v1/contrib/kohasuomi/coursereserves',
                'query' => $params,
            ]
        );
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $reserves = [];
        foreach ($result['data'] as $reserve) {
            $reserves[] = [
                'BIB_ID' => $reserve['biblio_id'],
                'COURSE_ID' => $reserve['course_id'],
                'DEPARTMENT_ID' => $reserve['course_department'],
                'INSTRUCTOR_ID' => $reserve['patron_id'],
            ];
        }
        return $reserves;
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
        if (empty($username)) {
            return null;
        }

        if ($this->dontValidatePasswords) {
            $result = $this->makeRequest(
                [
                    'path' => 'v1/patrons',
                    'query' => [
                        'userid' => $username,
                        '_match' => 'exact',
                    ],
                    'method' => 'GET',
                    'errors' => true,
                ]
            );

            if (isset($result['data'][0])) {
                $data = $result['data'][0];
            } else {
                return null;
            }
        } else {
            if (empty($password)) {
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

            if (isset($result['data'])) {
                $data = $result['data'];
            } else {
                return null;
            }
        }

        if (401 === $result['code'] || 403 === $result['code']) {
            return null;
        }
        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        return [
            'id' => $data['patron_id'],
            'firstname' => $data['firstname'],
            'lastname' => $data['surname'],
            'cat_username' => $username,
            'cat_password' => (string)$password,
            'email' => $data['email'],
            'major' => null,
            'college' => null,
            'home_library' => $data['library_id'],
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
            'expiration_date' => $this->convertDate($result['expiry_date'] ?? null),
            'birthdate' => $result['date_of_birth'] ?? '',
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
     * Function for attempting to renew a patron's items. The data in
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
            [$checkoutId, $itemId] = explode('|', $details);
            $result = $this->makeRequest(
                [
                    'path' => ['v1', 'checkouts', $checkoutId, 'renewal'],
                    'method' => 'POST',
                ]
            );
            if (201 === $result['code']) {
                $newDate
                    = $this->convertDate($result['data']['due_date'] ?? null, true);
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => true,
                    'new_date' => $newDate,
                ];
            } else {
                $finalResult['details'][$itemId] = [
                    'item_id' => $itemId,
                    'success' => false,
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
     * Purge Patron Transaction History
     *
     * @param array  $patron The patron array from patronLogin
     * @param ?array $ids    IDs to purge, or null for all
     *
     * @throws ILSException
     * @return array Associative array of the results
     */
    public function purgeTransactionHistory(array $patron, ?array $ids): array
    {
        if (null !== $ids) {
            throw new ILSException('Unsupported function');
        }
        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    'checkouts', 'history',
                ],
                'method' => 'DELETE',
                'errors' => true,
            ]
        );
        if (!in_array($result['code'], [200, 202, 204])) {
            return  [
                'success' => false,
                'status' => 'An error has occurred',
                'sys_message' => $result['data']['error'] ?? $result['code'],
            ];
        }

        return [
            'success' => true,
            'status' => 'loan_history_all_purged',
            'sys_message' => '',
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
            [
                'path' => 'v1/holds',
                'query' => [
                    'patron_id' => $patron['id'],
                    '_match' => 'exact',
                    '_per_page' => -1,
                ],
            ]
        );

        $holds = [];
        foreach ($result['data'] as $entry) {
            $biblio = $this->getBiblio($entry['biblio_id']);
            $frozen = !empty($entry['suspended']);
            $volume = '';
            if ($entry['item_id'] ?? null) {
                $item = $this->getItem($entry['item_id']);
                $volume = $item['serial_issue_number'];
            }
            $available = !empty($entry['waiting_date']);
            $inTransit = !empty($entry['status']) && $entry['status'] == 'T';
            $requestId = $entry['hold_id'];
            $cancelDetails
                = ($available || ($inTransit && !$this->allowCancelInTransit))
                ? ''
                : $requestId;
            $updateDetails = ($available || $inTransit) ? '' : $requestId;
            // Note: Expiration date is the last interest date until the hold becomes
            // available for pickup. Then it becomes the last pickup date.
            $expirationDate = $this->convertDate($entry['expiration_date']);
            $holds[] = [
                'id' => $entry['biblio_id'],
                'item_id' => $entry['hold_id'],
                'reqnum' => $requestId,
                'location' => $this->getLibraryName(
                    $entry['pickup_library_id'] ?? null
                ),
                'create' => $this->convertDate($entry['hold_date'] ?? null),
                '__create' => $entry['hold_date'] ?? null,
                'expire' => $available ? null : $expirationDate,
                'position' => $entry['priority'],
                'available' => $available,
                'last_pickup_date' => $available ? $expirationDate : null,
                'frozen' => $frozen,
                'frozenThrough' => $frozen
                    ? $this->convertDate($entry['suspended_until'] ?? null) : null,
                'in_transit' => $inTransit,
                'title' => $this->getBiblioTitle($biblio),
                'isbn' => $biblio['isbn'] ?? '',
                'issn' => $biblio['issn'] ?? '',
                'publication_year' => $biblio['copyright_date']
                    ?? $biblio['publication_year'] ?? '',
                'volume' => $volume,
                'cancel_details' => $cancelDetails,
                'updateDetails' => $updateDetails,
            ];
        }

        if ($this->config['Holds']['enableRecalls'] ?? false) {
            $result = $this->makeRequest(
                [
                    'path' => 'v1/recalls',
                    'query' => [
                        'patron_id' => $patron['id'],
                        'completed' => 'false',
                        '_match' => 'exact',
                        '_per_page' => -1,
                    ],
                ]
            );

            foreach ($result['data'] as $entry) {
                $biblio = $this->getBiblio($entry['biblio_id']);
                $volume = '';
                if ($entry['item_id'] ?? null) {
                    $item = $this->getItem($entry['item_id']);
                    $volume = $item['serial_issue_number'];
                }
                $available = !empty($entry['waiting_date']);
                $inTransit = !empty($entry['status']) && $entry['status'] == 'in_transit';
                $requestId = $entry['recall_id'];
                $cancelDetails = '';
                $updateDetails = ($available || $inTransit) ? '' : $requestId;
                // Note: Expiration date is the last interest date until the hold becomes
                // available for pickup. Then it becomes the last pickup date.
                $expirationDate = $this->convertDate($entry['expiration_date']);
                $holds[] = [
                    'id' => $entry['biblio_id'],
                    'item_id' => $entry['recall_id'],
                    'reqnum' => $requestId,
                    'location' => $this->getLibraryName(
                        $entry['pickup_library_id'] ?? null
                    ),
                    'create' => $this->convertDate($entry['hold_date'] ?? null),
                    '__create' => $entry['hold_date'] ?? null,
                    'expire' => $available ? null : $expirationDate,
                    'position' => $entry['priority'],
                    'available' => $available,
                    'last_pickup_date' => $available ? $expirationDate : null,
                    'in_transit' => $inTransit,
                    'title' => $this->getBiblioTitle($biblio),
                    'isbn' => $biblio['isbn'] ?? '',
                    'issn' => $biblio['issn'] ?? '',
                    'publication_year' => $biblio['copyright_date']
                        ?? $biblio['publication_year'] ?? '',
                    'volume' => $volume,
                    'cancel_details' => $cancelDetails,
                    'updateDetails' => $updateDetails,
                ];
            }
        }
        $callback = function ($a, $b) {
            return $a['__create'] === $b['__create']
                ? $a['item_id'] <=> $b['item_id']
                : $a['__create'] <=> $b['__create'];
        };
        usort($holds, $callback);
        return $holds;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold. The data in $cancelDetails['details'] is taken from
     * holds' cancel_details field.
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
                    'errors' => true,
                ]
            );

            if (200 === $result['code'] || 204 === $result['code']) {
                $response[$holdId] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                ];
                ++$count;
            } else {
                $response[$holdId] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false,
                ];
            }
        }
        return ['count' => $count, 'items' => $response];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for getting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing or editing a hold. When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data. When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored. The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
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
                return $this->getSorter()->compare(
                    $a['locationDisplay'],
                    $b['locationDisplay']
                );
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
     * placeHold, minus the patron data. May be used to limit the pickup options
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
     * @param array  $patron An array of patron data
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
                        'hold',
                    ],
                    'query' => ['patron_id' => $patron['id']],
                ]
            );
            if (!empty($result['data']['availability']['available'])) {
                return [
                    'valid' => true,
                    'status' => 'title_hold_place',
                ];
            }
            return [
                'valid' => false,
                'status' => $this->getHoldBlockReason($result['data']),
            ];
        }

        // Check if we have an item id:
        if (empty($data['item_id'])) {
            return false;
        }

        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'availability', 'items',
                    $data['item_id'], 'hold',
                ],
                'query' => ['patron_id' => $patron['id']],
            ]
        );
        if (!empty($result['data']['availability']['available'])) {
            return [
                'valid' => true,
                'status' => 'hold_place',
            ];
        }
        return [
            'valid' => false,
            'status' => $this->getHoldBlockReason($result['data']),
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

        // Make sure pickup location is valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $request = [
            'biblio_id' => (int)$bibId,
            'patron_id' => (int)$patron['id'],
            'pickup_library_id' => $pickUpLocation,
            'notes' => $comment,
            'expiration_date' => !empty($holdDetails['requiredByTS'])
                        ? date('Y-m-d', $holdDetails['requiredByTS'])
                        : null,
        ];
        if ($level == 'copy') {
            $request['item_id'] = (int)$itemId;
        }

        $result = $this->makeRequest(
            [
                'path' => 'v1/holds',
                'json' => $request,
                'method' => 'POST',
                'errors' => true,
            ]
        );

        if ($result['code'] >= 300) {
            return $this->holdError($result['data']['error'] ?? 'hold_error_fail');
        }

        if ($holdDetails['startDateTS']) {
            $holdId = $result['data']['hold_id'];
            // Suspend until the previous day from start date:
            $request = [
                'suspended_until' => \DateTime::createFromFormat(
                    'U',
                    $holdDetails['startDateTS']
                )->modify('-1 DAY')->format('Y-m-d') . ' 23:59:59',
            ];
            $result = $this->makeRequest(
                [
                    'path' => ['v1', 'holds', $holdId],
                    'json' => $request,
                    'method' => 'PUT',
                    'errors' => true,
                ]
            );
            if ($result['code'] >= 300) {
                // Report a success since the hold was created, but include a message
                // about the modification failure:
                return [
                    'success' => true,
                    'warningMessage' => 'hold_error_update_failed',
                ];
            }
        }

        return ['success' => true];
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
        foreach ($holdsDetails as $requestId) {
            $updateFields = [];
            // Suspension (bool) has its own endpoint, so we need to distinguish
            // between the cases
            if (isset($fields['frozen'])) {
                if ($fields['frozen']) {
                    if (isset($fields['frozenThrough'])) {
                        // Convert the date to end of day in RFC3339 format. Note
                        // that as of May 2022 Koha only uses the date part and
                        // ignores time, but requires a valid RFC3339 date+time.
                        $date = $this->dateConverter->convertToDateTime(
                            'U',
                            $fields['frozenThroughTS']
                        );
                        $date->setTime(23, 59, 59, 999);
                        $updateFields['suspended_until']
                            = $date->format($date::RFC3339);
                        $result = false;
                    } else {
                        $result = $this->makeRequest(
                            [
                                'path' => ['v1', 'holds', $requestId, 'suspension'],
                                'method' => 'POST',
                                'json' => new \stdClass(), // For empty JSON object
                                'errors' => true,
                            ]
                        );
                    }
                } else {
                    $result = $this->makeRequest(
                        [
                            'path' => ['v1', 'holds', $requestId, 'suspension'],
                            'method' => 'DELETE',
                            'json' => new \stdClass(), // For empty JSON object
                            'errors' => true,
                        ]
                    );
                }
                if ($result && $result['code'] >= 300) {
                    $results[$requestId]['status']
                        = $result['data']['error'] ?? 'hold_error_update_failed';
                }
            }
            if (empty($results[$requestId]['errors'])) {
                if (isset($fields['pickUpLocation'])) {
                    $updateFields['pickup_library_id'] = $fields['pickUpLocation'];
                }
                if ($updateFields) {
                    $result = $this->makeRequest(
                        [
                            'path' => ['v1', 'holds', $requestId],
                            'method' => 'PUT',
                            'json' => $updateFields,
                            'errors' => true,
                        ]
                    );
                    if ($result['code'] >= 300) {
                        $results[$requestId]['status']
                            = $result['data']['error'] ?? 'hold_error_update_failed';
                    }
                }
            }

            $results[$requestId]['success'] = empty($results[$requestId]['status']);
        }

        return $results;
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
                'articlerequests',
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
     * @param array $patron  Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelStorageRetrievalRequestDetails($details, $patron)
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
                        'articlerequests', $id,
                    ],
                    'method' => 'DELETE',
                    'errors' => true,
                ]
            );

            if (200 !== $result['code']) {
                $response[$id] = [
                    'success' => false,
                    'status' => 'storage_retrieval_request_cancel_fail',
                    'sysMessage' => false,
                ];
            } else {
                $response[$id] = [
                    'success' => true,
                    'status' => 'storage_retrieval_request_cancel_success',
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
     * @param array  $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        if (
            !isset($this->config['StorageRetrievalRequests'])
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
                        'articlerequest',
                    ],
                    'query' => ['patron_id' => $patron['id']],
                ]
            );
        } else {
            $result = $this->makeRequest(
                [
                    'path' => [
                        'v1', 'contrib', 'kohasuomi', 'availability', 'items',
                        $data['item_id'], 'articlerequest',
                    ],
                    'query' => ['patron_id' => $patron['id']],
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
        if (
            null !== $pickUpLocation
            && !$this->pickUpLocationIsValid($pickUpLocation, $patron, $details)
        ) {
            return [
                'success' => false,
                'sysMessage' => 'storage_retrieval_request_invalid_pickup',
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
                    'articlerequests',
                ],
                'json' => $request,
                'method' => 'POST',
                'errors' => true,
            ]
        );

        if ($result['code'] >= 300) {
            $message = $result['data']['error']
                ?? 'storage_retrieval_request_error_fail';
            return [
                'success' => false,
                'sysMessage' => $message,
            ];
        }
        return [
            'success' => true,
            'status' => 'storage_retrieval_request_place_success',
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
            $type = trim($entry['debit_type']);
            $type = $this->translate($this->feeTypeMappings[$type] ?? $type);
            $description = trim($entry['description']);
            if ($description !== $type) {
                $type .= " - $description";
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
            'password_2' => $details['newPassword'],
        ];

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $patron['id'], 'password'],
                'json' => $request,
                'method' => 'POST',
                'errors' => true,
            ]
        );

        if (200 !== $result['code']) {
            if (400 === $result['code']) {
                $message = 'password_error_invalid';
            } else {
                $message = 'An error has occurred';
            }
            return [
                'success' => false, 'status' => $message,
            ];
        }
        return ['success' => true, 'status' => 'change_password_ok'];
    }

    /**
     * Provide an array of URL data (in the same format returned by the record
     * driver's getURLs method) for the specified bibliographic record.
     *
     * @param string $id Bibliographic record ID
     *
     * @return array
     */
    public function getUrlsForRecord(string $id): array
    {
        $links = [];
        $opacUrl = $this->config['Catalog']['opacURL'] ?? false;
        if ($opacUrl) {
            $url = strstr($opacUrl, '%%id%%') === false
                ? $opacUrl . urlencode($id)
                : str_replace('%%id%%', urlencode($id), $opacUrl);
            $desc = $this->translate('view_in_opac');
            $links[] = compact('url', 'desc');
        }
        return $links;
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
    public function getConfig($function, $params = [])
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
                    '+title' => 'sort_title',
                ],
                'default_sort' => '-checkout_date',
                'purge_all' => $this->config['TransactionHistory']['purgeAll'] ?? true,
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
                    '+title' => 'sort_title',
                ],
                'default_sort' => '+due_date',
            ];
        } elseif ('Holdings' === $function) {
            $config = $this->config['Holdings'] ?? [];
            if ($limitByType = $this->config['Holdings']['itemLimitByType'] ?? null) {
                $biblio = $this->getBiblio($params['id']);
                $type   = $biblio['item_type'];
                if ($typeLimit = $limitByType[$type] ?? null) {
                    $config['itemLimit'] = $typeLimit;
                }
            }
            return $config;
        }

        return $this->config[$function] ?? false;
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

        if (
            isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Laminas\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context,
                    'ssl',
                    'verify_peer_name',
                    false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Laminas\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // Set timeout value
        $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
        // Make sure keepalive is disabled as this is known to cause problems:
        $client->setOptions(
            ['timeout' => $timeout, 'useragent' => 'VuFind', 'keepalive' => false]
        );

        // Set Accept header
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept',
            'application/json'
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
                'path' => $request,
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
                'Content-Type',
                'application/json'
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
        if (
            empty($request['errors']) && !$response->isSuccess()
            && (null === $decodedResult || !empty($decodedResult['error'])
            || !empty($decodedResult['errors']))
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

        try {
            $token = $this->getNewOAuth2Token(
                $url,
                $this->config['Catalog']['clientId'],
                $this->config['Catalog']['clientSecret'],
                $this->config['Catalog']['grantType'] ?? 'client_credentials'
            );
        } catch (AuthTokenException $exception) {
            throw new ILSException(
                'Problem with Koha REST API: ' . $exception->getMessage()
            );
        }

        $this->putCachedData(
            $cacheKey,
            $token->getHeaderValue(),
            $token->getExpiresIn()
        );

        return $token->getHeaderValue();
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron information, if available
     * @param array  $options Extra options
     *
     * @return array On success an array with the key "total" containing the total
     * number of items for the given bib id, and the key "holdings" containing an
     * array of holding information each one with these keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null, array $options = [])
    {
        // Prepare result array with default values. If no API result can be received
        // these will be returned.
        $results = ['total' => 0, 'holdings' => []];

        $requestParams = [
            'path' => [
                'v1', 'contrib', 'kohasuomi', 'availability', 'biblios', $id,
                'search',
            ],
            'errors' => true,
            'query' => [],
        ];
        if (($options['itemLimit'] ?? 0) > 0) {
            $requestParams['query'] = [
                'limit'  => $options['itemLimit'],
                'offset' => $options['offset'],
            ];
        }
        if ($this->includeSuspendedHoldsInQueueLength) {
            $requestParams['query']['include_suspended_in_hold_queue'] = '1';
        }
        $result = $this->makeRequest($requestParams);
        if (404 == $result['code']) {
            return $results;
        }
        if (200 != $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        if (empty($result['data']['item_availabilities'])) {
            return $results;
        }

        // Return total number of results for pagination (with fallback for older
        // Koha DI plugin versions that don't support paging).
        $results['total'] = (int)($result['data']['items_total'] ?? count($result['data']['item_availabilities']));

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

            $extraStatusInformation = [];
            if ($transit = $avail['unavailabilities']['Item::Transfer'] ?? null) {
                if (null !== ($toLibrary = $transit['to_library'] ?? null)) {
                    $extraStatusInformation['location'] = $this->getLibraryName($transit['to_library']);
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
                'location' => $this->getItemLocationName($item),
                'availability' => new AvailabilityStatus($available, $status, $extraStatusInformation),
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
                ),
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

            $results['holdings'][] = $entry;
        }

        usort($results['holdings'], [$this, 'statusSortFunction']);
        return $results;
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
            foreach ($item['availability']['unavailabilities'] as $code => $data) {
                // If we have a direct mapping, use it:
                if (isset($this->itemStatusMappings[$code])) {
                    $statuses[] = $this->itemStatusMappings[$code];
                    continue;
                }

                // Check for a mapping method for the unavailability reason:
                if ($methodName = ($this->itemStatusMappingMethods[$code] ?? '')) {
                    $statuses[]
                        = call_user_func([$this, $methodName], $code, $data, $item);
                } else {
                    if (!empty($data['code'])) {
                        $statuses[] = $data['code'];
                    } else {
                        $parts = explode('::', $code, 2);
                        if (isset($parts[1])) {
                            $statuses[] = $parts[1];
                        }
                    }
                }
            }
            if (empty($statuses)) {
                $statuses[] = 'Not Available';
            }
        } else {
            $this->logError(
                'Unable to determine status for item: ' . $this->varDump($item)
            );
        }

        if (empty($statuses)) {
            $statuses[] = 'No information available';
        }
        return array_unique($statuses);
    }

    /**
     * Get item status code for CheckedOut status
     *
     * @param string $code Status code
     * @param array  $data Status data
     * @param array  $item Item
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getStatusCodeItemCheckedOut($code, $data, $item)
    {
        $overdue = false;
        if (!empty($data['due_date'])) {
            $duedate = $this->dateConverter->convert(
                'Y-m-d',
                'U',
                $data['due_date']
            );
            $overdue = $duedate < time();
        }
        return $overdue ? 'Overdue' : 'Charged';
    }

    /**
     * Get item status code for NotForLoan or Lost status
     *
     * @param string $code Status code
     * @param array  $data Status data
     * @param array  $item Item
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getStatusCodeItemNotForLoanOrLost($code, $data, $item)
    {
        // NotForLoan and Lost are special: status has a library-specific
        // status number. Allow mapping of different status numbers
        // separately (e.g. Item::NotForLoan with status number 4
        // is mapped with key Item::NotForLoan4):
        $statusKey = $code . ($data['status'] ?? '-');
        // Replace ':' in status key if used as status since ':' is
        // the namespace separator in translatable strings:
        return $this->itemStatusMappings[$statusKey]
            ?? $data['code'] ?? str_replace(':', '_', $statusKey);
    }

    /**
     * Get item status code for Transfer status
     *
     * @param string $code Status code
     * @param array  $data Status data
     * @param array  $item Item
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getStatusCodeItemTransfer($code, $data, $item)
    {
        if (isset($data['to_library'])) {
            return isset($data['datesent']) ? 'HoldingStatus::transit_to_date' : 'HoldingStatus::transit_to';
        }

        $onHold = array_key_exists(
            'Item::Held',
            $item['availability']['notes'] ?? []
        );
        return $onHold ? 'In Transit On Hold' : 'In Transit';
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
        $result = $this->getSorter()->compare($a['location'], $b['location']);

        if (0 === $result && $this->sortItemsBySerialIssue) {
            $result = strnatcmp($a['number'] ?? '', $b['number'] ?? '');
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
        if (
            empty($this->config['StorageRetrievalRequests']['allow_checked_out'])
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
        return $this->statusRankings[$status] ?? 32000;
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
            $result = $this->makeRequest('v1/libraries?_per_page=-1');
            $libraries = [];
            foreach ($result['data'] as $library) {
                $libraries[$library['library_id']] = $library;
            }
            $this->putCachedData($cacheKey, $libraries, 3600);
        }
        return $libraries;
    }

    /**
     * Get shelving locations from cache or from the API
     *
     * @return array
     */
    protected function getShelvingLocations()
    {
        $cacheKey = 'shelvingLocations';
        $shelvingLocations = $this->getCachedData($cacheKey);
        if (null === $shelvingLocations) {
            $result = $this->makeRequest('v1/authorised_value_categories/loc/authorised_values?_per_page=-1');

            $shelvingLocations = [];
            foreach ($result['data'] as $shelvingLocation) {
                $shelvingLocations[$shelvingLocation['value']] = $shelvingLocation;
            }
            $this->putCachedData($cacheKey, $shelvingLocations, 3600);
        }
        return $shelvingLocations;
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
                        'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    ],
                    'query' => ['query_blocks' => 1],
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
                        $blockReason,
                        $this->translate('patron_status_card_blocked')
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
            'sysMessage' => $error,
        ];
    }

    /**
     * Map a Koha renewal block reason code to a VuFind translation string
     *
     * @param string $reason Koha block code
     * @param string $itype  Koha item type
     *
     * @return string
     */
    protected function mapRenewalBlockReason($reason, $itype)
    {
        return $this->config['ItemTypeRenewalBlockMappings'][$itype][$reason]
            ?? $this->renewalBlockMappings[$reason]
            ?? 'renew_item_no';
    }

    /**
     * Return a location (branch or shelving) for a Koha item
     *
     * @param array $item Item
     *
     * @return string
     */
    protected function getItemLocationName($item)
    {
        switch ($this->locationField) {
            case 'shelving':
                $shelvingLocationId = $item['location'];
                $name = $this->translateLocation($shelvingLocationId);
                if ($name === $shelvingLocationId) {
                    $shelvingLocations = $this->getShelvingLocations();
                    $name = $shelvingLocations[$shelvingLocationId]['description'] ?? $shelvingLocationId;
                }
                break;
            default:
                $libraryId = (!$this->useHomeLibrary && null !== $item['holding_library_id'])
                    ? $item['holding_library_id'] : $item['home_library_id'];
                $name = $this->translateLocation($libraryId);
                if ($name === $libraryId) {
                    $libraries = $this->getLibraries();
                    $name = $libraries[$libraryId]['name'] ?? $libraryId;
                }
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
            return $default ?? '';
        }
        $prefix = 'location_';
        return $this->translate(
            "$prefix$location",
            null,
            $default ?? $location
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
            foreach (
                array_keys($result['availability']['unavailabilities']) as $key
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
            'title' => 'title',
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
            $sort = '+title,+subtitle';
        } elseif ('-title' === $sort) {
            $sort = '-title,-subtitle';
        }
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
            ];

            $transactions[] = $transaction;
        }

        return [
            'count' => $result['headers']['X-Total-Count'] ?? count($transactions),
            $arrayKey => $transactions,
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
                    '%%blockLimit%%' => $details['max_holds_allowed'],
                ];
                break;
            case 'Patron::Debt':
            case 'Patron::DebtGuarantees':
                $count = isset($details['current_outstanding'])
                    ? $this->formatMoney($details['current_outstanding']) : '-';
                $limit = isset($details['max_outstanding'])
                    ? $this->formatMoney($details['max_outstanding']) : '-';
                $params = [
                    '%%blockCount%%' => $count,
                    '%%blockLimit%%' => $limit,
                ];
                break;
        }
        return $this->translate($this->patronStatusMappings[$reason] ?? '', $params);
    }

    /**
     * Helper function for formatting currency
     *
     * @param float $amount Number to format
     *
     * @return string
     */
    protected function formatMoney($amount)
    {
        return $this->currencyFormatter->convertToDisplayFormat($amount);
    }
}
