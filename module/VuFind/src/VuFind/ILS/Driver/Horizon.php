<?php

/**
 * Horizon ILS Driver
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
 * @author   Matt Mackey <vufind-tech@lists.sourceforge.net>
 * @author   Ray Cummins <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use Laminas\Log\LoggerAwareInterface;
use PDO;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Log\LoggerAwareTrait;

use function count;
use function in_array;
use function intval;

/**
 * Horizon ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Matt Mackey <vufind-tech@lists.sourceforge.net>
 * @author   Ray Cummins <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Horizon extends AbstractBase implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateFormat;

    /**
     * Database connection
     *
     * @var PDO
     */
    protected $db;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateFormat = $dateConverter;
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

        // Connect to database
        try {
            $this->db = new PDO(
                'dblib:host=' . $this->config['Catalog']['host'] .
                ':' . $this->config['Catalog']['port'] .
                ';dbname=' . $this->config['Catalog']['database'],
                $this->config['Catalog']['username'],
                $this->config['Catalog']['password']
            );

            // throw an exception instead of false on sql errors
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException(
                $e,
                'ILS Configuration problem : ' . $e->getMessage()
            );
        }
    }

    /**
     * Protected support method for building sql strings.
     *
     * @param array $sql An array of keyed sql data
     *
     * @return array               An string query string
     */
    protected function buildSqlFromArray($sql)
    {
        $modifier = isset($sql['modifier']) ? $sql['modifier'] . ' ' : '';

        // Put String Together
        $sqlString = 'select ' . $modifier . implode(', ', $sql['expressions']);
        $sqlString .= ' from ' . implode(', ', $sql['from']);
        $sqlString .= (!empty($sql['join']))
            ? ' join ' . implode(' join ', $sql['join']) : '';
        $sqlString .= (!empty($sql['innerJoin']))
            ? ' inner join ' . implode(' inner join ', $sql['innerJoin']) : '';
        $sqlString .= (!empty($sql['leftOuterJoin']))
            ? ' left outer join '
                . implode(' left outer join ', $sql['leftOuterJoin'])
            : '';
        $sqlString .= ' where ' . implode(' AND ', $sql['where']);
        $sqlString .= (!empty($sql['order']))
            ? ' ORDER BY ' . implode(', ', $sql['order']) : '';

        return $sqlString;
    }

    /**
     * Protected support method determine availability, reserve and duedate values
     * based on item status. Used by getHolding, getStatus and getStatuses.
     *
     * @param string $status Item status code
     *
     * @return array
     */
    protected function parseStatus($status)
    {
        $duedate = null;
        $statuses = $this->config['Statuses'][$status] ?? null;
        $reserve = 'N';
        $available = 0;

        // query the config file for the item status if there are
        // config values, use the configuration otherwise execute the switch
        if (!$statuses == null) {
            // break out the values
            $arrayValues = array_map('strtolower', explode(',', $statuses));

            //set the variables based on what we find in the config file
            if (in_array(strtolower('available:1'), $arrayValues)) {
                $available = 1;
            }
            if (in_array(strtolower('available:0'), $arrayValues)) {
                $available = 0;
            }
            if (in_array(strtolower('reserve:N'), $arrayValues)) {
                $reserve  = 'N';
            }
            if (in_array(strtolower('reserve:Y'), $arrayValues)) {
                $reserve  = 'Y';
            }
            if (in_array(strtolower('duedate:0'), $arrayValues)) {
                $duedate  = '';
            }
        } else {
            switch ($status) {
                case 'i': // checked in
                    $available = 1;
                    $reserve   = 'N';
                    break;
                case 'rb': // Reserve Bookroom
                    $available = 0;
                    $reserve   = 'Y';
                    break;
                case 'h': // being held
                    $available = 0;
                    $reserve   = 'N';
                    break;
                case 'l': // lost
                    $available = 0;
                    $reserve   = 'N';
                    $duedate   = ''; // No due date for lost items
                    break;
                case 'm': // missing
                    $available = 0;
                    $reserve   = 'N';
                    $duedate   = ''; // No due date for missing items
                    break;
                default:
                    $available = 0;
                    $reserve   = 'N';
                    break;
            }
        }

        $statusValues = ['available' => $available,
                              'reserve'   => $reserve];

        if (isset($duedate)) {
            $statusValues += ['duedate' => $duedate];
        }
        return $statusValues;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingSQL($id)
    {
        // Query holding information based on id field defined in
        // import/marc.properties
        // Expressions
        $sqlExpressions = [
            'i.item# as ITEM_ID',
            'i.item_status as STATUS_CODE',
            'ist.descr as STATUS',
            'l.name as LOCATION',
            'i.call_reconstructed as CALLNUMBER',
            'i.ibarcode as ITEM_BARCODE',
            'convert(varchar(10), ' .
            "        dateadd(dd,i.due_date,'jan 1 1970'), " .
            '        101) as DUEDATE',
            'i.copy_reconstructed as NUMBER',
            'convert(varchar(10), ' .
            "        dateadd(dd,ch.cki_date,'jan 1 1970'), " .
            '        101) as RETURNDATE',
            '(select count(*)
                from request r
               where r.bib# = i.bib#
                 and r.reactivate_date = NULL) as REQUEST',
            'i.notes as NOTES',
            'ist.available_for_request IS_HOLDABLE',

        ];

        // From
        $sqlFrom = ['item i'];

        // inner Join
        $sqlInnerJoin = [
            'item_status ist on i.item_status = ist.item_status',
            'location l on i.location = l.location',
        ];

        $sqlLeftOuterJoin = [
           'circ_history ch on ch.item# = i.item#',
        ];

        // Where
        $sqlWhere = [
            'i.bib# = ' . addslashes($id),
            'i.staff_only = 0',
        ];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'innerJoin' => $sqlInnerJoin,
            'leftOuterJoin' => $sqlLeftOuterJoin,
            'where' => $sqlWhere,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param string $id     Bib Id
     * @param array  $row    SQL Row Data
     * @param array  $patron Patron Array
     *
     * @return array Keyed data
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function processHoldingRow($id, $row, $patron)
    {
        $duedate     = $row['DUEDATE'];
        $item_status = $row['STATUS_CODE']; //get the item status code

        $statusValues = $this->parseStatus($item_status);

        if (isset($statusValues['duedate'])) {
            $duedate = $statusValues['duedate'];
        }

        $holding = [
            'id'              => $id,
            'availability'    => $statusValues['available'],
            'item_id'         => $row['ITEM_ID'],
            'status'          => $row['STATUS'],
            'location'        => $row['LOCATION'],
            'reserve'         => $statusValues['reserve'],
            'callnumber'      => $row['CALLNUMBER'],
            'duedate'         => $duedate,
            'returnDate'      => $row['RETURNDATE'],
            'barcode'         => $row['ITEM_BARCODE'],
            'requests_placed' => $row['REQUEST'],
            'is_holdable'     => $row['IS_HOLDABLE'],

        ];

        // Only set the number key if there is actually volume data
        if ($row['NUMBER'] != '') {
            $holding += ['number' => $row['NUMBER']];
        }

        // Only set the notes key if there are actually notes to display
        if ($row['NOTES'] != '') {
            $holding += ['notes' => [$row['NOTES']]];
        }

        return $holding;
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
        $sqlArray = $this->getHoldingSql($id);
        $sql = $this->buildSqlFromArray($sqlArray);

        $holding = [];
        try {
            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                $holding[] = $this->processHoldingRow($id, $row, $patron);
            }

            $this->debug(json_encode($holding));
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }
        return $holding;
    }

    /**
     * Protected support method for getStatuses.
     *
     * @param string $id  Bib Id
     * @param array  $row SQL Row Data
     *
     * @return array Keyed data
     */
    protected function processStatusRow($id, $row)
    {
        $item_status  = $row['STATUS_CODE']; //get the item status code
        $statusValues = $this->parseStatus($item_status);

        $status = [
            'id'           => $id,
            'availability' => $statusValues['available'],
            'status'       => $row['STATUS'],
            'location'     => $row['LOCATION'],
            'reserve'      => $statusValues['reserve'],
            'callnumber'   => $row['CALLNUMBER'],
        ];

        return $status;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a specific
     * record. It is a proxy to getStatuses.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed On success, an associative array with the following keys:
     *               id, availability (boolean), status, location, reserve, and
     *               callnumber.
     */
    public function getStatus($id)
    {
        $idList = [$id];
        $status = $this->getStatuses($idList);
        return current($status);
    }

    /**
     * Protected support method for getStatus.
     *
     * @param array $idList A list of Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getStatusesSQL($idList)
    {
        // Query holding information based on id field defined in
        // import/marc.properties
        // Expressions
        $sqlExpressions = ['i.bib# as ID',
                                'i.item_status as STATUS_CODE',
                                'ist.descr as STATUS',
                                'l.name as LOCATION',
                                'i.call_reconstructed as CALLNUMBER'];

        // From
        $sqlFrom = ['item i'];

        // inner Join
        $sqlInnerJoin = ['item_status ist on i.item_status = ist.item_status',
                              'location l on i.location = l.location'];

        $bibIDs = implode(',', $idList);

        // Where
        $sqlWhere = ['i.bib# in (' . $bibIDs . ')',
                          'i.staff_only = 0'];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from'        => $sqlFrom,
            'innerJoin'   => $sqlInnerJoin,
            'where'       => $sqlWhere,
        ];

        return $sqlArray;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a collection of
     * records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        // Make sure we only give Horizon integers
        $callback = function ($i) {
            return preg_match('/^[0-9]+$/', $i);
        };
        $idList = array_filter($idList, $callback);

        // Skip DB call if we have no valid IDs.
        if (empty($idList)) {
            return [];
        }

        $sqlArray = $this->getStatusesSQL($idList);
        $sql      = $this->buildSqlFromArray($sqlArray);

        $status  = [];
        try {
            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                $id            = $row['ID'];
                $status[$id][] = $this->processStatusRow($id, $row);
            }
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }
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
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
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
     * ILSException on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $sql = 'select name_reconstructed as FULLNAME, ' .
            'email_address as EMAIL ' .
            'from borrower ' .
            'left outer join borrower_address on ' .
                'borrower_address.borrower# = borrower.borrower# ' .
            'inner join borrower_barcode on ' .
                'borrower.borrower# = borrower_barcode.borrower# ' .
            'where borrower_barcode.bbarcode = ' .
                "'" . addslashes($username) . "' " .
            "and pin# = '" . addslashes($password) . "'";

        try {
            $user = [];

            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                [$lastname, $firstname] = explode(', ', $row['FULLNAME']);
                $user = [
                    'id' => $username,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'cat_username' => $username,
                    'cat_password' => $password,
                    'email' => $row['EMAIL'],
                    'major' => null,
                    'college' => null,
                ];

                $this->debug(json_encode($user));

                return $user;
            }

            throw new ILSException('Unable to login patron ' . $username);
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }
    }

    /**
     * Protected support method for getMyHolds.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldsSQL($patron)
    {
        // Expressions
        $sqlExpressions = [
            'r.bib#           as BIB_NUM',
            'r.request#       as REQNUM',
            'r.item#          as ITEM_ID',
            'r.bib_queue_ord  as POSITION',
            'l.name           as LOCATION',
            'r.request_status as STATUS',
            'case when r.request_status = 1 ' .
                'then 0 ' .
                'else 1 ' .
                'end          as SORT',
            't.processed      as TITLE',
            'p.pubdate        as PUBLICATION_YEAR',
            'i.volume         as VOLUME',
            "convert(varchar(12),dateadd(dd, r.hold_exp_date, '1 jan 1970')) " .
                             'as HOLD_EXPIRE',
            "convert(varchar(12),dateadd(dd, r.expire_date, '1 jan 1970'))   " .
                             'as REQUEST_EXPIRE',
            "convert(varchar(12),dateadd(dd, r.request_date, '1 jan 1970'))  " .
                             'as CREATED',
        ];

        // From
        $sqlFrom = ['request r'];

        // Join
        $sqlJoin = [
            'borrower_barcode bb on bb.borrower# = r.borrower#',
            'location l          on l.location = r.pickup_location',
            'title t             on t.bib# = r.bib#',
        ];

        $sqlLeftOuterJoin = [
            'item i             on i.item# = r.item#',
            'pubdate_inverted p on p.bib# = r.bib#',
        ];

        // Where
        $sqlWhere = [
            "bb.bbarcode='" . addslashes($patron['id']) . "'",
        ];

        $sqlOrder = [
            'SORT',
            't.processed',
        ];

        $sqlArray = [
            'expressions'   => $sqlExpressions,
            'from'          => $sqlFrom,
            'join'          => $sqlJoin,
            'leftOuterJoin' => $sqlLeftOuterJoin,
            'where'         => $sqlWhere,
            'order'         => $sqlOrder,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getMyHolds.
     *
     * @param array $row An sql row
     *
     * @throws DateException
     * @return array Keyed data
     */
    protected function processHoldsRow($row)
    {
        if ($row['STATUS'] != 6) {
            $position  = ($row['STATUS'] != 1) ? $row['POSITION'] : false;
            $available = ($row['STATUS'] == 1) ? true : false;
            $expire    = false;
            $create    = false;
            // Convert Horizon Format to display format
            if (!empty($row['HOLD_EXPIRE'])) {
                $expire = $this->dateFormat->convertToDisplayDate(
                    'M d Y',
                    trim($row['HOLD_EXPIRE'])
                );
            } elseif (!empty($row['REQUEST_EXPIRE'])) {
                // If there is no Hold Expiration date fall back to the
                // Request Expiration date.
                $expire = $this->dateFormat->convertToDisplayDate(
                    'M d Y',
                    trim($row['REQUEST_EXPIRE'])
                );
            } elseif ($row['STATUS'] == 2) {
                // Items that are 'In Transit' have no expiration date.
                $expire = 'In Transit';
            } else {
                // Just in case we missed a possible scenario
                $expire = false;
            }
            if (!empty($row['CREATED'])) {
                $create = $this->dateFormat->convertToDisplayDate(
                    'M d Y',
                    trim($row['CREATED'])
                );
            }

            return [
                'id' => $row['BIB_NUM'],
                'location'         => $row['LOCATION'],
                'reqnum'           => $row['REQNUM'],
                'expire'           => $expire,
                'create'           => $create,
                'position'         => $position,
                'available'        => $available,
                'item_id'          => $row['ITEM_ID'],
                'volume'           => $row['VOLUME'],
                'publication_year' => $row['PUBLICATION_YEAR'],
                'title'            => $row['TITLE'],
            ];
        }
        return false;
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
        $sqlArray = $this->getHoldsSQL($patron);
        $sql      = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                $hold = $this->processHoldsRow($row);
                if ($hold) {
                    $holdList[] = $hold;
                }
            }

            $this->debug(json_encode($holdList));
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }
        return $holdList;
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
        $sql = '   select bu.amount as AMOUNT ' .
               '        , coalesce( ' .
               '              convert(varchar(10), ' .
               "                      dateadd(dd, i.last_cko_date, '01jan70'), " .
               '                      101), ' .
               '              convert(varchar(10), ' .
               "                      dateadd(dd, bu2.date, '01jan70'), " .
               '                      101)) as CHECKOUT ' .
               '        , bl.descr as FINE ' .
               '        , (  select sum(b2.amount) ' .
               '               from burb b2 ' .
               '              where b2.reference# = bu.reference# ' .
               '           group by b2.reference#) as BALANCE ' .
               '        , convert(varchar(10), ' .
               "                  dateadd(dd, bu.date, '01jan70'), " .
               '                  101) as CREATEDATE ' .
               '        , coalesce( ' .
               '              convert(varchar(10), ' .
               "                      dateadd(dd, i.due_date, '01jan70'), " .
               '                      101), ' .
               '              convert(varchar(10), ' .
               "                      dateadd(dd, bu3.date, '01jan70'), " .
               '                      101)) as DUEDATE ' .
               '        , i2.bib# as ID ' .
               '        , coalesce (t.processed, bu4.comment) as TITLE ' .
               '        , case when bl.amount_type = 0 ' .
               '               then 0 ' .
               '               else 1 ' .
               '          end as FEEBLOCK ' .
               '     from burb bu ' .
               '     join block bl ' .
               '       on bl.block = bu.block ' .
               '     join borrower_barcode bb ' .
               '       on bb.borrower# = bu.borrower# ' .
               'left join item i ' .
               '       on i.item# = bu.item# ' .
               '      and i.borrower# = bu.borrower# ' .
               'left join item i2 ' .
               '       on i2.item# = bu.item# ' .
               'left join burb bu2 ' .
               '       on bu2.reference# = bu.reference# ' .
               "      and bu2.block = 'infocko' " .
               'left join burb bu3 ' .
               '       on bu3.reference# = bu.reference# ' .
               "      and bu3.block = 'infodue' " .
               'left join title t ' .
               '       on t.bib# = i2.bib# ' .
               'left join burb bu4 ' .
               '       on bu4.reference# = bu.reference# ' .
               '      and bu4.ord = 0 ' .
               "      and bu4.block in ('l', 'LostPro','fine','he') " .
               "    where bb.bbarcode = '" . addslashes($patron['id']) . "' " .
               '      and bu.ord = 0 ' .
               '      and bl.pac_display = 1 ' .
               ' order by FEEBLOCK desc ' .
               '        , bu.item# ' .
               '        , TITLE ' .
               '        , bu.block ' .
               '        , bu.date';

        try {
            $sqlStmt = $this->db->query($sql);
            $fineList = [];
            foreach ($sqlStmt as $row) {
                $fineList[] = [
                    'amount'     => $row['AMOUNT'],
                    'checkout'   => $row['CHECKOUT'],
                    'fine' => $row['FINE'],
                    'balance'    => $row['BALANCE'],
                    'createdate' => $row['CREATEDATE'],
                    'duedate'    => $row['DUEDATE'],
                    'id'         => $row['ID'],
                    'title'      => $row['TITLE'],
                ];
            }

            $this->debug(json_encode($fineList));

            return $fineList;
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
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
     * @return array        Array of the patron's profile data on success,
     * throw ILSException if none found
     */
    public function getMyProfile($patron)
    {
        $profile = [];
        $sql = 'select name_reconstructed as FULLNAME, address1 as ADDRESS1, ' .
            'city_st.descr as ADDRESS2, postal_code as ZIP, phone_no as PHONE ' .
            'from borrower ' .
            'left outer join borrower_phone on ' .
                'borrower_phone.borrower#=borrower.borrower# ' .
            'inner join borrower_address on ' .
                'borrower_address.borrower#=borrower.borrower# ' .
            'inner join city_st on city_st.city_st=borrower_address.city_st ' .
            'inner join borrower_barcode on ' .
            'borrower_barcode.borrower# = borrower.borrower# ' .
            "where borrower_barcode.bbarcode = '" . addslashes($patron['id']) . "'";

        try {
            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                [$lastname, $firstname] = explode(', ', $row['FULLNAME']);
                $profile = [
                    'lastname' => $lastname,
                    'firstname' => $firstname,
                    'address1' => $row['ADDRESS1'],
                    'address2' => $row['ADDRESS2'],
                    'zip' => $row['ZIP'],
                    'phone' => $row['PHONE'],
                    'group' => null,
                ];

                $this->debug(json_encode($profile));

                return $profile;
            }

            throw new ILSException(
                'Unable to retrieve profile for patron ' . $patron['id']
            );
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }
        return $profile;
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getTransactionSQL($patron)
    {
        // Expressions
        $sqlExpressions = [
            "convert(varchar(12), dateadd(dd, i.due_date, '01 jan 1970')) " .
                            'as DUEDATE',
            'i.bib#          as BIB_NUM',
            'i.ibarcode      as ITEM_BARCODE',
            'i.n_renewals    as RENEW',
            'r.bib_queue_ord as REQUEST',
            'i.volume        as VOLUME',
            'p.pubdate       as PUBLICATION_YEAR',
            't.processed     as TITLE',
            'i.item#         as ITEM_NUM',
        ];

        // From
        $sqlFrom = ['circ c'];

        // Join
        $sqlJoin = [
            'item i on i.item#=c.item#',
            'borrower b on b.borrower# = c.borrower#',
            'borrower_barcode bb on bb.borrower# = c.borrower#',
            'title t on t.bib# = i.bib#',
        ];

        // Left Outer Join
        $sqlLeftOuterJoin = [
            'request r on r.item#=c.item#',
            'pubdate_inverted p on p.bib# = i.bib#',
        ];

        // Where
        $sqlWhere = [
            "bb.bbarcode='" . addslashes($patron['id']) . "'"];

        // Order by
        $sqlOrder = [
            'i.due_date',
            't.processed',
        ];

        $sqlArray = [
            'expressions'   => $sqlExpressions,
            'from'          => $sqlFrom,
            'join'          => $sqlJoin,
            'leftOuterJoin' => $sqlLeftOuterJoin,
            'where'         => $sqlWhere,
            'order'         => $sqlOrder,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $row An array of keyed data
     *
     * @throws DateException
     * @return array Keyed data for display by template files
     */
    protected function processTransactionsRow($row)
    {
        $dueStatus = false;
        $dueDate = $row['DUEDATE'] ?? null;
        // Convert Horizon Format to display format
        if (!empty($row['DUEDATE'])) {
            $dueDate = $this->dateFormat->convertToDisplayDate(
                'M d Y',
                trim($row['DUEDATE'])
            );
            $now          = time();
            $dueTimeStamp = $this->dateFormat->convertFromDisplayDate(
                'U',
                $dueDate
            );
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = 'overdue';
                } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                    $dueStatus = 'due';
                }
            }
        }

        return [
            'id'               => $row['BIB_NUM'],
            'item_id'          => $row['ITEM_NUM'],
            'duedate'          => $dueDate,
            'barcode'          => $row['ITEM_BARCODE'],
            'renew'            => $row['RENEW'],
            'request'          => $row['REQUEST'],
            'dueStatus'        => $dueStatus,
            'volume'           => $row['VOLUME'],
            'publication_year' => $row['PUBLICATION_YEAR'],
            'title'            => $row['TITLE'],
        ];
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
        $sqlArray  = $this->getTransactionSQL($patron);
        $sql       = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                $transList[] = $this->processTransactionsRow($row);
            }

            $this->debug(json_encode($transList));
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }
        return $transList;
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
        // No funds for limiting in Horizon.
        return [];
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * The logic in this function follows the pattern used for the "New Additions"
     * functionality of the Horizon staff client. New Additions was delivered with
     * Horizon 7.4 and requires setup. Follow instructions in the "Circulation Setup
     * Guide". The minimum setup is to set the "Track First Availability" flag for
     * each appropriate item status.
     *
     * @param int $page    Not implemented in this driver - Sybase does not have SQL
     *                     query paging functionality.
     * @param int $limit   The maximum number of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  Not implemented in this driver - The contributing library
     *                     does not use acquisitions.
     *
     * @return array       Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // This functionality first appeared in Horizon 7.4 - check our version
        $hzVersionRequired = '7.4.0.0';
        if ($this->checkHzVersion($hzVersionRequired)) {
            // Set the Sybase or MSSQL rowcount limit (TODO: account for $page)
            $limitsql = "set rowcount {$limit}";
            // for Sybase ASE 12.5 : "set rowcount $limit"

            // This is the actual query for IDs.
            $newsql = '  select nb.bib# '
                    . '    from new_bib nb '
                    . '    join bib_control bc '
                    . '      on bc.bib# = nb.bib# '
                    . '     and bc.staff_only = 0 '
                    . '   where nb.date >= '
                    . "         datediff(dd, '01JAN1970', getdate()) - {$daysOld} "
                    . 'order by nb.date desc ';

            $results = [];

            // Set the rowcount limit before executing the query for IDs
            $this->db->query($limitsql);

            // Actual query for IDs
            try {
                $sqlStmt = $this->db->query($newsql);
                foreach ($sqlStmt as $row) {
                    $results[] = $row['bib#'];
                }

                $retVal = ['count' => count($results), 'results' => []];
                foreach ($results as $result) {
                    $retVal['results'][] = ['id' => $result];
                }

                return $retVal;
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
                $this->throwAsIlsException($e);
            }
        }
        return ['count' => 0, 'results' => []];
    }

    /**
     * Check Horizon Version
     *
     * Check the Horizon version found in the matham table to make sure it is at
     * least the required version.
     *
     * @param string $hzVersionRequired Minimum version required
     *
     * @return bool True or False the required version is the same or higher.
     */
    protected function checkHzVersion($hzVersionRequired)
    {
        $checkHzVersionSQL = 'select database_revision from matham';

        $hzVersionFound = '';
        try {
            $versionResult = $this->db->query($checkHzVersionSQL);
            foreach ($versionResult as $row) {
                $hzVersionFound = $row['database_revision'];
            }
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }

        /* The Horizon database version is made up of 4 numbers separated by periods.
         * Explode the string and check each segment against the required version.
         */
        $foundVersionParts    = explode('.', $hzVersionFound);
        $requiredVersionParts = explode('.', $hzVersionRequired);

        $versionOK = true;

        for ($i = 0; $i < count($foundVersionParts); $i++) {
            $required = intval($requiredVersionParts[$i]);
            $found    = intval($foundVersionParts[$i]);

            if ($found > $required) {
                // If found is greater than required stop checking
                break;
            } elseif ($found < $required) {
                /* If found is less than required set $versionOK false
                 * and stop checking
                 */
                $versionOK = false;
                break;
            }
        }

        return $versionOK;
    }

    /**
     * Get suppressed records.
     *
     * Get a list of Horizon bib numbers that have the staff-only flag set.
     *
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $list = [];

        $sql = 'select bc.bib#' .
            '  from bib_control bc' .
            ' where bc.staff_only = 1';
        try {
            $sqlStmt = $this->db->query($sql);
            foreach ($sqlStmt as $row) {
                $list[] = $row['bib#'];
            }
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            $this->throwAsIlsException($e);
        }

        return $list;
    }
}
