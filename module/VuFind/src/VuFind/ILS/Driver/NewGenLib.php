<?php

/**
 * ILS Driver for NewGenLib
 *
 * PHP version 8
 *
 * Copyright (C) Verus Solutions Pvt.Ltd 2010.
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
 * @author   Verus Solutions <info@verussolutions.biz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use PDO;
use PDOException;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function count;
use function is_array;

/**
 * ILS Driver for NewGenLib
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Verus Solutions <info@verussolutions.biz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class NewGenLib extends AbstractBase
{
    /**
     * Database connection
     *
     * @var PDO
     */
    protected $db;

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
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        try {
            $connectStr = 'pgsql:host=' . $this->config['Catalog']['hostname'] .
                ' user=' . $this->config['Catalog']['user'] .
                ' dbname=' . $this->config['Catalog']['database'] .
                ' password=' . $this->config['Catalog']['password'] .
                ' port=' . $this->config['Catalog']['port'];
            $this->db = new PDO($connectStr);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $RecordID The record id to retrieve the holdings for
     * @param array  $patron   Patron data
     * @param array  $options  Extra options (not currently used)
     *
     * @throws DateException
     * @throws ILSException
     * @return array           On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($RecordID, array $patron = null, array $options = [])
    {
        $holding = $this->getItemStatus($RecordID);
        for ($i = 0; $i < count($holding); $i++) {
            // add extra data
            $duedateql = 'select due_date from cir_transaction where ' .
                "accession_number='" . $holding[$i]['number'] .
                "' and document_library_id='" . $holding[$i]['library_id'] .
                "' and status='A'";
            try {
                $sqlStmt2 = $this->db->prepare($duedateql);
                $sqlStmt2->execute();
            } catch (PDOException $e1) {
                $this->throwAsIlsException($e1);
            }
            $duedate = '';
            while ($rowDD = $sqlStmt2->fetch(PDO::FETCH_ASSOC)) {
                $duedate = $rowDD['due_date'];
            }
            // add needed entries
            $holding[$i]['duedate'] = $duedate;
            // field with link to place holdings or recalls
            //$holding[$i]['link'] = "test";

            // remove not needed entries
            unset($holding[$i]['library_id']);
        }

        return $holding;
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
        $MyFines = [];
        $pid = $patron['cat_username'];
        $fine = 'Overdue';
        $LibId = 1;
        $mainsql = 'select d.volume_id as volume_id, c.status as status, ' .
            'v.volume_id as volume_id, d.accession_number as ' .
            'accession_number, v.cataloguerecordid as cataloguerecordid, ' .
            'v.owner_library_id as owner_library_id, c.patron_id as patron_id, ' .
            'c.due_date as due_date, c.ta_date as ta_date, c.fine_amt as ' .
            'fine_amt, c.ta_id as ta_id,c.library_id as library_id from ' .
            'document d,cat_volume v,cir_transaction c where ' .
            "d.volume_id=v.volume_id and v.owner_library_id='" . $LibId .
            "' and c.accession_number=d.accession_number and " .
            "c.document_library_id=d.library_id and c.patron_id='" .
            $pid . "' and c.status in('B','LOST') and c.fine_amt>0";

        try {
            $sqlStmt = $this->db->prepare($mainsql);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        $id = '';
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['cataloguerecordid'] . '_' . $row['owner_library_id'];
            $amount = $row['fine_amt'] * 100;
            $checkout = $row['ta_date'];
            $duedate = $row['due_date'];
            $paidamtsql = 'select sum(f.fine_amt_paid) as fine_amt_paid from ' .
                'cir_transaction_fine f where f.ta_id=' . $row['ta_id'] .
                ' and f.library_id=' . $row['library_id'];
            try {
                $sqlStmt1 = $this->db->prepare($paidamtsql);
                $sqlStmt1->execute();
            } catch (PDOException $e1) {
                $this->throwAsIlsException($e1);
            }
            $paidamt = '';
            $balance = '';
            while ($rowpaid = $sqlStmt1->fetch(PDO::FETCH_ASSOC)) {
                $paidamt = $rowpaid['fine_amt_paid'] * 100;
                $balance = $amount - $paidamt;
            }

            $MyFines[] = ['amount' => $amount,
                'checkout' => $checkout,
                'fine' => $fine,
                'balance' => $balance,
                'duedate' => $duedate,
                'id' => $id];
        }

        return $MyFines;
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
        $holds = [];
        $PatId = $patron['cat_username'];
        $LibId = 1;
        //SQL Statement
        $mainsql = 'select d.volume_id as volume_id, c.status as status, ' .
            'v.volume_id as volume_id, d.accession_number as accession_number, ' .
            'v.cataloguerecordid as cataloguerecordid, v.owner_library_id as ' .
            'owner_library_id, c.patron_id as patron_id from ' .
            'document d,cat_volume v,cir_transaction c where ' .
            "d.volume_id=v.volume_id and v.owner_library_id='" . $LibId .
            "' and c.accession_number=d.accession_number and " .
            "c.document_library_id=d.library_id and c.patron_id='" . $PatId .
            "' and c.status='C'";
        try {
            $sqlStmt = $this->db->prepare($mainsql);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $type = 'RECALLED ITEM - Return the item to the library';
            $rIdql = 'select due_date, ta_date from cir_transaction ' .
                "where patron_id='" . $row['patron_id'] . "'";
            try {
                $sqlStmt2 = $this->db->prepare($rIdql);
                $sqlStmt2->execute();
            } catch (PDOException $e1) {
                $this->throwAsIlsException($e1);
            }
            $RecordId = $row['cataloguerecordid'] . '_' . $row['owner_library_id'];
            $duedate = '';
            $tadate = '';
            while ($rowDD = $sqlStmt2->fetch(PDO::FETCH_ASSOC)) {
                $duedate = $rowDD['due_date'];
                $tadate = $rowDD['ta_date'];
            }
            $holds[] = ['type' => $type,
                'id' => $RecordId,
                'location' => null,
                'reqnum' => null,
                'expire' => $duedate . ' ' . $type,
                'create' => $tadate];
        }
        //SQL Statement 2
        $mainsql2 = 'select v.cataloguerecordid as cataloguerecordid, ' .
            'v.owner_library_id as owner_library_id, v.volume_id as volume_id, ' .
            'r.volume_id as volume_id, r.queue_no as queue_no, ' .
            'r.reservation_date as reservation_date, r.status as status ' .
            "from cir_reservation r, cat_volume v where r.patron_id='" . $PatId .
            "' and r.library_id='" . $LibId .
            "' and r.volume_id=v.volume_id and r.status in ('A', 'B')";
        try {
            $sqlStmt2 = $this->db->prepare($mainsql2);
            $sqlStmt2->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row2 = $sqlStmt2->fetch(PDO::FETCH_ASSOC)) {
            $location = '';
            $type2 = '';
            switch ($row2['status']) {
                case 'A':
                    $location = 'Checked out - No copy available in the library';
                    $type2 = $row2['queue_no'];
                    break;
                case 'B':
                    $location = 'Item available at the circulation desk';
                    $type2 = 'INTIMATED';
                    break;
            }
            $RecordId2 = $row2['cataloguerecordid'] . '_' .
                $row2['owner_library_id'];
            $holds[] = ['type' => $type2,
                'id' => $RecordId2,
                'location' => $location,
                'reqnum' => $row2['queue_no'],
                'expire' => null . ' ' . $type2,
                'create' => $row2['reservation_date']];
        }
        return $holds;
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
        $profile = null;
        $catusr = $patron['cat_username'];
        $catpswd = $patron['cat_password'];
        $sql = 'select p.patron_id as patron_id,p.user_password as ' .
            'user_password, p.fname as fname, p.lname as lname, p.address1 as ' .
            'address1, p.address2 as address2, p.pin as pin, p.phone1 as phone1 ' .
            "from patron p where p.patron_id='" . $catusr .
            "' and p.user_password='" . $catpswd . "'";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($catusr != $row['patron_id'] || $catpswd != $row['user_password']) {
                return null;
            } else {
                $profile = ['firstname' => $row['fname'],
                    'lastname' => $row['lname'],
                    'address1' => $row['address1'],
                    'address2' => $row['address2'],
                    'zip' => $row['pin'],
                    'phone' => $row['phone1'],
                    'group' => null];
            }
        }
        return $profile;
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
        $transactions = [];
        $PatId = $patron['cat_username'];
        $mainsql = 'select c.due_date as due_date, c.status as status, c.ta_id ' .
            'as ta_id, c.library_id as library_id, c.accession_number as ' .
            'accession_number, v.cataloguerecordid as cataloguerecordid, ' .
            'v.owner_library_id as owner_library_id, c.patron_id as ' .
            'patron_id from document d,cat_volume v,cir_transaction c where ' .
            "d.volume_id=v.volume_id and v.owner_library_id='1' and " .
            'c.accession_number=d.accession_number and ' .
            "c.document_library_id=d.library_id and c.patron_id='" .
            $PatId . "' and c.status in('A','C')";
        try {
            $sqlStmt = $this->db->prepare($mainsql);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $countql = 'select count(*) as total from cir_transaction c, ' .
                "cir_transaction_renewal r where r.ta_id='" . $row['ta_id'] .
                "' and r.library_id='" . $row['library_id'] .
                "' and c.status='A'";
            try {
                $sql = $this->db->prepare($countql);
                $sql->execute();
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }
            $RecordId = $row['cataloguerecordid'] . '_' . $row['owner_library_id'];
            $count = '';
            while ($srow = $sql->fetch(PDO::FETCH_ASSOC)) {
                $count = 'Renewed = ' . $srow['total'];
            }
            $transactions[] = ['duedate' => $row['due_date'] . ' ' . $count,
                'id' => $RecordId,
                'barcode' => $row['accession_number'],
                'renew' => $count,
                'reqnum' => null];
        }
        return $transactions;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $RecordID The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed           On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($RecordID)
    {
        $status = $this->getItemStatus($RecordID);
        if (!is_array($status)) {
            return $status;
        }
        // remove not needed entries within the items within the result array
        for ($i = 0; $i < count($status); $i++) {
            unset($status[$i]['number']);
            unset($status[$i]['barcode']);
            unset($status[$i]['library_id']);
        }
        return $status;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $StatusResult The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array              An array of getStatus() return values on success.
     */
    public function getStatuses($StatusResult)
    {
        $status = [];
        foreach ($StatusResult as $id) {
            $status[] = $this->getStatus($id);
        }
        return $status;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        //SQL Statement
        $sql = 'select p.patron_id as patron_id, p.library_id as library_id, ' .
            'p.fname as fname, p.lname as lname, p.user_password as ' .
            'user_password, p.membership_start_date as membership_start_date, ' .
            'p.membership_expiry_date as membership_expiry_date, p.email as ' .
            'email from patron p where p.patron_id=:patronId' .
            "' and p.user_password=:password and p.membership_start_date " .
            '<= current_date and p.membership_expiry_date > current_date';

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':patronId' => $username, ':password' => $password]);
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => $row['patron_id'],
            'firstname' => $row['fname'],
            'lastname' => $row['lname'],
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => $row['email'],
            'major' => null,
            'college' => null,
        ];
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
     * @throws ILSException
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // Do some initial work in solr so we aren't repeating it inside this loop.
        $retVal = [];
        $retVal[][] = [];

        $offset = ($page - 1) * $limit;
        $sql = 'select cataloguerecordid,owner_library_id from cataloguerecord ' .
            "where created_on + interval '$daysOld days' >= " .
            "current_timestamp offset $offset limit $limit";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        $results = [];
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['cataloguerecordid'] . '_' . $row['owner_library_id'];
            $results[] = $id;
        }
        $retVal = ['count' => count($results), 'results' => []];
        foreach ($results as $result) {
            $retVal['results'][] = ['id' => $result];
        }
        return $retVal;
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
     * Support method to get information about the items attached to a record
     *
     * @param string $RecordID Record ID
     *
     * @return array
     */
    protected function getItemStatus($RecordID)
    {
        $StatusResult = [];
        $pieces = explode('_', $RecordID);
        $CatId = $pieces[0];
        $LibId = $pieces[1];
        //SQL Statement
        $mainsql = 'select d.status as status, d.location_id as location_id, ' .
            'd.call_number as call_number, d.accession_number as accession_number,' .
            ' d.barcode as barcode, d.library_id as library_id from ' .
            'document d,cat_volume v where d.volume_id=v.volume_id and ' .
            "v.cataloguerecordid='" . $CatId . "' and v.owner_library_id=" . $LibId;

        try {
            $sqlSmt = $this->db->prepare($mainsql);
            $sqlSmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        $reserve = 'N';
        while ($row = $sqlSmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['status']) {
                case 'B':
                    $status = 'Available';
                    $available = true;
                    $reserve = 'N';
                    break;
                case 'A':
                    // Instead of relying on status = 'On holds shelf',
                    // I might want to see if:
                    // action.hold_request.current_copy = asset.copy.id
                    // and action.hold_request.capture_time is not null
                    // and I think action.hold_request.fulfillment_time is null
                    $status = 'Checked Out';
                    $available = false;
                    $reserve = 'N';
                    break;
                default:
                    $status = 'Not Available';
                    $available = false;
                    $reserve = 'N';
                    break;
            }
            $locationsql = "select location from location where location_id='" .
                $row['location_id'] . "' and library_id=" . $row['library_id'];
            try {
                $sqlSmt1 = $this->db->prepare($locationsql);
                $sqlSmt1->execute();
            } catch (PDOException $e1) {
                $this->throwAsIlsException($e1);
            }
            $location = '';
            while ($rowLoc = $sqlSmt1->fetch(PDO::FETCH_ASSOC)) {
                $location = $rowLoc['location'];
            }
            $StatusResult[] = ['id' => $RecordID,
                'status' => $status,
                'location' => $location,
                'reserve' => $reserve,
                'callnumber' => $row['call_number'],
                'availability' => $available,
                'number' => $row['accession_number'],
                'barcode' => $row['barcode'],
                'library_id' => $row['library_id']];
        }
        return $StatusResult;
    }
}
