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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use ArrayObject, VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Date as DateException,
    VuFind\Exception\ILS as ILSException,
    Zend\Session\Container as SessionContainer;

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Demo extends AbstractBase
{
    /**
     * Connection used when getting random bib ids from Solr
     *
     * @var object
     */
    protected $db;

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
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
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
        $loc = rand()%count($locations);
        return $returnText
            ? $locations[$loc]['locationDisplay']
            :$locations[$loc]['locationID'];
    }

    /**
     * Generate a fake status message.
     *
     * @return string
     */
    protected function getFakeStatus()
    {
        $loc = rand()%10;
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
        $a = $codes[rand()%strlen($codes)];
        $b = rand()%899 + 100;
        $c = rand()%9999;
        return $a.$b.".".$c;
    }

    /**
     * Set up the Solr index so we can retrieve some record data.
     *
     * @return void
     */
    protected function prepSolr()
    {
        // Create or solr connection
        $this->db = ConnectionManager::connectToIndex();

        // Get the total # of records in the system
        $result = $this->db->search(array('query' => '*:*'));
        $this->totalRecords = $result['response']['numFound'];
    }

    /**
     * Get a random ID from the Solr index.
     *
     * @return string
     */
    protected function getRandomBibId()
    {
        // Let's keep away from both ends of the index
        $result = $this->db->search(
            array(
                'query' => '*:*',
                'start' => rand()%($this->totalRecords-1),
                'limit' => 1
            )
        );
        return $result['response']['docs'][0]['id'];
    }

    /**
     * Generates a random, fake holding array
     *
     * @param string $id     set id
     * @param string $number set number for multiple items
     *
     * @return array
     */
    protected function getRandomHolding($id, $number)
    {
        $status = $this->getFakeStatus();
        return array(
            'id'           => $id,
            'number'       => $number,
            'barcode'      => sprintf("%08d", rand()%50000),
            'availability' => $status == 'Available',
            'status'       => $status,
            'location'     => $this->getFakeLoc(),
            'reserve'      => (rand()%100 > 49) ? 'Y' : 'N',
            'callnumber'   => $this->getFakeCallNum(),
            'duedate'      => '',
            'is_holdable'  => true,
            'addLink'      => rand()%10 == 0 ? 'block' : true
        );
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
        $id = $id.""; // make it a string for consistency
        // How many items are there?
        $records = rand()%15;
        $holding = array();

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
            $holding[] = $this->getRandomHolding($id, $i+1);
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
    public function setStatus($id, $holding = array(), $append = true)
    {
        $id = (string)$id;
        $status = ($holding['status']) ? $holding['status'] : $this->getFakeStatus();
        $i = ($this->session->statuses) ? count($this->session->statuses)+1 : 1;
        $holding = array_merge($this->getRandomHolding($id, $i), $holding);

        // if statuses is already stored
        if ($this->session->statuses) {
            // and this id is part of it
            if ($append && array_key_exists($id, $this->session->statuses)) {
                // add to the array
                $this->session->statuses[$id][] = $holding;
            } else {
                // if we're over-writing or if there's nothing stored for this id
                $this->session->statuses[$id] = array($holding);
            }
        } else {
            // brand new status storage!
            $this->session->statuses = array($id => array($holding));
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
            $this->session->statuses[$id] = array();
        } else {
            // brand new status storage!
            $this->session->statuses = array($id => array());
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

        $status = array();
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
    public function getHolding($id, $patron = false)
    {
        // Get basic status info:
        $status = $this->getStatus($id);

        // Add notes and summary:
        foreach ($status as $i => $current) {
            $itemNum = $i + 1;
            $noteCount = rand(1, 3);
            $status[$i]['notes'] = array();
            for ($j = 1; $j <= $noteCount; $j++) {
                $status[$i]['notes'][] = "Item $itemNum note $j";
            }
            $summCount = rand(1, 3);
            $status[$i]['summary'] = array();
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
        return array();
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
        $user = array();

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
        $patron = array(
            'firstname' => trim("Lib"),
            'lastname'  => trim("Rarian"),
            'address1'  => trim("Somewhere ..."),
            'address2'  => trim("Other the Rainbow"),
            'zip'       => trim("12345"),
            'phone'     => trim("1900 CALL ME"),
            'group'     => trim("Library Staff")
        );
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
     */
    public function getMyFines($patron)
    {
        if (!isset($this->session->fines)) {
            // How many items are there? %20 - 2 = 10% chance of none,
            // 90% of 1-18 (give or take some odd maths)
            $fines = rand()%20 - 2;

            // Do some initial work in solr so we aren't repeating it inside this
            // loop.
            $this->prepSolr();

            $fineList = array();
            for ($i = 0; $i < $fines; $i++) {
                // How many days overdue is the item?
                $day_overdue = rand()%30 + 5;
                // Calculate checkout date:
                $checkout = strtotime("now - ".($day_overdue+14)." days");
                // 50c a day fine?
                $fine = $day_overdue * 0.50;

                $fineList[] = array(
                    "amount"   => $fine * 100,
                    "checkout" => date("j-M-y", $checkout),
                    // After 20 days it becomes 'Long Overdue'
                    "fine"     => $day_overdue > 20 ? "Long Overdue" : "Overdue",
                    // 50% chance they've paid half of it
                    "balance"  => (rand()%100 > 49 ? $fine/2 : $fine) * 100,
                    "duedate"  =>
                        date("j-M-y", strtotime("now - $day_overdue days"))
                );
                // Some fines will have no id or title:
                if (rand() % 3 != 1) {
                    if ($this->idsInMyResearch) {
                        $fineList[$i]['id'] = $this->getRandomBibId();
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
     */
    public function getMyHolds($patron)
    {
        if (!isset($this->session->holds)) {
            // How many items are there?  %10 - 1 = 10% chance of none,
            // 90% of 1-9 (give or take some odd maths)
            $holds = rand()%10 - 1;

            // Do some initial work in solr so we aren't repeating it inside this
            // loop.
            $this->prepSolr();

            $holdList = new ArrayObject();
            for ($i = 0; $i < $holds; $i++) {
                $currentHold = array(
                    "location" => $this->getFakeLoc(false),
                    "expire"   => date("j-M-y", strtotime("now + 30 days")),
                    "create"   =>
                        date("j-M-y", strtotime("now - ".(rand()%10)." days")),
                    "reqnum"   => sprintf("%06d", $i),
                    "item_id" => $i
                );
                if ($this->idsInMyResearch) {
                    $currentHold['id'] = $this->getRandomBibId();
                } else {
                    $currentHold['title'] = 'Demo Title ' . $i;
                }
                $pos = rand()%5;
                if ($pos > 1) {
                    $currentHold['position'] = $pos;
                } else {
                    $currentHold['available'] = true;
                }
                $holdList->append($currentHold);
            }
            $this->session->holds = $holdList;
        }
        return $this->session->holds;
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
     */
    public function getMyTransactions($patron)
    {
        if (!isset($this->session->transactions)) {
            // How many items are there?  %10 - 1 = 10% chance of none,
            // 90% of 1-9 (give or take some odd maths)
            $trans = rand()%10 - 1;

            // Do some initial work in solr so we aren't repeating it inside this
            // loop.
            $this->prepSolr();

            $transList = array();
            for ($i = 0; $i < $trans; $i++) {
                // When is it due? +/- up to 15 days
                $due_relative = rand()%30 - 15;
                // Due date
                if ($due_relative >= 0) {
                    $due_date = date("j-M-y", strtotime("now +$due_relative days"));
                } else {
                    $due_date = date("j-M-y", strtotime("now $due_relative days"));
                }

                // Times renewed    : 0,0,0,0,0,1,2,3,4,5
                $renew = rand()%10 - 5;
                if ($renew < 0) {
                    $renew = 0;
                }

                // Pending requests : 0,0,0,0,0,1,2,3,4,5
                $req = rand()%10 - 5;
                if ($req < 0) {
                    $req = 0;
                }

                $transList[] = array(
                    'duedate' => $due_date,
                    'barcode' => sprintf("%08d", rand()%50000),
                    'renew'   => $renew,
                    'request' => $req,
                    'item_id' => $i,
                    'renewable' => true
                );
                if ($this->idsInMyResearch) {
                    $transList[$i]['id'] = $this->getRandomBibId();
                } else {
                    $transList[$i]['title'] = 'Demo Title ' . $i;
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
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        return array(
            array(
                'locationID' => 'A',
                'locationDisplay' => 'Campus A'
            ),
            array(
                'locationID' => 'B',
                'locationDisplay' => 'Campus B'
            ),
            array(
                'locationID' => 'C',
                'locationDisplay' => 'Campus C'
            )
        );
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
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        $locations = $this->getPickUpLocations($patron);
        return $locations[0]['locationID'];
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
        return array("Fund A", "Fund B", "Fund C");
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
        return array("Dept. A", "Dept. B", "Dept. C");
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
        return array("Instructor A", "Instructor B", "Instructor C");
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
        return array("Course A", "Course B", "Course C");
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
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // Do some initial work in solr so we aren't repeating it inside this loop.
        $this->prepSolr();

        // Pick a random number of results to return -- don't exceed limit or 30,
        // whichever is smaller (this can be pretty slow due to the random ID code).
        $count = rand(0, $limit > 30 ? 30 : $limit);
        $results = array();
        for ($x = 0; $x < $count; $x++) {
            $randomId = $this->getRandomBibId();

            // avoid duplicate entries in array:
            if (!in_array($randomId, $results)) {
                $results[] = $randomId;
            }
        }
        $retVal = array('count' => count($results), 'results' => array());
        foreach ($results as $result) {
            $retVal['results'][] = array('id' => $result);
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
     */
    public function findReserves($course, $inst, $dept)
    {
        // Do some initial work in solr so we aren't repeating it inside this loop.
        $this->prepSolr();

        // Pick a random number of results to return -- don't exceed 30.
        $count = rand(0, 30);
        $results = array();
        for ($x = 0; $x < $count; $x++) {
            $randomId = $this->getRandomBibId();

            // avoid duplicate entries in array:
            if (!in_array($randomId, $results)) {
                $results[] = $randomId;
            }
        }

        $retVal = array();
        foreach ($results as $current) {
            $retVal[] = array('BIB_ID' => $current);
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
        $retVal = array('count' => 0, 'items' => array());
        foreach ($this->session->holds as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newHolds->append($current);
            } else {
                // 50% chance of cancel failure for testing purposes
                if (rand() % 2) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = array(
                        'success' => true,
                        'status' => 'hold_cancel_success'
                    );
                } else {
                    $newHolds->append($current);
                    $retVal['items'][$current['item_id']] = array(
                        'success' => false,
                        'status' => 'hold_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
                    );
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
        // Set up return value -- no blocks in demo driver currently.
        $finalResult = array('blocks' => array(), 'details' => array());

        // Grab transactions from session so we can modify them:
        $transactions = $this->session->transactions;
        foreach ($transactions as $i => $current) {
            // Only renew requested items:
            if (in_array($current['item_id'], $renewDetails['details'])) {
                if (rand() % 2) {
                    $old = $transactions[$i]['duedate'];
                    $transactions[$i]['duedate']
                        = date("j-M-y", strtotime($old . " + 7 days"));

                    $finalResult['details'][$current['item_id']] = array(
                        "success" => true,
                        "new_date" => $transactions[$i]['duedate'],
                        "new_time" => '',
                        "item_id" => $current['item_id'],
                    );
                } else {
                    $finalResult['details'][$current['item_id']] = array(
                        "success" => false,
                        "new_date" => false,
                        "item_id" => $current['item_id'],
                        "sysMessage" =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
                    );
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
            return array(
                "success" => false,
                "sysMessage" =>
                    'Demonstrating failure; keep trying and ' .
                    'it will work eventually.'
            );
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
                // Hold Date is invalid
                return array(
                    'success' => false, 'sysMessage' => 'hold_date_invalid'
                );
            }
        }
        if ($expire <= time()) {
            return array(
                'success' => false, 'sysMessage' => 'hold_date_past'
            );
        }

        $this->session->holds->append(
            array(
                "id"       => $holdDetails['id'],
                "location" => $holdDetails['pickUpLocation'],
                "expire"   => date("j-M-y", $expire),
                "create"   => date("j-M-y"),
                "reqnum"   => sprintf("%06d", $nextId),
                "item_id" => $nextId
            )
        );

        return array('success' => true);
    }

    /**
     * Public Function which specifies renew, hold and cancel settings.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        if ($function == 'Holds') {
            return array(
                'HMACKeys' => 'id',
                'extraHoldFields' => 'comments:pickUpLocation:requiredByDate'
            );
        }
        return array();
    }
}
