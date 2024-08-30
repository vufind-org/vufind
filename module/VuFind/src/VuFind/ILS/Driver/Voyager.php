<?php

/**
 * Voyager ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
 * Copyright (C) The National Library of Finland 2014-2016.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use Laminas\Validator\EmailAddress as EmailAddressValidator;
use PDO;
use PDOException;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Marc\MarcReader;
use Yajra\Pdo\Oci8;

use function chr;
use function count;
use function in_array;
use function intval;
use function is_array;

/**
 * Voyager ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Voyager extends AbstractBase implements TranslatorAwareInterface, \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Lazily instantiated database connection. Use getDb() to access it.
     *
     * @var Oci8
     */
    protected $lazyDb;

    /**
     * Name of database
     *
     * @var string
     */
    protected $dbName;

    /**
     * Stored status rankings from the database; initialized to false but populated
     * by the pickStatus() method.
     *
     * @var array|bool
     */
    protected $statusRankings = false;

    /**
     * Date formatting object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateFormat;

    /**
     * Whether to use holdings sort groups to sort holdings records
     *
     * @var bool
     */
    protected $useHoldingsSortGroups;

    /**
     * Loan interval types for which to display the due time (empty = all)
     *
     * @var array
     */
    protected $displayDueTimeIntervals;

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
     * Log an SQL statement debug message.
     *
     * @param string $func   Function name or description
     * @param string $sql    The SQL statement
     * @param array  $params SQL bind parameters
     *
     * @return void
     */
    protected function debugSQL($func, $sql, $params = null)
    {
        if ($this->logger) {
            $logString = "[$func] $sql";
            if (isset($params)) {
                $logString .= ', params: ' . $this->varDump($params);
            }
            $this->debug($logString);
        }
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

        // Define Database Name
        $this->dbName = $this->config['Catalog']['database'];

        $this->useHoldingsSortGroups
            = $this->config['Holdings']['use_sort_groups'] ?? true;

        $this->displayDueTimeIntervals
            = isset($this->config['Loans']['display_due_time_only_for_intervals'])
            ? explode(
                ':',
                $this->config['Loans']['display_due_time_only_for_intervals']
            ) : [];
    }

    /**
     * Initialize database connection if necessary and return it.
     *
     * @throws ILSException
     * @return \PDO
     */
    protected function getDb()
    {
        if (null === $this->lazyDb) {
            // Based on the configuration file, use either "SID" or "SERVICE_NAME"
            // to connect (correct value varies depending on Voyager's Oracle setup):
            $connectType = isset($this->config['Catalog']['connect_with_sid']) &&
                $this->config['Catalog']['connect_with_sid'] ?
                'SID' : 'SERVICE_NAME';

            $tns = '(DESCRIPTION=' .
                     '(ADDRESS_LIST=' .
                       '(ADDRESS=' .
                         '(PROTOCOL=TCP)' .
                         '(HOST=' . $this->config['Catalog']['host'] . ')' .
                         '(PORT=' . $this->config['Catalog']['port'] . ')' .
                       ')' .
                     ')' .
                     '(CONNECT_DATA=' .
                       "({$connectType}={$this->config['Catalog']['service']})" .
                     ')' .
                   ')';
            try {
                $this->lazyDb = new Oci8(
                    "oci:dbname=$tns;charset=US7ASCII",
                    $this->config['Catalog']['user'],
                    $this->config['Catalog']['password']
                );
                $this->lazyDb
                    ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                $this->error(
                    "PDO Connection failed ($this->dbName): " . $e->getMessage()
                );
                $this->throwAsIlsException($e);
            }
        }
        return $this->lazyDb;
    }

    /**
     * Protected support method for building sql strings.
     *
     * @param array $sql An array of keyed sql data
     *
     * @return array               An string query string and bind data
     */
    protected function buildSqlFromArray($sql)
    {
        $modifier = isset($sql['modifier']) ? $sql['modifier'] . ' ' : '';

        // Put String Together
        $sqlString = 'SELECT ' . $modifier . implode(', ', $sql['expressions']);
        $sqlString .= ' FROM ' . implode(', ', $sql['from']);
        $sqlString .= (!empty($sql['where']))
            ? ' WHERE ' . implode(' AND ', $sql['where']) : '';
        $sqlString .= (!empty($sql['group']))
            ? ' GROUP BY ' . implode(', ', $sql['group']) : '';
        $sqlString .= (!empty($sql['order']))
            ? ' ORDER BY ' . implode(', ', $sql['order']) : '';

        return ['string' => $sqlString, 'bind' => $sql['bind']];
    }

    /**
     * Protected support method to pick which status message to display when multiple
     * options are present.
     *
     * @param array $statusArray Array of status messages to choose from.
     *
     * @throws ILSException
     * @return string            The best status message to display.
     */
    protected function pickStatus($statusArray)
    {
        // Pick the first entry by default, then see if we can find a better match:
        $status = $statusArray[0];
        $rank = $this->getStatusRanking($status);
        for ($x = 1; $x < count($statusArray); $x++) {
            $thisRank = $this->getStatusRanking($statusArray[$x]);
            if ($thisRank < $rank) {
                $status = $statusArray[$x];
                $rank = $thisRank;
            }
        }

        return $status;
    }

    /**
     * Support method for pickStatus() -- get the ranking value of the specified
     * status message.
     *
     * @param string $status Status message to look up
     *
     * @return int
     */
    protected function getStatusRanking($status)
    {
        // This array controls the rankings of possible status messages. The lower
        // the ID in the ITEM_STATUS_TYPE table, the higher the priority of the
        // message. We only need to load it once -- after that, it's cached in the
        // driver.
        if ($this->statusRankings == false) {
            // Execute SQL
            $sql = "SELECT * FROM $this->dbName.ITEM_STATUS_TYPE";
            try {
                $sqlStmt = $this->executeSQL($sql);
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            // Read results
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->statusRankings[$row['ITEM_STATUS_DESC']]
                    = $row['ITEM_STATUS_TYPE'];
            }

            if (!empty($this->config['StatusRankings'])) {
                $this->statusRankings = array_merge(
                    $this->statusRankings,
                    $this->config['StatusRankings']
                );
            }
        }

        // We may occasionally get a status message not found in the array (i.e. the
        // "No information available" message that we hard-code when items are
        // missing); return a large number in this case to avoid an undefined index
        // error and to allow recognized statuses to take precedence.
        return $this->statusRankings[$status] ?? 32000;
    }

    /**
     * Protected support method to take an array of status strings and determine
     * whether or not this indicates an available item. Returns an array with
     * two keys: 'available', the boolean availability status, and 'otherStatuses',
     * every status code found other than "Not Charged" - for use with
     * pickStatus().
     *
     * @param array $statusArray The status codes to analyze.
     *
     * @return array             Availability and other status information.
     */
    protected function determineAvailability($statusArray)
    {
        // It's possible for a record to have multiple status codes. We
        // need to loop through in search of the "Not Charged" (i.e. on
        // shelf) status, collecting any other statuses we find along the
        // way...
        $notCharged = false;
        $otherStatuses = [];
        foreach ($statusArray as $status) {
            switch ($status) {
                case 'Not Charged':
                    $notCharged = true;
                    break;
                default:
                    $otherStatuses[] = $status;
                    break;
            }
        }

        // If we found other statuses or if we failed to find "Not Charged,"
        // the item is not available!
        $available = (count($otherStatuses) == 0 && $notCharged);

        return ['available' => $available, 'otherStatuses' => $otherStatuses];
    }

    /**
     * Helper function that returns SQL for getting a sort sequence for a location
     *
     * @param string $locationColumn Column in the full where clause containing
     * the column id
     *
     * @return string
     */
    protected function getItemSortSequenceSQL($locationColumn)
    {
        if (!$this->useHoldingsSortGroups) {
            return '0 as SORT_SEQ';
        }

        return '(SELECT SORT_GROUP_LOCATION.SEQUENCE_NUMBER ' .
            "FROM $this->dbName.SORT_GROUP, $this->dbName.SORT_GROUP_LOCATION " .
            "WHERE SORT_GROUP.SORT_GROUP_DEFAULT = 'Y' " .
            'AND SORT_GROUP_LOCATION.SORT_GROUP_ID = SORT_GROUP.SORT_GROUP_ID ' .
            "AND SORT_GROUP_LOCATION.LOCATION_ID = $locationColumn) SORT_SEQ";
    }

    /**
     * Protected support method for getStatus -- get components required for standard
     * status lookup SQL.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getStatusSQL($id)
    {
        // Expressions
        $sqlExpressions = [
            'BIB_ITEM.BIB_ID', 'ITEM.ITEM_ID',  'MFHD_MASTER.MFHD_ID',
            'ITEM.ON_RESERVE', 'ITEM_STATUS_DESC as status',
            'NVL(LOCATION.LOCATION_DISPLAY_NAME, ' .
                'LOCATION.LOCATION_NAME) as location',
            'MFHD_MASTER.DISPLAY_CALL_NO as callnumber',
            'ITEM.TEMP_LOCATION', 'ITEM.ITEM_TYPE_ID',
            'ITEM.ITEM_SEQUENCE_NUMBER',
            $this->getItemSortSequenceSQL('ITEM.PERM_LOCATION'),
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.BIB_ITEM', $this->dbName . '.ITEM',
            $this->dbName . '.ITEM_STATUS_TYPE',
            $this->dbName . '.ITEM_STATUS',
            $this->dbName . '.LOCATION', $this->dbName . '.MFHD_ITEM',
            $this->dbName . '.MFHD_MASTER',
        ];

        // Where
        $sqlWhere = [
            'BIB_ITEM.BIB_ID = :id',
            'BIB_ITEM.ITEM_ID = ITEM.ITEM_ID',
            'ITEM.ITEM_ID = ITEM_STATUS.ITEM_ID',
            'ITEM_STATUS.ITEM_STATUS = ITEM_STATUS_TYPE.ITEM_STATUS_TYPE',
            'LOCATION.LOCATION_ID = ITEM.PERM_LOCATION',
            'MFHD_ITEM.ITEM_ID = ITEM.ITEM_ID',
            'MFHD_MASTER.MFHD_ID = MFHD_ITEM.MFHD_ID',
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'",
        ];

        // Bind
        $sqlBind = [':id' => $id];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getStatus -- get components for status lookup
     * SQL to use when a bib record has no items.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getStatusNoItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = [
            'BIB_MFHD.BIB_ID',
            'null as ITEM_ID', 'MFHD_MASTER.MFHD_ID', "'N' as ON_RESERVE",
            "'No information available' as status",
            'NVL(LOCATION.LOCATION_DISPLAY_NAME, ' .
                'LOCATION.LOCATION_NAME) as location',
            'MFHD_MASTER.DISPLAY_CALL_NO as callnumber',
            '0 AS TEMP_LOCATION',
            '0 as ITEM_SEQUENCE_NUMBER',
            $this->getItemSortSequenceSQL('LOCATION.LOCATION_ID'),
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.BIB_MFHD', $this->dbName . '.LOCATION',
            $this->dbName . '.MFHD_MASTER',
        ];

        // Where
        $sqlWhere = [
            'BIB_MFHD.BIB_ID = :id',
            'LOCATION.LOCATION_ID = MFHD_MASTER.LOCATION_ID',
            'MFHD_MASTER.MFHD_ID = BIB_MFHD.MFHD_ID',
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'",
            "NOT EXISTS (SELECT MFHD_ID FROM {$this->dbName}.MFHD_ITEM " .
            'WHERE MFHD_ITEM.MFHD_ID=MFHD_MASTER.MFHD_ID)',
        ];

        // Bind
        $sqlBind = [':id' => $id];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getStatus -- process rows returned by SQL
     * lookup.
     *
     * @param array $sqlRows Sql Data
     *
     * @return array Keyed data
     */
    protected function getStatusData($sqlRows)
    {
        $data = [];

        foreach ($sqlRows as $row) {
            $rowId = $row['ITEM_ID'] ?? 'MFHD' . $row['MFHD_ID'];
            if (!isset($data[$rowId])) {
                $data[$rowId] = [
                    'id' => $row['BIB_ID'],
                    'status' => $row['STATUS'],
                    'status_array' => [$row['STATUS']],
                    'location' => $row['TEMP_LOCATION'] > 0
                        ? $this->getLocationName($row['TEMP_LOCATION'])
                        : $this->utf8Encode($row['LOCATION']),
                    'reserve' => $row['ON_RESERVE'],
                    'callnumber' => $row['CALLNUMBER'],
                    'item_sort_seq' => $row['ITEM_SEQUENCE_NUMBER'],
                    'sort_seq' => $row['SORT_SEQ'] ?? PHP_INT_MAX,
                ];
            } else {
                $statusFound = in_array(
                    $row['STATUS'],
                    $data[$rowId]['status_array']
                );
                if (!$statusFound) {
                    $data[$rowId]['status_array'][] = $row['STATUS'];
                }
            }
        }
        return $data;
    }

    /**
     * Protected support method for getStatus -- process all details collected by
     * getStatusData().
     *
     * @param array $data SQL Row Data
     *
     * @throws ILSException
     * @return array Keyed data
     */
    protected function processStatusData($data)
    {
        // Process the raw data into final status information:
        $status = [];
        foreach ($data as $current) {
            // Get availability/status info based on the array of status codes:
            $availability = $this->determineAvailability($current['status_array']);

            // If we found other statuses, we should override the display value
            // appropriately:
            if (count($availability['otherStatuses']) > 0) {
                $current['status']
                    = $this->pickStatus($availability['otherStatuses']);
            }
            $current['availability'] = $availability['available'];
            $current['use_unknown_message']
                = in_array('No information available', $current['status_array']);

            $status[] = $current;
        }

        if ($this->useHoldingsSortGroups) {
            usort(
                $status,
                function ($a, $b) {
                    return $a['sort_seq'] == $b['sort_seq']
                        ? $a['item_sort_seq'] - $b['item_sort_seq']
                        : $a['sort_seq'] - $b['sort_seq'];
                }
            );
        }

        return $status;
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
        // There are two possible queries we can use to obtain status information.
        // The first (and most common) obtains information from a combination of
        // items and holdings records. The second (a rare case) obtains
        // information from the holdings record when no items are available.
        $sqlArrayItems = $this->getStatusSQL($id);
        $sqlArrayNoItems = $this->getStatusNoItemsSQL($id);
        $possibleQueries = [
            $this->buildSqlFromArray($sqlArrayItems),
            $this->buildSqlFromArray($sqlArrayNoItems),
        ];

        // Loop through the possible queries and merge results.
        $data = [];
        foreach ($possibleQueries as $sql) {
            // Execute SQL
            try {
                $sqlStmt = $this->executeSQL($sql);
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            $sqlRows = [];
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $sqlRows[] = $row;
            }

            $data += $this->getStatusData($sqlRows);
        }
        return $this->processStatusData($data);
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
        if (is_array($idList)) {
            foreach ($idList as $id) {
                $status[] = $this->getStatus($id);
            }
        }
        return $status;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingItemsSQL($id)
    {
        // Expressions
        $returnDate = <<<EOT
            CASE WHEN ITEM_STATUS_TYPE.ITEM_STATUS_DESC = 'Discharged' THEN (
              SELECT TO_CHAR(MAX(CIRC_TRANS_ARCHIVE.DISCHARGE_DATE), 'MM-DD-YY HH24:MI')
                FROM $this->dbName.CIRC_TRANS_ARCHIVE
                WHERE CIRC_TRANS_ARCHIVE.ITEM_ID = ITEM.ITEM_ID
            ) ELSE NULL END RETURNDATE
            EOT;
        $sqlExpressions = [
            'BIB_ITEM.BIB_ID', 'MFHD_ITEM.MFHD_ID',
            'ITEM_BARCODE.ITEM_BARCODE', 'ITEM.ITEM_ID',
            'ITEM.ON_RESERVE', 'ITEM.ITEM_SEQUENCE_NUMBER',
            'ITEM.RECALLS_PLACED', 'ITEM.HOLDS_PLACED',
            'ITEM_STATUS_TYPE.ITEM_STATUS_DESC as status',
            'MFHD_DATA.RECORD_SEGMENT', 'MFHD_ITEM.ITEM_ENUM',
            'NVL(LOCATION.LOCATION_DISPLAY_NAME, ' .
                'LOCATION.LOCATION_NAME) as location',
            'ITEM.TEMP_LOCATION',
            'ITEM.PERM_LOCATION',
            'MFHD_MASTER.DISPLAY_CALL_NO as callnumber',
            "to_char(CIRC_TRANSACTIONS.CURRENT_DUE_DATE, 'MM-DD-YY') as duedate",
            $returnDate,
            'ITEM.ITEM_SEQUENCE_NUMBER',
            $this->getItemSortSequenceSQL('ITEM.PERM_LOCATION'),
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.BIB_ITEM', $this->dbName . '.ITEM',
            $this->dbName . '.ITEM_STATUS_TYPE',
            $this->dbName . '.ITEM_STATUS',
            $this->dbName . '.LOCATION', $this->dbName . '.MFHD_ITEM',
            $this->dbName . '.MFHD_MASTER', $this->dbName . '.MFHD_DATA',
            $this->dbName . '.CIRC_TRANSACTIONS',
            $this->dbName . '.ITEM_BARCODE',
        ];

        // Where
        $sqlWhere = [
            'BIB_ITEM.BIB_ID = :id',
            'BIB_ITEM.ITEM_ID = ITEM.ITEM_ID',
            'ITEM.ITEM_ID = ITEM_STATUS.ITEM_ID',
            'ITEM_STATUS.ITEM_STATUS = ITEM_STATUS_TYPE.ITEM_STATUS_TYPE',
            'ITEM_BARCODE.ITEM_ID (+)= ITEM.ITEM_ID',
            'LOCATION.LOCATION_ID = ITEM.PERM_LOCATION',
            'CIRC_TRANSACTIONS.ITEM_ID (+)= ITEM.ITEM_ID',
            'MFHD_ITEM.ITEM_ID = ITEM.ITEM_ID',
            'MFHD_MASTER.MFHD_ID = MFHD_ITEM.MFHD_ID',
            'MFHD_DATA.MFHD_ID = MFHD_ITEM.MFHD_ID',
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'",
        ];

        // Order
        $sqlOrder = [
            'ITEM.ITEM_SEQUENCE_NUMBER', 'MFHD_DATA.MFHD_ID', 'MFHD_DATA.SEQNUM',
        ];

        // Bind
        $sqlBind = [':id' => $id];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingNoItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = [
            'null as ITEM_BARCODE', 'null as ITEM_ID',
            'MFHD_DATA.RECORD_SEGMENT', 'null as ITEM_ENUM',
            "'N' as ON_RESERVE", '1 as ITEM_SEQUENCE_NUMBER',
            "'No information available' as status",
            'NVL(LOCATION.LOCATION_DISPLAY_NAME, ' .
                'LOCATION.LOCATION_NAME) as location',
            'MFHD_MASTER.DISPLAY_CALL_NO as callnumber',
            'BIB_MFHD.BIB_ID', 'MFHD_MASTER.MFHD_ID',
            'null as duedate', 'null as RETURNDATE', '0 AS TEMP_LOCATION',
            '0 as PERM_LOCATION',
            '0 as ITEM_SEQUENCE_NUMBER',
            $this->getItemSortSequenceSQL('LOCATION.LOCATION_ID'),
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.BIB_MFHD', $this->dbName . '.LOCATION',
            $this->dbName . '.MFHD_MASTER', $this->dbName . '.MFHD_DATA',
        ];

        // Where
        $sqlWhere = [
            'BIB_MFHD.BIB_ID = :id',
            'LOCATION.LOCATION_ID = MFHD_MASTER.LOCATION_ID',
            'MFHD_MASTER.MFHD_ID = BIB_MFHD.MFHD_ID',
            'MFHD_DATA.MFHD_ID = BIB_MFHD.MFHD_ID',
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'",
            "NOT EXISTS (SELECT MFHD_ID FROM {$this->dbName}.MFHD_ITEM"
            . ' WHERE MFHD_ITEM.MFHD_ID=MFHD_MASTER.MFHD_ID)',
        ];

        // Order
        $sqlOrder = ['MFHD_DATA.MFHD_ID', 'MFHD_DATA.SEQNUM'];

        // Bind
        $sqlBind = [':id' => $id];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $sqlRows Sql Data
     *
     * @return array Keyed data
     */
    protected function getHoldingData($sqlRows)
    {
        $data = [];

        foreach ($sqlRows as $row) {
            // Determine Copy Number
            $number = $row['ITEM_SEQUENCE_NUMBER'];

            // Concat wrapped rows (MARC data more than 300 bytes gets split
            // into multiple rows)
            $rowId = $row['ITEM_ID'] ?? 'MFHD' . $row['MFHD_ID'];
            if (isset($data[$rowId][$number])) {
                // We don't want to concatenate the same MARC information to
                // itself over and over due to a record with multiple status
                // codes -- we should only concat wrapped rows for the FIRST
                // status code we encounter!
                $record = & $data[$rowId][$number];
                if ($record['STATUS_ARRAY'][0] == $row['STATUS']) {
                    $record['RECORD_SEGMENT'] .= $row['RECORD_SEGMENT'];
                }

                // If we've encountered a new status code, we should track it:
                if (!in_array($row['STATUS'], $record['STATUS_ARRAY'])) {
                    $record['STATUS_ARRAY'][] = $row['STATUS'];
                }

                // If we have a return date for this status, take it
                if (null !== $row['RETURNDATE']) {
                    $record['RETURNDATE'] = $row['RETURNDATE'];
                }
            } else {
                // This is the first time we've encountered this row number --
                // initialize the row and start an array of statuses.
                $data[$rowId][$number] = $row;
                $data[$rowId][$number]['STATUS_ARRAY']
                    = [$row['STATUS']];
            }
        }
        return $data;
    }

    /**
     * Get Purchase History Data
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial). It is used
     * by getHoldings() and getPurchaseHistory() depending on whether the purchase
     * history is displayed by holdings or in a separate list.
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    protected function getPurchaseHistoryData($id)
    {
        $sql = 'select LINE_ITEM_COPY_STATUS.MFHD_ID, SERIAL_ISSUES.ENUMCHRON ' .
               "from $this->dbName.SERIAL_ISSUES, $this->dbName.COMPONENT, " .
               "$this->dbName.ISSUES_RECEIVED, $this->dbName.SUBSCRIPTION, " .
               "$this->dbName.LINE_ITEM, $this->dbName.LINE_ITEM_COPY_STATUS " .
               'where SERIAL_ISSUES.COMPONENT_ID = COMPONENT.COMPONENT_ID ' .
               'and ISSUES_RECEIVED.ISSUE_ID = SERIAL_ISSUES.ISSUE_ID ' .
               'and ISSUES_RECEIVED.COMPONENT_ID = COMPONENT.COMPONENT_ID ' .
               'and COMPONENT.SUBSCRIPTION_ID = SUBSCRIPTION.SUBSCRIPTION_ID ' .
               'and SUBSCRIPTION.LINE_ITEM_ID = LINE_ITEM.LINE_ITEM_ID ' .
               'and LINE_ITEM_COPY_STATUS.LINE_ITEM_ID = LINE_ITEM.LINE_ITEM_ID ' .
               'and SERIAL_ISSUES.RECEIVED > 0 ' .
               'and ISSUES_RECEIVED.OPAC_SUPPRESSED = 1 ' .
               'and LINE_ITEM.BIB_ID = :id ' .
               'order by LINE_ITEM_COPY_STATUS.MFHD_ID, SERIAL_ISSUES.ISSUE_ID DESC';
        try {
            $sqlStmt = $this->executeSQL($sql, [':id' => $id]);
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        $raw = $processed = [];
        // Collect raw data:
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $raw[] = $row['MFHD_ID'] . '||' . $this->utf8Encode($row['ENUMCHRON']);
        }
        // Deduplicate data and format it:
        foreach (array_unique($raw) as $current) {
            [$holdings_id, $issue] = explode('||', $current, 2);
            $processed[] = compact('issue', 'holdings_id');
        }
        return $processed;
    }

    /**
     * Get specified fields from an MFHD MARC Record
     *
     * @param MarcReader   $record     Marc reader
     * @param array|string $fieldSpecs Array or colon-separated list of
     * field/subfield specifications (3 chars for field code and then subfields,
     * e.g. 866az)
     *
     * @return string|array Results as a string if single, array if multiple
     */
    protected function getMFHDData(MarcReader $record, $fieldSpecs)
    {
        if (!is_array($fieldSpecs)) {
            $fieldSpecs = explode(':', $fieldSpecs);
        }
        $results = '';
        foreach ($fieldSpecs as $fieldSpec) {
            $fieldCode = substr($fieldSpec, 0, 3);
            $subfieldCodes = substr($fieldSpec, 3);
            if ($fields = $record->getFields($fieldCode)) {
                foreach ($fields as $field) {
                    if ($subfields = $field['subfields'] ?? []) {
                        $line = '';
                        foreach ($subfields as $subfield) {
                            if (
                                !str_contains($subfieldCodes, $subfield['code'])
                            ) {
                                continue;
                            }
                            if ($line) {
                                $line .= ' ';
                            }
                            $line .= $subfield['data'];
                        }
                        if ($line) {
                            if (!$results) {
                                $results = $line;
                            } else {
                                if (!is_array($results)) {
                                    $results = [$results];
                                }
                                $results[] = $line;
                            }
                        }
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $recordSegment A Marc Record Segment obtained from an SQL query
     *
     * @return array Keyed data
     */
    protected function processRecordSegment($recordSegment)
    {
        $marcDetails = [];

        try {
            $record = new MarcReader(str_replace(["\n", "\r"], '', $recordSegment));
            // Get Notes
            $data = $this->getMFHDData(
                $record,
                $this->config['Holdings']['notes'] ?? '852z'
            );
            if ($data) {
                $marcDetails['notes'] = $data;
            }

            // Get Summary (may be multiple lines)
            $data = $this->getMFHDData(
                $record,
                $this->config['Holdings']['summary'] ?? '866a'
            );
            if ($data) {
                $marcDetails['summary'] = $data;
            }

            // Get Supplements
            if (isset($this->config['Holdings']['supplements'])) {
                $data = $this->getMFHDData(
                    $record,
                    $this->config['Holdings']['supplements']
                );
                if ($data) {
                    $marcDetails['supplements'] = $data;
                }
            }

            // Get Indexes
            if (isset($this->config['Holdings']['indexes'])) {
                $data = $this->getMFHDData(
                    $record,
                    $this->config['Holdings']['indexes']
                );
                if ($data) {
                    $marcDetails['indexes'] = $data;
                }
            }
        } catch (\Exception $e) {
            trigger_error(
                'Poorly Formatted MFHD Record',
                E_USER_NOTICE
            );
        }
        return $marcDetails;
    }

    /**
     * Look up a location name by ID.
     *
     * @param int $id Location ID to look up
     *
     * @return string
     */
    protected function getLocationName($id)
    {
        static $cache = [];

        // Fill cache if empty:
        if (!isset($cache[$id])) {
            $sql = 'SELECT NVL(LOCATION_DISPLAY_NAME, LOCATION_NAME) as location ' .
                "FROM {$this->dbName}.LOCATION WHERE LOCATION_ID=:id";
            $bind = ['id' => $id];
            $sqlStmt = $this->executeSQL($sql, $bind);
            $sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $cache[$id] = $this->utf8Encode($sqlRow['LOCATION']);
        }

        return $cache[$id];
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $sqlRow SQL Row Data
     *
     * @return array Keyed data
     */
    protected function processHoldingRow($sqlRow)
    {
        return [
            'id' => $sqlRow['BIB_ID'],
            'holdings_id' => $sqlRow['MFHD_ID'],
            'item_id' => $sqlRow['ITEM_ID'],
            'status' => $sqlRow['STATUS'],
            'location' => $sqlRow['TEMP_LOCATION'] > 0
                ? $this->getLocationName($sqlRow['TEMP_LOCATION'])
                : $this->utf8Encode($sqlRow['LOCATION']),
            'reserve' => $sqlRow['ON_RESERVE'],
            'callnumber' => $sqlRow['CALLNUMBER'],
            'barcode' => $sqlRow['ITEM_BARCODE'],
            'use_unknown_message' =>
                in_array('No information available', $sqlRow['STATUS_ARRAY']),
            'item_sort_seq' => $sqlRow['ITEM_SEQUENCE_NUMBER'],
            'sort_seq' => $sqlRow['SORT_SEQ'] ?? PHP_INT_MAX,
        ];
    }

    /**
     * Support method for processHoldingData: format a due date for inclusion in
     * holdings data.
     *
     * @param array $row Row to process
     *
     * @return string|bool
     */
    protected function processHoldingDueDate(array $row)
    {
        if (!empty($row['DUEDATE'])) {
            return $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $row['DUEDATE']
            );
        }
        return false;
    }

    /**
     * Support method for processHoldingData: format a return date for inclusion in
     * holdings data.
     *
     * @param array $row Row to process
     *
     * @return string|bool
     */
    protected function processHoldingReturnDate(array $row)
    {
        if (!empty($row['RETURNDATE'])) {
            return $this->dateFormat->convertToDisplayDateAndTime(
                'm-d-y H:i',
                $row['RETURNDATE']
            );
        }
        return false;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array  $data   Item Data
     * @param string $id     The BIB record id
     * @param array  $patron Patron Data
     *
     * @throws DateException
     * @throws ILSException
     * @return array Keyed data
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function processHoldingData($data, $id, $patron = null)
    {
        $holding = [];

        // Build Holdings Array
        $purchaseHistory = [];
        if (
            isset($this->config['Holdings']['purchase_history'])
            && $this->config['Holdings']['purchase_history'] === 'split'
        ) {
            $purchaseHistory = $this->getPurchaseHistoryData($id);
        }
        $i = 0;
        foreach ($data as $item) {
            foreach ($item as $number => $row) {
                // Get availability/status info based on the array of status codes:
                $availability = $this->determineAvailability($row['STATUS_ARRAY']);

                // If we found other statuses, we should override the display value
                // appropriately:
                if (count($availability['otherStatuses']) > 0) {
                    $row['STATUS']
                        = $this->pickStatus($availability['otherStatuses']);
                }

                $requests_placed = $row['HOLDS_PLACED'] ?? 0;
                if (isset($row['RECALLS_PLACED'])) {
                    $requests_placed += $row['RECALLS_PLACED'];
                }

                $holding[$i] = $this->processHoldingRow($row);
                $purchases = [];
                foreach ($purchaseHistory as $historyItem) {
                    if ($holding[$i]['holdings_id'] == $historyItem['holdings_id']) {
                        $purchases[] = $historyItem;
                    }
                }
                $holding[$i] += [
                    'availability' => $availability['available'],
                    'enumchron' => isset($row['ITEM_ENUM'])
                        ? $this->utf8Encode($row['ITEM_ENUM']) : null,
                    'duedate' => $this->processHoldingDueDate($row),
                    'number' => $number,
                    'requests_placed' => $requests_placed,
                    'returnDate' => $this->processHoldingReturnDate($row),
                    'purchase_history' => $purchases,
                ];

                // Parse Holding Record
                if ($row['RECORD_SEGMENT']) {
                    $marcDetails
                        = $this->processRecordSegment($row['RECORD_SEGMENT']);
                    if (!empty($marcDetails)) {
                        $holding[$i] += $marcDetails;
                    }
                }

                $i++;
            }
        }

        if ($this->useHoldingsSortGroups) {
            usort(
                $holding,
                function ($a, $b) {
                    return $a['sort_seq'] == $b['sort_seq']
                        ? $a['item_sort_seq'] - $b['item_sort_seq']
                        : $a['sort_seq'] - $b['sort_seq'];
                }
            );
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
        $possibleQueries = [];

        // There are two possible queries we can use to obtain status information.
        // The first (and most common) obtains information from a combination of
        // items and holdings records. The second (a rare case) obtains
        // information from the holdings record when no items are available.

        $sqlArrayItems = $this->getHoldingItemsSQL($id);
        $possibleQueries[] = $this->buildSqlFromArray($sqlArrayItems);

        $sqlArrayNoItems = $this->getHoldingNoItemsSQL($id);
        $possibleQueries[] = $this->buildSqlFromArray($sqlArrayNoItems);

        // Loop through the possible queries and merge results.
        $data = [];
        foreach ($possibleQueries as $sql) {
            // Execute SQL
            try {
                $sqlStmt = $this->executeSQL($sql);
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            $sqlRows = [];
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $sqlRows[] = $row;
            }

            $data = array_merge($data, $this->getHoldingData($sqlRows));
        }
        return $this->processHoldingData($data, $id, $patron);
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
        // Return empty array if purchase history is disabled or embedded
        // in holdings
        $setting = $this->config['Holdings']['purchase_history'] ?? true;
        return (!$setting || $setting === 'split')
            ? [] : $this->getPurchaseHistoryData($id);
    }

    /**
     * Sanitize patron PIN code (remove characters Voyager doesn't handle properly)
     *
     * @param string $pin PIN code to sanitize
     *
     * @return string Sanitized PIN code
     */
    protected function sanitizePIN($pin)
    {
        $pin = preg_replace('/[^0-9a-zA-Z#&<>+^`~]+/', '', $pin);
        return $pin;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron barcode or institution ID (depending on
     * config)
     * @param string $login    The patron's last name or PIN (depending on config)
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $login)
    {
        // Load the field used for verifying the login from the config file, and
        // make sure there's nothing crazy in there:
        $usernameField = preg_replace(
            '/[^\w]/',
            '',
            $this->config['Catalog']['username_field'] ?? 'PATRON_BARCODE'
        );
        $loginField = $this->config['Catalog']['login_field'] ?? 'LAST_NAME';
        $loginField = preg_replace('/[^\w]/', '', $loginField);
        $fallbackLoginField = preg_replace(
            '/[^\w]/',
            '',
            $this->config['Catalog']['fallback_login_field'] ?? ''
        );

        // Turns out it's difficult and inefficient to handle the mismatching
        // character sets of the Voyager database in the query (in theory something
        // like
        // "UPPER(UTL_I18N.RAW_TO_NCHAR(UTL_RAW.CAST_TO_RAW(field), 'WE8ISO8859P1'))"
        // could be used, but it's SLOW and ugly). We'll rely on the fact that the
        // barcode shouldn't contain any characters outside the basic latin
        // characters and check login verification fields here.

        $sql = 'SELECT PATRON.PATRON_ID, PATRON.FIRST_NAME, PATRON.LAST_NAME, ' .
               "PATRON.{$loginField} as LOGIN";
        if ($fallbackLoginField) {
            $sql .= ", PATRON.{$fallbackLoginField} as FALLBACK_LOGIN";
        }
        $sql .= " FROM $this->dbName.PATRON, $this->dbName.PATRON_BARCODE " .
               'WHERE PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID AND ';
        $sql .= $usernameField === 'PATRON_BARCODE'
            ? 'lower(PATRON_BARCODE.PATRON_BARCODE) = :username'
            : "lower(PATRON.{$usernameField}) = :username";

        // Limit the barcode statuses that allow logging in. By default only
        // 1 (active) and 4 (expired) are allowed.
        $allowedStatuses = preg_replace(
            '/[^:\d]*/',
            '',
            $this->config['Catalog']['allowed_barcode_statuses'] ?? '1:4'
        );
        if ($allowedStatuses) {
            $sql .= ' AND PATRON_BARCODE.BARCODE_STATUS IN ('
                . str_replace(':', ',', $allowedStatuses) . ')';
        }

        try {
            $bindUsername = strtolower(mb_convert_encoding($username, 'ISO-8859-1', 'UTF-8'));
            $compareLogin = mb_strtolower($login, 'UTF-8');

            $sqlStmt = $this->executeSQL($sql, [':username' => $bindUsername]);
            // For some reason barcode is not unique, so evaluate all resulting
            // rows just to be safe
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $primary = null !== $row['LOGIN']
                    ? mb_strtolower($this->utf8Encode($row['LOGIN']), 'UTF-8')
                    : null;
                $fallback = $fallbackLoginField && null === $row['LOGIN']
                    ? mb_strtolower($this->utf8Encode($row['FALLBACK_LOGIN']), 'UTF-8')
                    : null;

                if (
                    (null !== $primary && ($primary == $compareLogin
                    || $primary == $this->sanitizePIN($compareLogin)))
                    || ($fallbackLoginField && null === $primary
                    && $fallback == $compareLogin)
                ) {
                    return [
                        'id' => $this->utf8Encode($row['PATRON_ID']),
                        'firstname' => $this->utf8Encode($row['FIRST_NAME']),
                        'lastname' => $this->utf8Encode($row['LAST_NAME']),
                        'cat_username' => $username,
                        'cat_password' => $login,
                        // There's supposed to be a getPatronEmailAddress stored
                        // procedure in Oracle, but I couldn't get it to work here;
                        // might be worth investigating further if needed later.
                        'email' => null,
                        'major' => null,
                        'college' => null];
                }
            }
            return null;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getMyTransactionsSQL($patron)
    {
        // Expressions
        $sqlExpressions = [
            "to_char(MAX(CIRC_TRANSACTIONS.CURRENT_DUE_DATE), 'MM-DD-YY HH24:MI')" .
            ' as DUEDATE',
            "to_char(MAX(CURRENT_DUE_DATE), 'YYYYMMDD HH24:MI') as FULLDATE",
            'MAX(BIB_ITEM.BIB_ID) AS BIB_ID',
            'MAX(CIRC_TRANSACTIONS.ITEM_ID) as ITEM_ID',
            'MAX(MFHD_ITEM.ITEM_ENUM) AS ITEM_ENUM',
            'MAX(MFHD_ITEM.YEAR) AS YEAR',
            'MAX(ITEM_BARCODE.ITEM_BARCODE) AS ITEM_BARCODE',
            'MAX(BIB_TEXT.TITLE_BRIEF) AS TITLE_BRIEF',
            'MAX(BIB_TEXT.TITLE) AS TITLE',
            'LISTAGG(ITEM_STATUS_DESC, CHR(9)) '
            . 'WITHIN GROUP (ORDER BY ITEM_STATUS_DESC) as status',
            'MAX(CIRC_TRANSACTIONS.RENEWAL_COUNT) AS RENEWAL_COUNT',
            'MAX(CIRC_POLICY_MATRIX.RENEWAL_COUNT) as RENEWAL_LIMIT',
            'MAX(LOCATION.LOCATION_DISPLAY_NAME) as BORROWING_LOCATION',
            'MAX(CIRC_POLICY_MATRIX.LOAN_INTERVAL) as LOAN_INTERVAL',
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.CIRC_TRANSACTIONS',
            $this->dbName . '.BIB_ITEM',
            $this->dbName . '.ITEM',
            $this->dbName . '.ITEM_STATUS',
            $this->dbName . '.ITEM_STATUS_TYPE',
            $this->dbName . '.ITEM_BARCODE',
            $this->dbName . '.MFHD_ITEM',
            $this->dbName . '.BIB_TEXT',
            $this->dbName . '.CIRC_POLICY_MATRIX',
            $this->dbName . '.LOCATION',
        ];

        // Where
        $sqlWhere = [
            'CIRC_TRANSACTIONS.PATRON_ID = :id',
            'BIB_ITEM.ITEM_ID = CIRC_TRANSACTIONS.ITEM_ID',
            'CIRC_TRANSACTIONS.ITEM_ID = MFHD_ITEM.ITEM_ID(+)',
            'BIB_TEXT.BIB_ID = BIB_ITEM.BIB_ID',
            'CIRC_TRANSACTIONS.CIRC_POLICY_MATRIX_ID = ' .
            'CIRC_POLICY_MATRIX.CIRC_POLICY_MATRIX_ID',
            'CIRC_TRANSACTIONS.CHARGE_LOCATION = LOCATION.LOCATION_ID',
            'BIB_ITEM.ITEM_ID = ITEM.ITEM_ID',
            'ITEM.ITEM_ID = ITEM_STATUS.ITEM_ID',
            'ITEM_STATUS.ITEM_STATUS = ITEM_STATUS_TYPE.ITEM_STATUS_TYPE',
            'ITEM.ITEM_ID = ITEM_BARCODE.ITEM_ID(+)',
            '(ITEM_BARCODE.BARCODE_STATUS IS NULL OR ' .
            'ITEM_BARCODE.BARCODE_STATUS IN (SELECT BARCODE_STATUS_TYPE FROM ' .
            "$this->dbName.ITEM_BARCODE_STATUS " .
            " WHERE BARCODE_STATUS_DESC = 'Active'))",
        ];

        // Order
        $sqlOrder = ['FULLDATE ASC', 'TITLE ASC'];

        // Bind
        $sqlBind = [':id' => $patron['id']];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind,
            'group' => ['CIRC_TRANSACTIONS.ITEM_ID'],
        ];

        return $sqlArray;
    }

    /**
     * Pick a transaction status worth displaying to the user (or return false
     * if nothing important is found).
     *
     * @param array $statuses Status strings
     *
     * @return string|bool
     */
    protected function pickTransactionStatus($statuses)
    {
        $regex = $this->config['Loans']['show_statuses'] ?? '/lost|missing|claim/i';
        $retVal = [];
        foreach ($statuses as $status) {
            if (preg_match($regex, $status)) {
                $retVal[] = $status;
            }
        }
        return empty($retVal) ? false : implode(', ', $retVal);
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $sqlRow An array of keyed data
     * @param array $patron An array of keyed patron data
     *
     * @throws DateException
     * @return array Keyed data for display by template files
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function processMyTransactionsData($sqlRow, $patron = false)
    {
        // Convert Voyager Format to display format
        if (!empty($sqlRow['DUEDATE'])) {
            $dueDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y H:i',
                $sqlRow['DUEDATE']
            );
            $dueTime = $this->dateFormat->convertToDisplayTime(
                'm-d-y H:i',
                $sqlRow['DUEDATE']
            );
        }

        $dueStatus = false;
        if (!empty($sqlRow['FULLDATE'])) {
            $now = time();
            $dueTimeStamp = strtotime($sqlRow['FULLDATE']);
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = 'overdue';
                } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                    $dueStatus = 'due';
                }
            }
        }

        $transaction = [
            'id' => $sqlRow['BIB_ID'],
            'item_id' => $sqlRow['ITEM_ID'],
            'barcode' => $this->utf8Encode($sqlRow['ITEM_BARCODE']),
            'duedate' => $dueDate,
            'dueStatus' => $dueStatus,
            'volume' => str_replace('v.', '', $this->utf8Encode($sqlRow['ITEM_ENUM'])),
            'publication_year' => $sqlRow['YEAR'],
            'title' => empty($sqlRow['TITLE_BRIEF'])
                ? $sqlRow['TITLE'] : $sqlRow['TITLE_BRIEF'],
            'renew' => $sqlRow['RENEWAL_COUNT'],
            'renewLimit' => $sqlRow['RENEWAL_LIMIT'],
            'message' =>
                $this->pickTransactionStatus(explode(chr(9), $sqlRow['STATUS'])),
        ];
        // Display due time only if loan interval is not in days if configured
        if (
            empty($this->displayDueTimeIntervals)
            || in_array($sqlRow['LOAN_INTERVAL'], $this->displayDueTimeIntervals)
        ) {
            $transaction['dueTime'] = $dueTime;
        }
        if (!empty($this->config['Loans']['display_borrowing_location'])) {
            $transaction['borrowingLocation']
                = $this->utf8Encode($sqlRow['BORROWING_LOCATION']);
        }

        return $transaction;
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

        $sqlArray = $this->getMyTransactionsSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $processRow = $this->processMyTransactionsData($row, $patron);
                $transList[] = $processRow;
            }
            return $transList;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
    }

    /**
     * Protected support method for getMyFines.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getFineSQL($patron)
    {
        // Expressions
        $sqlExpressions = [
            'FINE_FEE_TYPE.FINE_FEE_DESC',
            'PATRON.PATRON_ID', 'FINE_FEE.FINE_FEE_AMOUNT',
            'FINE_FEE.FINE_FEE_BALANCE',
            "to_char(FINE_FEE.CREATE_DATE, 'MM-DD-YY HH:MI:SS') as CREATEDATE",
            "to_char(FINE_FEE.ORIG_CHARGE_DATE, 'MM-DD-YY') as CHARGEDATE",
            "to_char(FINE_FEE.DUE_DATE, 'MM-DD-YY') as DUEDATE",
            'BIB_ITEM.BIB_ID',
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.FINE_FEE', $this->dbName . '.FINE_FEE_TYPE',
            $this->dbName . '.PATRON', $this->dbName . '.BIB_ITEM',
        ];

        // Where
        $sqlWhere = [
            'PATRON.PATRON_ID = :id',
            'FINE_FEE.FINE_FEE_TYPE = FINE_FEE_TYPE.FINE_FEE_TYPE',
            'FINE_FEE.PATRON_ID  = PATRON.PATRON_ID',
            'FINE_FEE.ITEM_ID = BIB_ITEM.ITEM_ID(+)',
            'FINE_FEE.FINE_FEE_BALANCE > 0',
        ];

        // Bind
        $sqlBind = [':id' => $patron['id']];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getMyFines.
     *
     * @param array $sqlRow An array of keyed data
     *
     * @throws DateException
     * @return array Keyed data for display by template files
     */
    protected function processFinesData($sqlRow)
    {
        $dueDate = $this->translate('not_applicable');
        // Convert Voyager Format to display format
        if (!empty($sqlRow['DUEDATE'])) {
            $dueDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['DUEDATE']
            );
        }

        $createDate = $this->translate('not_applicable');
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CREATEDATE'])) {
            $createDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['CREATEDATE']
            );
        }

        $chargeDate = $this->translate('not_applicable');
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CHARGEDATE'])) {
            $chargeDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['CHARGEDATE']
            );
        }

        return ['amount' => $sqlRow['FINE_FEE_AMOUNT'],
              'fine' => $this->utf8Encode($sqlRow['FINE_FEE_DESC']),
              'balance' => $sqlRow['FINE_FEE_BALANCE'],
              'createdate' => $createDate,
              'checkout' => $chargeDate,
              'duedate' => $dueDate,
              'id' => $sqlRow['BIB_ID']];
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

        $sqlArray = $this->getFineSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $processFine = $this->processFinesData($row);
                $fineList[] = $processFine;
            }
            return $fineList;
        } catch (PDOException $e) {
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
    protected function getMyHoldsSQL($patron)
    {
        // Modifier
        $sqlSelectModifier = 'distinct';

        // Expressions
        $sqlExpressions = [
            'HOLD_RECALL.HOLD_RECALL_ID', 'HOLD_RECALL.BIB_ID',
            'HOLD_RECALL.PICKUP_LOCATION',
            'HOLD_RECALL.HOLD_RECALL_TYPE',
            "to_char(HOLD_RECALL.EXPIRE_DATE, 'MM-DD-YY') as EXPIRE_DATE",
            "to_char(HOLD_RECALL.CREATE_DATE, 'MM-DD-YY') as CREATE_DATE",
            'HOLD_RECALL_ITEMS.ITEM_ID',
            'HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS',
            'HOLD_RECALL_ITEMS.QUEUE_POSITION',
            'MFHD_ITEM.ITEM_ENUM',
            'MFHD_ITEM.YEAR',
            'BIB_TEXT.TITLE_BRIEF',
            'BIB_TEXT.TITLE',
            'REQUEST_GROUP.GROUP_NAME as REQUEST_GROUP_NAME',
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.HOLD_RECALL',
            $this->dbName . '.HOLD_RECALL_ITEMS',
            $this->dbName . '.MFHD_ITEM',
            $this->dbName . '.BIB_TEXT',
            $this->dbName . '.VOYAGER_DATABASES',
            $this->dbName . '.REQUEST_GROUP',
        ];

        // Where
        $sqlWhere = [
            'HOLD_RECALL.PATRON_ID = :id',
            'HOLD_RECALL.HOLD_RECALL_ID = HOLD_RECALL_ITEMS.HOLD_RECALL_ID(+)',
            'HOLD_RECALL_ITEMS.ITEM_ID = MFHD_ITEM.ITEM_ID(+)',
            '(HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS IS NULL OR ' .
            'HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS < 3)',
            'BIB_TEXT.BIB_ID = HOLD_RECALL.BIB_ID',
            '(HOLD_RECALL.HOLDING_DB_ID IS NULL OR HOLD_RECALL.HOLDING_DB_ID = 0 ' .
            'OR (HOLD_RECALL.HOLDING_DB_ID = ' .
            "VOYAGER_DATABASES.DB_ID AND VOYAGER_DATABASES.DB_CODE = 'LOCAL'))",
            'HOLD_RECALL.REQUEST_GROUP_ID = REQUEST_GROUP.GROUP_ID(+)',
        ];

        // Bind
        $sqlBind = [':id' => $patron['id']];

        $sqlArray = [
            'modifier' => $sqlSelectModifier,
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getMyHolds.
     *
     * @param array $sqlRow An array of keyed data
     *
     * @throws DateException
     * @return array Keyed data for display by template files
     */
    protected function processMyHoldsData($sqlRow)
    {
        $available = ($sqlRow['HOLD_RECALL_STATUS'] == 2) ? true : false;
        $expireDate = $this->translate('Unknown');
        // Convert Voyager Format to display format
        if (!empty($sqlRow['EXPIRE_DATE'])) {
            $expireDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['EXPIRE_DATE']
            );
        }

        $createDate = $this->translate('Unknown');
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CREATE_DATE'])) {
            $createDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['CREATE_DATE']
            );
        }

        return [
            'id' => $sqlRow['BIB_ID'],
            'type' => $sqlRow['HOLD_RECALL_TYPE'],
            'location' => $sqlRow['PICKUP_LOCATION'],
            'requestGroup' => $sqlRow['REQUEST_GROUP_NAME'],
            'expire' => $expireDate,
            'create' => $createDate,
            'position' => $sqlRow['QUEUE_POSITION'],
            'available' => $available,
            'reqnum' => $sqlRow['HOLD_RECALL_ID'],
            'item_id' => $sqlRow['ITEM_ID'],
            'volume' => str_replace('v.', '', $this->utf8Encode($sqlRow['ITEM_ENUM'])),
            'publication_year' => $sqlRow['YEAR'],
            'title' => empty($sqlRow['TITLE_BRIEF'])
                ? $sqlRow['TITLE'] : $sqlRow['TITLE_BRIEF'],
        ];
    }

    /**
     * Process Holds List
     *
     * This is responsible for processing holds to ensure only one record is shown
     * for each hold.
     *
     * @param array $holdList The Hold List Array
     *
     * @return mixed Array of the patron's holds.
     */
    protected function processHoldsList($holdList)
    {
        $returnList = [];

        if (!empty($holdList)) {
            $sortHoldList = [];
            // Get a unique List of Bib Ids
            foreach ($holdList as $holdItem) {
                $sortHoldList[$holdItem['id']][] = $holdItem;
            }

            // Use the first copy hold only
            foreach ($sortHoldList as $bibHold) {
                $returnList[] = $bibHold[0];
            }
        }
        return $returnList;
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
        $returnList = [];

        $sqlArray = $this->getMyHoldsSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $holds = $this->processMyHoldsData($sqlRow);
                $holdList[] = $holds;
            }
            $returnList = $this->processHoldsList($holdList);
            return $returnList;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
    }

    /**
     * Protected support method for getMyStorageRetrievalRequests.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getMyStorageRetrievalRequestsSQL($patron)
    {
        // Modifier
        $sqlSelectModifier = 'distinct';

        // Expressions
        $sqlExpressions = [
            'CALL_SLIP.CALL_SLIP_ID', 'CALL_SLIP.BIB_ID',
            'CALL_SLIP.PICKUP_LOCATION_ID',
            "to_char(CALL_SLIP.DATE_REQUESTED, 'YYYY-MM-DD HH24:MI:SS')"
                . ' as CREATE_DATE',
            "to_char(CALL_SLIP.DATE_PROCESSED, 'YYYY-MM-DD HH24:MI:SS')"
                . ' as PROCESSED_DATE',
            "to_char(CALL_SLIP.STATUS_DATE, 'YYYY-MM-DD HH24:MI:SS')"
                . ' as STATUS_DATE',
            'CALL_SLIP.ITEM_ID',
            'CALL_SLIP.MFHD_ID',
            'CALL_SLIP.STATUS',
            'CALL_SLIP_STATUS_TYPE.STATUS_DESC',
            'CALL_SLIP.ITEM_YEAR',
            'CALL_SLIP.ITEM_ENUM',
            'CALL_SLIP.ITEM_CHRON',
            'CALL_SLIP.REPLY_NOTE',
            'CALL_SLIP.PICKUP_LOCATION_ID',
            'MFHD_ITEM.ITEM_ENUM',
            'MFHD_ITEM.YEAR',
            'BIB_TEXT.TITLE_BRIEF',
            'BIB_TEXT.TITLE',
        ];

        // From
        $sqlFrom = [
            $this->dbName . '.CALL_SLIP',
            $this->dbName . '.CALL_SLIP_STATUS_TYPE',
            $this->dbName . '.MFHD_ITEM',
            $this->dbName . '.BIB_TEXT',
        ];

        // Where
        $sqlWhere = [
            'CALL_SLIP.PATRON_ID = :id',
            'CALL_SLIP.STATUS = CALL_SLIP_STATUS_TYPE.STATUS_TYPE(+)',
            'CALL_SLIP.ITEM_ID = MFHD_ITEM.ITEM_ID(+)',
            'BIB_TEXT.BIB_ID = CALL_SLIP.BIB_ID',
        ];

        if (!empty($this->config['StorageRetrievalRequests']['display_statuses'])) {
            $statuses = preg_replace(
                '/[^:\d]*/',
                '',
                $this->config['StorageRetrievalRequests']['display_statuses']
            );
            if ($statuses) {
                $sqlWhere[] = 'CALL_SLIP.STATUS IN ('
                    . str_replace(':', ',', $statuses) . ')';
            }
        }

        // Order by
        $sqlOrderBy = [
            "to_char(CALL_SLIP.DATE_REQUESTED, 'YYYY-MM-DD HH24:MI:SS')",
        ];

        // Bind
        $sqlBind = [':id' => $patron['id']];

        $sqlArray = [
            'modifier' => $sqlSelectModifier,
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrderBy,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getMyStorageRetrievalRequests.
     *
     * @param array $sqlRow An array of keyed data
     *
     * @return array Keyed data for display by template files
     */
    protected function processMyStorageRetrievalRequestsData($sqlRow)
    {
        $available = ($sqlRow['STATUS'] == 4) ? true : false;
        $expireDate = '';
        $processedDate = '';
        $statusDate = '';
        // Convert Voyager Format to display format
        if (!empty($sqlRow['PROCESSED_DATE'])) {
            $processedDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['PROCESSED_DATE']
            );
        }
        if (!empty($sqlRow['STATUS_DATE'])) {
            $statusDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['STATUS_DATE']
            );
        }

        $createDate = $this->translate('Unknown');
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CREATE_DATE'])) {
            $createDate = $this->dateFormat->convertToDisplayDate(
                'm-d-y',
                $sqlRow['CREATE_DATE']
            );
        }

        return [
            'id' => $sqlRow['BIB_ID'],
            'status' => $this->utf8Encode($sqlRow['STATUS_DESC']),
            'statusDate' => $statusDate,
            'location' => $this->getLocationName($sqlRow['PICKUP_LOCATION_ID']),
            'create' => $createDate,
            'processed' => $processedDate,
            'expire' => $expireDate,
            'reply' => $this->utf8Encode($sqlRow['REPLY_NOTE']),
            'available' => $available,
            'canceled' => $sqlRow['STATUS'] == 7 ? $statusDate : false,
            'reqnum' => $sqlRow['CALL_SLIP_ID'],
            'item_id' => $sqlRow['ITEM_ID'],
            'volume' => str_replace(
                'v.',
                '',
                $this->utf8Encode($sqlRow['ITEM_ENUM'])
            ),
            'issue' => $this->utf8Encode($sqlRow['ITEM_CHRON']),
            'year' => $this->utf8Encode($sqlRow['ITEM_YEAR']),
            'title' => empty($sqlRow['TITLE_BRIEF'])
                ? $sqlRow['TITLE'] : $sqlRow['TITLE_BRIEF'],
        ];
    }

    /**
     * Get Patron Storage Retrieval Requests
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return array        Array of the patron's storage retrieval requests.
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        $list = [];

        $sqlArray = $this->getMyStorageRetrievalRequestsSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->processMyStorageRetrievalRequestsData($sqlRow);
            }
            return $list;
        } catch (PDOException $e) {
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
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $sql = 'SELECT PATRON.LAST_NAME, PATRON.FIRST_NAME, ' .
               'PATRON.HISTORICAL_CHARGES, PATRON_ADDRESS.ADDRESS_LINE1, ' .
               'PATRON_ADDRESS.ADDRESS_LINE2, PATRON_ADDRESS.ZIP_POSTAL, ' .
               'PATRON_ADDRESS.CITY, PATRON_ADDRESS.COUNTRY, ' .
               'PATRON_PHONE.PHONE_NUMBER, PHONE_TYPE.PHONE_DESC, ' .
               'PATRON_GROUP.PATRON_GROUP_NAME ' .
               "FROM $this->dbName.PATRON, $this->dbName.PATRON_ADDRESS, " .
               "$this->dbName.PATRON_PHONE, $this->dbName.PHONE_TYPE, " .
               "$this->dbName.PATRON_BARCODE, $this->dbName.PATRON_GROUP " .
               'WHERE PATRON.PATRON_ID = PATRON_ADDRESS.PATRON_ID (+) ' .
               'AND PATRON_ADDRESS.ADDRESS_ID = PATRON_PHONE.ADDRESS_ID (+) ' .
               'AND PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID (+) ' .
               'AND PATRON_BARCODE.PATRON_GROUP_ID = ' .
               'PATRON_GROUP.PATRON_GROUP_ID (+) ' .
               'AND PATRON_PHONE.PHONE_TYPE = PHONE_TYPE.PHONE_TYPE (+) ' .
               'AND PATRON.PATRON_ID = :id';
        $primaryPhoneType = $this->config['Profile']['primary_phone'] ?? 'Primary';
        $mobilePhoneType = $this->config['Profile']['mobile_phone'] ?? 'Mobile';
        try {
            $sqlStmt = $this->executeSQL($sql, [':id' => $patron['id']]);
            $patron = [];
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['FIRST_NAME'])) {
                    $patron['firstname'] = $this->utf8Encode($row['FIRST_NAME']);
                }
                if (!empty($row['LAST_NAME'])) {
                    $patron['lastname'] = $this->utf8Encode($row['LAST_NAME']);
                }
                if (!empty($row['PHONE_NUMBER'])) {
                    if ($primaryPhoneType === $row['PHONE_DESC']) {
                        $patron['phone'] = $this->utf8Encode($row['PHONE_NUMBER']);
                    } elseif ($mobilePhoneType === $row['PHONE_DESC']) {
                        $patron['mobile_phone'] = $this->utf8Encode($row['PHONE_NUMBER']);
                    }
                }
                if (!empty($row['PATRON_GROUP_NAME'])) {
                    $patron['group'] = $this->utf8Encode($row['PATRON_GROUP_NAME']);
                }
                $validator = new EmailAddressValidator();
                $addr1 = $this->utf8Encode($row['ADDRESS_LINE1']);
                if ($validator->isValid($addr1)) {
                    $patron['email'] = $addr1;
                } elseif (!isset($patron['address1'])) {
                    if (!empty($addr1)) {
                        $patron['address1'] = $addr1;
                    }
                    if (!empty($row['ADDRESS_LINE2'])) {
                        $patron['address2'] = $this->utf8Encode($row['ADDRESS_LINE2']);
                    }
                    if (!empty($row['ZIP_POSTAL'])) {
                        $patron['zip'] = $this->utf8Encode($row['ZIP_POSTAL']);
                    }
                    if (!empty($row['CITY'])) {
                        $patron['city'] = $this->utf8Encode($row['CITY']);
                    }
                    if (!empty($row['COUNTRY'])) {
                        $patron['country'] = $this->utf8Encode($row['COUNTRY']);
                    }
                }
            }
            return empty($patron) ? null : $patron;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
    }

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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldLink($recordId, $details)
    {
        // There is no easy way to link directly to hold screen; let's just use
        // the record view. For better hold behavior, use the VoyagerRestful
        // driver.
        return $this->config['Catalog']['pwebrecon'] . '?BBID=' . $recordId;
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
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $items = [];

        $bindParams = [
            ':enddate' => date('d-m-Y', strtotime('now')),
            ':startdate' => date('d-m-Y', strtotime("-$daysOld day")),
        ];

        $sql = 'select count(distinct LINE_ITEM.BIB_ID) as count ' .
               "from $this->dbName.LINE_ITEM, " .
               "$this->dbName.LINE_ITEM_COPY_STATUS, " .
               "$this->dbName.LINE_ITEM_FUNDS, $this->dbName.FUND " .
               'where LINE_ITEM.LINE_ITEM_ID = LINE_ITEM_COPY_STATUS.LINE_ITEM_ID ' .
               'and LINE_ITEM_COPY_STATUS.COPY_ID = LINE_ITEM_FUNDS.COPY_ID ' .
               'and LINE_ITEM_FUNDS.FUND_ID = FUND.FUND_ID ';
        if ($fundId) {
            // Although we're getting an ID value from getFunds() passed in here,
            // it's not actually an ID -- we use names as IDs (see note in getFunds
            // itself for more details).
            $sql .= 'and lower(FUND.FUND_NAME) = :fund ';
            $bindParams[':fund'] = strtolower($fundId);
        }
        $sql .= "and LINE_ITEM.CREATE_DATE >= to_date(:startdate, 'dd-mm-yyyy') " .
               "and LINE_ITEM.CREATE_DATE < to_date(:enddate, 'dd-mm-yyyy')";
        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['COUNT'];
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        $page = ($page) ? $page : 1;
        $limit = ($limit) ? $limit : 20;
        $bindParams[':startRow'] = (($page - 1) * $limit) + 1;
        $bindParams[':endRow'] = ($page * $limit);
        $sql = 'select * from ' .
               '(select a.*, rownum rnum from ' .
               '(select LINE_ITEM.BIB_ID, LINE_ITEM.CREATE_DATE ' .
               "from $this->dbName.LINE_ITEM, " .
               "$this->dbName.LINE_ITEM_COPY_STATUS, " .
               "$this->dbName.LINE_ITEM_STATUS, $this->dbName.LINE_ITEM_FUNDS, " .
               "$this->dbName.FUND " .
               'where LINE_ITEM.LINE_ITEM_ID = LINE_ITEM_COPY_STATUS.LINE_ITEM_ID ' .
               'and LINE_ITEM_COPY_STATUS.COPY_ID = LINE_ITEM_FUNDS.COPY_ID ' .
               'and LINE_ITEM_STATUS.LINE_ITEM_STATUS = ' .
               'LINE_ITEM_COPY_STATUS.LINE_ITEM_STATUS ' .
               'and LINE_ITEM_FUNDS.FUND_ID = FUND.FUND_ID ';
        if ($fundId) {
            $sql .= 'and lower(FUND.FUND_NAME) = :fund ';
        }
        $sql .= "and LINE_ITEM.CREATE_DATE >= to_date(:startdate, 'dd-mm-yyyy') " .
               "and LINE_ITEM.CREATE_DATE < to_date(:enddate, 'dd-mm-yyyy') " .
               'group by LINE_ITEM.BIB_ID, LINE_ITEM.CREATE_DATE ' .
               'order by LINE_ITEM.CREATE_DATE desc) a ' .
               'where rownum <= :endRow) ' .
               'where rnum >= :startRow';
        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['BIB_ID'];
            }
            return $items;
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
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
        $list = [];

        // Are funds disabled?  If so, do no work!
        if ($this->config['Funds']['disabled'] ?? false) {
            return $list;
        }

        // Load and normalize inclusion/exclusion lists if necessary:
        $rawIncludeList = $this->config['Funds']['include_list']
            ?? $this->config['Funds']['whitelist'] // deprecated terminology
            ?? null;
        $include = is_array($rawIncludeList)
            ? array_map('strtolower', $rawIncludeList) : false;
        $rawExcludeList = $this->config['Funds']['exclude_list']
            ?? $this->config['Funds']['blacklist'] // deprecated terminology
            ?? null;
        $exclude = is_array($rawExcludeList)
            ? array_map('strtolower', $rawExcludeList) : false;

        // Retrieve the data from Voyager; if we're limiting to a parent fund, we
        // need to apply a special WHERE clause and bind parameter.
        if (isset($this->config['Funds']['parent_fund'])) {
            $bindParams = [':parent' => $this->config['Funds']['parent_fund']];
            $whereClause = 'WHERE FUND.PARENT_FUND = :parent';
        } else {
            $bindParams = [];
            $whereClause = '';
        }
        $sql = 'select distinct lower(FUND.FUND_NAME) as name ' .
            "from $this->dbName.FUND {$whereClause} order by name";
        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                // Process inclusion/exclusion lists to skip illegal values:
                if (
                    (is_array($exclude) && in_array($row['NAME'], $exclude))
                    || (is_array($include) && !in_array($row['NAME'], $include))
                ) {
                    continue;
                }

                // Normalize the capitalization of the name:
                $name = ucwords($row['NAME']);

                // Set the array key to the lookup ID used by getNewItems and the
                // array value to the on-screen display name.
                //
                // We actually want to use the NAME of the fund to do lookups, not
                // its ID. This is because multiple funds may share the same name,
                // and it is useful to collate all these results together. To
                // achieve the effect, we just fill the same value in as the name
                // and the ID in the return array.
                //
                // If you want to change this code to use numeric IDs instead,
                // you can adjust the SQL above, change the array key used in the
                // line below, and adjust the lookups done in getNewItems().
                $list[$name] = $name;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $list;
    }

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
        $deptList = [];

        $sql = 'select DEPARTMENT.DEPARTMENT_ID, DEPARTMENT.DEPARTMENT_NAME ' .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.DEPARTMENT " .
               'where ' .
               'RESERVE_LIST.RESERVE_LIST_ID = ' .
               'RESERVE_LIST_COURSES.RESERVE_LIST_ID and ' .
               'RESERVE_LIST_COURSES.DEPARTMENT_ID = DEPARTMENT.DEPARTMENT_ID ' .
               'group by DEPARTMENT.DEPARTMENT_ID, DEPARTMENT_NAME ' .
               'order by DEPARTMENT_NAME';
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $deptList[$row['DEPARTMENT_ID']] = $row['DEPARTMENT_NAME'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $deptList;
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
        $instList = [];

        $sql = 'select INSTRUCTOR.INSTRUCTOR_ID, ' .
               "INSTRUCTOR.LAST_NAME || ', ' || INSTRUCTOR.FIRST_NAME as NAME " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.INSTRUCTOR " .
               'where RESERVE_LIST.RESERVE_LIST_ID = ' .
               'RESERVE_LIST_COURSES.RESERVE_LIST_ID and ' .
               'RESERVE_LIST_COURSES.INSTRUCTOR_ID = INSTRUCTOR.INSTRUCTOR_ID ' .
               'group by INSTRUCTOR.INSTRUCTOR_ID, LAST_NAME, FIRST_NAME ' .
               'order by LAST_NAME';
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $instList[$row['INSTRUCTOR_ID']] = $row['NAME'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $instList;
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
        $courseList = [];

        $sql = "select COURSE.COURSE_NUMBER || ': ' || COURSE.COURSE_NAME as NAME," .
               ' COURSE.COURSE_ID ' .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.COURSE " .
               'where RESERVE_LIST.RESERVE_LIST_ID = ' .
               'RESERVE_LIST_COURSES.RESERVE_LIST_ID and ' .
               'RESERVE_LIST_COURSES.COURSE_ID = COURSE.COURSE_ID ' .
               'group by COURSE.COURSE_ID, COURSE_NUMBER, COURSE_NAME ' .
               'order by COURSE_NUMBER';
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $courseList[$row['COURSE_ID']] = $row['NAME'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $courseList;
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * This version of findReserves was contributed by Matthew Hooper and includes
     * support for electronic reserves (though eReserve support is still a work in
     * progress).
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     */
    public function findReserves($course, $inst, $dept)
    {
        $recordList = [];
        $reserveWhere = [];
        $bindParams = [];

        if ($course != '') {
            $reserveWhere[] = 'RESERVE_LIST_COURSES.COURSE_ID = :course';
            $bindParams[':course'] = $course;
        }
        if ($inst != '') {
            $reserveWhere[] = 'RESERVE_LIST_COURSES.INSTRUCTOR_ID = :inst';
            $bindParams[':inst'] = $inst;
        }
        if ($dept != '') {
            $reserveWhere[] = 'RESERVE_LIST_COURSES.DEPARTMENT_ID = :dept';
            $bindParams[':dept'] = $dept;
        }

        $reserveWhere = empty($reserveWhere) ?
            '' : 'where (' . implode(' AND ', $reserveWhere) . ')';

        /* OLD SQL -- simpler but without support for the Solr-based reserves
         * module:
        $sql = " select MFHD_MASTER.DISPLAY_CALL_NO, BIB_TEXT.BIB_ID, " .
               " BIB_TEXT.AUTHOR, BIB_TEXT.TITLE, " .
               " BIB_TEXT.PUBLISHER, BIB_TEXT.PUBLISHER_DATE " .
               " FROM $this->dbName.BIB_TEXT, $this->dbName.MFHD_MASTER where " .
               " bib_text.bib_id = (select bib_mfhd.bib_id " .
               " from $this->dbName.bib_mfhd " .
               " where bib_mfhd.mfhd_id = mfhd_master.mfhd_id) " .
               " and " .
               "  mfhd_master.mfhd_id in ( ".
               "  ((select distinct eitem.mfhd_id from $this->dbName.eitem where " .
               "    eitem.eitem_id in " .
               "    (select distinct reserve_list_eitems.eitem_id from " .
               "     $this->dbName.reserve_list_eitems" .
               "     where reserve_list_eitems.reserve_list_id in " .
               "     (select distinct reserve_list_courses.reserve_list_id from " .
               "      $this->dbName.reserve_list_courses " .
               "      $reserveWhere )) )) union " .
               "  ((select distinct mfhd_item.mfhd_id from $this->dbName.mfhd_item" .
               "    where mfhd_item.item_id in " .
               "    (select distinct reserve_list_items.item_id from " .
               "    $this->dbName.reserve_list_items" .
               "    where reserve_list_items.reserve_list_id in " .
               "    (select distinct reserve_list_courses.reserve_list_id from " .
               "      $this->dbName.reserve_list_courses $reserveWhere )) )) " .
               "  ) ";
         */
        $sql = ' select MFHD_MASTER.DISPLAY_CALL_NO, BIB_TEXT.BIB_ID, ' .
               ' BIB_TEXT.AUTHOR, BIB_TEXT.TITLE, ' .
               ' BIB_TEXT.PUBLISHER, BIB_TEXT.PUBLISHER_DATE, subquery.COURSE_ID, ' .
               ' subquery.INSTRUCTOR_ID, subquery.DEPARTMENT_ID ' .
               " FROM $this->dbName.BIB_TEXT " .
               " JOIN $this->dbName.BIB_MFHD ON BIB_TEXT.BIB_ID=BIB_MFHD.BIB_ID " .
               " JOIN $this->dbName.MFHD_MASTER " .
               ' ON BIB_MFHD.MFHD_ID = MFHD_MASTER.MFHD_ID' .
               ' JOIN ' .
               '  ( ' .
               '  ((select distinct eitem.mfhd_id, subsubquery1.COURSE_ID, ' .
               '     subsubquery1.INSTRUCTOR_ID, subsubquery1.DEPARTMENT_ID ' .
               "     from $this->dbName.eitem join " .
               '    (select distinct reserve_list_eitems.eitem_id, ' .
               '     RESERVE_LIST_COURSES.COURSE_ID, ' .
               '     RESERVE_LIST_COURSES.INSTRUCTOR_ID, ' .
               '     RESERVE_LIST_COURSES.DEPARTMENT_ID from ' .
               "     $this->dbName.reserve_list_eitems" .
               "     JOIN $this->dbName.reserve_list_courses ON " .
               '      reserve_list_courses.reserve_list_id = ' .
               '      reserve_list_eitems.reserve_list_id' .
               "      $reserveWhere ) subsubquery1 ON " .
               '      subsubquery1.eitem_id = eitem.eitem_id)) union ' .
               '  ((select distinct mfhd_item.mfhd_id, subsubquery2.COURSE_ID, ' .
               '    subsubquery2.INSTRUCTOR_ID, subsubquery2.DEPARTMENT_ID ' .
               "    from $this->dbName.mfhd_item join" .
               '    (select distinct reserve_list_items.item_id, ' .
               '     RESERVE_LIST_COURSES.COURSE_ID, ' .
               '     RESERVE_LIST_COURSES.INSTRUCTOR_ID, ' .
               '     RESERVE_LIST_COURSES.DEPARTMENT_ID from ' .
               "    $this->dbName.reserve_list_items" .
               "    JOIN $this->dbName.reserve_list_courses on " .
               '    reserve_list_items.reserve_list_id = ' .
               '    reserve_list_courses.reserve_list_id' .
               "    $reserveWhere) subsubquery2 ON " .
               '    subsubquery2.item_id = mfhd_item.item_id )) ' .
               '  ) subquery ON mfhd_master.mfhd_id = subquery.mfhd_id ';

        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $recordList[] = $row;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $recordList;
    }

    /**
     * Get bib records for recently returned items.
     *
     * @param int   $limit  Maximum number of records to retrieve (default = 30)
     * @param int   $maxage The maximum number of days to consider "recently
     * returned."
     * @param array $patron Patron Data
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRecentlyReturnedBibs(
        $limit = 30,
        $maxage = 30,
        $patron = null
    ) {
        $recordList = [];

        // Oracle does not support the SQL LIMIT clause before version 12, so
        // instead we need to provide an optimizer hint, which requires us to
        // ensure that $limit is a valid integer.
        $intLimit = intval($limit);
        $safeLimit = $intLimit < 1 ? 30 : $intLimit;

        $sql = "select /*+ FIRST_ROWS($safeLimit) */ BIB_MFHD.BIB_ID, "
            . 'max(CIRC_TRANS_ARCHIVE.DISCHARGE_DATE) as RETURNED '
            . "from $this->dbName.CIRC_TRANS_ARCHIVE "
            . "join $this->dbName.MFHD_ITEM "
            . 'on CIRC_TRANS_ARCHIVE.ITEM_ID = MFHD_ITEM.ITEM_ID '
            . "join $this->dbName.BIB_MFHD "
            . 'on BIB_MFHD.MFHD_ID = MFHD_ITEM.MFHD_ID '
            . "join $this->dbName.BIB_MASTER "
            . 'on BIB_MASTER.BIB_ID = BIB_MFHD.BIB_ID '
            . 'where CIRC_TRANS_ARCHIVE.DISCHARGE_DATE is not null '
            . 'and CIRC_TRANS_ARCHIVE.DISCHARGE_DATE > SYSDATE - :maxage '
            . "and BIB_MASTER.SUPPRESS_IN_OPAC='N' "
            . 'group by BIB_MFHD.BIB_ID '
            . 'order by RETURNED desc';
        try {
            $sqlStmt = $this->executeSQL($sql, [':maxage' => $maxage]);
            while (
                count($recordList) < $limit
                && $row = $sqlStmt->fetch(PDO::FETCH_ASSOC)
            ) {
                $recordList[] = ['id' => $row['BIB_ID']];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $recordList;
    }

    /**
     * Get bib records for "trending" items (recently returned with high usage).
     *
     * @param int   $limit  Maximum number of records to retrieve (default = 30)
     * @param int   $maxage The maximum number of days' worth of data to examine.
     * @param array $patron Patron Data
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getTrendingBibs($limit = 30, $maxage = 30, $patron = null)
    {
        $recordList = [];

        // Oracle does not support the SQL LIMIT clause before version 12, so
        // instead we need to provide an optimizer hint, which requires us to
        // ensure that $limit is a valid integer.
        $intLimit = intval($limit);
        $safeLimit = $intLimit < 1 ? 30 : $intLimit;

        $sql = "select /*+ FIRST_ROWS($safeLimit) */ BIB_MFHD.BIB_ID, "
            . 'count(CIRC_TRANS_ARCHIVE.DISCHARGE_DATE) as RECENT, '
            . 'sum(ITEM.HISTORICAL_CHARGES) as OVERALL '
            . "from $this->dbName.CIRC_TRANS_ARCHIVE "
            . "join $this->dbName.MFHD_ITEM "
            . 'on CIRC_TRANS_ARCHIVE.ITEM_ID = MFHD_ITEM.ITEM_ID '
            . "join $this->dbName.BIB_MFHD "
            . 'on BIB_MFHD.MFHD_ID = MFHD_ITEM.MFHD_ID '
            . "join $this->dbName.ITEM "
            . 'on CIRC_TRANS_ARCHIVE.ITEM_ID = ITEM.ITEM_ID '
            . "join $this->dbName.BIB_MASTER "
            . 'on BIB_MASTER.BIB_ID = BIB_MFHD.BIB_ID '
            . 'where CIRC_TRANS_ARCHIVE.DISCHARGE_DATE is not null '
            . 'and CIRC_TRANS_ARCHIVE.DISCHARGE_DATE > SYSDATE - :maxage '
            . "and BIB_MASTER.SUPPRESS_IN_OPAC='N' "
            . 'group by BIB_MFHD.BIB_ID '
            . 'order by RECENT desc, OVERALL desc';
        try {
            $sqlStmt = $this->executeSQL($sql, [':maxage' => $maxage]);
            while (
                count($recordList) < $limit
                && $row = $sqlStmt->fetch(PDO::FETCH_ASSOC)
            ) {
                $recordList[] = ['id' => $row['BIB_ID']];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $recordList;
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

        $sql = 'select BIB_MASTER.BIB_ID ' .
               "from $this->dbName.BIB_MASTER " .
               "where BIB_MASTER.SUPPRESS_IN_OPAC='Y'";
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['BIB_ID'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $list;
    }

    /**
     * Execute an SQL query
     *
     * @param string|array $sql  SQL statement (string or array that includes
     * bind params)
     * @param array        $bind Bind parameters (if $sql is string)
     *
     * @return PDOStatement
     */
    protected function executeSQL($sql, $bind = [])
    {
        if (is_array($sql)) {
            $bind = $sql['bind'];
            $sql = $sql['string'];
        }
        if ($this->logger) {
            [, $caller] = debug_backtrace(false);
            $this->debugSQL($caller['function'], $sql, $bind);
        }
        $sqlStmt = $this->getDb()->prepare($sql);
        $sqlStmt->execute($bind);

        return $sqlStmt;
    }

    /**
     * Convert string from ISO 8859-1 into UTF-8
     *
     * @param string $iso88591 String to convert
     *
     * @return string
     */
    protected function utf8Encode(string $iso88591): string
    {
        return mb_convert_encoding($iso88591, 'UTF-8', 'ISO-8859-1');
    }
}
