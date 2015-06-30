<?php
/**
 * Clavius SQL ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Josef Moravec, Municipal Library Ústí nad Orlicí 2012.
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
 * @author   Josef Moravec <josef.moravec@knihovna-uo.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use PDO, PDOException, VuFind\Exception\ILS as ILSException;

/**
 * VuFind Driver for Clavius SQL (version: 0.1 dev)
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Josef Moravec <josef.moravec@knihovna-uo.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class ClaviusSQL extends AbstractBase
{
    /**
     * Database connection.
     *
     * @var object
     */
    protected $db;

    /**
     * URL to Clavius original katalog
     *
     * @var string
     */
    protected $ilsBaseUrl;

    /**
     * Library prefix in czech library system - used for ids and barcodes
     *
     * @var string
     */
    protected $prefix;

    /**
     * If is library prefix used also for record ids in vufind
     *
     * @var bool
     */
    protected $idPrefix;

    /**
     * If is library using manually entered barcodes
     *
     * @var bool
     */
    protected $useBarcodes;

    /**
     * Library departments and branches, filled from database by getDepartments
     *
     * @var array
     */
    protected $locations;

    /**
     * How many days is new document hidden in catalog
     *
     * @var integer
     */
    protected $hideNewItemsDays;

    /**
     * Fine codes and descriptions, filled from database by getFineTypes
     *
     * @var array
     */
    protected $fineTypes;

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
            $this->config['Catalog']['password']
        );
        // Throw PDOExceptions if something goes wrong
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Return result set like mysql_fetch_assoc()
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        //character set utf8
        $this->db->exec("SET NAMES utf8");
        //Storing the base URL of ILS
        $this->ilsBaseUrl = $this->config['Catalog']['url'];
        //Boolean - use id prefixes like "KN31120" or not
        $this->idPrefix = $this->config['Catalog']['id_prefix'];
        //Boolean - indicates if library uses barcodes (true), or if barcodes are
        // generated automatically (false)
        $this->useBarcodes = $this->config['Catalog']['use_barcodes'];
        //set number prefix for library
        $this->prefix = $this->config['Catalog']['prefix'];
        //how long (in days) hide new items
        $this->hideNewItemsDays = 0;
        if (isset($this->config['Catalog']['hide_days'])) {
             $this->c = $this->config['Catalog']['hide_days'];
        }
    }

     /**
      * Get list of departments
      *
      * This method queries the ILS for a list of departments to be used as input
      * to the findReserves method
      *
      * @throws ILSException
      *
      * @return array An associative array with key = department ID,
      * value = department name.
      */
    public function getDepartments()
    {
        if (!is_array($this->locations)) {
            $this->locations = [];
            try {
                // TODO - overit/upavit funkcnost na MSSQL a Oracle
                $sqlLoc = "SELECT TRIM(lokace) as lokace, TRIM(jmeno) as jmeno " .
                    "FROM deflok ORDER BY lokace";
                $sqlSt = $this->db->prepare($sqlLoc);
                $sqlSt->execute();
                foreach ($sqlSt->fetchAll() as $l) {
                    $this->locations[$l["lokace"]] = $l["jmeno"];
                }
            } catch (PDOException $e) {
                throw new ILSException($e->getMessage());
            }
        }
        return $this->locations;
    }
    /**
      * Get list of fine types
      *
      * This method queries the ILS for a list of fine types
      *
      * @throws ILSException
      *
      * @return array An associative array with key = fine code,
      * value = fine description
      */
    public function getFineTypes()
    {
        if (!is_array($this->fineTypes)) {
            $this->fineTypes = ["G" => "Registracní poplatek",
                                    "H" => "Upomínka",
                                    "J" => "Poplatek za rezervaci",
                                    "L" => "Poplatek za pujcení",
                                    "M" => "Kauce za výpujcku"
                                    ];
            // TODO MSsql Oracle
            $sql = "SELECT kod, nazev FROM defpopl";
            try {
                $sqlSt = $this->db->prepare($sql);
                $sqlSt->execute();
                foreach ($sqlSt->fetchAll() as $row) {
                    $this->fineTypes[$row['kod']] = $row['nazev'];
                }
            } catch (PDOException $e) {
                throw new ILSException($e->getMessage());
            }
        }
        return $this->fineTypes;
    }

    /**
      * Get a list of funds that can be used to limit the "new item" search
      *
      * @throws ILSException
      *
      * @return array An associative array with key = fund ID, value = fund name.
      */
    public function getFunds()
    {
        return $this->getDepartments();
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
      * @return array       An associative array with two keys: 'count' (the number
      * of items in the 'results' array) and 'results' (an array of associative
      * arrays, each with a single key: 'id', a record ID).
      */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $limitFrom = ($page - 1) * $limit;
        //TODO better escaping; mssql, oracle
        $sql = "SELECT t.tcislo as tcislo, t.druhdoku as druhdoku "
            . "FROM svazky s JOIN tituly t ON s.tcislo = t.tcislo "
            . "WHERE s.datumvloz > DATE_SUB(CURDATE(),INTERVAL "
            . $this->db->quote($daysOld)
            . " DAY) AND s.datumvloz <= DATE_SUB(CURDATE(),INTERVAL "
            . $this->db->quote($this->hideNewItemsDays) . " DAY)";
        if ($fundId) {
            $sql .= " AND s.lokace = " . $this->db->quote($fundId);
        }
        $sql .= " ORDER BY s.datumvloz DESC LIMIT $limitFrom, $limit";
        try {
            $sqlSt = $this->db->prepare($sql);
            $sqlSt->execute();
            $result = $sqlSt->fetchAll();
            $return = ['count' => count($result), 'results' => []];
            foreach ($result as $row) {
                $return['results'][] = [
                    'id' => $this->getLongId($row['tcislo'], $row['druhdoku'])
                ];
            }
            return $return;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
      * Get Short ID
      *
      * This method make short id (only title number - tcislo), from full identifier
      *
      * @param string $id The full record id
      *
      * @return string    Short id
      */
    protected function getShortID($id)
    {
        $shortId = $id;
        if ($this->idPrefix) {
            $shortId = ltrim(substr($id, -11), "0");
        }
        return $shortId;
    }

    /**
      * Get Long ID
      *
      * This method make long id (full identifier) from short id (only title number)
      *
      * @param string $id        The short record id
      * @param string $docPrefix Two chars prefix indicated type of document
      *
      * @return string           Long id
      */
    protected function getLongID($id, $docPrefix = "KN")
    {
        $longId = $id;
        if ($this->idPrefix) {
            $prefix1 = $docPrefix . $this->prefix;
            $longId = $prefix1
                . str_pad($id, 18 - strlen($prefix1), "0", STR_PAD_LEFT);
        }
        return $longId;
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
      * keys: id, availability (bool), status, location, reserve, callnumber,
      * duedate, number, barcode.
      *
      * todo: reserve
      */
    public function getHolding($id, array $patron = null)
    {
        $holding = [];
        $originalId = $id;
        //if ($this->idPrefix) { $id = ltrim(substr($id, -11), "0"); }
        $id = $this->getShortID($id);
        // TODO - overit/upavit funkcnost na MSSQL a Oracle
        $sql = "SELECT trim(pcislo) as number, TRIM(lokace) as location, scislo, "
            . "TRIM(sign) as callnumber, TRIM(ckod) as barcode "
            . "FROM svazky WHERE tcislo = :id ORDER BY number";
        $sql2 = "SELECT (co = 'V') as availability, "
            . "IF(co = 'P',DATE_FORMAT(datum2,'%e. %c. %Y'),'') as duedate, "
            . "IF(co = 'V', 'Available', 'Checked Out') as status "
            . "FROM kpujcky WHERE scislo = :scislo ORDER BY datum2 DESC LIMIT 1";
        try {
            $sqlSt = $this->db->prepare($sql);
            $sqlSt->execute([':id' => $id]);
            $sqlSt2 = $this->db->prepare($sql2);
            /**** TODO reserve  *******/
            foreach ($sqlSt->fetchAll() as $item) {
                $reserve = "N";
                $sqlSt2->execute([':scislo' => $item['scislo']]);
                $item2 = $sqlSt2->fetch();
                if (!$item2) {
                    $availability = true;
                    $status = "K dispozici";
                    $duedate = '';
                } else {
                    $availability = ($item2['availability'] == 1) ? true : false;
                    $status = $item2['status'];
                    $duedate = $item2['duedate'];
                }
                $locs = $this->getDepartments();
                $holding[] = [
                    'id' => $originalId,
                    //'location' => $item['location'],
                    'location' => $locs[$item['location']],
                    'callnumber' => ($item['callnumber'] == "")
                        ? null : $item['callnumber'],
                    'number' => intval($item['number']),
                    'barcode' => ($this->useBarcodes)
                        ? $item['barcode'] : $item['number'],
                    'availability' => $availability,
                    'status' => $status,
                    'duedate' => $duedate,
                    'reserve' => $reserve,
                ];
            }
            return $holding;
        } catch (PDOException $e) {
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
        return $this->ilsBaseUrl . "l.dll?clr~" . $this->getShortID($id);
    }

    /**
      * Get Patron Fines
      *
      * This method queries the ILS for a patron's current fines
      *
      * @param array $patron The patron array from patronLogin
      *
      * @throws \VuFind\Exception\Date
      * @throws ILSException
      * @return mixed        Array associative arrays of the patron's fines on
      * success.
      * <ul>
      *   <li>amount - The total amount of the fine IN PENNIES. Be sure to adjust
      * decimal points appropriately (i.e. for a $1.00 fine, amount should be set to
      * 100).</li>
      *   <li>checkout - A string representing the date when the item was checked
      * out.</li>
      *   <li>fine - A string describing the reason for the fine (i.e. "Overdue",
      * "Long Overdue").</li>
      *   <li>balance - The unpaid portion of the fine IN PENNIES.</li>
      *   <li>createdate - A string representing the date when the fine was accrued
      * (optional)</li>
      *   <li>duedate - A string representing the date when the item was due.</li>
      *   <li>id - The bibliographic ID of the record involved in the fine.</li>
      * </ul>
      */
    public function getMyFines($patron)
    {
        $fines = [];
        $reasons = $this->getFineTypes();
        // TODO mssql, oracle
        $sql = "SELECT scislo as amount, co as reason, "
            . "DATE_FORMAT(datum,'%e. %c. %Y') as createdate "
            . "FROM poplatky WHERE ccislo = :patronId ORDER BY datum DESC";
        try {
            $sqlSt = $this->db->prepare($sql);
            $sqlSt->execute([':patronId' => $patron['id']]);
            foreach ($sqlSt->fetchAll() as $fine) {
                $fines[] = [
                    'amount' => abs($fine['amount']),
                    'checkout' => null, // TODO maybe
                    'fine' => $reasons[$fine['reason']],
                    'balance' => ($fine['amount'] < 0) ? abs($fine['amount']) : 0,
                    'createdate' => $fine['createdate'],
                    'duedate' => null, // TODO maybe
                    'id' => null,        // TODO maybe
                ];
            }
            return $fines;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Pick Up Locations
     *
     * This method returns a list of locations where a user may collect a hold.
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
        $locations = [];
        foreach ($this->getDepartments() as $id => $text) {
            $locations[] = ['locationID' => $id,
                                'locationDisplay' => $text
                                ];
        }
        return $locations;
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
     * @return array        Array of associative arrays, one for each hold associated
     *      with the specified account. Each associative array contains these keys:
     * <ul>
     *   <li>type - A string describing the type of hold - i.e. hold vs. recall
     * (optional).</li>
     *   <li>id - The bibliographic record ID associated with the hold
     * (optional).</li>
     *   <li>location - A string describing the pickup location for the held item
     * (optional). In VuFind 1.2, this should correspond with a locationID value from
     * getPickUpLocations. In VuFind 1.3 and later, it may be either a locationID
     * value or a raw ready-to-display string.</li>
     *   <li>reqnum - A control number for the request (optional).</li>
     *   <li>expire - The expiration date of the hold (a string).</li>
     *   <li>create - The creation date of the hold (a string).</li>
     *   <li>position - The position of the user in the holds queue (optional)</li>
     *   <li>available - Whether or not the hold is available (true) or not (false)
     * (optional)</li>
     *   <li>item_id - The item id the request item (optional).</li>
     *   <li>volume - The volume number of the item (optional)</li>
     *   <li>publication_year - The publication year of the item (optional)</li>
     *   <li>title - The title of the item (optional - only used if the record
     * cannot be found in VuFind's index).</li>
     * </ul>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyHolds($patron)
    {
        // TODO
        return [];
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
            firstname
            lastname
            address1
            address2
            zip
            phone
            group - i.e. Student, Staff, Faculty, etc.
     */
    public function getMyProfile($patron)
    {
        $profile = [];
        $sql = "SELECT jmeno, tulice, tmesto, tpsc, telefon "
            . "FROM ctenari WHERE ccislo = :userId";
        try {
            $sqlSt = $this->db->prepare($sql);
            $sqlSt->execute([':userId' => $patron['id']]);
            $patron2 = $sqlSt->fetch();
            $names = $this->explodeName($patron2['jmeno']);
            if ($patron2) {
                $profile = [
                    'firstname' => $names['firstname'],
                    'lastname' => $names['lastname'],
                    'address1' => $patron2['tulice'],
                    'address2' => $patron2['tmesto'],
                    'zip' => $patron2['tpsc'],
                    'phone' => $patron2['telefon'] ? $patron2['telefon'] : null,
                    'group' => null              //TODO - Maybe
                ];
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
        return $profile;
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
     * id, availability (bool), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $statuses = $this->getHolding($id);
        foreach ($statuses as $status) {
            $status['status'] = ($status['availability'])
                ? 'Available' : 'Unavailable';
        }
        return $statuses;
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
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        // TODO - MAYBE
        return [];
    }

    /**
     * Get first and last name from name
     *
     * @param string $name Full Patron Name
     *
     * @return array associative array with keys firstname, lastname
     */
    protected function explodeName($name)
    {
        $names = [];
        $nameArray = explode(" ", $name);
        $names['lastname'] = array_pop($nameArray);
        $names['firstname'] = implode(" ", $nameArray);
        return $names;
    }

    /**
     * Replace codes
     *
     * @param string $stringToCode encoded/decoded String
     * @param bool   $decode       true if you want to decode string
     *
     * @return string       coded string
     */
    protected function replaceCodes($stringToCode, $decode = false)
    {
        $from = str_split(
            "()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ"
            . "[\\]^_`abcdefghijklmnopqrstuvwxyz{|}"
        );
        $to = str_split(
            "g5p{+.Yt|Cy8(oM)^LTuE-\\1OKxPwv=9@:n7adb2QkIG6XcVe]"
            . "[,/ziS3Hf?m0<ZrD_ljA}4FU>Js*WBNhR`;q"
        );
        if ($decode) {
            $coding = array_combine($to, $from);
        } else {
            $coding = array_combine($from, $to);
        }
        $toCodeArray = str_split($stringToCode);
        $output = "";
        foreach ($toCodeArray as $char) {
            $output .= $coding[$char];
        }
        return $output;
    }

    /**
     * Encode password
     *
     * @param string $password password given by user
     * @param bool   $woman    true if user is woman
     *
     * @return string          encoded password
     */
    protected function encodePassword($password, $woman = false)
    {
        $password = str_pad($password, 6);
        $kod3 = substr($password, 2, 1);
        $kod3int = intval($kod3);
        if ($kod3int > 4) {
            $kod3int = $kod3int - 5;
        }
        substr_replace($password, chr($kod3int), 2);
        $sexConstant = $woman ? 1 : 0;
        $kod6 = substr($password, 5, 1);
        $kod6r = chr(70 + (intval($kod6) * 2) + $sexConstant);
        if ($kod6 != " ") {
            $password = substr_replace($password, $kod6r, 5);
        }
        $password = trim($password);
        $encoded = $this->replaceCodes($password);
        return $encoded;
    }

    /**
      * Encode PIN number
      *
      * @param string $pin    password given by user
      * @param string $patron number of patron
      *
      * @return long          encoded pin
      */
    protected function encodePin($pin, $patron)
    {
        for ($i = 0; $i < strlen($pin); $i++) {
            $char = substr($pin, $i, 1);
            $return = 1 + intval($char) + $return * 12;
        }
        return 2109876543 -  $return * 7 * (intval($patron) % 89 + 7);
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
        // TODO - oracle a mssql
        $sqlPatron = "SELECT ccislo, jmeno, mail, SUBSTRING(rcislo,1,6) as rcislo,"
            . " pin, pohlavi FROM ctenari WHERE ccislo = :userId";
        try {
            $sqlStPatron = $this->db->prepare($sqlPatron);
            $sqlStPatron->execute([':userId' => $username]);
            $patronRow = $sqlStPatron->fetch();
            if (!$patronRow) {
                return null;
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
        if ($patronRow['pin'] == "0") {
            $encodedPassword
                = $this->encodePassword($password, $patronRow['pohlavi']);
            if ($encodedPassword != $patronRow['rcislo']) {
                return null;
            }
        } else {
            $encodedPin = $this->encodePin($password, $patronRow['ccislo']);
            if ($encodedPin != $patronRow['pin']) {
                return null;
            }
        }
        $names = $this->explodeName($patronRow['jmeno']);
        $patron = [
            'id' => $patronRow['ccislo'],
            'firstname' => $names['firstname'],
            'lastname' => $names['lastname'],
            'cat_username' => $username,
            'cat_password' => $password,
            'email' => $patronRow['mail'] ? $patronRow['mail'] : null,
            'major' => null,
            'college' => null
        ];
        return $patron;
    }

    /**
     * Get Patron Transactions
     *
     * This method queries the ILS for a patron's current checked out items
     *
     * @param array $user    The patron array from patronLogin
     * @param bool  $history Include history of transactions (true) or just get
     * current ones (false).
     *
     * @throws ILSException
     * @return array   Array of the patron's transactions on success.
     * <ul>
     *   <li>duedate - The item's due date (a string).</li>
     *   <li>id - The bibliographic ID of the checked out item.</li>
     *   <li>barcode - The barcode of the item (optional).</li>
     *   <li>renew - The number of times the item has been renewed (optional).</li>
     *   <li>request - The number of pending requests for the item (optional).</li>
     *   <li>volume - The volume number of the item (optional)</li>
     *   <li>publication_year - The publication year of the item (optional)</li>
     *   <li>renewable - Whether or not an item is renewable (required for
     * renewals)</li>
     *   <li>message - A message regarding the item (optional)</li>
     *   <li>title - The title of the item (optional - only used if the record
     * cannot be found in VuFind's index).</li>
     *   <li>item_id - this is used to match up renew responses and must match
     * the item_id in the renew response</li>
     * </ul>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyTransactions($user, $history = false)
    {
        //TODO mssql a Oracle
        $sql = "SELECT DATE_FORMAT(k.datum2,'%e. %c. %Y') as duedate,
                TRIM(s.ckod) as barcode, t.druhdoku as druhdoku, t.tcislo as tcislo,
                t.rokvydani as year, CONCAT(t.nazev, t.big_nazev) as title,
                TRIM(s.pcislo) as item_id
                FROM kpujcky k
                JOIN svazky s
                ON s.scislo = k.scislo
                JOIN tituly t
                ON s.tcislo = t.tcislo
                WHERE k.ccislo = :userId AND k.co = :action";
        try {
            $sqlSt = $this->db->prepare($sql);
            $sqlSt->execute([':userId' => $user['id'], ':action' => 'P']);
            $transactions = [];
            foreach ($sqlSt->fetchAll() as $item) {
                $id = $this->getLongId($item['tcislo'], $item['druhdoku']);
                //TODO - requests
                //$requestsSql = "";
                $transactions[] = [
                    'duedate' => $item['duedate'],
                    'id' => $id,
                    'barcode' => $item['duedate'],
                    'renew' => null,
                    'request' => null, // TODO
                    'volume' => null,  // TODO - maybe
                    'publication_year' => $item['year'],
                    'renewable' => null,  //TODO maybe - for renewals
                    'message' => '',
                    'title' => $item['title'],
                    'item_id' => $item['item_id']  // TODO - maybe for renewals
                ];
            }
            return $transactions;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }
}
