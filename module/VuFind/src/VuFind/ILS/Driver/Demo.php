<?php

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * Note that some sample values (holds, transactions, fines) are stored in
 * the session. You can log out and log back in to get a different set of
 * values.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007, 2022.
 * Copyright (C) The National Library of Finland 2014.
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
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use ArrayObject;
use Laminas\Http\Request as HttpRequest;
use Laminas\Session\Container as SessionContainer;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\ILS\Logic\AvailabilityStatusInterface;
use VuFindSearch\Command\RandomCommand;
use VuFindSearch\Query\Query;
use VuFindSearch\Service as SearchService;

use function array_key_exists;
use function array_slice;
use function count;
use function in_array;
use function is_callable;
use function sprintf;
use function strlen;

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Demo extends AbstractBase implements \VuFind\I18n\HasSorterInterface
{
    use \VuFind\I18n\HasSorterTrait;

    /**
     * Catalog ID used to distinguish between multiple Demo driver instances with the
     * MultiBackend driver
     *
     * @var string
     */
    protected $catalogId = 'demo';

    /**
     * Connection used when getting random bib ids from Solr
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Total count of records in the Solr index (used for random bib lookup)
     *
     * @var int
     */
    protected $totalRecords;

    /**
     * Container for storing persistent simulated ILS data.
     *
     * @var SessionContainer[]
     */
    protected $session = [];

    /**
     * Factory function for constructing the SessionContainer.
     *
     * @var callable
     */
    protected $sessionFactory;

    /**
     * HTTP Request object (if available).
     *
     * @var ?HttpRequest
     */
    protected $request;

    /**
     * Should we return bib IDs in MyResearch responses?
     *
     * @var bool
     */
    protected $idsInMyResearch = true;

    /**
     * Should we support Storage Retrieval Requests?
     *
     * @var bool
     */
    protected $storageRetrievalRequests = true;

    /**
     * Should we support ILLRequests?
     *
     * @var bool
     */
    protected $ILLRequests = true;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Failure probability settings
     *
     * @var array
     */
    protected $failureProbabilities = [];

    /**
     * Courses for use in course reserves.
     *
     * @var array
     */
    protected $courses = ['Course A', 'Course B', 'Course C'];

    /**
     * Departments for use in course reserves.
     *
     * @var array
     */
    protected $departments = ['Dept. A', 'Dept. B', 'Dept. C'];

    /**
     * Instructors for use in course reserves.
     *
     * @var array
     */
    protected $instructors = ['Instructor A', 'Instructor B', 'Instructor C'];

    /**
     * Item and pick up locations
     *
     * @var array
     */
    protected $locations = [
        [
            'locationID' => 'A',
            'locationDisplay' => 'Campus A',
        ],
        [
            'locationID' => 'B',
            'locationDisplay' => 'Campus B',
        ],
        [
            'locationID' => 'C',
            'locationDisplay' => 'Campus C',
        ],
    ];

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter  Date converter object
     * @param SearchService          $ss             Search service
     * @param callable               $sessionFactory Factory function returning
     * SessionContainer object for fake data to simulate consistency and reduce Solr
     * hits
     * @param HttpRequest            $request        HTTP request object (optional)
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        SearchService $ss,
        $sessionFactory,
        HttpRequest $request = null
    ) {
        $this->dateConverter = $dateConverter;
        $this->searchService = $ss;
        if (!is_callable($sessionFactory)) {
            throw new \Exception('Invalid session factory passed to constructor.');
        }
        $this->sessionFactory = $sessionFactory;
        $this->request = $request;
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
        if (isset($this->config['Catalog']['id'])) {
            $this->catalogId = $this->config['Catalog']['id'];
        }
        if (isset($this->config['Catalog']['idsInMyResearch'])) {
            $this->idsInMyResearch = $this->config['Catalog']['idsInMyResearch'];
        }
        if (isset($this->config['Catalog']['storageRetrievalRequests'])) {
            $this->storageRetrievalRequests
                = $this->config['Catalog']['storageRetrievalRequests'];
        }
        if (isset($this->config['Catalog']['ILLRequests'])) {
            $this->ILLRequests = $this->config['Catalog']['ILLRequests'];
        }
        if (isset($this->config['Failure_Probabilities'])) {
            $this->failureProbabilities = $this->config['Failure_Probabilities'];
        }
        $this->defaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation'] ?? '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }
        $this->checkIntermittentFailure();
    }

    /**
     * Check for a simulated failure. Returns true for failure, false for
     * success.
     *
     * @param string $method  Name of method that might fail
     * @param int    $default Default probability (if config is empty)
     *
     * @return bool
     */
    protected function isFailing($method, $default = 0)
    {
        // Method may come in like Class::Method, we just want the Method part
        $parts = explode('::', $method);
        $key = array_pop($parts);
        $probability = $this->failureProbabilities[$key] ?? $default;
        return rand(1, 100) <= $probability;
    }

    /**
     * Generate a fake location name.
     *
     * @param bool $returnText If true, return location text; if false, return ID
     *
     * @return string
     */
    protected function getFakeLoc($returnText = true)
    {
        $locations = $this->locations;
        $loc = rand() % count($locations);
        return $returnText
            ? $locations[$loc]['locationDisplay']
            : $locations[$loc]['locationID'];
    }

    /**
     * Generate fake services.
     *
     * @return array
     */
    protected function getFakeServices()
    {
        // Load service configuration; return empty array if no services defined.
        $services = isset($this->config['Records']['services'])
            ? (array)$this->config['Records']['services']
            : [];
        if (empty($services)) {
            return [];
        }

        // Make it more likely we have a single service than many:
        $count = rand(1, 5) == 1 ? rand(1, count($services)) : 1;
        $keys = (array)array_rand($services, $count);
        $fakeServices = [];

        foreach ($keys as $key) {
            if ($key !== null) {
                $fakeServices[] = $services[$key];
            }
        }

        return $fakeServices;
    }

    /**
     * Generate a fake status message.
     *
     * @return string
     */
    protected function getFakeStatus()
    {
        $loc = rand() % 10;
        switch ($loc) {
            case 10:
                return 'Missing';
            case 9:
                return 'On Order';
            case 8:
                return 'Invoiced';
            case 7:
                return 'Uncertain';
            default:
                return 'Available';
        }
    }

    /**
     * Generate a fake call number.
     *
     * @return string
     */
    protected function getFakeCallNum()
    {
        $codes = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $a = $codes[rand() % strlen($codes)];
        $b = rand() % 899 + 100;
        $c = rand() % 9999;
        return $a . $b . '.' . $c;
    }

    /**
     * Generate a fake call number prefix sometimes.
     *
     * @return string
     */
    protected function getFakeCallNumPrefix()
    {
        $codes = '0123456789';
        $prefix = substr(str_shuffle($codes), 1, rand(0, 1));
        if (!empty($prefix)) {
            return 'Prefix: ' . $prefix;
        }
        return '';
    }

    /**
     * Get a random ID from the Solr index.
     *
     * @return string
     */
    protected function getRandomBibId()
    {
        [$id] = $this->getRandomBibIdAndTitle();
        return $id;
    }

    /**
     * Get a random ID and title from the Solr index.
     *
     * @return array [id, title]
     */
    protected function getRandomBibIdAndTitle()
    {
        $source = $this->getRecordSource();
        $query = $this->config['Records']['query'] ?? '*:*';
        $command = new RandomCommand($source, new Query($query), 1);
        $result = $this->searchService->invoke($command)->getResult();
        if (count($result) === 0) {
            throw new \Exception("Problem retrieving random record from $source.");
        }
        $record = current($result->getRecords());
        return [$record->getUniqueId(), $record->getTitle()];
    }

    /**
     * Get the name of the search backend providing records.
     *
     * @return string
     */
    protected function getRecordSource()
    {
        return $this->config['Records']['source'] ?? DEFAULT_SEARCH_BACKEND;
    }

    /**
     * Should we simulate a system failure?
     *
     * @return void
     * @throws ILSException
     */
    protected function checkIntermittentFailure()
    {
        if ($this->isFailing(__METHOD__, 0)) {
            throw new ILSException('Simulating low-level system failure');
        }
    }

    /**
     * Are renewals blocked?
     *
     * @return bool
     */
    protected function checkRenewBlock()
    {
        return $this->isFailing(__METHOD__, 25);
    }

    /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestBlocks($patron)
    {
        return $this->isFailing(__METHOD__, 10)
            ? ['simulated request block'] : false;
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getAccountBlocks($patron)
    {
        return $this->isFailing(__METHOD__, 10)
            ? ['simulated account block'] : false;
    }

    /**
     * Generates a random, fake holding array
     *
     * @param string $id     set id
     * @param string $number set number for multiple items
     * @param array  $patron Patron data
     *
     * @return array
     */
    protected function getRandomHolding($id, $number, array $patron = null)
    {
        $status = $this->getFakeStatus();
        $location = $this->getFakeLoc();
        $locationhref = ($location === 'Campus A') ? 'http://campus-a' : false;
        switch ($status) {
            case 'Uncertain':
                $availability = AvailabilityStatusInterface::STATUS_UNCERTAIN;
                break;
            case 'Available':
                if (rand(1, 2) === 1) {
                    // Legacy boolean value
                    $availability = true;
                } else {
                    $availability = AvailabilityStatusInterface::STATUS_AVAILABLE;
                    $status = 'Item in Library';
                }
                break;
            default:
                if (rand(1, 2) === 1) {
                    // Legacy boolean value
                    $availability = false;
                } else {
                    $availability = AvailabilityStatusInterface::STATUS_UNAVAILABLE;
                }
                break;
        }
        $result = [
            'id'           => $id,
            'source'       => $this->getRecordSource(),
            'item_id'      => $number,
            'number'       => $number,
            'barcode'      => sprintf('%08d', rand() % 50000),
            'availability' => $availability,
            'status'       => $status,
            'location'     => $location,
            'locationhref' => $locationhref,
            'reserve'      => rand(1, 4) === 1 ? 'Y' : 'N',
            'callnumber'   => $this->getFakeCallNum(),
            'callnumber_prefix' => $this->getFakeCallNumPrefix(),
            'duedate'      => '',
            'is_holdable'  => true,
            'addLink'      => $patron ? true : false,
            'level'        => 'copy',
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => $patron ? 'check' : false,
            'ILLRequest'   => 'auto',
            'addILLRequestLink' => $patron ? 'check' : false,
            'services'     => $status == 'Available' ? $this->getFakeServices() : [],
        ];

        switch (rand(1, 5)) {
            case 1:
                $result['location'] = 'Digital copy available';
                $result['locationhref'] = 'http://digital';
                $result['__electronic__'] = true;
                $result['availability'] = true;
                $result['status'] = '';
                break;
            case 2:
                $result['location'] = 'Electronic Journals';
                $result['locationhref'] = 'http://electronic';
                $result['__electronic__'] = true;
                $result['availability'] = true;
                $result['status'] = 'Available from ' . rand(2010, 2019);
        }

        return $result;
    }

    /**
     * Generate an associative array containing some sort of ID (for cover
     * generation).
     *
     * @return array
     */
    protected function getRandomItemIdentifier()
    {
        switch (rand(1, 4)) {
            case 1:
                return ['isbn' => '1558612742'];
            case 2:
                return ['oclc' => '55114477'];
            case 3:
                return ['issn' => '1133-0686'];
        }
        return ['upc' => '733961100525'];
    }

    /**
     * Generate a list of holds, storage retrieval requests or ILL requests.
     *
     * @param string $requestType Request type (Holds, StorageRetrievalRequests or
     * ILLRequests)
     *
     * @return ArrayObject List of requests
     */
    protected function createRequestList($requestType)
    {
        // How many items are there?  %10 - 1 = 10% chance of none,
        // 90% of 1-9 (give or take some odd maths)
        $items = rand() % 10 - 1;

        $requestGroups = $this->getRequestGroups(null, null);

        $list = new ArrayObject();
        for ($i = 0; $i < $items; $i++) {
            $location = $this->getFakeLoc(false);
            $randDays = rand() % 10;
            $currentItem = [
                'location' => $location,
                'create'   => $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime("now - {$randDays} days")
                ),
                'expire'   => $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime('now + 30 days')
                ),
                'item_id' => $i,
                'reqnum' => $i,
            ];
            // Inject a random identifier of some sort:
            $currentItem += $this->getRandomItemIdentifier();
            if ($i == 2 || rand() % 5 == 1) {
                // Mimic an ILL request
                $currentItem['id'] = "ill_request_$i";
                $currentItem['title'] = "ILL Hold Title $i";
                $currentItem['institution_id'] = 'ill_institution';
                $currentItem['institution_name'] = 'ILL Library';
                $currentItem['institution_dbkey'] = 'ill_institution';
            } else {
                if ($this->idsInMyResearch) {
                    [$currentItem['id'], $currentItem['title']]
                        = $this->getRandomBibIdAndtitle();
                    $currentItem['source'] = $this->getRecordSource();
                } else {
                    $currentItem['title'] = 'Demo Title ' . $i;
                }
            }

            if ($requestType == 'Holds') {
                $pos = rand() % 5;
                if ($pos > 1) {
                    $currentItem['position'] = $pos;
                    $currentItem['available'] = false;
                    $currentItem['in_transit'] = (rand() % 2) === 1;
                } else {
                    $currentItem['available'] = true;
                    $currentItem['in_transit'] = false;
                    if (rand() % 3 != 1) {
                        $lastDate = strtotime('now + 3 days');
                        $currentItem['last_pickup_date'] = $this->dateConverter
                            ->convertToDisplayDate('U', $lastDate);
                    }
                }
                $pos = rand(0, count($requestGroups) - 1);
                $currentItem['requestGroup'] = $requestGroups[$pos]['name'];
                $currentItem['cancel_details'] = $currentItem['updateDetails']
                    = (!$currentItem['available'] && !$currentItem['in_transit'])
                    ? $currentItem['reqnum'] : '';
                if (rand(0, 3) === 1) {
                    $currentItem['proxiedBy'] = 'Fictional Proxy User';
                }
            } else {
                $status = rand() % 5;
                $currentItem['available'] = $status == 1;
                $currentItem['canceled'] = $status == 2;
                $currentItem['processed'] = ($status == 1 || rand(1, 3) == 3)
                    ? $this->dateConverter->convertToDisplayDate('U', time())
                    : '';
                if ($requestType == 'ILLRequests') {
                    $transit = rand() % 2;
                    if (
                        !$currentItem['available']
                        && !$currentItem['canceled']
                        && $transit == 1
                    ) {
                        $currentItem['in_transit'] = $location;
                    } else {
                        $currentItem['in_transit'] = false;
                    }
                }
            }

            $list->append($currentItem);
        }
        return $list;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $status = $this->getSimulatedStatus($id);
        foreach (array_keys($status) as $i) {
            $itemNum = $i + 1;
            $status[$i] += $this->getNotesAndSummary($itemNum);
        }
        return $status;
    }

    /**
     * Get suppressed records.
     *
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        return $this->config['Records']['suppressed'] ?? [];
    }

    /**
     * Get the session container (constructing it on demand if not already present)
     *
     * @param string $patron ID of current patron
     *
     * @return SessionContainer
     */
    protected function getSession($patron = null)
    {
        $sessionKey = md5($this->catalogId . '/' . ($patron ?? 'default'));

        // SessionContainer not defined yet? Build it now:
        if (!isset($this->session[$sessionKey])) {
            $this->session[$sessionKey] = ($this->sessionFactory)($sessionKey);
        }
        $result = $this->session[$sessionKey];
        // Special case: check for clear_demo request parameter to reset:
        if ($this->request && $this->request->getQuery('clear_demo')) {
            $result->exchangeArray([]);
        }

        return $result;
    }

    /**
     * Get Simulated Status (support method for getStatus/getHolding)
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getSimulatedStatus($id, array $patron = null)
    {
        $id = (string)$id;

        if ($json = $this->config['StaticHoldings'][$id] ?? null) {
            foreach (json_decode($json, true) as $i => $status) {
                if ($status['use_status_class'] ?? false) {
                    $availability = $status['availability'] ?? false;
                    if ($status['use_unknown_message'] ?? false) {
                        $availability = AvailabilityStatusInterface::STATUS_UNKNOWN;
                    }
                    $status['availability'] = new AvailabilityStatus(
                        $availability,
                        $status['status'] ?? '',
                        $status['extraStatusInformation'] ?? []
                    );
                    unset($status['status']);
                    unset($status['use_unknown_message']);
                }
                $this->setStatus($id, $status, $i > 0, $patron);
            }
        }

        // Do we have a fake status persisted in the session?
        $session = $this->getSession($patron['id'] ?? null);
        if (isset($session->statuses[$id])) {
            return $session->statuses[$id];
        }

        // Create fake entries for a random number of items
        $holding = [];
        $records = rand() % 15;
        for ($i = 1; $i <= $records; $i++) {
            $holding[] = $this->setStatus($id, [], true, $patron);
        }
        return $holding;
    }

    /**
     * Set Status
     *
     * @param string $id      id for record
     * @param array  $holding associative array with options to specify
     *      number, barcode, availability, status, location,
     *      reserve, callnumber, duedate, is_holdable, and addLink
     * @param bool   $append  add another record or replace current record
     * @param array  $patron  Patron data
     *
     * @return array
     */
    protected function setStatus(string $id, $holding = [], $append = true, $patron = null)
    {
        $session = $this->getSession($patron['id'] ?? null);
        $i = isset($session->statuses[$id])
            ? count($session->statuses[$id]) + 1 : 1;
        $holding = array_merge($this->getRandomHolding($id, $i, $patron), $holding);

        // if statuses is already stored
        if ($session->statuses) {
            // and this id is part of it
            if ($append && isset($session->statuses[$id])) {
                // add to the array
                $session->statuses[$id][] = $holding;
            } else {
                // if we're over-writing or if there's nothing stored for this id
                $session->statuses[$id] = [$holding];
            }
        } else {
            // brand new status storage!
            $session->statuses = [$id => [$holding]];
        }
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $this->checkIntermittentFailure();

        if ($this->isFailing(__METHOD__, 0)) {
            return array_map(
                function ($id) {
                    return [
                        [
                            'id' => $id,
                            'error' => 'Simulated failure',
                        ],
                    ];
                },
                $ids
            );
        }

        return array_map([$this, 'getStatus'], $ids);
    }

    /**
     * Generate random notes and summary for inclusion in a status/holding array.
     *
     * @param int $itemNum Number of item having notes generated
     *
     * @return array
     */
    protected function getNotesAndSummary(int $itemNum): array
    {
        $noteCount = rand(1, 3);
        $fields = ['holdings_notes' => [], 'item_notes' => [], 'summary' => []];
        for ($j = 1; $j <= $noteCount; $j++) {
            $fields['holdings_notes'][] = "Item $itemNum holdings note $j"
                . ($j === 1 ? ' https://vufind.org/?f=1&b=2#sample_link' : '');
            $fields['item_notes'][] = "Item $itemNum note $j";
        }
        $summCount = rand(1, 3);
        for ($j = 1; $j <= $summCount; $j++) {
            $fields['summary'][] = "Item $itemNum summary $j";
        }
        return $fields;
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
     * @return array On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        $this->checkIntermittentFailure();

        if ($this->isFailing(__METHOD__, 0)) {
            return [
                'id' => $id,
                'error' => 'Simulated failure',
            ];
        }

        // Get basic status info:
        $status = $this->getSimulatedStatus($id, $patron);

        $issue = 1;
        // Add notes and summary:
        foreach (array_keys($status) as $i) {
            $itemNum = $i + 1;
            $status[$i] += $this->getNotesAndSummary($itemNum);
            $volume = intdiv($issue, 4) + 1;
            $seriesIssue = $issue % 4;
            $issue = $issue + 1;
            $status[$i]['enumchron'] = "volume $volume, issue $seriesIssue";
            if (rand(1, 100) <= ($this->config['Holdings']['boundWithProbability'] ?? 25)) {
                $status[$i]['bound_with_records'] = [];
                $boundWithCount = 3;
                for ($j = 0; $j < $boundWithCount; $j++) {
                    $randomRecord = array_combine(['bibId', 'title'], $this->getRandomBibIdAndTitle());
                    $status[$i]['bound_with_records'][] = $randomRecord;
                }
                $boundWithIndex = rand(0, $boundWithCount + 1);
                array_splice($status[$i]['bound_with_records'], $boundWithIndex, 0, [
                    [
                        'title' => 'The Title on This Page',
                        'bibId' => $id,
                    ],
                ]);
            }
        }

        // Filter out electronic holdings from the normal holdings list:
        $status = array_filter(
            $status,
            function ($a) {
                return !($a['__electronic__'] ?? false);
            }
        );

        // Slice out a chunk if pagination is enabled.
        $slice = null;
        if ($options['itemLimit'] ?? null) {
            // For sensible pagination, we need to sort by location:
            $callback = function ($a, $b) {
                return $this->getSorter()->compare($a['location'], $b['location']);
            };
            usort($status, $callback);
            $slice = array_slice(
                $status,
                $options['offset'] ?? 0,
                $options['itemLimit']
            );
        }

        // Electronic holdings:
        $statuses = $this->getStatus($id);
        $electronic = [];
        foreach ($statuses as $item) {
            if ($item['__electronic__'] ?? false) {
                // Don't expose internal __electronic__ flag upstream:
                unset($item['__electronic__']);
                $electronic[] = $item;
            }
        }

        // Send back final value:
        return [
            'total' => count($status),
            'holdings' => $slice ?: $status,
            'electronic_holdings' => $electronic,
        ];
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        $this->checkIntermittentFailure();
        $issues = rand(0, 3);
        $retval = [];
        for ($i = 0; $i < $issues; $i++) {
            $retval[] = ['issue' => 'issue ' . ($i + 1)];
        }
        return $retval;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @throws ILSException
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $this->checkIntermittentFailure();

        $user = [
            'id'           => trim($username),
            'firstname'    => 'Lib',
            'lastname'     => 'Rarian',
            'cat_username' => trim($username),
            'cat_password' => trim($password),
            'email'        => 'Lib.Rarian@library.not',
            'major'        => null,
            'college'      => null,
        ];

        $loginMethod = $this->config['Catalog']['loginMethod'] ?? 'password';
        if ('email' === $loginMethod) {
            $user['email'] = $username;
            $user['cat_password'] = '';
            return $user;
        }

        if (isset($this->config['Users'])) {
            if (
                !isset($this->config['Users'][$username])
                || $password !== $this->config['Users'][$username]
            ) {
                return null;
            }
        }

        return $user;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $this->checkIntermittentFailure();
        $age = rand(13, 113);
        $birthDate = new \DateTime();
        $birthDate->sub(new \DateInterval("P{$age}Y"));
        $patron = [
            'firstname'       => 'Lib-' . $patron['cat_username'],
            'lastname'        => 'Rarian',
            'address1'        => 'Somewhere...',
            'address2'        => 'Over the Rainbow',
            'zip'             => '12345',
            'city'            => 'City',
            'country'         => 'Country',
            'phone'           => '1900 CALL ME',
            'mobile_phone'    => '1234567890',
            'group'           => 'Library Staff',
            'expiration_date' => 'Someday',
            'birthdate'       => $birthDate->format('Y-m-d'),
        ];
        return $patron;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyFines($patron)
    {
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (!isset($session->fines)) {
            // How many items are there? %20 - 2 = 10% chance of none,
            // 90% of 1-18 (give or take some odd maths)
            $fines = rand() % 20 - 2;

            $fineList = [];
            for ($i = 0; $i < $fines; $i++) {
                // How many days overdue is the item?
                $day_overdue = rand() % 30 + 5;
                // Calculate checkout date:
                $checkout = strtotime('now - ' . ($day_overdue + 14) . ' days');
                // 1 in 10 chance of this being a "Manual Fee":
                if (rand(1, 10) === 1) {
                    $fine = 2.50;
                    $type = 'Manual Fee';
                } else {
                    // 50c a day fine
                    $fine = $day_overdue * 0.50;
                    // After 20 days it becomes 'Long Overdue'
                    $type = $day_overdue > 20 ? 'Long Overdue' : 'Overdue';
                }

                $fineList[] = [
                    'amount'   => $fine * 100,
                    'checkout' => $this->dateConverter
                        ->convertToDisplayDate('U', $checkout),
                    'createdate' => $this->dateConverter
                        ->convertToDisplayDate('U', time()),
                    'fine'     => $type,
                    // Additional description for long overdue fines:
                    'description' => 'Manual Fee' === $type ? 'Interlibrary loan request fee' : '',
                    // 50% chance they've paid half of it
                    'balance'  => (rand() % 100 > 49 ? $fine / 2 : $fine) * 100,
                    'duedate'  => $this->dateConverter->convertToDisplayDate(
                        'U',
                        strtotime("now - $day_overdue days")
                    ),
                ];
                // Some fines will have no id or title:
                if (rand() % 3 != 1) {
                    if ($this->idsInMyResearch) {
                        [$fineList[$i]['id'], $fineList[$i]['title']]
                            = $this->getRandomBibIdAndTitle();
                        $fineList[$i]['source'] = $this->getRecordSource();
                    } else {
                        $fineList[$i]['title'] = 'Demo Title ' . $i;
                    }
                }
            }
            $session->fines = $fineList;
        }
        return $session->fines;
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
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (!isset($session->holds)) {
            $session->holds = $this->createRequestList('Holds');
        }
        return $session->holds;
    }

    /**
     * Get Patron Storage Retrieval Requests
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (!isset($session->storageRetrievalRequests)) {
            $session->storageRetrievalRequests
                = $this->createRequestList('StorageRetrievalRequests');
        }
        return $session->storageRetrievalRequests;
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all ILL requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's ILL requests
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyILLRequests($patron)
    {
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (!isset($session->ILLRequests)) {
            $session->ILLRequests = $this->createRequestList('ILLRequests');
        }
        return $session->ILLRequests;
    }

    /**
     * Construct a transaction list for getMyTransactions; may be random or
     * pre-set depending on Demo.ini settings.
     *
     * @return array
     */
    protected function getTransactionList()
    {
        $this->checkIntermittentFailure();
        // If Demo.ini includes a fixed set of transactions, load those; otherwise
        // build some random ones.
        return isset($this->config['Records']['transactions'])
            ? json_decode($this->config['Records']['transactions'], true)
            : $this->getRandomTransactionList();
    }

    /**
     * Calculate the due status for a due date.
     *
     * @param int $due Due date as Unix timestamp
     *
     * @return string
     */
    protected function calculateDueStatus($due)
    {
        $dueRelative = $due - time();
        if ($dueRelative < 0) {
            return 'overdue';
        } elseif ($dueRelative < 24 * 60 * 60) {
            return 'due';
        }
        return false;
    }

    /**
     * Construct a random set of transactions for getMyTransactions().
     *
     * @return array
     */
    protected function getRandomTransactionList()
    {
        // How many items are there?  %10 - 1 = 10% chance of none,
        // 90% of 1-9 (give or take some odd maths)
        $trans = rand() % 10 - 1;

        $transList = [];
        for ($i = 0; $i < $trans; $i++) {
            // When is it due? +/- up to 15 days
            $due_relative = rand() % 30 - 15;
            // Due date
            $rawDueDate = strtotime(
                'now ' . ($due_relative >= 0 ? '+' : '') . $due_relative . ' days'
            );

            // Times renewed    : 0,0,0,0,0,1,2,3,4,5
            $renew = rand() % 10 - 5;
            if ($renew < 0) {
                $renew = 0;
            }

            // Renewal limit
            $renewLimit = $renew + rand() % 3;

            // Pending requests : 0,0,0,0,0,1,2,3,4,5
            $req = rand() % 10 - 5;
            if ($req < 0) {
                $req = 0;
            }

            // Create a generic transaction:
            $transList[] = $this->getRandomItemIdentifier() + [
                // maintain separate display vs. raw due dates (the raw
                // one is used for renewals, in case the user display
                // format is incompatible with date math).
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    $rawDueDate
                ),
                'rawduedate' => $rawDueDate,
                'dueStatus' => $this->calculateDueStatus($rawDueDate),
                'barcode' => sprintf('%08d', rand() % 50000),
                'renew'   => $renew,
                'renewLimit' => $renewLimit,
                'request' => $req,
                'item_id' => $i,
                'renewable' => $renew < $renewLimit,
            ];
            if ($i == 2 || rand() % 5 == 1) {
                // Mimic an ILL loan
                $transList[$i] += [
                    'id'      => "ill_institution_$i",
                    'title'   => "ILL Loan Title $i",
                    'institution_id' => 'ill_institution',
                    'institution_name' => 'ILL Library',
                    'institution_dbkey' => 'ill_institution',
                    'borrowingLocation' => 'ILL Service Desk',
                ];
            } else {
                $transList[$i]['borrowingLocation'] = $this->getFakeLoc();
                if ($this->idsInMyResearch) {
                    [$transList[$i]['id'], $transList[$i]['title']]
                        = $this->getRandomBibIdAndTitle();
                    $transList[$i]['source'] = $this->getRecordSource();
                } else {
                    $transList[$i]['title'] = 'Demo Title ' . $i;
                }
            }
        }
        return $transList;
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
     * @return mixed        Array of the patron's transactions on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyTransactions($patron, $params = [])
    {
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (!isset($session->transactions)) {
            $session->transactions = $this->getTransactionList();
        }
        // Order
        $transactions = $session->transactions;
        if (!empty($params['sort'])) {
            $sort = explode(
                ' ',
                !empty($params['sort']) ? $params['sort'] : 'date_due desc',
                2
            );

            $descending = isset($sort[1]) && 'desc' === $sort[1];

            usort(
                $transactions,
                function ($a, $b) use ($sort, $descending) {
                    if ('title' === $sort[0]) {
                        $cmp = $this->getSorter()->compare(
                            $a['title'] ?? '',
                            $b['title'] ?? ''
                        );
                    } else {
                        $cmp = $a['rawduedate'] - $b['rawduedate'];
                    }
                    return $descending ? -$cmp : $cmp;
                }
            );
        }

        if (isset($params['limit'])) {
            $limit = $params['limit'] ?? 50;
            $offset = isset($params['page']) ? ($params['page'] - 1) * $limit : 0;
            $transactions = array_slice($transactions, $offset, $limit);
        }

        return [
            'count' => count($session->transactions),
            'records' => $transactions,
        ];
    }

    /**
     * Construct a historic transaction list for getMyTransactionHistory; may be
     * random or pre-set depending on Demo.ini settings.
     *
     * @return array
     */
    protected function getHistoricTransactionList()
    {
        $this->checkIntermittentFailure();
        // If Demo.ini includes a fixed set of transactions, load those; otherwise
        // build some random ones.
        return isset($this->config['Records']['historicTransactions'])
            ? json_decode($this->config['Records']['historicTransactions'], true)
            : $this->getRandomHistoricTransactionList();
    }

    /**
     * Construct a random set of transactions for getMyTransactionHistory().
     *
     * @return array
     */
    protected function getRandomHistoricTransactionList()
    {
        // How many items are there?  %10 - 1 = 10% chance of none,
        // 90% of 1-150 (give or take some odd maths)
        $trans = rand() % 10 - 1 > 0 ? rand() % 15 : 0;

        $transList = [];
        for ($i = 0; $i < $trans; $i++) {
            // Checkout date
            $relative = rand() % 300;
            $checkoutDate = strtotime("now -$relative days");
            // Due date (7-30 days from checkout)
            $dueDate = $checkoutDate + 60 * 60 * 24 * (rand() % 23 + 7);
            // Return date (1-40 days from checkout and < now)
            $returnDate = min(
                [$checkoutDate + 60 * 60 * 24 * (rand() % 39 + 1), time()]
            );

            // Create a generic transaction:
            $transList[] = $this->getRandomItemIdentifier() + [
                'checkoutDate' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    $checkoutDate
                ),
                'dueDate' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    $dueDate
                ),
                'returnDate' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    $returnDate
                ),
                // Raw dates for sorting
                '_checkoutDate' => $checkoutDate,
                '_dueDate' => $dueDate,
                '_returnDate' => $returnDate,
                'barcode' => sprintf('%08d', rand() % 50000),
                'row_id' => $i,
            ];
            if ($this->idsInMyResearch) {
                [$transList[$i]['id'], $transList[$i]['title']]
                    = $this->getRandomBibIdAndTitle();
                $transList[$i]['source'] = $this->getRecordSource();
            } else {
                $transList[$i]['title'] = 'Demo Title ' . $i;
            }
        }
        return $transList;
    }

    /**
     * Get Patron Loan History
     *
     * This is responsible for retrieving all historic transactions for a specific
     * patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @return mixed        Array of the patron's historic transactions on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyTransactionHistory($patron, $params)
    {
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (!isset($session->historicLoans)) {
            $session->historicLoans = $this->getHistoricTransactionList();
        }

        // Sort and splice the list
        $historicLoans = $session->historicLoans;
        if (isset($params['sort'])) {
            switch ($params['sort']) {
                case 'checkout asc':
                    $sorter = function ($a, $b) {
                        return strcmp($a['_checkoutDate'], $b['_checkoutDate']);
                    };
                    break;
                case 'return desc':
                    $sorter = function ($a, $b) {
                        return strcmp($b['_returnDate'], $a['_returnDate']);
                    };
                    break;
                case 'return asc':
                    $sorter = function ($a, $b) {
                        return strcmp($a['_returnDate'], $b['_returnDate']);
                    };
                    break;
                case 'due desc':
                    $sorter = function ($a, $b) {
                        return strcmp($b['_dueDate'], $a['_dueDate']);
                    };
                    break;
                case 'due asc':
                    $sorter = function ($a, $b) {
                        return strcmp($a['_dueDate'], $b['_dueDate']);
                    };
                    break;
                default:
                    $sorter = function ($a, $b) {
                        return strcmp($b['_checkoutDate'], $a['_checkoutDate']);
                    };
                    break;
            }

            usort($historicLoans, $sorter);
        }

        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
        $start = isset($params['page'])
            ? ((int)$params['page'] - 1) * $limit : 0;

        $historicLoans = array_splice($historicLoans, $start, $limit);

        return [
            'count' => count($session->historicLoans),
            'transactions' => $historicLoans,
        ];
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
        $this->checkIntermittentFailure();
        $session = $this->getSession($patron['id'] ?? null);
        if (null === $ids) {
            $session->historicLoans = [];
            $status = 'loan_history_all_purged';
        } else {
            $session->historicLoans = array_filter(
                $session->historicLoans ?? [],
                function ($loan) use ($ids) {
                    return !in_array($loan['row_id'], $ids);
                }
            );
            $status = 'loan_history_selected_purged';
        }
        return [
            'success' => true,
            'status' => $status,
            'sys_message' => '',
        ];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
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
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $this->checkIntermittentFailure();
        $result = $this->locations;
        if (($holdDetails['reqnum'] ?? '') == 1) {
            $result[] = [
                'locationID' => 'D',
                'locationDisplay' => 'Campus D',
            ];
        }

        if (isset($this->config['Holds']['excludePickupLocations'])) {
            $excluded
                = explode(':', $this->config['Holds']['excludePickupLocations']);
            $result = array_filter(
                $result,
                function ($loc) use ($excluded) {
                    return !in_array($loc['locationID'], $excluded);
                }
            );
        }

        return $result;
    }

    /**
     * Get Default "Hold Required By" Date (as Unix timestamp) or null if unsupported
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Contains most of the same values passed to
     * placeHold, minus the patron data.
     *
     * @return int|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldDefaultRequiredDate($patron, $holdInfo)
    {
        $this->checkIntermittentFailure();
        // 5 years in the future (but similate intermittent failure):
        return !$this->isFailing(__METHOD__, 50)
            ? mktime(0, 0, 0, date('m'), date('d'), date('Y') + 5) : null;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in HorizonXMLAPI.ini
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
        $this->checkIntermittentFailure();
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return false|string      The default request group for the patron.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        $this->checkIntermittentFailure();
        if ($this->isFailing(__METHOD__, 50)) {
            return false;
        }
        $requestGroups = $this->getRequestGroups(0, 0);
        return $requestGroups[0]['id'];
    }

    /**
     * Get request groups
     *
     * @param int   $bibId       BIB ID
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestGroups(
        $bibId = null,
        $patron = null,
        $holdDetails = null
    ) {
        $this->checkIntermittentFailure();
        return [
            [
                'id' => 1,
                'name' => 'Main Library',
            ],
            [
                'id' => 2,
                'name' => 'Branch Library',
            ],
        ];
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        $this->checkIntermittentFailure();
        return ['Fund A', 'Fund B', 'Fund C'];
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        $this->checkIntermittentFailure();
        return $this->departments;
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        $this->checkIntermittentFailure();
        return $this->instructors;
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        $this->checkIntermittentFailure();
        return $this->courses;
    }

    /**
     * Get a set of random bib IDs
     *
     * @param int $limit Maximum number of IDs to return (max 30)
     *
     * @return string[]
     */
    protected function getRandomBibIds($limit): array
    {
        $count = rand(0, $limit > 30 ? 30 : $limit);
        $results = [];
        for ($x = 0; $x < $count; $x++) {
            $randomId = $this->getRandomBibId();

            // avoid duplicate entries in array:
            if (!in_array($randomId, $results)) {
                $results[] = $randomId;
            }
        }
        return $results;
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
        $this->checkIntermittentFailure();
        // Pick a random number of results to return -- don't exceed limit or 30,
        // whichever is smaller (this can be pretty slow due to the random ID code).
        $results = $this->config['Records']['new_items']
            ?? $this->getRandomBibIds(30);
        $retVal = ['count' => count($results), 'results' => []];
        foreach ($results as $result) {
            $retVal['results'][] = ['id' => $result];
        }
        return $retVal;
    }

    /**
     * Determine a course ID for findReserves.
     *
     * @param string $course Course ID (or empty for a random choice)
     *
     * @return string
     */
    protected function getCourseId(string $course = ''): string
    {
        return empty($course) ? (string)rand(0, count($this->courses) - 1) : $course;
    }

    /**
     * Determine a department ID for findReserves.
     *
     * @param string $dept Department ID (or empty for a random choice)
     *
     * @return string
     */
    protected function getDepartmentId(string $dept = ''): string
    {
        return empty($dept) ? (string)rand(0, count($this->departments) - 1) : $dept;
    }

    /**
     * Determine an instructor ID for findReserves.
     *
     * @param string $inst Instructor ID (or empty for a random choice)
     *
     * @return string
     */
    protected function getInstructorId(string $inst = ''): string
    {
        return empty($inst) ? (string)rand(0, count($this->instructors) - 1) : $inst;
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
        $this->checkIntermittentFailure();
        // Pick a random number of results to return -- don't exceed 30.
        $count = rand(0, 30);
        $results = [];
        for ($x = 0; $x < $count; $x++) {
            $randomId = $this->getRandomBibId();

            // avoid duplicate entries in array:
            if (!in_array($randomId, $results)) {
                $results[] = $randomId;
            }
        }

        $retVal = [];
        foreach ($results as $current) {
            $retVal[] = [
                'BIB_ID' => $current,
                'INSTRUCTOR_ID' => $this->getInstructorId($inst),
                'COURSE_ID' => $this->getCourseId($course),
                'DEPARTMENT_ID' => $this->getDepartmentId($dept),
            ];
        }
        return $retVal;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item.
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $this->checkIntermittentFailure();
        // Rewrite the holds in the session, removing those the user wants to
        // cancel.
        $newHolds = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        $session = $this->getSession($cancelDetails['patron']['id'] ?? null);
        foreach ($session->holds as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newHolds->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'hold_cancel_success',
                    ];
                } else {
                    $newHolds->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.',
                    ];
                }
            }
        }

        $session->holds = $newHolds;
        return $retVal;
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
        $session = $this->getSession($patron['id']);
        foreach ($session->holds as &$currentHold) {
            if (
                !isset($currentHold['updateDetails'])
                || !in_array($currentHold['updateDetails'], $holdsDetails)
            ) {
                continue;
            }
            if ($this->isFailing(__METHOD__, 25)) {
                $results[$currentHold['reqnum']]['success'] = false;
                $results[$currentHold['reqnum']]['status']
                    = 'Simulated error; try again and it will work eventually.';
                continue;
            }
            if (array_key_exists('frozen', $fields)) {
                if ($fields['frozen']) {
                    $currentHold['frozen'] = true;
                    if (isset($fields['frozenThrough'])) {
                        $currentHold['frozenThrough'] = $this->dateConverter
                            ->convertToDisplayDate('U', $fields['frozenThroughTS']);
                    } else {
                        $currentHold['frozenThrough'] = '';
                    }
                } else {
                    $currentHold['frozen'] = false;
                    $currentHold['frozenThrough'] = '';
                }
            }
            if (isset($fields['pickUpLocation'])) {
                $currentHold['location'] = $fields['pickUpLocation'];
            }
            $results[$currentHold['reqnum']]['success'] = true;
        }

        return $results;
    }

    /**
     * Cancel Storage Retrieval Request
     *
     * Attempts to Cancel a Storage Retrieval Request on a particular item. The
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
        $this->checkIntermittentFailure();
        // Rewrite the items in the session, removing those the user wants to
        // cancel.
        $newRequests = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        $session = $this->getSession($cancelDetails['patron']['id'] ?? null);
        foreach ($session->storageRetrievalRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'storage_retrieval_request_cancel_success',
                    ];
                } else {
                    $newRequests->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'storage_retrieval_request_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.',
                    ];
                }
            }
        }

        $session->storageRetrievalRequests = $newRequests;
        return $retVal;
    }

    /**
     * Get Cancel Storage Retrieval Request Details
     *
     * In order to cancel a hold, Voyager requires the patron details an item ID
     * and a recall ID. This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.
     *
     * @param array $request An array of request data
     * @param array $patron  Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelStorageRetrievalRequestDetails($request, $patron)
    {
        return $request['reqnum'];
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
        $this->checkIntermittentFailure();
        // Simulate an account block at random.
        if ($this->checkRenewBlock()) {
            return [
                'blocks' => [
                    'Simulated account block; try again and it will work eventually.',
                ],
                'details' => [],
            ];
        }

        // Set up successful return value.
        $finalResult = ['blocks' => false, 'details' => []];

        // Grab transactions from session so we can modify them:
        $session = $this->getSession($renewDetails['patron']['id'] ?? null);
        $transactions = $session->transactions;
        foreach ($transactions as $i => $current) {
            // Only renew requested items:
            if (in_array($current['item_id'], $renewDetails['details'])) {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $transactions[$i]['rawduedate'] += 21 * 24 * 60 * 60;
                    $transactions[$i]['dueStatus']
                        = $this->calculateDueStatus($transactions[$i]['rawduedate']);
                    $transactions[$i]['duedate']
                        = $this->dateConverter->convertToDisplayDate(
                            'U',
                            $transactions[$i]['rawduedate']
                        );
                    $transactions[$i]['renew'] = $transactions[$i]['renew'] + 1;
                    $transactions[$i]['renewable']
                        = $transactions[$i]['renew']
                        < $transactions[$i]['renewLimit'];

                    $finalResult['details'][$current['item_id']] = [
                        'success' => true,
                        'new_date' => $transactions[$i]['duedate'],
                        'new_time' => '',
                        'item_id' => $current['item_id'],
                    ];
                } else {
                    $finalResult['details'][$current['item_id']] = [
                        'success' => false,
                        'new_date' => false,
                        'item_id' => $current['item_id'],
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.',
                    ];
                }
            }
        }

        // Write modified transactions back to session; in-place changes do not
        // work due to ArrayObject eccentricities:
        $session->transactions = $transactions;

        return $finalResult;
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, Voyager requires the patron details and an item
     * id. This function returns the item id as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['item_id'];
    }

    /**
     * Check if hold or recall available
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $this->checkIntermittentFailure();
        if ($this->isFailing(__METHOD__, 10)) {
            return [
                'valid' => false,
                'status' => rand() % 3 != 0
                    ? 'hold_error_blocked' : 'Demonstrating a custom failure',
            ];
        }
        return [
            'valid' => true,
            'status' => 'request_place_text',
        ];
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details.
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $this->checkIntermittentFailure();
        // Simulate failure:
        if ($this->isFailing(__METHOD__, 50)) {
            return [
                'success' => false,
                'sysMessage' =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.',
            ];
        }

        $session = $this->getSession($holdDetails['patron']['id'] ?? null);
        if (!isset($session->holds)) {
            $session->holds = new ArrayObject();
        }
        $lastHold = count($session->holds) - 1;
        $nextId = $lastHold >= 0
            ? $session->holds[$lastHold]['item_id'] + 1 : 0;

        // Figure out appropriate expiration date:
        $expire = !empty($holdDetails['requiredByTS'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d',
                gmdate('Y-m-d', $holdDetails['requiredByTS'])
            ) : null;

        $requestGroup = '';
        foreach ($this->getRequestGroups(null, null) as $group) {
            if (
                isset($holdDetails['requestGroupId'])
                && $group['id'] == $holdDetails['requestGroupId']
            ) {
                $requestGroup = $group['name'];
                break;
            }
        }
        if ($holdDetails['startDateTS']) {
            // Suspend until the previous day:
            $frozen = true;
            $frozenThrough = $this->dateConverter->convertToDisplayDate(
                'U',
                \DateTime::createFromFormat(
                    'U',
                    $holdDetails['startDateTS']
                )->modify('-1 DAY')->getTimestamp()
            );
        } else {
            $frozen = false;
            $frozenThrough = '';
        }
        $reqNum = sprintf('%06d', $nextId);
        $proxiedFor = null;
        if (!empty($holdDetails['proxiedUser'])) {
            $proxies = $this->getProxiedUsers($holdDetails['patron']);
            $proxiedFor = $proxies[$holdDetails['proxiedUser']];
        }
        $session->holds->append(
            [
                'id'       => $holdDetails['id'],
                'source'   => $this->getRecordSource(),
                'location' => $holdDetails['pickUpLocation'],
                'expire'   => $expire,
                'create'   =>
                    $this->dateConverter->convertToDisplayDate('U', time()),
                'reqnum'   => $reqNum,
                'item_id'  => $nextId,
                'volume'   => '',
                'processed' => '',
                'requestGroup' => $requestGroup,
                'frozen'   => $frozen,
                'frozenThrough' => $frozenThrough,
                'updateDetails' => $reqNum,
                'cancel_details' => $reqNum,
                'proxiedFor' => $proxiedFor,
            ]
        );

        return ['success' => true];
    }

    /**
     * Check if storage retrieval request available
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        $this->checkIntermittentFailure();
        if (!$this->storageRetrievalRequests || $this->isFailing(__METHOD__, 10)) {
            return [
                'valid' => false,
                'status' => rand() % 3 != 0
                    ? 'storage_retrieval_request_error_blocked'
                    : 'Demonstrating a custom failure',
            ];
        }
        return [
            'valid' => true,
            'status' => 'storage_retrieval_request_place_text',
        ];
    }

    /**
     * Place a Storage Retrieval Request
     *
     * Attempts to place a request on a particular item and returns
     * an array with result details.
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeStorageRetrievalRequest($details)
    {
        $this->checkIntermittentFailure();
        if (!$this->storageRetrievalRequests) {
            return [
                'success' => false,
                'sysMessage' => 'Storage Retrieval Requests are disabled.',
            ];
        }

        // Make sure pickup location is valid
        $pickUpLocation = $details['pickUpLocation'] ?? null;
        $validLocations = array_column($this->getPickUpLocations(), 'locationID');
        if (
            null !== $pickUpLocation
            && !in_array($pickUpLocation, $validLocations)
        ) {
            return [
                'success' => false,
                'sysMessage' => 'storage_retrieval_request_invalid_pickup',
            ];
        }

        // Simulate failure:
        if ($this->isFailing(__METHOD__, 50)) {
            return [
                'success' => false,
                'sysMessage' =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.',
            ];
        }

        $session = $this->getSession($details['patron']['id'] ?? null);
        if (!isset($session->storageRetrievalRequests)) {
            $session->storageRetrievalRequests = new ArrayObject();
        }
        $lastRequest = count($session->storageRetrievalRequests) - 1;
        $nextId = $lastRequest >= 0
            ? $session->storageRetrievalRequests[$lastRequest]['item_id'] + 1
            : 0;

        // Figure out appropriate expiration date:
        if (
            !isset($details['requiredBy'])
            || empty($details['requiredBy'])
        ) {
            $expire = strtotime('now + 30 days');
        } else {
            try {
                $expire = $this->dateConverter->convertFromDisplayDate(
                    'U',
                    $details['requiredBy']
                );
            } catch (DateException $e) {
                // Expiration date is invalid
                return [
                    'success' => false,
                    'sysMessage' => 'storage_retrieval_request_date_invalid',
                ];
            }
        }
        if ($expire <= time()) {
            return [
                'success' => false,
                'sysMessage' => 'storage_retrieval_request_date_past',
            ];
        }

        $session->storageRetrievalRequests->append(
            [
                'id'       => $details['id'],
                'source'   => $this->getRecordSource(),
                'location' => $details['pickUpLocation'],
                'expire'   =>
                    $this->dateConverter->convertToDisplayDate('U', $expire),
                'create'   =>
                    $this->dateConverter->convertToDisplayDate('U', time()),
                'processed' => rand() % 3 == 0
                    ? $this->dateConverter->convertToDisplayDate('U', $expire) : '',
                'reqnum'   => sprintf('%06d', $nextId),
                'item_id'  => $nextId,
            ]
        );

        return ['success' => true];
    }

    /**
     * Check if ILL request available
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        $this->checkIntermittentFailure();
        if (!$this->ILLRequests || $this->isFailing(__METHOD__, 10)) {
            return [
                'valid' => false,
                'status' => rand() % 3 != 0
                    ? 'ill_request_error_blocked' : 'Demonstrating a custom failure',
            ];
        }
        return [
            'valid' => true,
            'status' => 'ill_request_place_text',
        ];
    }

    /**
     * Place ILL Request
     *
     * Attempts to place an ILL request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeILLRequest($details)
    {
        $this->checkIntermittentFailure();
        if (!$this->ILLRequests) {
            return [
                'success' => false,
                'sysMessage' => 'ILL requests are disabled.',
            ];
        }
        // Simulate failure:
        if ($this->isFailing(__METHOD__, 50)) {
            return [
                'success' => false,
                'sysMessage' =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.',
            ];
        }

        $session = $this->getSession($details['patron']['id'] ?? null);
        if (!isset($session->ILLRequests)) {
            $session->ILLRequests = new ArrayObject();
        }
        $lastRequest = count($session->ILLRequests) - 1;
        $nextId = $lastRequest >= 0
            ? $session->ILLRequests[$lastRequest]['item_id'] + 1
            : 0;

        // Figure out appropriate expiration date:
        if (
            !isset($details['requiredBy'])
            || empty($details['requiredBy'])
        ) {
            $expire = strtotime('now + 30 days');
        } else {
            try {
                $expire = $this->dateConverter->convertFromDisplayDate(
                    'U',
                    $details['requiredBy']
                );
            } catch (DateException $e) {
                // Expiration Date is invalid
                return [
                    'success' => false,
                    'sysMessage' => 'ill_request_date_invalid',
                ];
            }
        }
        if ($expire <= time()) {
            return [
                'success' => false,
                'sysMessage' => 'ill_request_date_past',
            ];
        }

        // Verify pickup library and location
        $pickupLocation = '';
        $pickupLocations = $this->getILLPickupLocations(
            $details['id'],
            $details['pickUpLibrary'],
            $details['patron']
        );
        foreach ($pickupLocations as $location) {
            if ($location['id'] == $details['pickUpLibraryLocation']) {
                $pickupLocation = $location['name'];
                break;
            }
        }
        if (!$pickupLocation) {
            return [
                'success' => false,
                'sysMessage' => 'ill_request_place_fail_missing',
            ];
        }

        $session->ILLRequests->append(
            [
                'id'       => $details['id'],
                'source'   => $this->getRecordSource(),
                'location' => $pickupLocation,
                'expire'   =>
                    $this->dateConverter->convertToDisplayDate('U', $expire),
                'create'   =>
                    $this->dateConverter->convertToDisplayDate('U', time()),
                'processed' => rand() % 3 == 0
                    ? $this->dateConverter->convertToDisplayDate('U', $expire) : '',
                'reqnum'   => sprintf('%06d', $nextId),
                'item_id'  => $nextId,
            ]
        );

        return ['success' => true];
    }

    /**
     * Get ILL Pickup Libraries
     *
     * This is responsible for getting information on the possible pickup libraries
     *
     * @param string $id     Record ID
     * @param array  $patron Patron
     *
     * @return bool|array False if request not allowed, or an array of associative
     * arrays with libraries.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getILLPickupLibraries($id, $patron)
    {
        $this->checkIntermittentFailure();
        if (!$this->ILLRequests) {
            return false;
        }

        $details = [
            [
                'id' => 1,
                'name' => 'Main Library',
                'isDefault' => true,
            ],
            [
                'id' => 2,
                'name' => 'Branch Library',
                'isDefault' => false,
            ],
        ];

        return $details;
    }

    /**
     * Get ILL Pickup Locations
     *
     * This is responsible for getting a list of possible pickup locations for a
     * library
     *
     * @param string $id        Record ID
     * @param string $pickupLib Pickup library ID
     * @param array  $patron    Patron
     *
     * @return bool|array False if request not allowed, or an array of locations.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        $this->checkIntermittentFailure();
        switch ($pickupLib) {
            case 1:
                return [
                    [
                        'id' => 1,
                        'name' => 'Circulation Desk',
                        'isDefault' => true,
                    ],
                    [
                        'id' => 2,
                        'name' => 'Reference Desk',
                        'isDefault' => false,
                    ],
                ];
            case 2:
                return [
                    [
                        'id' => 3,
                        'name' => 'Main Desk',
                        'isDefault' => false,
                    ],
                    [
                        'id' => 4,
                        'name' => 'Library Bus',
                        'isDefault' => true,
                    ],
                ];
        }
        return [];
    }

    /**
     * Cancel ILL Request
     *
     * Attempts to Cancel an ILL request on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelILLRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelILLRequests($cancelDetails)
    {
        $this->checkIntermittentFailure();
        // Rewrite the items in the session, removing those the user wants to
        // cancel.
        $newRequests = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        $session = $this->getSession($cancelDetails['patron']['id'] ?? null);
        foreach ($session->ILLRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'ill_request_cancel_success',
                    ];
                } else {
                    $newRequests->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'ill_request_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.',
                    ];
                }
            }
        }

        $session->ILLRequests = $newRequests;
        return $retVal;
    }

    /**
     * Get Cancel ILL Request Details
     *
     * @param array $request An array of request data
     * @param array $patron  Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelILLRequestDetails($request, $patron)
    {
        return $request['reqnum'];
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function changePassword($details)
    {
        $this->checkIntermittentFailure();
        if (!$this->isFailing(__METHOD__, 33)) {
            return ['success' => true, 'status' => 'change_password_ok'];
        }
        return [
            'success' => false,
            'status' => 'An error has occurred',
            'sysMessage' =>
                'Demonstrating failure; keep trying and it will work eventually.',
        ];
    }

    /**
     * Public Function which specifies renew, hold and cancel settings.
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
        $this->checkIntermittentFailure();
        if ($function == 'Holds') {
            return $this->config['Holds']
                ?? [
                    'HMACKeys' => 'id:item_id:level',
                    'extraHoldFields' =>
                        'comments:requestGroup:pickUpLocation:requiredByDate',
                    'defaultRequiredDate' => 'driver:0:2:0',
                ];
        }
        if ($function == 'Holdings') {
            return [
                'itemLimit' => $this->config['Holdings']['itemLimit'] ?? null,
            ];
        }
        if (
            $function == 'StorageRetrievalRequests'
            && $this->storageRetrievalRequests
        ) {
            return [
                'HMACKeys' => 'id',
                'extraFields' => 'comments:pickUpLocation:requiredByDate:item-issue',
                'helpText' => 'This is a storage retrieval request help text'
                    . ' with some <span style="color: red">styling</span>.',
            ];
        }
        if ($function == 'ILLRequests' && $this->ILLRequests) {
            return [
                'enabled' => true,
                'HMACKeys' => 'number',
                'extraFields' =>
                    'comments:pickUpLibrary:pickUpLibraryLocation:requiredByDate',
                'defaultRequiredDate' => '0:1:0',
                'helpText' => 'This is an ILL request help text'
                    . ' with some <span style="color: red">styling</span>.',
            ];
        }
        if ($function == 'changePassword') {
            return $this->config['changePassword']
                ?? ['minLength' => 4, 'maxLength' => 20];
        }
        if ($function == 'getMyTransactionHistory') {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }
            $config = [
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'return desc' => 'sort_return_date_desc',
                    'return asc' => 'sort_return_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                ],
                'default_sort' => 'checkout desc',
                'purge_all' => $this->config['TransactionHistory']['purgeAll'] ?? true,
                'purge_selected' => $this->config['TransactionHistory']['purgeSelected'] ?? true,
            ];
            if ($this->config['Loans']['paging'] ?? false) {
                $config['max_results']
                    = $this->config['Loans']['max_page_size'] ?? 100;
            }
            return $config;
        }
        if ('getMyTransactions' === $function) {
            if (empty($this->config['Loans']['paging'])) {
                return [];
            }
            return [
                'max_results' => $this->config['Loans']['max_page_size'] ?? 100,
                'sort' => [
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                    'title asc' => 'sort_title',
                ],
                'default_sort' => 'due asc',
            ];
        }
        if ($function == 'patronLogin') {
            return [
                'loginMethod'
                    => $this->config['Catalog']['loginMethod'] ?? 'password',
            ];
        }

        return [];
    }

    /**
     * Get bib records for recently returned items.
     *
     * @param int   $limit  Maximum number of records to retrieve (default = 30)
     * @param int   $maxage The maximum number of days to consider "recently
     * returned."
     * @param array $patron Patron Data
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRecentlyReturnedBibs(
        $limit = 30,
        $maxage = 30,
        $patron = null
    ) {
        $this->checkIntermittentFailure();

        $results = $this->config['Records']['recently_returned']
            ?? $this->getRandomBibIds($limit);
        $mapper = function ($id) {
            return ['id' => $id];
        };
        return array_map($mapper, $results);
    }

    /**
     * Get bib records for "trending" items (recently returned with high usage).
     *
     * @param int   $limit  Maximum number of records to retrieve (default = 30)
     * @param int   $maxage The maximum number of days' worth of data to examine.
     * @param array $patron Patron Data
     *
     * @return array
     */
    public function getTrendingBibs($limit = 30, $maxage = 30, $patron = null)
    {
        // This is similar to getRecentlyReturnedBibs for demo purposes.
        return $this->getRecentlyReturnedBibs($limit, $maxage, $patron);
    }

    /**
     * Get list of users for whom the provided patron is a proxy.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getProxiedUsers(array $patron): array
    {
        return $this->config['ProxiedUsers'] ?? [];
    }

    /**
     * Get list of users who act as proxies for the provided patron.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getProxyingUsers(array $patron): array
    {
        return $this->config['ProxyingUsers'] ?? [];
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
        if ($this->config['RecordLinks']['fakeOpacLink'] ?? false) {
            $links[] = [
                'url' => 'http://localhost/my-fake-ils?id=' . urlencode($id),
                'desc' => 'View in OPAC (fake demo link)',
            ];
        }
        return $links;
    }
}
