<?php
/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * Note that some sample values (holds, transactions, fines) are stored in
 * the session.  You can log out and log back in to get a different set of
 * values.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
use ArrayObject, VuFind\Exception\Date as DateException,
    VuFind\Exception\ILS as ILSException,
    VuFindSearch\Query\Query, VuFindSearch\Service as SearchService,
    Zend\Session\Container as SessionContainer;

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
class Demo extends AbstractBase
{
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
     * @var SessionContainer
     */
    protected $session = null;

    /**
     * Factory function for constructing the SessionContainer.
     *
     * @var Callable
     */
    protected $sessionFactory;

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
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter  Date converter object
     * @param SearchService          $ss             Search service
     * @param Callable               $sessionFactory Factory function returning
     * SessionContainer object
     * fake data to simulate consistency and reduce Solr hits
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        SearchService $ss, $sessionFactory
    ) {
        $this->dateConverter = $dateConverter;
        $this->searchService = $ss;
        if (!is_callable($sessionFactory)) {
            throw new \Exception('Invalid session factory passed to constructor.');
        }
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
        if (isset($this->config['Holdings'])) {
            foreach ($this->config['Holdings'] as $id => $json) {
                foreach (json_decode($json, true) as $i => $status) {
                    $this->setStatus($id, $status, $i > 0);
                }
            }
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
        $probability = isset($this->failureProbabilities[$key])
            ? $this->failureProbabilities[$key] : $default;
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
        $locations = $this->getPickUpLocations();
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
            ? (array) $this->config['Records']['services']
            : [];
        if (empty($services)) {
            return [];
        }

        // Make it more likely we have a single service than many:
        $count = rand(1, 5) == 1 ? rand(1, count($services)) : 1;
        $keys = (array) array_rand($services, $count);
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
            return "Missing";
        case  9:
            return "On Order";
        case  8:
            return "Invoiced";
        default:
            return "Available";
        }
    }

    /**
     * Generate a fake call number.
     *
     * @return string
     */
    protected function getFakeCallNum()
    {
        $codes = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $a = $codes[rand() % strlen($codes)];
        $b = rand() % 899 + 100;
        $c = rand() % 9999;
        return $a . $b . "." . $c;
    }

    /**
     * Get a random ID from the Solr index.
     *
     * @return string
     */
    protected function getRandomBibId()
    {
        $source = $this->getRecordSource();
        $query = isset($this->config['Records']['query'])
            ? $this->config['Records']['query'] : '*:*';
        $result = $this->searchService->random($source, new Query($query), 1);
        if (count($result) === 0) {
            throw new \Exception('Problem retrieving random record from $source.');
        }
        return current($result->getRecords())->getUniqueId();
    }

    /**
     * Get the name of the search backend providing records.
     *
     * @return string
     */
    protected function getRecordSource()
    {
        return isset($this->config['Records']['source'])
            ? $this->config['Records']['source'] : DEFAULT_SEARCH_BACKEND;
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
        return [
            'id'           => $id,
            'source'       => $this->getRecordSource(),
            'item_id'      => $number,
            'number'       => $number,
            'barcode'      => sprintf("%08d", rand() % 50000),
            'availability' => $status == 'Available',
            'status'       => $status,
            'location'     => $location,
            'locationhref' => $locationhref,
            'reserve'      => (rand() % 100 > 49) ? 'Y' : 'N',
            'callnumber'   => $this->getFakeCallNum(),
            'duedate'      => '',
            'is_holdable'  => true,
            'addLink'      => $patron ? true : false,
            'level'        => 'copy',
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => $patron ? 'check' : false,
            'ILLRequest'   => 'auto',
            'addILLRequestLink' => $patron ? 'check' : false,
            'services'     => $status == 'Available' ? $this->getFakeServices() : []
        ];
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
                "location" => $location,
                "create"   => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime("now - {$randDays} days")
                ),
                "expire"   => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime("now + 30 days")
                ),
                "reqnum"   => sprintf("%06d", $i),
                "item_id" => $i,
                "reqnum" => $i
            ];
            // Inject a random identifier of some sort:
            $currentItem += $this->getRandomItemIdentifier();
            if ($i == 2 || rand() % 5 == 1) {
                // Mimic an ILL request
                $currentItem["id"] = "ill_request_$i";
                $currentItem["title"] = "ILL Hold Title $i";
                $currentItem['institution_id'] = 'ill_institution';
                $currentItem['institution_name'] = 'ILL Library';
                $currentItem['institution_dbkey'] = 'ill_institution';
            } else {
                if ($this->idsInMyResearch) {
                    $currentItem['id'] = $this->getRandomBibId();
                    $currentItem['source'] = $this->getRecordSource();
                } else {
                    $currentItem['title'] = 'Demo Title ' . $i;
                }
            }

            if ($requestType == 'Holds') {
                $pos = rand() % 5;
                if ($pos > 1) {
                    $currentItem['position'] = $pos;
                } else {
                    $currentItem['available'] = true;
                }
                $pos = rand(0, count($requestGroups) - 1);
                $currentItem['requestGroup'] = $requestGroups[$pos]['name'];
            } else {
                $status = rand() % 5;
                $currentItem['available'] = $status == 1;
                $currentItem['canceled'] = $status == 2;
                $currentItem['processed'] = ($status == 1 || rand(1, 3) == 3)
                    ? $this->dateConverter->convertToDisplayDate('U', time())
                    : '';
                if ($requestType == 'ILLRequests') {
                    $transit = rand() % 2;
                    if (!$currentItem['available']
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
        return $this->getSimulatedStatus($id);
    }

    /**
     * Get the session container (constructing it on demand if not already present)
     *
     * @return SessionContainer
     */
    protected function getSession()
    {
        // SessionContainer not defined yet? Build it now:
        if (null === $this->session) {
            $factory = $this->sessionFactory;
            $this->session = $factory();
        }
        return $this->session;
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
     */
    protected function getSimulatedStatus($id, array $patron = null)
    {
        $id = (string) $id;

        // Do we have a fake status persisted in the session?
        $session = $this->getSession();
        if (isset($session->statuses[$id])) {
            return $session->statuses[$id];
        }

        // Create fake entries for a random number of items
        $holding = [];
        $records = rand() % 15;
        for ($i = 1; $i <= $records; $i++) {
            $holding[] = $this->getRandomHolding($id, $i, $patron);
        }
        return $holding;
    }

    /**
     * Set Status
     *
     * @param array $id      id for record
     * @param array $holding associative array with options to specify
     *      number, barcode, availability, status, location,
     *      reserve, callnumber, duedate, is_holdable, and addLink
     * @param bool  $append  add another record or replace current record
     *
     * @return array
     */
    protected function setStatus($id, $holding = [], $append = true)
    {
        $id = (string)$id;
        $session = $this->getSession();
        $i = isset($session->statuses[$id])
            ? count($session->statuses[$id]) + 1 : 1;
        $holding = array_merge($this->getRandomHolding($id, $i), $holding);

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
        return array_map([$this, 'getStatus'], $ids);
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
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $this->checkIntermittentFailure();

        // Get basic status info:
        $status = $this->getSimulatedStatus($id, $patron);

        // Add notes and summary:
        foreach (array_keys($status) as $i) {
            $itemNum = $i + 1;
            $noteCount = rand(1, 3);
            $status[$i]['holdings_notes'] = [];
            $status[$i]['item_notes'] = [];
            for ($j = 1; $j <= $noteCount; $j++) {
                $status[$i]['holdings_notes'][] = "Item $itemNum holdings note $j";
                $status[$i]['item_notes'][] = "Item $itemNum note $j";
            }
            $summCount = rand(1, 3);
            $status[$i]['summary'] = [];
            for ($j = 1; $j <= $summCount; $j++) {
                $status[$i]['summary'][] = "Item $itemNum summary $j";
            }
        }

        // Send back final value:
        return $status;
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
     * @param string $barcode  The patron barcode
     * @param string $password The patron password
     *
     * @throws ILSException
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $password)
    {
        $this->checkIntermittentFailure();
        if (isset($this->config['Users'])) {
            if (!isset($this->config['Users'][$barcode])
                || $password !== $this->config['Users'][$barcode]
            ) {
                return null;
            }
        }
        $user = [];

        $user['id']           = trim($barcode);
        $user['firstname']    = trim("Lib");
        $user['lastname']     = trim("Rarian");
        $user['cat_username'] = trim($barcode);
        $user['cat_password'] = trim($password);
        $user['email']        = trim("Lib.Rarian@library.not");
        $user['major']        = null;
        $user['college']      = null;

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
        $patron = [
            'firstname' => 'Lib-' . $patron['cat_username'],
            'lastname'  => 'Rarian',
            'address1'  => 'Somewhere...',
            'address2'  => 'Over the Rainbow',
            'zip'       => '12345',
            'city'      => 'City',
            'country'   => 'Country',
            'phone'     => '1900 CALL ME',
            'group'     => 'Library Staff'
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
        $session = $this->getSession();
        if (!isset($session->fines)) {
            // How many items are there? %20 - 2 = 10% chance of none,
            // 90% of 1-18 (give or take some odd maths)
            $fines = rand() % 20 - 2;

            $fineList = [];
            for ($i = 0; $i < $fines; $i++) {
                // How many days overdue is the item?
                $day_overdue = rand() % 30 + 5;
                // Calculate checkout date:
                $checkout = strtotime("now - " . ($day_overdue + 14) . " days");
                // 50c a day fine?
                $fine = $day_overdue * 0.50;

                $fineList[] = [
                    "amount"   => $fine * 100,
                    "checkout" => $this->dateConverter->convertToDisplayDate(
                        'U', $checkout
                    ),
                    // After 20 days it becomes 'Long Overdue'
                    "fine"     => $day_overdue > 20 ? "Long Overdue" : "Overdue",
                    // 50% chance they've paid half of it
                    "balance"  => (rand() % 100 > 49 ? $fine / 2 : $fine) * 100,
                    "duedate"  => $this->dateConverter->convertToDisplayDate(
                        'U', strtotime("now - $day_overdue days")
                    )
                ];
                // Some fines will have no id or title:
                if (rand() % 3 != 1) {
                    if ($this->idsInMyResearch) {
                        $fineList[$i]['id'] = $this->getRandomBibId();
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
        $session = $this->getSession();
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
        $session = $this->getSession();
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
        $session = $this->getSession();
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
            $dueStatus = false;
            if ($due_relative >= 0) {
                $rawDueDate = strtotime("now +$due_relative days");
                if ($due_relative == 0) {
                    $dueStatus = 'due';
                }
            } else {
                $rawDueDate = strtotime("now $due_relative days");
                $dueStatus = 'overdue';
            }

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
                    'U', $rawDueDate
                ),
                'rawduedate' => $rawDueDate,
                'dueStatus' => $dueStatus,
                'barcode' => sprintf("%08d", rand() % 50000),
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
                    'borrowingLocation' => 'ILL Service Desk'
                ];
            } else {
                $transList[$i]['borrowingLocation'] = $this->getFakeLoc();
                if ($this->idsInMyResearch) {
                    $transList[$i]['id'] = $this->getRandomBibId();
                    $transList[$i]['source'] = $this->getRecordSource();
                } else {
                    $transList[$i]['title'] = 'Demo Title ' . $i;
                }
            }
            return $transList;
        }
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's transactions on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyTransactions($patron)
    {
        $this->checkIntermittentFailure();
        $session = $this->getSession();
        if (!isset($session->transactions)) {
            $session->transactions = $this->getTransactionList();
        }
        return $session->transactions;
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
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $this->checkIntermittentFailure();
        return [
            [
                'locationID' => 'A',
                'locationDisplay' => 'Campus A'
            ],
            [
                'locationID' => 'B',
                'locationDisplay' => 'Campus B'
            ],
            [
                'locationID' => 'C',
                'locationDisplay' => 'Campus C'
            ]
        ];
    }

    /**
     * Get Default "Hold Required By" Date (as Unix timestamp) or null if unsupported
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Contains most of the same values passed to
     * placeHold, minus the patron data.
     *
     * @return int
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
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        $this->checkIntermittentFailure();
        $locations = $this->getPickUpLocations($patron);
        return $locations[0]['locationID'];
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
     * placeHold, minus the patron data.  May be used to limit the request group
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
     * placeHold, minus the patron data.  May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestGroups($bibId = null, $patron = null,
        $holdDetails = null
    ) {
        $this->checkIntermittentFailure();
        return [
            [
                'id' => 1,
                'name' => 'Main Library'
            ],
            [
                'id' => 2,
                'name' => 'Branch Library'
            ]
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
        return ["Fund A", "Fund B", "Fund C"];
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
        return ["Dept. A", "Dept. B", "Dept. C"];
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
        return ["Instructor A", "Instructor B", "Instructor C"];
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
        return ["Course A", "Course B", "Course C"];
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
        $count = rand(0, $limit > 30 ? 30 : $limit);
        $results = [];
        for ($x = 0; $x < $count; $x++) {
            $randomId = $this->getRandomBibId();

            // avoid duplicate entries in array:
            if (!in_array($randomId, $results)) {
                $results[] = $randomId;
            }
        }
        $retVal = ['count' => count($results), 'results' => []];
        foreach ($results as $result) {
            $retVal['results'][] = ['id' => $result];
        }
        return $retVal;
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
            $retVal[] = ['BIB_ID' => $current];
        }
        return $retVal;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
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
        $session = $this->getSession();
        foreach ($session->holds as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newHolds->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'hold_cancel_success'
                    ];
                } else {
                    $newHolds->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
                    ];
                }
            }
        }

        $session->holds = $newHolds;
        return $retVal;
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, Voyager requires the patron details an item ID
     * and a recall ID. This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['reqnum'];
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
        $session = $this->getSession();
        foreach ($session->storageRetrievalRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'storage_retrieval_request_cancel_success'
                    ];
                } else {
                    $newRequests->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'storage_retrieval_request_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
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
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        return $details['reqnum'];
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
        $this->checkIntermittentFailure();
        // Simulate an account block at random.
        if ($this->checkRenewBlock()) {
            return [
                'blocks' => [
                    'Simulated account block; try again and it will work eventually.'
                ],
                'details' => []
            ];
        }

        // Set up successful return value.
        $finalResult = ['blocks' => false, 'details' => []];

        // Grab transactions from session so we can modify them:
        $session = $this->getSession();
        $transactions = $session->transactions;
        foreach ($transactions as $i => $current) {
            // Only renew requested items:
            if (in_array($current['item_id'], $renewDetails['details'])) {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $transactions[$i]['rawduedate'] += 7 * 24 * 60 * 60;
                    $transactions[$i]['duedate']
                        = $this->dateConverter->convertToDisplayDate(
                            'U', $transactions[$i]['rawduedate']
                        );
                    $transactions[$i]['renew'] = $transactions[$i]['renew'] + 1;
                    $transactions[$i]['renewable']
                        = $transactions[$i]['renew']
                        < $transactions[$i]['renewLimit'];

                    $finalResult['details'][$current['item_id']] = [
                        "success" => true,
                        "new_date" => $transactions[$i]['duedate'],
                        "new_time" => '',
                        "item_id" => $current['item_id'],
                    ];
                } else {
                    $finalResult['details'][$current['item_id']] = [
                        "success" => false,
                        "new_date" => false,
                        "item_id" => $current['item_id'],
                        "sysMessage" =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
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
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $this->checkIntermittentFailure();
        return !$this->isFailing(__METHOD__, 10);
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
                "success" => false,
                "sysMessage" =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            ];
        }

        $session = $this->getSession();
        if (!isset($session->holds)) {
            $session->holds = new ArrayObject();
        }
        $lastHold = count($session->holds) - 1;
        $nextId = $lastHold >= 0
            ? $session->holds[$lastHold]['item_id'] + 1 : 0;

        // Figure out appropriate expiration date:
        if (!isset($holdDetails['requiredBy'])
            || empty($holdDetails['requiredBy'])
        ) {
            $expire = strtotime("now + 30 days");
        } else {
            try {
                $expire = $this->dateConverter->convertFromDisplayDate(
                    "U", $holdDetails['requiredBy']
                );
            } catch (DateException $e) {
                // Expiration date is invalid
                return [
                    'success' => false, 'sysMessage' => 'hold_date_invalid'
                ];
            }
        }
        if ($expire <= time()) {
            return [
                'success' => false, 'sysMessage' => 'hold_date_past'
            ];
        }

        $requestGroup = '';
        foreach ($this->getRequestGroups(null, null) as $group) {
            if (isset($holdDetails['requestGroupId'])
                && $group['id'] == $holdDetails['requestGroupId']
            ) {
                $requestGroup = $group['name'];
                break;
            }
        }
        $session->holds->append(
            [
                'id'       => $holdDetails['id'],
                'source'   => $this->getRecordSource(),
                'location' => $holdDetails['pickUpLocation'],
                'expire'   =>
                    $this->dateConverter->convertToDisplayDate('U', $expire),
                'create'   =>
                    $this->dateConverter->convertToDisplayDate('U', time()),
                'reqnum'   => sprintf('%06d', $nextId),
                'item_id' => $nextId,
                'volume' => '',
                'processed' => '',
                'requestGroup' => $requestGroup
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
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        $this->checkIntermittentFailure();
        if (!$this->storageRetrievalRequests || $this->isFailing(__METHOD__, 10)) {
            return false;
        }
        return true;
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
                "success" => false,
                "sysMessage" => 'Storage Retrieval Requests are disabled.'
            ];
        }
        // Simulate failure:
        if ($this->isFailing(__METHOD__, 50)) {
            return [
                "success" => false,
                "sysMessage" =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            ];
        }

        $session = $this->getSession();
        if (!isset($session->storageRetrievalRequests)) {
            $session->storageRetrievalRequests = new ArrayObject();
        }
        $lastRequest = count($session->storageRetrievalRequests) - 1;
        $nextId = $lastRequest >= 0
            ? $session->storageRetrievalRequests[$lastRequest]['item_id'] + 1
            : 0;

        // Figure out appropriate expiration date:
        if (!isset($details['requiredBy'])
            || empty($details['requiredBy'])
        ) {
            $expire = strtotime("now + 30 days");
        } else {
            try {
                $expire = $this->dateConverter->convertFromDisplayDate(
                    "U", $details['requiredBy']
                );
            } catch (DateException $e) {
                // Expiration date is invalid
                return [
                    'success' => false,
                    'sysMessage' => 'storage_retrieval_request_date_invalid'
                ];
            }
        }
        if ($expire <= time()) {
            return [
                'success' => false,
                'sysMessage' => 'storage_retrieval_request_date_past'
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
                'item_id'  => $nextId
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
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        $this->checkIntermittentFailure();
        if (!$this->ILLRequests || $this->isFailing(__METHOD__, 10)) {
            return false;
        }
        return true;
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
                'sysMessage' => 'ILL requests are disabled.'
            ];
        }
        // Simulate failure:
        if ($this->isFailing(__METHOD__, 50)) {
            return [
                'success' => false,
                'sysMessage' =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            ];
        }

        $session = $this->getSession();
        if (!isset($session->ILLRequests)) {
            $session->ILLRequests = new ArrayObject();
        }
        $lastRequest = count($session->ILLRequests) - 1;
        $nextId = $lastRequest >= 0
            ? $session->ILLRequests[$lastRequest]['item_id'] + 1
            : 0;

        // Figure out appropriate expiration date:
        if (!isset($details['requiredBy'])
            || empty($details['requiredBy'])
        ) {
            $expire = strtotime('now + 30 days');
        } else {
            try {
                $expire = $this->dateConverter->convertFromDisplayDate(
                    'U', $details['requiredBy']
                );
            } catch (DateException $e) {
                // Expiration Date is invalid
                return [
                    'success' => false,
                    'sysMessage' => 'ill_request_date_invalid'
                ];
            }
        }
        if ($expire <= time()) {
            return [
                'success' => false,
                'sysMessage' => 'ill_request_date_past'
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
                'sysMessage' => 'ill_request_place_fail_missing'
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
                'item_id'  => $nextId
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
                'isDefault' => true
            ],
            [
                'id' => 2,
                'name' => 'Branch Library',
                'isDefault' => false
            ]
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
                    'isDefault' => true
                ],
                [
                    'id' => 2,
                    'name' => 'Reference Desk',
                    'isDefault' => false
                ]
            ];
        case 2:
            return [
                [
                    'id' => 3,
                    'name' => 'Main Desk',
                    'isDefault' => false
                ],
                [
                    'id' => 4,
                    'name' => 'Library Bus',
                    'isDefault' => true
                ]
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
        $session = $this->getSession();
        foreach ($session->ILLRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'ill_request_cancel_success'
                    ];
                } else {
                    $newRequests->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'ill_request_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
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
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelILLRequestDetails($details)
    {
        return $details['reqnum'];
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
                'Demonstrating failure; keep trying and it will work eventually.'
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
    public function getConfig($function, $params = null)
    {
        $this->checkIntermittentFailure();
        if ($function == 'Holds') {
            return [
                'HMACKeys' => 'id:item_id:level',
                'extraHoldFields' =>
                    'comments:requestGroup:pickUpLocation:requiredByDate',
                'defaultRequiredDate' => 'driver:0:2:0',
            ];
        }
        if ($function == 'StorageRetrievalRequests'
            && $this->storageRetrievalRequests
        ) {
            return [
                'HMACKeys' => 'id',
                'extraFields' => 'comments:pickUpLocation:requiredByDate:item-issue',
                'helpText' => 'This is a storage retrieval request help text'
                    . ' with some <span style="color: red">styling</span>.'
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
                    . ' with some <span style="color: red">styling</span>.'
            ];
        }
        if ($function == 'changePassword') {
            return isset($this->config['changePassword'])
                ? $this->config['changePassword']
                : ['minLength' => 4, 'maxLength' => 20];
        }
        return [];
    }
}
