<?php

/**
 * Evergreen ILS Driver
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Warren Layton, NRCan Library <warren.layton@gmail.com>
 * @author   Galen Charlton, Equinox <gmcharlt@equinoxOLI.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use PDO;
use PDOException;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function count;

/**
 * VuFind Connector for Evergreen
 *
 * Written by Warren Layton at the NRCan (Natural Resources Canada)
 * Library.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Warren Layton, NRCan Library <warren.layton@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Evergreen extends AbstractBase implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
    }

    /**
     * Evergreen constants
     */
    public const EVG_ITEM_STATUS_IN_TRANSIT = '6';

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
            SELECT ccs.name AS status, acn.label AS callnumber, aou.name AS location
            FROM config.copy_status ccs
                INNER JOIN asset.copy ac ON ac.status = ccs.id
                INNER JOIN asset.call_number acn ON acn.id = ac.call_number
                INNER JOIN actor.org_unit aou ON aou.id = ac.circ_lib
            WHERE
                acn.record = ? AND
                NOT ac.deleted
            HERE;

        // Execute SQL
        try {
            $holding = [];
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(1, $id, PDO::PARAM_INT);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
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
                'callnumber' => $row['callnumber'],
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
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options (not currently used)
     *
     * @throws DateException
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
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
            WHERE
                acn.record = ? AND
                NOT ac.deleted
            HERE;

        // Execute SQL
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(1, $id, PDO::PARAM_INT);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
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
                $due_date = $row['due_year'] . '-' . $row['due_month'] . '-' .
                            $row['due_day'];
            } else {
                $due_date = '';
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
                'barcode' => $row['barcode'],
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
                AND actor.verify_passwd(usr.id, 'main',
                                       MD5(actor.get_salt(usr.id, 'main') || MD5(?)))
            HERE;
        if (is_numeric($barcode)) {
            // A barcode was supplied as ID
            $sql .= 'AND card.barcode = ?';
        } else {
            // A username was supplied as ID
            $sql .= 'AND usr.usrname = ?';
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
            $this->throwAsIlsException($e);
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
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $transList = [];

        $sql = 'select call_number.record as bib_id, ' .
               'circulation.due_date as due_date, ' .
               'circulation.target_copy as item_id, ' .
               'circulation.renewal_remaining as renewal_remaining, ' .
               'aou_circ.name as borrowing_location, ' .
               'aou_own.name as owning_library, ' .
               'copy.barcode as barcode ' .
               "from $this->dbName.action.circulation " .
               "join $this->dbName.asset.copy ON " .
               ' (circulation.target_copy = copy.id) ' .
               "join $this->dbName.asset.call_number ON " .
               '  (copy.call_number = call_number.id) ' .
               "join $this->dbName.actor.org_unit aou_circ ON " .
               '  (circulation.circ_lib = aou_circ.id) ' .
               "join $this->dbName.actor.org_unit aou_own ON " .
               '  (call_number.owning_lib = aou_own.id) ' .
               "where circulation.usr = '" . $patron['id'] . "' " .
               'and circulation.checkin_time is null ' .
               'and circulation.xact_finish is null';

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();

            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $due_date = $this->formatDate($row['due_date']);
                $_due_time = new \DateTime($row['due_date']);
                if ($_due_time->format('H:i:s') == '23:59:59') {
                    $dueTime = ''; // don't display due time for non-hourly loans
                } else {
                    $dueTime = $this->dateConverter->convertToDisplayTime(
                        'Y-m-d H:i',
                        $row['due_date']
                    );
                }

                $today = new \DateTime();
                $now = time();
                // since Evergreen normalizes the due time of non-hourly
                // loans to be 23:59:59, we use a slightly flexible definition
                // of "due in 24 hours"
                $end_of_today = strtotime($today->format('Y-m-d 23:59:59'));
                $dueTimeStamp = strtotime($row['due_date']);
                $dueStatus = false;
                if (is_numeric($dueTimeStamp)) {
                    $_dueTimeLessDay = $dueTimeStamp - (1 * 24 * 60 * 60) - 1;
                    if ($now > $dueTimeStamp) {
                        $dueStatus = 'overdue';
                    } elseif ($end_of_today > $_dueTimeLessDay) {
                        $dueStatus = 'due';
                    }
                }

                $transList[] = [
                                    'duedate' => $due_date,
                                    'dueTime' => $dueTime,
                                    'id' => $row['bib_id'],
                                    'barcode' => $row['barcode'],
                                    'item_id' => $row['item_id'],
                                    'renewLimit' => $row['renewal_remaining'],
                                    'renewable' => $row['renewal_remaining'] > 1,
                                    'institution_name' => $row['owning_library'],
                                    'borrowingLocation' =>
                                        $row['borrowing_location'],
                                    'dueStatus' => $dueStatus,
                               ];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return ['count' => count($transList), 'records' => $transList];
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
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $fineList = [];

        $sql = 'select billable_xact_summary.total_owed * 100 as total_owed, ' .
               'billable_xact_summary.balance_owed * 100 as balance_owed, ' .
               'billable_xact_summary.last_billing_type, ' .
               'billable_xact_summary.last_billing_ts, ' .
               'billable_circulations.create_time as checkout_time, ' .
               'billable_circulations.due_date, ' .
               'billable_circulations.target_copy, ' .
               'call_number.record ' .
               "from $this->dbName.money.billable_xact_summary " .
               "LEFT JOIN $this->dbName.action.billable_circulations " .
               'ON (billable_xact_summary.id = billable_circulations.id ' .
               ' and billable_circulations.xact_finish is null) ' .
               "LEFT JOIN $this->dbName.asset.copy ON " .
               '  (billable_circulations.target_copy = copy.id) ' .
               "LEFT JOIN $this->dbName.asset.call_number ON " .
               '  (copy.call_number = call_number.id) ' .
               "where billable_xact_summary.usr = '" . $patron['id'] . "' " .
               'and billable_xact_summary.total_owed <> 0 ' .
               'and billable_xact_summary.xact_finish is null';

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();

            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $fineList[] = [
                    'amount' => $row['total_owed'],
                    'fine' => $row['last_billing_type'],
                    'balance' => $row['balance_owed'],
                    'checkout' => $this->formatDate($row['checkout_time']),
                    'createdate' => $this->formatDate($row['last_billing_ts']),
                    'duedate' => $this->formatDate($row['due_date']),
                    'id' => $row['record'],
                ];
            }
            return $fineList;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
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
        $holdList = [];

        $sql = 'select ahr.hold_type, bib_record, ' .
               'ahr.id as hold_id, ' .
               'expire_time, request_time, shelf_time, capture_time, ' .
               'shelf_time, shelf_expire_time, frozen, thaw_date, ' .
               'org_unit.name as lib_name, acp.status as copy_status ' .
               "from $this->dbName.action.hold_request ahr " .
               "join $this->dbName.actor.org_unit on " .
               '  (ahr.pickup_lib = org_unit.id) ' .
               "join $this->dbName.reporter.hold_request_record rhrr on " .
               '  (rhrr.id = ahr.id) ' .
               "left join $this->dbName.asset.copy acp on " .
               '  (acp.id = ahr.current_copy) ' .
               "where ahr.usr = '" . $patron['id'] . "' " .
               'and ahr.fulfillment_time is null ' .
               'and ahr.cancel_time is null';

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $holdList[] = [
                    'type' => $row['hold_type'],
                    'id' => $row['bib_record'],
                    'reqnum' => $row['hold_id'],
                    'location' => $row['lib_name'],
                    'expire' => $this->formatDate($row['expire_time']),
                    'last_pickup_date' =>
                        $this->formatDate($row['shelf_expire_time']),
                    'available' => $row['shelf_time'],
                    'frozen' => $row['frozen'],
                    'frozenThrough' => $this->formatDate($row['thaw_date']),
                    'create' => $this->formatDate($row['request_time']),
                    'in_transit' =>
                        $row['copy_status'] == self::EVG_ITEM_STATUS_IN_TRANSIT,
                ];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $holdList;
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
                aua.street2, aua.post_code, pgt.name AS usrgroup,
                aua.city, aua.country, usr.expire_date
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
                    'city' => $row['city'],
                    'zip' => $row['post_code'],
                    'country' => $row['country'],
                    'phone' => $phone,
                    'group' => $row['usrgroup'],
                    'expiration_date' => $this->formatDate($row['expire_date']),
                ];
                return $patron;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return null;
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
        $this->throwAsIlsException($e);
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

        $enddate = date('Y-m-d', strtotime('now'));
        $startdate = date('Y-m-d', strtotime("-$daysOld day"));

        $sql = 'select count(distinct copy.id) as count ' .
               'from asset.copy ' .
               "where copy.create_date >= '$startdate' " .
               'and copy.status = 0 ' .
               "and copy.create_date < '$enddate' LIMIT 50";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['count'];
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        // TODO: implement paging support
        //$page = ($page) ? $page : 1;
        //$limit = ($limit) ? $limit : 20;
        //$startRow = (($page-1)*$limit)+1;
        //$endRow = ($page*$limit);

        $sql = 'select copy.id, call_number.record from asset.copy ' .
               'join asset.call_number on (call_number.id = copy.call_number) ' .
               "where copy.create_date >= '$startdate' " .
               'and copy.status = 0 ' .
               "and copy.create_date < '$enddate' LIMIT 50";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['record'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $items;
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
        $list = [];

        /* TODO:
        $sql = "";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['name'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        */

        return $list;
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

        $sql = 'select copy.id as id ' .
               "from $this->dbName.asset " .
               'where copy.opac_visible = false';

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['id'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
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

    /**
     * Format date
     *
     * This formats a date coming from Evergreen for display
     *
     * @param string $date The date string to format; may be null
     *
     * @throws ILSException
     * @return string The formatted date
     */
    protected function formatDate($date)
    {
        if (!$date) {
            return '';
        }
        return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
    }
}
