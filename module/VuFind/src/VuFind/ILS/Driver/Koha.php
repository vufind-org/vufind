<?php
/**
 * Koha ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Ayesha Abed Library, BRAC University 2010.
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
 * @author   Altaf Mahmud, System Programmer <altaf.mahmud@gmail.com>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use PDO, PDOException, VuFind\Exception\ILS as ILSException;

/**
 * VuFind Driver for Koha (version: 3.02)
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Altaf Mahmud, System Programmer <altaf.mahmud@gmail.com>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Koha extends AbstractBase
{
    /**
     * Database connection
     *
     * @var PDO
     */
    protected $db;

    /**
     * ILS base URL
     *
     * @var string
     */
    protected $ilsBaseUrl;

    /**
     * Location codes
     *
     * @var array
     */
    protected $locCodes;

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

        //Connect to MySQL
        $this->db = new PDO(
            'mysql:host=' . $this->config['Catalog']['host'] .
            ';port=' . $this->config['Catalog']['port'] .
            ';dbname=' . $this->config['Catalog']['database'],
            $this->config['Catalog']['username'],
            $this->config['Catalog']['password'],
            [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
        );
        // Throw PDOExceptions if something goes wrong
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Return result set like mysql_fetch_assoc()
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        //Storing the base URL of ILS
        $this->ilsBaseUrl = $this->config['Catalog']['url'];

        // Location codes are defined in 'Koha.ini' file according to current
        // version (3.02)
        $this->locCodes = $this->config['Location_Codes'];
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
        $available = true;
        $duedate = $status = '';
        $inum = 0;
        $loc = $shelf = '';
        $sql = "select itemnumber as ITEMNO, location as LOCATION, " .
            "holdingbranch as HLDBRNCH, reserves as RESERVES, itemcallnumber as " .
            "CALLNO, barcode as BARCODE, copynumber as COPYNO, " .
            "notforloan as NOTFORLOAN from items where biblionumber = :id" .
            " order by itemnumber";
        try {
            $itemSqlStmt = $this->db->prepare($sql);
            $itemSqlStmt->execute([':id' => $id]);
            foreach ($itemSqlStmt->fetchAll() as $rowItem) {
                $inum = $rowItem['ITEMNO'];
                $sql = "select date_due as DUEDATE from issues " .
                    "where itemnumber = :inum";

                switch ($rowItem['NOTFORLOAN']) {
                case 0:
                    // If the item is available for loan, then check its current
                    // status
                    $issueSqlStmt = $this->db->prepare($sql);
                    $issueSqlStmt->execute([':inum' => $inum]);
                    $rowIssue = $issueSqlStmt->fetch();
                    if ($rowIssue) {
                        $available = false;
                        $status = 'Checked out';
                        $duedate = $rowIssue['DUEDATE'];
                    } else {
                        $available = true;
                        $status = 'Available';
                        // No due date for an available item
                        $duedate = '';
                    }
                    break;
                case 1: // The item is not available for loan
                default: $available = false;
                    $status = 'Not for loan';
                    $duedate = '';
                    break;
                }

                //Retrieving the full branch name
                if (null != ($loc = $rowItem['HLDBRNCH'])) {
                    $sql = "select branchname as BNAME from branches where " .
                        "branchcode = :loc";
                    $locSqlStmt = $this->db->prepare($sql);
                    $locSqlStmt->execute([':loc' => $loc]);
                    $row = $locSqlStmt->fetch();
                    if ($row) {
                        $loc = $row['BNAME'];
                    }
                } else {
                    $loc = "Unknown";
                }

                //Retrieving the location (shelf types)
                $shelf = $rowItem['LOCATION'];
                $loc = (null != $shelf)
                    ? $loc . ": " . $this->locCodes[$shelf]
                    : $loc . ": " . 'Unknown';

                //A default value is stored for null
                $holding[] = [
                    'id' => $id,
                    'availability' => $available,
                    'item_num' => $rowItem['ITEMNO'],
                    'status' => $status,
                    'location' => $loc,
                    'reserve' => (null == $rowItem['RESERVES'])
                        ? 'Unknown' : $rowItem['RESERVES'],
                    'callnumber' => (null == $rowItem['CALLNO'])
                        ? 'Unknown' : $rowItem['CALLNO'],
                    'duedate' => $duedate,
                    'barcode' => (null == $rowItem['BARCODE'])
                        ? 'Unknown' : $rowItem['BARCODE'],
                    'number' => (null == $rowItem['COPYNO'])
                        ? 'Unknown' : $rowItem['COPYNO']
                ];
            }
            return $holding;
        }
        catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $id      The id of the bib record
     * @param array  $details Item details from getHoldings return array
     *
     * @return string         URL to ILS's OPAC's place hold screen.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldLink($id, $details)
    {
        // Web link of the ILS for placing hold on the item
        return $this->ilsBaseUrl . "/cgi-bin/koha/opac-reserve.pl?biblionumber=$id";
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
        $sql = $sqlStmt = $row = '';
        $id = 0;
        $fineLst = [];
        try {
            $id = $patron['id'];
            $sql = "select round(accountlines.amount*100) as AMOUNT, " .
                "issues.issuedate as CHECKOUT, " .
                "accountlines.description as FINE, " .
                "round(accountlines.amountoutstanding*100) as BALANCE, " .
                "issues.date_due as DUEDATE, items.biblionumber as BIBNO " .
                "from accountlines join issues on " .
                "accountlines.borrowernumber = issues.borrowernumber and " .
                "accountlines.itemnumber = issues.itemnumber " .
                "join items on accountlines.itemnumber = items.itemnumber " .
                "where accountlines.borrowernumber = :id";
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $fineLst[] = [
                    'amount' => (null == $row['AMOUNT']) ? 0 : $row['AMOUNT'],
                    'checkout' => $row['CHECKOUT'],
                    'fine' => (null == $row['FINE']) ? 'Unknown' : $row['FINE'],
                    'balance' => (null == $row['BALANCE']) ? 0 : $row['BALANCE'],
                    'duedate' => $row['DUEDATE'],
                    'id' => $row['BIBNO']
                ];
            }
            return $fineLst;
        }
        catch (PDOException $e) {
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
        $sql = $sqlStmt = $row = '';
        $id = 0;
        $holdLst = [];
        try {
            $id = $patron['id'];
            $sql = "select reserves.biblionumber as BIBNO, " .
                "branches.branchname as BRNAME, " .
                "reserves.expirationdate as EXDATE, " .
                "reserves.reservedate as RSVDATE from reserves " .
                "join branches on reserves.branchcode = branches.branchcode " .
                "where reserves.borrowernumber = :id";
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $holdLst[] = [
                    'id' => $row['BIBNO'],
                    'location' => $row['BRNAME'],
                    'expire' => $row['EXDATE'],
                    'create' => $row['RSVDATE']
                ];
            }
            return $holdLst;
        }
        catch (PDOException $e) {
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
        $id = 0;
        $sql = $sqlStmt = $row = '';
        $profile = [];
        try {
            $id = $patron['id'];
            $sql = "select address as ADDR1, address2 as ADDR2, zipcode as ZIP, " .
                "phone as PHONE, categorycode as GRP from borrowers " .
                "where borrowernumber = :id";
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            $row = $sqlStmt->fetch();
            if ($row) {
                $profile = [
                    'firstname' => $patron['firstname'],
                    'lastname' => $patron['lastname'],
                    'address1' => $row['ADDR1'],
                    'address2' => $row['ADDR2'],
                    'zip' => $row['ZIP'],
                    'phone' => $row['PHONE'],
                    'group' => $row['GRP']
                ];
                return $profile;
            }
            return null;
        }
        catch (PDOException $e) {
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
        $id = 0;
        $transactionLst = [];
        $row = $sql = $sqlStmt = '';
        try {
            $id = $patron['id'];
            $sql = "select issues.date_due as DUEDATE, items.biblionumber as " .
                "BIBNO, items.barcode BARCODE, issues.renewals as RENEWALS " .
                "from issues join items on issues.itemnumber = items.itemnumber " .
                "where issues.borrowernumber = :id";
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $transactionLst[] = [
                    'duedate' => $row['DUEDATE'],
                    'id' => $row['BIBNO'],
                    'barcode' => $row['BARCODE'],
                    'renew' => $row['RENEWALS']
                ];
            }
            return $transactionLst;
        }
        catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
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
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idLst The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array       An array of getStatus() return values on success.
     */
    public function getStatuses($idLst)
    {
        $statusLst = [];
        foreach ($idLst as $id) {
            $statusLst[] = $this->getStatus($id);
        }
        return $statusLst;
    }

    /**
     * Get suppressed records.
     *
     * NOTE: This function needs to be modified only if Koha has
     *       suppressed records in OPAC view
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        // TODO
        return [];
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
        $patron = [];
        $row = '';

        $stored_hash = '';
        try {
            $sql = "select password from borrowers where userid = :username";
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':username' => $username]);
            $row = $sqlStmt->fetch();
            if ($row) {
                $stored_hash = $row['password'];
            } else {
                return null;
            }
        }
        catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        if ("$2a$" == substr($stored_hash, 0, 4)) {
            // Newer Koha version that uses bcrypt
            $db_pwd = crypt($password, $stored_hash);
        } else {
            // Koha used to use MD5_BASE64 encoding to save borrowers' passwords,
            // function 'rtrim' is used to discard trailing '=' signs, suitable for
            // pushing into MySQL database
            $db_pwd = rtrim(base64_encode(pack('H*', md5($password))), '=');
        }

        $sql = "select borrowernumber as ID, firstname as FNAME, " .
            "surname as LNAME, email as EMAIL from borrowers " .
            "where userid = :username and password = :db_pwd";
        
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':username' => $username, ':db_pwd' => $db_pwd]);
            $row = $sqlStmt->fetch();
            if ($row) {
                // NOTE: Here, 'cat_password' => $password is used, password is
                // saved in a clear text as user provided.  If 'cat_password' =>
                // $db_pwd was used, then password will be saved encrypted as in
                // 'borrowers' table of 'koha' database
                $patron = [
                    'id' => $row['ID'],
                    'firstname' => $row['FNAME'],
                    'lastname' => $row['LNAME'],
                    'cat_username' => $username,
                    'cat_password' => $password,
                    'email' => $row['EMAIL'],
                    'major' => null,
                    'college' => null
                ];

                return $patron;
            }
            return null;
        }
        catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }
}
