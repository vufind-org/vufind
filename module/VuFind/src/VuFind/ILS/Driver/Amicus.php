<?php

/**
 * Amicus ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) Scanbit 2011.
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
 * @author   Josu Moreno <jmoreno@scanbit.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use PDO;
use PDOException;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function count;
use function in_array;

/**
 * Amicus ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Josu Moreno <jmoreno@scanbit.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Amicus extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Database connection
     *
     * @var PDO
     */
    protected $db;

    /**
     * Stored status rankings from the database; initialized to false but populated
     * by the pickStatus() method.
     *
     * @var array|bool
     */
    protected $statusRankings = false;

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
        $tns = '(DESCRIPTION=' .
                 '(ADDRESS_LIST=' .
                   '(ADDRESS=' .
                     '(PROTOCOL=TCP)' .
                     '(HOST=' . $this->config['Catalog']['host'] . ')' .
                     '(PORT=' . $this->config['Catalog']['port'] . ')' .
                   ')' .
                 ')' .
                 '(CONNECT_DATA=' .
                   '(SERVICE_NAME=' . $this->config['Catalog']['service'] . ')' .
                 ')' .
               ')';
        try {
            $this->db = new PDO(
                "oci:dbname=$tns",
                $this->config['Catalog']['user'],
                $this->config['Catalog']['password']
            );
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Support method to pick which status message to display when multiple
     * options are present.
     *
     * @param array $statusArray Array of status messages to choose from.
     *
     * @return string            The best status message to display.
     */
    protected function pickStatus($statusArray)
    {
        // This array controls the rankings of possible status messages. The lower
        // the ID in the ITEM_STATUS_TYPE table, the higher the priority of the
        // message. We only need to load it once -- after that, it's cached in the
        // driver.
        if ($this->statusRankings == false) {
            // Execute SQL
            $sql = 'SELECT * FROM T_HLDG_STUS_TYP';
            try {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute();
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            // Read results
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->statusRankings[$row['TBL_LNG_FR_TXT']] = $row['TBL_VLU_CDE'];
            }
        }

        // Pick the first entry by default, then see if we can find a better match:
        $status = $statusArray[0];
        $rank = $this->statusRankings[$status];
        for ($x = 1; $x < count($statusArray); $x++) {
            if ($this->statusRankings[$statusArray[$x]] < $rank) {
                $status = $statusArray[$x];
            }
        }
        return $status;
    }

    /**
     * Support method to take an array of status strings and determine
     * whether or not this indicates an available item. Returns an array with
     * two keys: 'available', the boolean availability status, and 'otherStatuses',
     * every status code found other than "Not Charged" - for use with _pickStatus().
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
                case 'Disponible':
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
     * Function that returns the number or on loan items for a given copy number.
     * If there is no on loan items it returns 0.
     * Used in getHolding and getStatus functions
     *
     * @param int $copyId The copy id number to check.
     *
     * @return int        Number of on loan items.
     */
    protected function sacaStatus($copyId)
    {
        $circulacion = 'SELECT COUNT(*) AS PRESTADO ' .
            'FROM CIRT_ITM ' .
            "WHERE CPY_ID_NBR = '$copyId'";

        //$holds = "SELECT COUNT(*) AS PRESTADO FROM CIRTN_HLD " .
        //"WHERE CPY_ID_NBR = '$copyId'";

        $prestados = 0;
        try {
            $sqlStmt = $this->db->prepare($circulacion);
            $sqlStmt->execute();
            //$sqlStmt2 = $this->db->prepare($holds);
            //$sqlStmt2->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $prestados = $row['PRESTADO'];
            if ($row['PRESTADO'] == 0) {
                $prestados = 'Disponible';
            } else {
                $prestados = 'No disponible';
            }
        }
        return $prestados;
    }

    /**
     * Function that returns the due date or a special message.
     * If the difference is greater than 50 days it will return one special message
     * If not it returns the due date
     *
     * @param int $copyId The copy id number to check.
     *
     * @return string     String with special message or due date.
     */
    protected function sacaFecha($copyId)
    {
        $circulacion = "SELECT to_char(CIRT_ITM_DUE_DTE,'dd-mm-yyyy') AS FECHADEV, "
            . 'ROUND(CIRT_ITM_DUE_DTE - SYSDATE) AS DIFERENCIA '
            . 'FROM CIRT_ITM '
            . "WHERE CPY_ID_NBR = '$copyId'";
        $fecha = 0;
        $diferencia = 0;
        try {
            $sqlStmt = $this->db->prepare($circulacion);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $diferencia = $row['DIFERENCIA'];
            if ($diferencia > 50) {
                $fecha = 'SIN DETERMINAR';
            } else {
                $fecha = $row['FECHADEV'];
            }
        }
        return $fecha;
    }

    /**
     * Function that returns the numbers of holds for a copy id number given.
     * If there is no holds it returns 0.
     *
     * @param int $holdingId The copy id number to check.
     *
     * @return int           Integer with the number of holds.
     */
    protected function sacaReservas($holdingId)
    {
        $reservas = 'SELECT COUNT(*) as reservados ' .
                    'FROM CIRTN_HLD ' .
                    "WHERE CPY_ID_NBR = '$holdingId'";

        $reservados = 0;
        try {
            $sqlStmt = $this->db->prepare($reservas);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $reservados = $row['RESERVADOS'];
        }
        return $reservados;
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

        $items = 'select BIB_ITM_NBR, ILL_CDE as ON_RESERVE, ' .
            'T_LCTN_NME_BUO.TBL_LNG_ENG_TXT ' .
            'as LOCATION, SHLF_LIST_SRT_FORM as CALLNUMBER, CPY_ID_NBR as ' .
            'CPY_ID_NBR ' .
            'from CPY_ID, SHLF_LIST, T_LCTN_NME_BUO ' .
            'where CPY_ID.SHLF_LIST_KEY_NBR = SHLF_LIST.SHLF_LIST_KEY_NBR ' .
            'and CPY_ID.LCTN_NME_CDE = T_LCTN_NME_BUO.TBL_VLU_CDE ' .
            "and CPY_ID.BIB_ITM_NBR = '$id'";

        $multipleLoc = 'SELECT COUNT(DISTINCT(SHLF_LIST_KEY_NBR)) AS multiple ' .
                 'FROM CPY_ID ' .
                 "WHERE CPY_ID.BIB_ITM_NBR = '$id'";

        try {
            $sqlStmt = $this->db->prepare($multipleLoc);
            $sqlStmt->execute();
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        $multiple = '';
        // Read results
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $multiple = $row['MULTIPLE'];
        }
        $prestados = 0;
        $reservados = 0;
        $possibleQueries = [$items];

        // Loop through the possible queries and try each in turn -- the first one
        // that yields results will cause the function to return.
        foreach ($possibleQueries as $sql) {
            // Execute SQL

            try {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute();
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            // Build Array of Item Information
            $data = [];
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $prestados = $this->sacaStatus($row['CPY_ID_NBR']);
                $reservados = $this->sacaReservas($row['CPY_ID_NBR']);
                if (!isset($data[$row['BIB_ITM_NBR']])) {
                    if ($multiple != 1) {
                        $multiple = $this->translate('Multiple Locations');
                        $textoLoc = $this->translate('Multiple');
                        $textoSign = $this->translate('Multiple Locations');
                        $data[$row['BIB_ITM_NBR']] = [
                            'id' => $id,
                            'status' => $prestados,
                            'status_array' => [$prestados],
                            'location' => $textoLoc,
                            'reserve' => $reservados,
                            'callnumber' => $textoSign,
                        ];
                    } else {
                        $multiple = $row['LOCATION'];
                        if ($multiple == 'Deposito2') {
                            $multiple = 'Depósito2';
                        }
                        if ($multiple == 'Deposito') {
                            $multiple = 'Depósito';
                        }
                        $data[$row['BIB_ITM_NBR']] = [
                            'id' => $id,
                            'status' => $prestados,
                            'status_array' => [$prestados],
                            'location' => $multiple,
                            'reserve' => $reservados,
                            'callnumber' => $row['CALLNUMBER'],
                        ];
                    }
                } else {
                    $status_array = & $data[$row['BIB_ITM_NBR']]['status_array'];
                    if (!in_array($prestados, $status_array)) {
                        $status_array[] = $prestados;
                    }
                }
            }
            // If we found any information, break out of the foreach loop;
            // we don't need to try any more queries.
            if (count($data) == 0) {
                $data[$id] = [
                    'id' => $id,
                    'status' => $prestados,
                    'status_array' => [$prestados],
                    'location' => $this->translate('No copies'),
                    'reserve' => $reservados,
                    'callnumber' => $this->translate('No copies'),
                ];
                break;
            }
            if (count($data) > 0) {
                break;
            }
        }
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
            $status[] = $current;
        }
        return $status;
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
        $items = 'select CPY_ID.BRCDE_NBR, CPY_ID.BIB_ITM_NBR, ' .
            'T_LCTN_NME_BUO.TBL_LNG_ENG_TXT ' .
            'as LOCATION, SHLF_LIST_SRT_FORM as CALLNUMBER, CPY_ID.CPY_ID_NBR as ' .
            'CPY_ID_NBR ' .
            'from CPY_ID, SHLF_LIST, T_LCTN_NME_BUO ' .
            'where CPY_ID.SHLF_LIST_KEY_NBR = SHLF_LIST.SHLF_LIST_KEY_NBR ' .
            'AND CPY_ID.LCTN_NME_CDE = T_LCTN_NME_BUO.TBL_VLU_CDE ' .
            "and CPY_ID.BIB_ITM_NBR = '$id' " .
            'order by SHLF_LIST_SRT_FORM ASC, CPY_ID.CPY_ID_NBR ASC';

        $possibleQueries = [$items];

        // Loop through the possible queries and try each in turn -- the first one
        // that yields results will cause us to break out of the loop.
        foreach ($possibleQueries as $sql) {
            // Execute SQL
            try {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute();
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            // Build Holdings Array
            $data = [];
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                // Determine Location
                $loc = $row['LOCATION'];
                if ($loc == 'Deposito2') {
                    $loc = 'Depósito2';
                }
                if ($loc == 'Deposito') {
                    $loc = 'Depósito';
                }

                $status = $this->sacaStatus($row['CPY_ID_NBR']);
                $availability = $this->determineAvailability([$status]);
                $currentItem = [
                    'id' => $id,
                    'availability' => $availability['available'],
                    'status' => $status,
                    'location' => $loc,
                    // TODO: make this smarter if you use course reserves:
                    'reserve' => 'N',
                    'callnumber' => $row['CALLNUMBER'],
                    'duedate' => $this->sacaFecha($row['CPY_ID_NBR']),
                    // TODO: fill this in if you want "recently returned" support:
                    'returnDate' => false,
                    'number' => count($data) + 1,
                    'item_id' => $row['CPY_ID_NBR'],
                    'barcode' => $row['BRCDE_NBR'],
                ];
                $data[] = $currentItem;
            }
            // If we found data, we can leave the foreach loop -- we don't need to
            // try any more queries.
            if (count($data) > 0) {
                break;
            }
        }
        return $data;
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
        $sql = "select REPLACE(REPLACE(CPY_STMT_TXT,'a',''),'Fondos: ','') as " .
            'ENUMCHRON ' .
            'from CPY_ID ' .
            "WHERE CPY_ID.BIB_ITM_NBR = '$id' " .
            'order by CPY_ID.SHLF_LIST_KEY_NBR ASC, CPY_ID.CPY_ID_NBR ASC';
        $data = [];
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = ['issue' => $row['ENUMCHRON']];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $data;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode The patron username
     * @param string $lname   The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $lname)
    {
        $sql = 'SELECT LOGIN , PASSWORD AS FIRST_NAME ' .
               'FROM LV_USER ' .
               "WHERE PASSWORD = '$lname' AND LOGIN = '$barcode'";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            if (isset($row['LOGIN']) && ($row['LOGIN'] != '')) {
                return [
                    'id' => $row['LOGIN'],
                    'firstname' => $row['FIRST_NAME'],
                    'lastname' => $lname,
                    'cat_username' => $barcode,
                    'cat_password' => $lname,
                    // There's supposed to be a getPatronEmailAddress stored
                    // procedure in Oracle, but I couldn't get it to work here;
                    // might be worth investigating further if needed later.
                    'email' => null,
                    'major' => null,
                    'college' => null];
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

        $sql = "SELECT TO_CHAR(CIRT_ITM.CIRT_ITM_DUE_DTE,'DD/MM/YYYY') " .
            'AS DUEDATE, CIRT_ITM.BIB_ITM_NBR AS BIB_ID ' .
            'FROM LV_USER, CIRT_ITM ' .
            'WHERE LV_USER.PRSN_NBR = CIRT_ITM.PRSN_NBR ' .
            "AND LV_USER.LOGIN = '" . $patron['id'] . "'";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $transList[] = ['duedate' => $row['DUEDATE'],
                                     'id' => $row['BIB_ID']];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $transList;
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

        $sql = "SELECT UNIQUE TO_CHAR(CIRT_ITM.CIRT_ITM_CHRG_OUT_DTE,'DD/MM/YYYY') "
            . 'AS ORIG_CHARGE_DATE, '
            . "TO_CHAR(CIRT_ITM.CIRT_ITM_DUE_DTE,'DD/MM/YYYY')  AS DUE_DATE, "
            . 'CIRT_ITM.BIB_ITM_NBR AS BIB_ID '
            . 'FROM CIRT_ITM, LV_USER '
            . 'WHERE CIRT_ITM.PRSN_NBR = LV_USER.PRSN_NBR '
            . 'AND CIRT_ITM_DUE_DTE < SYSDATE '
            . "AND  LV_USER.LOGIN='" . $patron['id'] . "'";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $fineList[] = ['checkout' => $row['ORIG_CHARGE_DATE'],
                                    'duedate' => $row['DUE_DATE'],
                                    'id' => $row['BIB_ID']];
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

        $sql = 'SELECT CIRTN_HLD.BIB_ITM_NBR AS BIB_ID, ' .
            'CIRTN_HLD.CIRTN_HLD_LCTN_ORG_NBR AS PICKUP_LOCATION, ' .
            'CIRTN_HLD.CIRTN_HLD_TYP_CDE AS  HOLD_RECALL_TYPE, ' .
            "TO_CHAR(CIRTN_HLD.TME_HLD_END_DTE,'DD/MM/YYYY') AS EXPIRE_DATE, " .
            "TO_CHAR(CIRTN_HLD.CIRTN_HLD_CRTE_DTE,'DD/MM/YYYY') AS " .
            'CREATE_DATE FROM CIRTN_HLD, LV_USER ' .
            'WHERE CIRTN_HLD.PRSN_NBR = LV_USER.PRSN_NBR ' .
            "AND LV_USER.LOGIN = '" . $patron['id'] . "'";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $holdList[] = ['type' => $row['HOLD_RECALL_TYPE'],
                                    'id' => $row['BIB_ID'],
                                    'location' => $row['PICKUP_LOCATION'],
                                    'expire' => $row['EXPIRE_DATE'],
                                    'create' => $row['CREATE_DATE']];
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
        $sql = 'SELECT DISTINCT  PRSN.PRSN_NBR AS UNO, (SELECT p1.PRSN_CMCTN_NBR ' .
            'FROM PRSN_CMCTN p1 ' .
            'WHERE p1.prsn_nbr = PRSN.prsn_nbr AND  PRSN_CMCTN_TYP_CDE = 7) tfno, ' .
            '(SELECT p1.PRSN_CMCTN_NBR  FROM PRSN_CMCTN p1 WHERE p1.prsn_nbr = ' .
            'PRSN.prsn_nbr AND  ' .
            'PRSN_CMCTN_TYP_CDE = 1) email, ' .
            'PRSN.PRSN_SRNME_SRT_FORM AS  LAST_NAME, PRSN.PRSN_1ST_NME_SRT_FORM ' .
            'AS FIRST_NAME, ' .
            "CONCAT(PSTL_ADR_ST_NME,CONCAT(' ',CONCAT(PSTL_ADR_ST_NBR,CONCAT(' ', " .
            "CONCAT(PSTL_ADR_FLR_NBR,CONCAT(' ',PSTL_ADR_RM_NBR)))))) " .
            'AS ADDRESS_LINE1, PRSN_PSTL_ADR.PSTL_ADR_CTY_NME ' .
            'AS ADDRESS_LINE2, PRSN_PSTL_ADR.PSTL_ADR_PSTL_CDE AS ZIP_POSTAL ' .
            'FROM PRSN, PRSN_CMCTN, PRSN_PSTL_ADR, LV_USER ' .
            'WHERE   PRSN_CMCTN.PRSN_nbr = PRSN.PRSN_NBR (+) ' .
            'AND PRSN.PRSN_NBR = PRSN_PSTL_ADR.PRSN_NBR (+) ' .
            'AND LV_USER.PRSN_NBR = PRSN.PRSN_NBR ' .
            "AND LV_USER.LOGIN = UPPER('" . $patron['id'] . "')";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $patron = ['firstname' => $row['FIRST_NAME'],
                                'lastname' => $row['LAST_NAME'],
                                'address1' => $row['ADDRESS_LINE1'],
                                'address2' => $row['ADDRESS_LINE2'],
                                'zip' => $row['ZIP_POSTAL'],
                                'phone' => $row['TFNO'],
                                'email' => $row['EMAIL'],
                                'group' => $row['PATRON_GROUP_NAME']];
                return $patron;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return null;
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
        return $this->config['Catalog']['hold'] . $recordId;
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
        $items = [];

        // Prevent unnecessary load on voyager
        if ($daysOld > 30) {
            $daysOld = 30;
        }

        $enddate = date('d-m-Y', strtotime('now'));
        $startdate = date('d-m-Y', strtotime("-$daysOld day"));

        $sql = 'select count(distinct BIB_ITM_NBR) as count ' .
               'from CPY_ID ' .
               "where CPY_ID.CRTN_DTE >= to_date('$startdate', 'dd-mm-yyyy') " .
               "and CPY_ID.CRTN_DTE < to_date('$enddate', 'dd-mm-yyyy')";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['COUNT'];
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        $page = ($page) ? $page : 1;
        $limit = ($limit) ? $limit : 20;
        $startRow = (($page - 1) * $limit) + 1;
        $endRow = ($page * $limit);
        $sql = 'select * from ' .
               '(select a.*, rownum rnum from ' .
               '(select  CPY_ID.BIB_ITM_NBR  as BIB_ID, CPY_ID.CRTN_DTE ' .
               'as CREATE_DATE ' .
               'from CPY_ID ' .
               "where CPY_ID.CRTN_DTE >= to_date('$startdate', 'dd-mm-yyyy') " .
               "and CPY_ID.CRTN_DTE < to_date('$enddate', 'dd-mm-yyyy') " .
               'group by CPY_ID.BIB_ITM_NBR, CPY_ID.CRTN_DTE ' .
               'order by CPY_ID.CRTN_DTE desc) a ' .
               "where rownum <= $endRow) " .
               "where rnum >= $startRow";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['BIB_ID'];
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

        $sql = 'select distinct * from ' .
               '(select initcap(lower(FUND.FUND_NME)) as name from FUND) ' .
               'order by name';
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['NAME'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $list;
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
        $recordList = [];

        $dept = str_replace("'", '', $dept);
        $dept = str_replace('"', '', $dept);
        $dept = str_replace(':', '', $dept);
        $dept = str_replace(',', '', $dept);
        $dept = str_replace('.', '', $dept);
        $dept = str_replace(';', '', $dept);
        $dept = str_replace('*', '%', $dept);

        $sql = 'select distinct(BIB_ITM_NBR) as BIB_ID ' .
               'FROM CPY_ID, SHLF_LIST ' .
               'WHERE CPY_ID.SHLF_LIST_KEY_NBR = SHLF_LIST.SHLF_LIST_KEY_NBR ' .
               'AND UPPER(SUBSTR(SHLF_LIST.SHLF_LIST_STRNG_TEXT,3,20)) LIKE ' .
               "UPPER('" . $dept . "%') " .
               'AND ROWNUM <= 1000';

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $recordList[] = $row;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $recordList;
    }

    /*
    function findReserves($course, $inst, $dept)
    {
        $recordList = array();

        $reserve_subset = "";

        $reserve_subset = "(".$reserve_subset.")";
        $sql = "select SHLF_LIST_SRT_FORM as DISPLAY_CALL_NO, CPY_ID.BIB_ITM_NBR " .
            "as BIB_ID, NME_MAIN_ENTRY_STRNG_TXT as AUTHOR, TTL_HDG_MAIN_STRNG_TXT" .
            " as TITLE, BIB_NTE_IPRNT_STRNG_TXT as PUBLISHER, ITM_DTE_1_DSC as " .
            "PUBLISHER_DATE " .
            "from CIRTN_HLD, S_CACHE_BIB_ITM_DSPLY, SHLF_LIST, CPY_ID " .
            "where S_CACHE_BIB_ITM_DSPLY.BIB_ITM_NBR = CIRTN_HLD.BIB_ITM_NBR and " .
            "CIRTN_HLD.CPY_ID_NBR = CPY_ID.CPY_ID_NBR and " .
            "CPY_ID.SHLF_LIST_KEY_NBR = SHLF_LIST.SHLF_LIST_KEY_NBR";


        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $recordList[] = $row;
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $recordList;
    }
    */

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $list = [];
        $sql = 'SELECT BIB_AUT_ITM_NBR as BIB_ID ' .
            'FROM CTLGG_TRSTN_ACTVT_LOG ' .
            'WHERE STATS_TRSTN_TYP_CDE = 4 ' .
            'AND trstn_log_tmest >= SYSDATE -30';
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['BIB_ID'];
            }
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        return $list;
    }
}
