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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use ArrayObject, VuFind\Exception\Date as DateException,
    VuFind\Exception\ILS as ILSException,
    VuFindSearch\Query\Query, VuFindSearch\Service as SearchService,
    Zend\Session\Container as SessionContainer;

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
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
    protected $session;

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
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     * @param SearchService          $ss            Search service
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        SearchService $ss
    ) {
        $this->dateConverter = $dateConverter;
        $this->searchService = $ss;
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

        // Establish a namespace in the session for persisting fake data (to save
        // on Solr hits):
        $this->session = new SessionContainer('DemoDriver');
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
            ? $this->config['Records']['source'] : 'VuFind';
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
            'addLink'      => $patron ? rand() % 10 == 0 ? 'block' : true : false,
            'level'        => 'copy',
            'storageRetrievalRequest' => 'auto',
            'addStorageRetrievalRequestLink' => $patron
                ? rand() % 10 == 0 ? 'block' : 'check'
                : false,
            'ILLRequest'   => 'auto',
            'addILLRequestLink' => $patron
                ? rand() % 10 == 0 ? 'block' : 'check'
                : false
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
                "create"   => date("j-M-y", strtotime("now - {$randDays} days")),
                "expire"   => date("j-M-y", strtotime("now + 30 days")),
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
                    ? date("j-M-y")
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
    public function getSimulatedStatus($id, array $patron = null)
    {
        $id = $id . ""; // make it a string for consistency
        // How many items are there?
        $records = rand() % 15;
        $holding = [];

        // NOTE: Ran into an interesting bug when using:

        // 'availability' => rand()%2 ? true : false

        // It seems rand() returns alternating even/odd
        // patterns on some versions running under windows.
        // So this method gives better 50/50 results:

        // 'availability' => (rand()%100 > 49) ? true : false
        if ($this->session->statuses
            && array_key_exists($id, $this->session->statuses)
        ) {
            return $this->session->statuses[$id];
        }

        // Create a fake entry for each one
        for ($i = 0; $i < $records; $i++) {
            $holding[] = $this->getRandomHolding($id, $i + 1, $patron);
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
    public function setStatus($id, $holding = [], $append = true)
    {
        $id = (string)$id;
        $i = ($this->session->statuses) ? count($this->session->statuses) + 1 : 1;
        $holding = array_merge($this->getRandomHolding($id, $i), $holding);

        // if statuses is already stored
        if ($this->session->statuses) {
            // and this id is part of it
            if ($append && array_key_exists($id, $this->session->statuses)) {
                // add to the array
                $this->session->statuses[$id][] = $holding;
            } else {
                // if we're over-writing or if there's nothing stored for this id
                $this->session->statuses[$id] = [$holding];
            }
        } else {
            // brand new status storage!
            $this->session->statuses = [$id => [$holding]];
        }
        return $holding;
    }

    /**
     * Set Status to return an invalid entry
     *
     * @param array $id id for condemned record
     *
     * @return void;
     */
    public function setInvalidId($id)
    {
        $id = (string)$id;
        // if statuses is already stored
        if ($this->session->statuses) {
            $this->session->statuses[$id] = [];
        } else {
            // brand new status storage!
            $this->session->statuses = [$id => []];
        }
    }

    /**
     * Clear status
     *
     * @param array $id id for clearing the status
     *
     * @return void;
     */
    public function clearStatus($id)
    {
        $id = (string)$id;

        // if statuses is already stored
        if ($this->session->statuses) {
            unset($this->session->statuses[$id]);
        }
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
        // Random Seed
        srand(time());

        $status = [];
        foreach ($ids as $id) {
            $status[] = $this->getStatus($id);
        }
        return $status;
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
        // Get basic status info:
        $status = $this->getSimulatedStatus($id, $patron);

        // Add notes and summary:
        foreach (array_keys($status) as $i) {
            $itemNum = $i + 1;
            $noteCount = rand(1, 3);
            $status[$i]['notes'] = [];
            for ($j = 1; $j <= $noteCount; $j++) {
                $status[$i]['notes'][] = "Item $itemNum note $j";
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
        if (!isset($this->session->fines)) {
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
                    "checkout" => date("j-M-y", $checkout),
                    // After 20 days it becomes 'Long Overdue'
                    "fine"     => $day_overdue > 20 ? "Long Overdue" : "Overdue",
                    // 50% chance they've paid half of it
                    "balance"  => (rand() % 100 > 49 ? $fine / 2 : $fine) * 100,
                    "duedate"  =>
                        date("j-M-y", strtotime("now - $day_overdue days"))
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
            $this->session->fines = $fineList;
        }
        return $this->session->fines;
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
        if (!isset($this->session->holds)) {
            $this->session->holds = $this->createRequestList('Holds');
        }
        return $this->session->holds;
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
        if (!isset($this->session->storageRetrievalRequests)) {
            $this->session->storageRetrievalRequests
                = $this->createRequestList('StorageRetrievalRequests');
        }
        return $this->session->storageRetrievalRequests;
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
        if (!isset($this->session->ILLRequests)) {
            $this->session->ILLRequests
                = $this->createRequestList('ILLRequests');
        }
        return $this->session->ILLRequests;
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
        if (!isset($this->session->transactions)) {
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
                    $due_date = date("j-M-y", strtotime("now +$due_relative days"));
                    if ($due_relative == 0) {
                        $dueStatus = 'due';
                    }
                } else {
                    $due_date = date("j-M-y", strtotime("now $due_relative days"));
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

                if ($i == 2 || rand() % 5 == 1) {
                    // Mimic an ILL loan
                    $transList[] = $this->getRandomItemIdentifier() + [
                        'duedate' => $due_date,
                        'dueStatus' => $dueStatus,
                        'barcode' => sprintf("%08d", rand() % 50000),
                        'renew'   => $renew,
                        'renewLimit' => $renewLimit,
                        'request' => $req,
                        'id'      => "ill_institution_$i",
                        'item_id' => $i,
                        'renewable' => $renew < $renewLimit,
                        'title'   => "ILL Loan Title $i",
                        'institution_id' => 'ill_institution',
                        'institution_name' => 'ILL Library',
                        'institution_dbkey' => 'ill_institution',
                        'borrowingLocation' => 'ILL Service Desk'
                    ];
                } else {
                    $transList[] = $this->getRandomItemIdentifier() + [
                        'duedate' => $due_date,
                        'dueStatus' => $dueStatus,
                        'barcode' => sprintf("%08d", rand() % 50000),
                        'renew'   => $renew,
                        'renewLimit' => $renewLimit,
                        'request' => $req,
                        'item_id' => $i,
                        'renewable' => $renew < $renewLimit,
                        'borrowingLocation' => $this->getFakeLoc()
                    ];
                    if ($this->idsInMyResearch) {
                        $transList[$i]['id'] = $this->getRandomBibId();
                        $transList[$i]['source'] = $this->getRecordSource();
                    } else {
                        $transList[$i]['title'] = 'Demo Title ' . $i;
                    }
                }
            }
            $this->session->transactions = $transList;
        }
        return $this->session->transactions;
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
        // 5 years in the future (but similate intermittent failure):
        return rand(0, 1) == 1
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
        if (rand(0, 1) == 1) {
            return false;
        }
        $requestGroups = $this->getRequestGroups(0, 0);
        return $requestGroups[0]['id'];
    }

    /**
     * Get request groups
     *
     * @param integer $bibId  BIB ID
     * @param array   $patron Patron information returned by the patronLogin
     * method.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestGroups($bibId = null, $patron = null)
    {
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
        // Rewrite the holds in the session, removing those the user wants to
        // cancel.
        $newHolds = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        foreach ($this->session->holds as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newHolds->append($current);
            } else {
                // 50% chance of cancel failure for testing purposes
                if (rand() % 2) {
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

        $this->session->holds = $newHolds;
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
        // Rewrite the items in the session, removing those the user wants to
        // cancel.
        $newRequests = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        foreach ($this->session->storageRetrievalRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                // 50% chance of cancel failure for testing purposes
                if (rand() % 2) {
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

        $this->session->storageRetrievalRequests = $newRequests;
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
        // Simulate an account block at random.
        if (rand() % 4 == 1) {
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
        $transactions = $this->session->transactions;
        foreach ($transactions as $i => $current) {
            // Only renew requested items:
            if (in_array($current['item_id'], $renewDetails['details'])) {
                if (rand() % 2) {
                    $old = $transactions[$i]['duedate'];
                    $transactions[$i]['duedate']
                        = date("j-M-y", strtotime($old . " + 7 days"));
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
        $this->session->transactions = $transactions;

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
        if (rand() % 10 == 0) {
            return false;
        }
        return true;
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
        // Simulate failure:
        if (rand() % 2) {
            return [
                "success" => false,
                "sysMessage" =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            ];
        }

        if (!isset($this->session->holds)) {
            $this->session->holds = new ArrayObject();
        }
        $lastHold = count($this->session->holds) - 1;
        $nextId = $lastHold >= 0
            ? $this->session->holds[$lastHold]['item_id'] + 1 : 0;

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
        $this->session->holds->append(
            [
                'id'       => $holdDetails['id'],
                'source'   => $this->getRecordSource(),
                'location' => $holdDetails['pickUpLocation'],
                'expire'   => date('j-M-y', $expire),
                'create'   => date('j-M-y'),
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
        if (!$this->storageRetrievalRequests || rand() % 10 == 0) {
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
        if (!$this->storageRetrievalRequests) {
            return [
                "success" => false,
                "sysMessage" => 'Storage Retrieval Requests are disabled.'
            ];
        }
        // Simulate failure:
        if (rand() % 2) {
            return [
                "success" => false,
                "sysMessage" =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            ];
        }

        if (!isset($this->session->storageRetrievalRequests)) {
            $this->session->storageRetrievalRequests = new ArrayObject();
        }
        $lastRequest = count($this->session->storageRetrievalRequests) - 1;
        $nextId = $lastRequest >= 0
            ? $this->session->storageRetrievalRequests[$lastRequest]['item_id'] + 1
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

        $this->session->storageRetrievalRequests->append(
            [
                'id'       => $details['id'],
                'source'   => $this->getRecordSource(),
                'location' => $details['pickUpLocation'],
                'expire'   => date('j-M-y', $expire),
                'create'  => date('j-M-y'),
                'processed' => rand() % 3 == 0 ? date('j-M-y', $expire) : '',
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
        if (!$this->ILLRequests || rand() % 10 == 0) {
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
        if (!$this->ILLRequests) {
            return [
                'success' => false,
                'sysMessage' => 'ILL requests are disabled.'
            ];
        }
        // Simulate failure:
        if (rand() % 2) {
            return [
                'success' => false,
                'sysMessage' =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            ];
        }

        if (!isset($this->session->ILLRequests)) {
            $this->session->ILLRequests = new ArrayObject();
        }
        $lastRequest = count($this->session->ILLRequests) - 1;
        $nextId = $lastRequest >= 0
            ? $this->session->ILLRequests[$lastRequest]['item_id'] + 1
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

        $this->session->ILLRequests->append(
            [
                'id'       => $details['id'],
                'source'   => $this->getRecordSource(),
                'location' => $pickupLocation,
                'expire'   => date('j-M-y', $expire),
                'create'  => date('j-M-y'),
                'processed' => rand() % 3 == 0 ? date('j-M-y', $expire) : '',
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
        // Rewrite the items in the session, removing those the user wants to
        // cancel.
        $newRequests = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        foreach ($this->session->ILLRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                // 50% chance of cancel failure for testing purposes
                if (rand() % 2) {
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

        $this->session->ILLRequests = $newRequests;
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
        if (rand() % 3) {
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
            return [
                'minLength' => 4,
                'maxLength' => 20
            ];
        }
        return [];
    }
}
