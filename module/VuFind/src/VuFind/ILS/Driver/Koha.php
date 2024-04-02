<?php

/**
 * Koha ILS Driver
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Altaf Mahmud, System Programmer <altaf.mahmud@gmail.com>
 * @author   David Maus <maus@hab.de>
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
 * VuFind Driver for Koha (version: 3.02)
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Altaf Mahmud, System Programmer <altaf.mahmud@gmail.com>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
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
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter = null;

    /**
     * Should we validate passwords against Koha system?
     *
     * @var boolean
     */
    protected $validatePasswords;

    /**
     * Default terms for block types, can be overridden by configuration
     *
     * @var array
     */
    protected $blockTerms = [
        'SUSPENSION' => 'Account Suspended',
        'OVERDUES' => 'Account Blocked (Overdue Items)',
        'MANUAL' => 'Account Blocked',
        'DISCHARGE' => 'Account Blocked for Discharge',
    ];

    /**
     * Display comments for patron debarments, see Koha.ini
     *
     * @var array
     */
    protected $showBlockComments;

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

        // If we are using SAML/Shibboleth for authentication for both ourselves
        // and Koha then we can't validate the patrons passwords against Koha as
        // they won't have one. (Double negative logic used so that if the config
        // option isn't present in Koha.ini then ILS passwords will be validated)
        $this->validatePasswords
            = empty($this->config['Catalog']['dontValidatePasswords']);

        // Now override the default with any defined in the `Koha.ini` config file
        foreach (['SUSPENSION','OVERDUES','MANUAL','DISCHARGE'] as $blockType) {
            if (!empty($this->config['Blocks'][$blockType])) {
                $this->blockTerms[$blockType] = $this->config['Blocks'][$blockType];
            }
        }

        // Allow the users to set if an account block's comments should be included
        // by setting the block type to true or false () in the `Koha.ini` config
        // file (defaults to false if not present)
        $this->showBlockComments = [];

        foreach (['SUSPENSION','OVERDUES','MANUAL','DISCHARGE'] as $blockType) {
            $this->showBlockComments[$blockType]
                = !empty($this->config['Show_Block_Comments'][$blockType]);
        }
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
        $available = true;
        $duedate = $status = '';
        $inum = 0;
        $loc = $shelf = '';
        $sql = 'select itemnumber as ITEMNO, location as LOCATION, ' .
            'holdingbranch as HLDBRNCH, reserves as RESERVES, itemcallnumber as ' .
            'CALLNO, barcode as BARCODE, copynumber as COPYNO, ' .
            'enumchron AS ENUMCHRON, notforloan as NOTFORLOAN' .
            ' from items where biblionumber = :id' .
            ' order by itemnumber';
        try {
            $itemSqlStmt = $this->db->prepare($sql);
            $itemSqlStmt->execute([':id' => $id]);
            foreach ($itemSqlStmt->fetchAll() as $rowItem) {
                $inum = $rowItem['ITEMNO'];
                $sql = 'select date_due as DUEDATE from issues ' .
                    'where itemnumber = :inum';

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
                            $duedate = $this->displayDateTime($rowIssue['DUEDATE']);
                        } else {
                            $available = true;
                            $status = 'Available';
                            // No due date for an available item
                            $duedate = '';
                        }
                        break;
                    case 1: // The item is not available for loan
                    default:
                        $available = false;
                        $status = 'Not for loan';
                        $duedate = '';
                        break;
                }

                //Retrieving the full branch name
                if (null != ($loc = $rowItem['HLDBRNCH'])) {
                    $sql = 'select branchname as BNAME from branches where ' .
                        'branchcode = :loc';
                    $locSqlStmt = $this->db->prepare($sql);
                    $locSqlStmt->execute([':loc' => $loc]);
                    $row = $locSqlStmt->fetch();
                    if ($row) {
                        $loc = $row['BNAME'];
                    }
                } else {
                    $loc = 'Unknown';
                }

                //Retrieving the location (shelf types)
                $shelf = $rowItem['LOCATION'];
                $loc = (null != $shelf)
                    ? $loc . ': ' . ($this->locCodes[$shelf] ?? $shelf)
                    : $loc . ': ' . 'Unknown';

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
                        ? 'Unknown' : $rowItem['COPYNO'],
                    'enumchron'    => $rowItem['ENUMCHRON'] ?? null,
                ];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $holding;
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
     * @throws DateException
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
            $sql = 'select round(accountlines.amount*100) as AMOUNT, ' .
                'issues.issuedate as CHECKOUT, ' .
                'accountlines.description as FINE, ' .
                'round(accountlines.amountoutstanding*100) as BALANCE, ' .
                'issues.date_due as DUEDATE, items.biblionumber as BIBNO ' .
                'from accountlines join issues on ' .
                'accountlines.borrowernumber = issues.borrowernumber and ' .
                'accountlines.itemnumber = issues.itemnumber ' .
                'join items on accountlines.itemnumber = items.itemnumber ' .
                'where accountlines.borrowernumber = :id';
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $fineLst[] = [
                    'amount' => (null == $row['AMOUNT']) ? 0 : $row['AMOUNT'],
                    'checkout' => $this->displayDate($row['CHECKOUT']),
                    'fine' => (null == $row['FINE']) ? 'Unknown' : $row['FINE'],
                    'balance' => (null == $row['BALANCE']) ? 0 : $row['BALANCE'],
                    'duedate' => $this->displayDate($row['DUEDATE']),
                    'id' => $row['BIBNO'],
                ];
            }
            return $fineLst;
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
        $sql = $sqlStmt = $row = '';
        $id = 0;
        $holdLst = [];
        try {
            $id = $patron['id'];
            $sql = 'select reserves.biblionumber as BIBNO, ' .
                'branches.branchname as BRNAME, ' .
                'reserves.expirationdate as EXDATE, ' .
                'reserves.reservedate as RSVDATE from reserves ' .
                'join branches on reserves.branchcode = branches.branchcode ' .
                'where reserves.borrowernumber = :id';
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $holdLst[] = [
                    'id' => $row['BIBNO'],
                    'location' => $row['BRNAME'],
                    'expire' => $this->displayDate($row['EXDATE']),
                    'create' => $this->displayDate($row['RSVDATE']),
                ];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $holdLst;
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
            $sql = 'select address as ADDR1, address2 as ADDR2, zipcode as ZIP, ' .
                'phone as PHONE, categorycode as GRP from borrowers ' .
                'where borrowernumber = :id';
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
                    'group' => $row['GRP'],
                ];
                return $profile;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return null;
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
        $id = 0;
        $transactionLst = [];
        $row = $sql = $sqlStmt = '';
        try {
            $id = $patron['id'];
            $sql = 'select issues.date_due as DUEDATE, items.biblionumber as ' .
                'BIBNO, items.barcode BARCODE, issues.renewals as RENEWALS ' .
                'from issues join items on issues.itemnumber = items.itemnumber ' .
                'where issues.borrowernumber = :id';
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $transactionLst[] = [
                    'duedate' => $this->displayDateTime($row['DUEDATE']),
                    'id' => $row['BIBNO'],
                    'barcode' => $row['BARCODE'],
                    'renew' => $row['RENEWALS'],
                ];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $transactionLst;
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin
     *
     * @throws ILSException
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        $blocks = [];

        try {
            $id = $patron['id'];
            $sql = 'select type as TYPE, comment as COMMENT ' .
                'from borrower_debarments ' .
                'where (expiration is null or expiration >= NOW()) ' .
                'and borrowernumber = :id';
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);

            foreach ($sqlStmt->fetchAll() as $row) {
                $block = empty($this->blockTerms[$row['TYPE']])
                    ? [$row['TYPE']]
                    : [$this->blockTerms[$row['TYPE']]];

                if (
                    !empty($this->showBlockComments[$row['TYPE']])
                    && !empty($row['COMMENT'])
                ) {
                    $block[] = $row['COMMENT'];
                }

                $blocks[] = implode(' - ', $block);
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return count($blocks) ? $blocks : false;
    }

    /**
     * Get Patron Loan History
     *
     * This is responsible for retrieving all historic loans (i.e. items previously
     * checked out and then returned), for a specific patron.
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
        $id = 0;
        $historicLoans = [];
        $row = $sql = $sqlStmt = '';
        try {
            $id = $patron['id'];

            // Get total count first
            $sql = 'select count(*) as cnt from old_issues ' .
                'where old_issues.borrowernumber = :id';
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':id' => $id]);
            $totalCount = $sqlStmt->fetch()['cnt'];

            // Get rows
            $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
            $start = isset($params['page'])
                ? ((int)$params['page'] - 1) * $limit : 0;
            if (isset($params['sort'])) {
                $parts = explode(' ', $params['sort'], 2);
                switch ($parts[0]) {
                    case 'return':
                        $sort = 'RETURNED';
                        break;
                    case 'due':
                        $sort = 'DUEDATE';
                        break;
                    default:
                        $sort = 'ISSUEDATE';
                        break;
                }
                $sort .= isset($parts[1]) && 'asc' === $parts[1] ? ' asc' : ' desc';
            } else {
                $sort = 'ISSUEDATE desc';
            }
            $sql = 'select old_issues.issuedate as ISSUEDATE, ' .
                'old_issues.date_due as DUEDATE, items.biblionumber as ' .
                'BIBNO, items.barcode BARCODE, old_issues.returndate as RETURNED, ' .
                'biblio.title as TITLE ' .
                'from old_issues join items ' .
                'on old_issues.itemnumber = items.itemnumber ' .
                'join biblio on items.biblionumber = biblio.biblionumber ' .
                'where old_issues.borrowernumber = :id ' .
                "order by $sort limit $start,$limit";
            $sqlStmt = $this->db->prepare($sql);

            $sqlStmt->execute([':id' => $id]);
            foreach ($sqlStmt->fetchAll() as $row) {
                $historicLoans[] = [
                    'title' => $row['TITLE'],
                    'checkoutDate' => $this->displayDateTime($row['ISSUEDATE']),
                    'dueDate' => $this->displayDateTime($row['DUEDATE']),
                    'id' => $row['BIBNO'],
                    'barcode' => $row['BARCODE'],
                    'returnDate' => $this->displayDateTime($row['RETURNED']),
                ];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return [
            'count' => $totalCount,
            'transactions' => $historicLoans,
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
            $sql = 'select password from borrowers where userid = :username';
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute([':username' => $username]);
            $row = $sqlStmt->fetch();
            if ($row) {
                $stored_hash = $row['password'];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        if (str_starts_with($stored_hash, '$2a$')) {
            // Newer Koha version that uses bcrypt
            $db_pwd = crypt($password, $stored_hash);
        } else {
            // Koha used to use MD5_BASE64 encoding to save borrowers' passwords,
            // function 'rtrim' is used to discard trailing '=' signs, suitable for
            // pushing into MySQL database
            $db_pwd = rtrim(base64_encode(pack('H*', md5($password))), '=');
        }

        $sql = 'select borrowernumber as ID, firstname as FNAME, ' .
            'surname as LNAME, email as EMAIL from borrowers ' .
            'where userid = :username';

        $parameters = [':username' => $username];

        if ($this->validatePasswords) {
            $sql .= ' and password = :db_pwd';
            $parameters[':db_pwd'] = $db_pwd;
        }

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($parameters);

            $row = $sqlStmt->fetch();
            if ($row) {
                // NOTE: Here, 'cat_password' => $password is used, password is
                // saved in a clear text as user provided. If 'cat_password' =>
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
                    'college' => null,
                ];

                return $patron;
            }
            return null;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
    }

    /**
     * Convert a database date to a displayable date.
     *
     * @param string $date Date to convert
     *
     * @return string
     */
    public function displayDate($date)
    {
        if (empty($date)) {
            return '';
        } elseif (preg_match("/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/", $date) === 1) {
            // YYYY-MM-DD HH:MM:SS
            return $this->dateConverter->convertToDisplayDate('Y-m-d H:i:s', $date);
        } elseif (preg_match("/^\d{4}-\d{2}-\d{2}$/", $date) === 1) { // YYYY-MM-DD
            return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
        } else {
            error_log("Unexpected date format: $date");
            return $date;
        }
    }

    /**
     * Convert a database datetime to a displayable date and time.
     *
     * @param string $date Datetime to convert
     *
     * @return string
     */
    public function displayDateTime($date)
    {
        if (empty($date)) {
            return '';
        } elseif (preg_match("/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/", $date) === 1) {
            // YYYY-MM-DD HH:MM:SS
            return
                $this->dateConverter->convertToDisplayDateAndTime(
                    'Y-m-d H:i:s',
                    $date
                );
        } else {
            error_log("Unexpected date format: $date");
            return $date;
        }
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
            return [
                'max_results' => 100,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'return desc' => 'sort_return_date_desc',
                    'return asc' => 'sort_return_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                ],
                'default_sort' => 'checkout desc',
            ];
        }
        return $this->config[$function] ?? false;
    }
}
