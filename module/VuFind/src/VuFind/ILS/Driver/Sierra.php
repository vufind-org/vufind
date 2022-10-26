<?php

/**
 * Sierra (III) ILS Driver for VuFind
 *
 * PHP version 7
 *
 * Copyright (C) 2013 Julia Bauder
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Sierra (III) ILS Driver for VuFind
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Sierra extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Database connection
     *
     * @var resource
     */
    protected $db;

    /**
     * Removes leading ".b" and trailing check digit from id numbers before querying
     * the database with them
     *
     * @param string $id ID string
     *
     * @return string
     */
    protected function idStrip($id)
    {
        $id = preg_replace('/\.b/', '', $id);
        $id = substr($id, 0, -1);
        return $id;
    }

    /**
     * Converts the record numbers in the Postgres database to the record numbers in
     * VuFind
     *
     * @param string $bareNumber Record number from database
     *
     * @return string
     */
    protected function createFullId($bareNumber)
    {
        $digitArray = str_split($bareNumber);
        $numberLength = count($digitArray);
        $partialCheck = 0;
        // see Millennium manual page #105781 for the logic behind this
        for ($i = $numberLength; $i > 0; $i--) {
            $j = $numberLength - $i;
            $partialCheck = $partialCheck + ($digitArray[$j] * ($i + 1));
        }
        $checkdigit = $partialCheck % 11;
        if ($checkdigit == 10) {
            $checkdigit = "x";
        }
        $fullNumber = ".b" . $bareNumber . $checkdigit;
        return $fullNumber;
    }

    /**
     * Uses the bib number in VuFind to look up the database ids for the associated
     * items
     *
     * @param string $id VuFind bib number
     *
     * @return array
     */
    protected function getIds($id)
    {
        $get_record_ids_query
            = "SELECT bib_record_item_record_link.item_record_id, bib_view.id "
            . "FROM sierra_view.bib_view "
            . "LEFT JOIN sierra_view.bib_record_item_record_link ON "
            . "(bib_view.id = bib_record_item_record_link.bib_record_id) "
            . "INNER JOIN sierra_view.item_view ON "
            . "(bib_record_item_record_link.item_record_id = item_view.id) "
            . "WHERE bib_view.record_num = $1 "
            . "AND item_view.is_suppressed = false;";
        $record_ids = pg_query_params(
            $this->db,
            $get_record_ids_query,
            [$this->idStrip($id)]
        );
        $itemRecords = [];
        while ($record = pg_fetch_row($record_ids)) {
            $itemRecords[] = $record[0];
        }
        return $itemRecords;
    }

    /**
     * Modify location string to add status information, if necessary
     *
     * @param string $location Original location string
     * @param string $cattime  Date and time item record was created
     *
     * @return string
     */
    protected function getLocationText($location, $cattime)
    {
        // No "just cataloged" setting? Default to unmodified location.
        if (!isset($this->config['Catalog']['just_cataloged_time'])) {
            return $location;
        }

        // Convert hours to seconds:
        $seconds = 60 * 60 * $this->config['Catalog']['just_cataloged_time'];

        // Was this a recently cataloged item? If so, return a special string
        // based on the append setting....
        if (time() - $seconds < strtotime($cattime)) {
            if (isset($this->config['Catalog']['just_cataloged_append'])
                && $this->config['Catalog']['just_cataloged_append'] == 'Y'
            ) {
                return $location . ' ' . $this->translate('just_cataloged');
            }
            return $this->translate('just_cataloged');
        }

        // Default case: return the location unmodified:
        return $location;
    }

    /**
     * Some call number processing used for both getStatus and getHoldings
     *
     * @param string $callnumber Call number
     * @param string $id         ID
     *
     * @return string
     */
    protected function processCallNumber($callnumber, $id)
    {
        // If there's no item-specific call number from the item-level queries
        // in getStatus/getHoldings, get the bib-level call number
        if ($callnumber == null) {
            $query = "SELECT varfield_view.field_content "
                . "FROM sierra_view.varfield_view "
                . "WHERE varfield_view.record_type_code = 'b' AND "
                . "varfield_view.varfield_type_code = 'c' and "
                . "varfield_view.record_num = $1;";
            $results = pg_query_params(
                $this->db,
                $query,
                [$this->idStrip($id)]
            );
            if (pg_num_rows($results) > 0) {
                $callnumberarray = pg_fetch_array($results, 0, PGSQL_NUM);
                $callnumber = $callnumberarray[0];
                // stripping subfield codes from call numbers
                $callnumber = preg_replace('/\|(a|b)/', ' ', $callnumber);
            } else {
                $callnumber = '';
            }
        }
        return $callnumber;
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
        try {
            $conn_string = "host=" . $this->config['Catalog']['dna_url']
                . " port=" . $this->config['Catalog']['dna_port']
                . " dbname=" . $this->config['Catalog']['dna_db']
                . " user=" . $this->config['Catalog']['dna_user']
                . " password=" . $this->config['Catalog']['dna_password'];
            $this->db = pg_connect($conn_string);
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
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
        try {
            // Sierra allows for multiple names for a course. Only the first name
            // will be included here; all others will be ignored. If you absolutely
            // need to allow multiple course names to be listed here, duplicate the
            // hack that's currently in getInstructors and findReserves to allow for
            // multiple instructors for a course.
            $query = "SELECT field_content, record_num "
                . "FROM sierra_view.varfield_view WHERE record_type_code = 'r' AND "
                . "varfield_type_code = 'r' AND occ_num = '0' "
                . "ORDER BY field_content;";
            $results = pg_query($query);
            $courses = [];
            while ($row = pg_fetch_row($results)) {
                $courses[$row[1]] = $row[0];
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $courses;
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
        // Sierra does not allow for searching for reserves by departments.
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
        // This function contains some hacks. To wit:
        // Each instructor will be listed once for each course they are teaching,
        // with each course name in parentheses after the instructor name. This is
        // because Sierra doesn't actually have an "instructor ID" -- reserve books
        // can only be looked up by course, not by instructor.
        // To deal with cases where a given course may have multiple instructors,
        // "fake IDs" are constructed for each instance of a course after the first.
        // The findReserves function can work back from these fake IDs to the actual
        // course ID. (Otherwise a course could only be listed under one instructor.)
        try {
            $query = "SELECT t1.field_content, t2.field_content, t1.record_num "
                . "FROM sierra_view.varfield_view AS t1 "
                . "INNER JOIN sierra_view.varfield_view AS t2 ON "
                . "t1.record_num = t2.record_num WHERE "
                . "t1.record_type_code = 'r' AND t1.varfield_type_code = 'p' AND "
                . "t2.record_type_code = 'r' AND t2.varfield_type_code = 'r' AND "
                . "t2.occ_num = '0' ORDER BY t1.field_content;";
            $results = pg_query($query);
            $instructors = [];
            $j = 0;
            while ($row = pg_fetch_row($results)) {
                if ($instructors[$row[2]] != null) {
                    $fakeId = $row[2] . "-" . $j;
                    $instructors[$fakeId] = $row[0] . " (" . $row[1] . ")";
                    $j++;
                } else {
                    $instructors[$row[2]] = $row[0] . " (" . $row[1] . ")";
                }
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $instructors;
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course     ID from getCourses (empty string to match all)
     * @param string $instructor ID from getInstructors (empty string to match all)
     * @param string $department ID from getDepartments (empty string to match all)
     *
     * @throws ILSException
     * @return array An array of associative arrays representing reserve items.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $instructor, $department)
    {
        $reserves = [];
        try {
            $coursenum = '';
            if ($course != null) {
                $coursenum = $course;
            } elseif ($instructor != null) {
                // This deals with the "fake ID" hack explained in the getInstructors
                // function
                $instructor = explode("-", $instructor);
                $coursenum = $instructor[0];
            }
            $query = "SELECT DISTINCT bib_view.record_num "
                . "FROM sierra_view.bib_view "
                . "INNER JOIN sierra_view.bib_record_item_record_link "
                . "ON (bib_view.id = bib_record_item_record_link.bib_record_id) "
                . "INNER JOIN sierra_view.course_record_item_record_link "
                . "ON (course_record_item_record_link.item_record_id = "
                . "bib_record_item_record_link.item_record_id) "
                . "INNER JOIN sierra_view.varfield_view "
                . "ON (course_record_item_record_link.course_record_id = "
                . "varfield_view.record_id) "
                . "WHERE varfield_view.record_num = $1;";
            $results = pg_query_params($this->db, $query, [$coursenum]);
            while ($resultArray = pg_fetch_row($results)) {
                $bareNumber = $resultArray[0];
                $fullNumber = $this->createFullId($bareNumber);
                $reserves[]['BIB_ID'] = $fullNumber;
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $reserves;
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
        try {
            $funds = [];
            $query = "SELECT DISTINCT fund_master.code_num, fund_master.code
                FROM sierra_view.fund_master;";
            $results = pg_query($this->db, $query);
            while ($resultArray = pg_fetch_row($results)) {
                $funds[$resultArray[0]] = $resultArray[1];
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $funds;
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
        try {
            $status = [];
            $itemIds = $this->getIds($id);
            // Use the database ids to get the item-level information (status,
            // location, and potentially call number) associated with that bib record
            $query1 = "SELECT item_view.item_status_code, "
                    . "location_name.name, "
                    . "varfield_view.field_content, "
                    . "varfield_view.varfield_type_code, "
                    . "checkout.due_gmt, "
                    . "item_view.record_creation_date_gmt "
                    . "FROM sierra_view.item_view "
                    . "LEFT JOIN sierra_view.varfield_view "
                    . "ON (item_view.id = varfield_view.record_id) "
                    . "LEFT JOIN sierra_view.location "
                    . "ON (item_view.location_code = location.code) "
                    . "LEFT JOIN sierra_view.location_name "
                    . "ON (location.id = location_name.location_id) "
                    . "LEFT JOIN sierra_view.checkout "
                    . "ON (item_view.id = checkout.item_record_id) "
                    . "WHERE item_view.id = $1 "
                    . "AND varfield_view.record_type_code = 'i' "
                    . "AND location_name.iii_language_id = '1';";
            pg_prepare($this->db, "prep_query", $query1);
            foreach ($itemIds as $item) {
                $callnumber = null;
                $results1 = pg_execute($this->db, "prep_query", [$item]);
                while ($resultArray = pg_fetch_row($results1)) {
                    if ($resultArray[3] == "c") {
                        $callnumber = $resultArray[2];
                    }
                }

                $finalcallnumber = $this->processCallNumber($callnumber, $id);

                $resultArray = pg_fetch_array($results1, 0);

                if (($resultArray[0] == "-" && $resultArray[4] == null)
                    || ($resultArray[0] == "o" && $resultArray[4] == null)
                ) {
                    $availability = true;
                } else {
                    $availability = false;
                }
                $location = $this->getLocationText($resultArray[1], $resultArray[5]);
                $itemInfo = [
                    "id" => $id,
                    "status" => $resultArray[0],
                    "location" => $location,
                    "reserve" => "N",
                    "callnumber" => $finalcallnumber,
                    "availability" => $availability
                    ];

                $status[] = $itemInfo;
            }
            return $status;
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
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
        try {
            $holdings = [];
            $itemIds = $this->getIds($id);
            // Use the database ids to get the item-level information (status,
            // location, and potentially call number) associated with that bib record
            $query1 = "SELECT
                        item_view.item_status_code,
                        location_name.name,
                        checkout.due_gmt,
                        varfield_view.field_content,
                        varfield_view.varfield_type_code,
                        item_view.record_creation_date_gmt
                        FROM
                        sierra_view.item_view
                        LEFT JOIN sierra_view.location
                        ON (item_view.location_code = location.code)
                        LEFT JOIN sierra_view.location_name
                        ON (location.id = location_name.location_id)
                        LEFT JOIN sierra_view.checkout
                        ON (item_view.id = checkout.item_record_id)
                        LEFT JOIN sierra_view.varfield_view
                        ON (item_view.id = varfield_view.record_id)
                        WHERE item_view.id = $1
                        AND varfield_view.record_type_code = 'i'
                        AND location_name.iii_language_id = '1';";
            pg_prepare($this->db, "prep_query", $query1);
            foreach ($itemIds as $item) {
                $callnumber = null;
                $barcode = null;
                $results1 = pg_execute($this->db, "prep_query", [$item]);
                $number = null;
                while ($row1 = pg_fetch_row($results1)) {
                    if ($row1[4] == "b") {
                        $barcode = $row1[3];
                    } elseif ($row1[4] == "c") {
                        $callnumber = $row1[3];
                    } elseif ($row1[4] == "v") {
                        $number = $row1[3];
                    }
                }

                $finalcallnumber = $this->processCallNumber($callnumber, $id);

                $resultArray = pg_fetch_array($results1, 0);

                if (($resultArray[0] == "-" && $resultArray[2] == null)
                    || ($resultArray[0] == "o" && $resultArray[2] == null)
                ) {
                    $availability = true;
                } else {
                    $availability = false;
                }
                $location = $this->getLocationText($resultArray[1], $resultArray[5]);
                $itemInfo = [
                    "id" => $id,
                    "availability" => $availability,
                    "status" => $resultArray[0],
                    "location" => $location,
                    "reserve" => "N",
                    "callnumber" => $finalcallnumber,
                    "duedate" => $resultArray[2],
                    "returnDate" => false,
                    "number" => $number,
                    "barcode" => $barcode
                    ];

                $holdings[] = $itemInfo;
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $holdings;
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundID  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @throws ILSException
     * @return array       Associative array with 'count' and 'results' keys
     */
    public function getNewItems($page, $limit, $daysOld, $fundID)
    {
        try {
            $newItems = [];
            $offset = $limit * ($page - 1);
            $daysOld = (int)$daysOld;
            if (is_int($daysOld) == false || $daysOld > 30) {
                $daysOld = "30";
            }
            $query = "SELECT bib_view.record_num FROM sierra_view.bib_view ";
            if ($fundID != null) {
                $query .= "INNER JOIN sierra_view.bib_record_order_record_link "
                    . "ON (bib_view.id = bib_record_order_record_link.bib_record_id)"
                    . " INNER JOIN sierra_view.order_record_cmf "
                    . "ON (bib_record_order_record_link.order_record_id = "
                    . "order_record_cmf.order_record_id) "
                    . "INNER JOIN sierra_view.fund_master "
                    . "ON (CAST (order_record_cmf.fund_code AS integer) = "
                    . "fund_master.code_num) "
                    . "WHERE fund_master.code_num = CAST ($3 AS integer) AND ";
            } else {
                $query .= "WHERE ";
            }
            if ($this->config['Catalog']['new_by_cat_date'] == "Y") {
                $query .= "bib_view.cataloging_date_gmt BETWEEN "
                    . "date_trunc('day', (now() - interval '" . $daysOld
                    . " days')) AND now() ";
            } else {
                $query .= "bib_view.record_creation_date_gmt BETWEEN "
                    . "date_trunc('day', (now() - interval '" . $daysOld
                    . " days')) AND now() ";
            }
            $query .= "ORDER BY cataloging_date_gmt LIMIT CAST ($1 AS integer) "
                . "OFFSET CAST ($2 AS integer);";
            if ($fundID != null) {
                $results = pg_query_params(
                    $this->db,
                    $query,
                    [$limit, $offset, $fundID]
                );
            } else {
                $results = pg_query_params(
                    $this->db,
                    $query,
                    [$limit, $offset]
                );
            }
            $newItems['count'] = (string)pg_num_rows($results);
            if (pg_num_rows($results) != 0) {
                while ($record = pg_fetch_row($results)) {
                    $bareNumber = $record[0];
                    $fullNumber = $this->createFullId($bareNumber);
                    $newItems['results'][]['id'] = $fullNumber;
                }
            } else {
                $newItems['results'] = [];
                $newItems['results'][0]['id'] = null;
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $newItems;
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
        try {
            // TODO
            $history = [];
            return $history;
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
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
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $statuses = [];
        try {
            foreach ($ids as $id) {
                $statuses[] = $this->getStatus($id);
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $statuses;
    }

    /**
     * Get suppressed authority records
     *
     * @return array ID numbers of suppressed authority records in the system.
     */
    public function getSuppressedAuthorityRecords()
    {
        $authRecords = [];
        try {
            $query = "SELECT record_metadata.record_num FROM "
                . "sierra_view.authority_record LEFT JOIN "
                . "sierra_view.record_metadata ON "
                . "(authority_record.record_id = record_metadata.id) "
                . "where authority_record.is_suppressed = 't';";
            $record_ids = pg_query($this->db, $query);
            while ($record = pg_fetch_row($record_ids)) {
                $authRecords[] = $record[0];
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $authRecords;
    }

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $suppRecords = [];
        try {
            $query = "SELECT record_metadata.record_num FROM "
                . "sierra_view.bib_record LEFT JOIN sierra_view.record_metadata "
                . "ON (bib_record.record_id = record_metadata.id) "
                . "where bib_record.is_suppressed = 't';";
            $record_ids = pg_query($this->db, $query);
            while ($record = pg_fetch_row($record_ids)) {
                $suppRecords[] = $record[0];
            }
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }
        return $suppRecords;
    }
}
