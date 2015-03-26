<?php
/**
 * Evergreen ILS Driver
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
 * @author   Warren Layton, NRCan Library <warren.layton@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use PDO, PDOException, VuFind\Exception\ILS as ILSException;

/**
 * VuFind Connector for Evergreen
 *
 * Written by Warren Layton at the NRCan (Natural Resources Canada)
 * Library.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Warren Layton, NRCan Library <warren.layton@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Evergreen extends AbstractBase
{
    /**
     * Database connection
     *
     * @var PDO
     */
    protected $db;

    /**
     * Database name
     *
     * @var string
     */
    protected $dbName;

     /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @throws PDOException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        // Define Database Name
        $this->dbName = $this->config['Catalog']['database'];

        try {
            $this->db = new PDO(
                'pgsql:host='
                . $this->config['Catalog']['hostname']
                . ' user='
                . $this->config['Catalog']['user']
                . ' dbname='
                . $this->config['Catalog']['database']
                . ' password='
                . $this->config['Catalog']['password']
                . ' port='
                . $this->config['Catalog']['port']
            );
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $holding = [];

        // Build SQL Statement
        $sql = <<<HERE
SELECT ccs.name AS status, acn.label AS callnumber, acpl.name AS location
FROM config.copy_status ccs
    INNER JOIN asset.copy ac ON ccs.id = ac.status
    INNER JOIN asset.call_number acn ON ac.call_number = acn.id
    INNER JOIN asset.copy_location acpl ON ac.copy_location = acpl.id
WHERE ac.id = ?
HERE;

        // Execute SQL
        try {
            $holding = [];
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(1, $id, PDO::PARAM_INT);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        // Build Holdings Array
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['status']) {
            case 'Available':
                $available = true;
                $reserve = false;
                break;
            case 'On holds shelf':
                $available = false;
                $reserve = true;
                break;
            default:
                $available = false;
                $reserve = false;
                break;
            }

            $holding[] = [
                'id' => $id,
                'availability' => $available,
                'status' => $row['status'],
                'location' => $row['location'],
                'reserve' => $reserve,
                'callnumber' => $row['callnumber']
            ];
        }

        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        $status = [];
        foreach ($idList as $id) {
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
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $holding = [];

        // Build SQL Statement
        $sql = <<<HERE
SELECT ccs.name AS status, acn.label AS callnumber, aou.name AS location,
    ac.copy_number, ac.barcode,
    extract (year from circ.due_date) as due_year,
    extract (month from circ.due_date) as due_month,
    extract (day from circ.due_date) as due_day
FROM config.copy_status ccs
    INNER JOIN asset.copy ac ON ac.status = ccs.id
    INNER JOIN asset.call_number acn ON acn.id = ac.call_number
    INNER JOIN actor.org_unit aou ON aou.id = ac.circ_lib
    FULL JOIN action.circulation circ ON (
        ac.id = circ.target_copy AND circ.checkin_time IS NULL
    )
WHERE acn.record = ?
HERE;

        // Execute SQL
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(1, $id, PDO::PARAM_INT);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        // Build Holdings Array
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['status']) {
            case 'Available':
                $available = true;
                $reserve = false;
                break;
            case 'On holds shelf':
                // Instead of relying on status = 'On holds shelf',
                // I might want to see if:
                // action.hold_request.current_copy = asset.copy.id
                // and action.hold_request.capture_time is not null
                // and I think action.hold_request.fulfillment_time is null
                $available = false;
                $reserve = true;
                break;
            default:
                $available = false;
                $reserve = false;
                break;
            }

            if ($row['due_year']) {
                $due_date = $row['due_year'] . "-" . $row['due_month'] . "-" .
                            $row['due_day'];
            } else {
                $due_date = "";
            }
            $holding[] = [
                'id' => $id,
                'availability' => $available,
                'status' => $row['status'],
                'location' => $row['location'],
                'reserve' => $reserve,
                'callnumber' => $row['callnumber'],
                'duedate' => $due_date,
                'number' => $row['copy_number'],
                'barcode' => $row['barcode']
            ];
        }

        return $holding;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return [];
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode The patron username OR barcode number
     * @param string $passwd  The patron password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $passwd)
    {
        $sql = <<<HERE
SELECT usr.id, usr.first_given_name as firstName,
    usr.family_name as lastName, usr.email, usrname
FROM actor.usr usr
    INNER JOIN actor.card ON usr.card = card.id
WHERE card.active = true
    AND usr.passwd = MD5(?)
HERE;
        if (is_numeric($barcode)) {
            // A barcode was supplied as ID
            $sql .= "AND card.barcode = ?";
        } else {
            // A username was supplied as ID
            $sql .= "AND usr.usrname = ?";
        }

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(1, $passwd, PDO::PARAM_STR);
            $sqlStmt->bindParam(2, $barcode, PDO::PARAM_STR);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            if (isset($row['id']) && ($row['id'] != '')) {
                $return = [];
                $return['id'] = $row['id'];
                $return['firstname'] = $row['firstname'];
                $return['lastname'] = $row['lastname'];
                $return['cat_username'] = $row['usrname'];
                $return['cat_password'] = $passwd;
                $return['email'] = $row['email'];
                $return['major'] = null;    // Don't know which table this comes from
                $return['college'] = null;  // Don't know which table this comes from
                return $return;
            } else {
                return null;
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
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
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $transList = [];

        $sql = "select circulation.target_copy as bib_id, " .
               "extract (year from circulation.due_date) as due_year, " .
               "extract (month from circulation.due_date) as due_month, " .
               "extract (day from circulation.due_date) as due_day " .
               "from $this->dbName.action.circulation " .
               "where circulation.usr = '" . $patron['id'] . "' " .
               "and circulation.checkin_time is null";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();

            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['due_year']) {
                    $due_date = $row['due_year'] . "-" . $row['due_month'] . "-" .
                                $row['due_day'];
                } else {
                    $due_date = "";
                }

                $transList[] = ['duedate' => $due_date,
                                     'id' => $row['bib_id']];
            }
            return $transList;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $fineList = [];

        $sql = "select billable_xact_summary.total_owed, " .
               "billable_xact_summary.balance_owed, " .
               "billable_xact_summary.last_billing_type, " .
               "extract (year from billable_xact_summary.xact_start) " .
               "as start_year, " .
               "extract (month from billable_xact_summary.xact_start) " .
               "as start_month, " .
               "extract (day from billable_xact_summary.xact_start) " .
               "as start_day, " .
               "billable_cirulations.target_copy " .
               "from $this->dbName.money.billable_xact_summary " .
               "LEFT JOIN $this->dbName.action.billable_cirulations " .
               "ON (billable_xact_summary.id = billable_cirulations.id " .
               " and billable_cirulations.xact_finish is null) " .
               "where billable_xact_summary.usr = '" . $patron['id'] . "' " .
               "and billable_xact_summary.xact_finish is null";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();

            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['start_year']) {
                    $charge_date = $row['start_year'] . "-" . $row['start_month'] .
                            "-" . $row['start_day'];
                } else {
                    $charge_date = "";
                }

                $fineList[] = ['amount' => $row['total_owed'],
                                    'fine' => $row['last_billing_type'],
                                    'balance' => $row['balance_owed'],
                                    'checkout' => $charge_date,
                                    'duedate' => "",
                                    'id' => $row['target_copy']];
            }
            return $fineList;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $holdList = [];

        $sql = "select hold_request.hold_type, hold_request.current_copy, " .
               "extract (year from hold_request.expire_time) as exp_year, " .
               "extract (month from hold_request.expire_time) as exp_month, " .
               "extract (day from hold_request.expire_time) as exp_day, " .
               "extract (year from hold_request.request_time) as req_year, " .
               "extract (month from hold_request.request_time) as req_month, " .
               "extract (day from hold_request.request_time) as req_day, " .
               "org_unit.name as lib_name " .
               "from $this->dbName.action.hold_request, " .
               "$this->dbName.actor.org_unit " .
               "where hold_request.usr = '" . $patron['id'] . "' " .
               "and hold_request.pickup_lib = org_unit.id " .
               "and hold_request.capture_time is not null " .
               "and hold_request.fulfillment_time is null";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['req_year']) {
                    $req_time = $row['req_year'] . "-" . $row['req_month'] .
                            "-" . $row['req_day'];
                } else {
                    $req_time = "";
                }

                if ($row['exp_year']) {
                    $exp_time = $row['exp_year'] . "-" . $row['exp_month'] .
                            "-" . $row['exp_day'];
                } else {
                    $exp_time = "";
                }

                $holdList[] = ['type' => $row['hold_type'],
                                    'id' => $row['current_copy'],
                                    'location' => $row['lib_name'],
                                    'expire' => $exp_time,
                                    'create' => $req_time];
            }
            return $holdList;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
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
        $sql = <<<HERE
SELECT usr.family_name, usr.first_given_name, usr.day_phone,
    usr.evening_phone, usr.other_phone, aua.street1,
    aua.street2, aua.post_code, pgt.name AS usrgroup
FROM actor.usr
    FULL JOIN actor.usr_address aua ON aua.id = usr.mailing_address
    INNER JOIN permission.grp_tree pgt ON pgt.id = usr.profile
WHERE usr.active = true
     AND usr.id = ?
HERE;

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(1, $patron['id'], PDO::PARAM_INT);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);

            if ($row['day_phone']) {
                $phone = $row['day_phone'];
            } elseif ($row['evening_phone']) {
                $phone = $row['evening_phone'];
            } else {
                $phone = $row['other_phone'];
            }

            if ($row) {
                $patron = [
                    'firstname' => $row['first_given_name'],
                    'lastname' => $row['family_name'],
                    'address1' => $row['street1'],
                    'address2' => $row['street2'],
                    'zip' => $row['post_code'],
                    'phone' => $phone,
                    'group' => $row['usrgroup']
                ];
                return $patron;
            } else {
                return null;
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Only one of the following 2 function should be implemented.
     * Placing a hold directly can be done with placeHold.
     * Otherwise, getHoldLink will link to Evergreen's page to place
     * a hold via the ILS.
     */

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
    //public function placeHold($holdDetails)
    //{
        // Need to check asset.copy.status -> config.copy_status.holdable = true
        // If it is holdable, place hold in action.hold_request:
        // request_time to now, current_copy to asset.copy.id,
        // usr to action.usr.id of requesting patron,
        // phone_notify to phone number, email_notify to t/f
        // set pickup_lib too?

        /*
        $sql = "";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
        */
    //}

    /**
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $recordId The id of the bib record
     * @param array  $details  Item details from getHoldings return array
     *
     * @return string          URL to ILS's OPAC's place hold screen.
     */
    //public function getHoldLink($recordId, $details)
    //{
    //}

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
     * @throws ILSException
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $items = [];

        // Prevent unnecessary load
        // (Taken from Voyager driver - does Evergreen need this?)
        if ($daysOld > 30) {
            $daysOld = 30;
        }

        $enddate = date('Y-m-d', strtotime('now'));
        $startdate = date('Y-m-d', strtotime("-$daysOld day"));

        $sql = "select count(distinct copy.id) as count " .
               "from asset.copy " .
               "where copy.create_date >= '$startdate' " .
               "and copy.create_date < '$enddate'";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['count'];
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        // TODO: implement paging support
        //$page = ($page) ? $page : 1;
        //$limit = ($limit) ? $limit : 20;
        //$startRow = (($page-1)*$limit)+1;
        //$endRow = ($page*$limit);

        $sql = "select copy.id from asset.copy " .
               "where copy.create_date >= '$startdate' " .
               "and copy.create_date < '$enddate'";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['id'];
            }
            return $items;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @throws ILSException
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        /*
        $list = array();

        $sql = "";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['name'];
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        return $list;
        */
    }

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $list = [];

        $sql = "select copy.id as id " .
               "from $this->dbName.asset " .
               "where copy.opac_visible = false";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['id'];
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        return $list;
    }

    // *** The functions below are not (yet) applicable to Evergreen ***

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        // TODO
        return [];
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        // TODO
        return [];
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @throws ILSException
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        // TODO
        return [];
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return [];
    }
}
