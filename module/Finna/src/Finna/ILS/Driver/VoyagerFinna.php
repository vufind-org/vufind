<?php
/**
 * Voyager/VoyagerRestful Common Trait
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use Finna\ILS\SIP2;
use Laminas\Validator\EmailAddress as EmailAddressValidator;
use PDO;
use VuFind\Exception\ILS as ILSException;

/**
 * Voyager/VoyagerRestful Common Trait
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
trait VoyagerFinna
{
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
            $number = '';
            if (isset($row['YEAR'])) {
                $number .= utf8_encode($row['YEAR']) . ' ';
            }
            if (isset($row['ITEM_ENUM'])) {
                $number .= utf8_encode($row['ITEM_ENUM']);
            }
            $number = trim($number);

            // Concat wrapped rows (MARC data more than 300 bytes gets split
            // into multiple rows)
            $rowId = $row['ITEM_ID'] ?? $row['MFHD_ID'];
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
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingItemsSQL($id)
    {
        $sqlArray = parent::getHoldingItemsSQL($id);
        $sqlArray['expressions'][] = "LOCATION.LOCATION_CODE";
        $sqlArray['expressions'][] = "MFHD_ITEM.YEAR";

        return $sqlArray;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = $reservationsTotal = 0;
        $locations = [];

        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            $locations[$item['location']] = true;
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.

        return [
           'available' => $availableTotal,
           'total' => count($holdings),
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => '__HOLDINGSSUMMARYLOCATION__'
        ];
    }

    /**
     * Get purchase order data for a holding
     *
     * @param string $mfhdID MFHD record ID
     *
     * @return array Keyed array
     */
    protected function getPurchaseOrderData($mfhdID)
    {
        $expressions = [
            'LI_COPY_STATUS.STATUS_DATE',
            'LINE_ITEM_STATUS.LINE_ITEM_STATUS_DESC'
        ];

        $from = [
            "$this->dbName.LINE_ITEM_STATUS",
            "$this->dbName.LINE_ITEM_COPY_STATUS LI_COPY_STATUS"
        ];

        $where = [
            'LI_COPY_STATUS.MFHD_ID=:mfhdId',
            'LINE_ITEM_STATUS.LINE_ITEM_STATUS = LI_COPY_STATUS.LINE_ITEM_STATUS'
        ];

        if (!empty($this->config['Holdings']['order_statuses'])
            && $this->config['Holdings']['order_statuses'] != '*'
        ) {
            $statuses = array_map(
                function ($s) {
                    return "'" . preg_replace('/[^\w\s\/]*/', '', $s) . "'";
                },
                explode(':', $this->config['Holdings']['order_statuses'])
            );
            $statuses = implode(', ', $statuses);

            $where = array_merge(
                $where, ["LINE_ITEM_STATUS.LINE_ITEM_STATUS_DESC in ($statuses)"]
            );
        }

        if (!empty($this->config['Holdings']['order_formats'])) {
            $formats = array_map(
                function ($s) {
                    return "'" . preg_replace('/[^\w]*/', '', $s) . "'";
                },
                explode(':', $this->config['Holdings']['order_formats'])
            );
            $formats = implode(', ', $formats);

            $from = array_merge(
                $from, ["$this->dbName.LINE_ITEM", "$this->dbName.BIB_TEXT"]
            );

            $where = array_merge(
                $where, [
                    'LINE_ITEM.LINE_ITEM_ID = LI_COPY_STATUS.LINE_ITEM_ID',
                    'BIB_TEXT.BIB_ID = LINE_ITEM.BIB_ID',
                    "BIB_TEXT.BIB_FORMAT in ($formats)"
                ]
            );
        }

        $sqlArray = [
            'expressions' => $expressions,
            'from' => $from,
            'where' => $where,
            'group' => ['STATUS_DATE', 'LINE_ITEM_STATUS_DESC'],
            'bind' => ['mfhdId' => $mfhdID]
        ];

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->executeSQL($sql);
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        $result = [];
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = [
                'order_update_date' => $this->dateFormat->convertToDisplayDate(
                    'm-d-y', $row['STATUS_DATE']
                ),
                'status' => $row['LINE_ITEM_STATUS_DESC']
            ];
        }

        return $result;
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
        $data = parent::getStatusData($sqlRows);
        foreach ($sqlRows as $row) {
            if (isset($row['LOCATION_CODE'])) {
                $data[$row['ITEM_ID']]['collection'] = $row['LOCATION_CODE'];
            }
        }

        return $data;
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
        $sqlArray = parent::getStatusSQL($id);
        $sqlArray['expressions'][] = "LOCATION.LOCATION_CODE";

        return $sqlArray;
    }

    /**
     * Protected support method to take an array of status strings and determine
     * whether or not this indicates an available item.  Returns an array with
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
        $result = parent::determineAvailability($statusArray);
        // Treat non-charged items that have 'Hold Request' as the only other status
        // as available.
        if (!$result['available'] && count($statusArray) == 2
            && in_array('Not Charged', $statusArray)
            && in_array('Hold Request', $statusArray)
        ) {
            $result['available'] = true;
        }
        return $result;
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
    protected function processHoldingData($data, $id, $patron = false)
    {
        $data = parent::processHoldingData($data, $id, $patron);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
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
        $data = parent::processHoldingRow($sqlRow);

        $data['collection'] = $sqlRow['LOCATION_CODE'] ?? '';

        // Get purchase order information for holdings that don't have items
        if ($data['status'] == 'No information available'
            && isset($this->config['Holdings']['order_statuses'])
        ) {
            $data += [
                'order_statuses' => $this->getPurchaseOrderData($sqlRow['MFHD_ID'])
            ];

            // Modify 'No information available' status if we have order information
            if ($data['status'] == 'No information available'
                && !empty($data['order_statuses'])
            ) {
                $orderedStatuses = ['Approved', 'Approved/Sent', 'Pending'];
                foreach ($data['order_statuses'] as $orderStatus) {
                    if (in_array($orderStatus['status'], $orderedStatuses)) {
                        $data['status'] = 'Ordered';
                        break;
                    }
                }
            }
        }
        if (!empty($sqlRow['DUEDATE'])
            && !empty($this->config['Holdings']['ignore_due_date_in_statuses'])
        ) {
            $statuses = explode(
                ':', $this->config['Holdings']['ignore_due_date_in_statuses']
            );
            $data['ignoreDueDate'] = !empty(
                array_intersect($statuses, $sqlRow['STATUS_ARRAY'])
            );
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
        $data = parent::processStatusData($data);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data);
            $data[] = $summary;
        }
        return $data;
    }

    /**
     * Check if patron is authorized (e.g. to access licensed electronic material).
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return bool True if patron is authorized, false if not
     */
    public function getPatronAuthorizationStatus($patron)
    {
        if (!isset($this->config['Authorization']['enabled'])
            || !$this->config['Authorization']['enabled']
        ) {
            // Authorization not enabled
            return false;
        }

        if (!empty($this->config['Authorization']['stat_codes'])) {
            // Check stat codes
            $expressions = ['PATRON_STAT_CODE.PATRON_STAT_CODE'];
            $from = [
                "$this->dbName.PATRON_STAT_CODE",
                "$this->dbName.PATRON_STATS"
            ];
            $where = [
                'PATRON_STATS.PATRON_ID = :id',
                'PATRON_STAT_CODE.PATRON_STAT_ID = PATRON_STATS.PATRON_STAT_ID'
            ];
            $bind = [':id' => $patron['id']];

            $sql = $this->buildSqlFromArray(
                [
                    'expressions' => $expressions,
                    'from' => $from,
                    'where' => $where,
                    'bind' => $bind
                ]
            );

            try {
                $sqlStmt = $this->executeSQL($sql);
                $statCodes = $sqlStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $common = array_intersect(
                    $statCodes,
                    explode(':', $this->config['Authorization']['stat_codes'])
                );
                if (empty($common)) {
                    return false;
                }
            } catch (PDOException $e) {
                throw new ILSException($e->getMessage());
            }
        }

        return true;
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
    public function getConfig($function, $params = null)
    {
        if ($function == 'patronLogin') {
            if (!empty($this->config['Catalog']['secondary_login_field'])) {
                list(, $label) = explode(
                    ':', $this->config['Catalog']['secondary_login_field'], 2
                );
                return [
                    'secondary_login_field_label' => $label
                ];
            }
        } elseif ($function == 'onlinePayment'
            && isset($this->config['OnlinePayment'])
        ) {
            $functionConfig = $this->config['OnlinePayment'];
            $functionConfig['exactBalanceRequired'] = true;
            return $functionConfig;
        }

        if (is_callable('parent::getConfig')) {
            return parent::getConfig($function, $params);
        }
        return false;
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
        $sql = "SELECT PATRON.LAST_NAME, PATRON.FIRST_NAME, " .
               "PATRON.HISTORICAL_CHARGES, PATRON_ADDRESS.ADDRESS_LINE1, " .
               "PATRON_ADDRESS.ADDRESS_LINE2, PATRON_ADDRESS.ZIP_POSTAL, " .
               "PATRON_ADDRESS.CITY, PATRON_ADDRESS.COUNTRY, " .
               "PATRON_PHONE.PHONE_NUMBER, PATRON_GROUP.PATRON_GROUP_NAME, " .
               "to_char(PATRON.EXPIRE_DATE, 'MM-DD-YYYY') as EXPIRE_DATE " .
               "FROM $this->dbName.PATRON, $this->dbName.PATRON_ADDRESS, " .
               "$this->dbName.PATRON_PHONE, $this->dbName.PATRON_BARCODE, " .
               "$this->dbName.PATRON_GROUP " .
               "WHERE PATRON.PATRON_ID = PATRON_ADDRESS.PATRON_ID (+) " .
               "AND PATRON_ADDRESS.ADDRESS_ID = PATRON_PHONE.ADDRESS_ID (+) " .
               "AND PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID (+) " .
               "AND PATRON_BARCODE.PATRON_GROUP_ID = " .
               "PATRON_GROUP.PATRON_GROUP_ID (+) " .
               "AND PATRON.PATRON_ID = :id";
        try {
            $sqlStmt = $this->executeSQL($sql, [':id' => $patron['id']]);
            $patron = [];
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['FIRST_NAME'])) {
                    $patron['firstname'] = utf8_encode($row['FIRST_NAME']);
                }
                if (!empty($row['LAST_NAME'])) {
                    $patron['lastname'] = utf8_encode($row['LAST_NAME']);
                }
                if (!empty($row['PHONE_NUMBER'])) {
                    $patron['phone'] = utf8_encode($row['PHONE_NUMBER']);
                }
                if (!empty($row['PATRON_GROUP_NAME'])) {
                    $patron['group'] = utf8_encode($row['PATRON_GROUP_NAME']);
                }
                $validator = new EmailAddressValidator();
                $addr1 = utf8_encode($row['ADDRESS_LINE1']);
                if ($validator->isValid($addr1)) {
                    $patron['email'] = $addr1;
                } elseif (!isset($patron['address1'])) {
                    if (!empty($addr1)) {
                        $patron['address1'] = $addr1;
                    }
                    if (!empty($row['ADDRESS_LINE2'])) {
                        $patron['address2'] = utf8_encode($row['ADDRESS_LINE2']);
                    }
                    if (!empty($row['ZIP_POSTAL'])) {
                        $patron['zip'] = utf8_encode($row['ZIP_POSTAL']);
                    }
                    if (!empty($row['CITY'])) {
                        $patron['city'] = utf8_encode($row['CITY']);
                    }
                    if (!empty($row['COUNTRY'])) {
                        $patron['country'] = utf8_encode($row['COUNTRY']);
                    }
                }
                if (!empty($row['EXPIRE_DATE'])) {
                    $patron['expiration_date']
                        = $this->dateFormat->convertToDisplayDate(
                            'm-d-Y', $row['EXPIRE_DATE']
                        );
                    $date = $this->dateFormat->convertFromDisplayDate(
                        'U', $patron['expiration_date']
                    );
                    $dateLimit = strtotime('+10 years');
                    if ($date > $dateLimit) {
                        unset($patron['expiration_date']);
                    }
                }
            }
            return empty($patron) ? null : $patron;
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
     * @throws DateException
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        try {
            $fines = parent::getMyFines($patron);
            return $this->markOnlinePayableFines($fines);
        } catch (ILSException $e) {
            return false;
        }
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     * @param array $fines  Patron's fines
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron, $fines)
    {
        if (!empty($fines)) {
            $nonPayableReason = false;
            $amount = 0;
            foreach ($fines as $fine) {
                if (!$fine['payableOnline'] && !$fine['accruedFine']) {
                    $nonPayableReason
                        = 'online_payment_fines_contain_nonpayable_fees';
                } elseif ($fine['payableOnline']) {
                    $amount += $fine['balance'];
                }
            }
            $config = $this->getConfig('onlinePayment');
            if (!$nonPayableReason
                && isset($config['minimumFee']) && $amount < $config['minimumFee']
            ) {
                $nonPayableReason = 'online_payment_minimum_fee';
            }
            $res = ['payable' => empty($nonPayableReason), 'amount' => $amount];
            if ($nonPayableReason) {
                $res['reason'] = $nonPayableReason;
            }
            return $res;
        }
        return [
            'payable' => false,
            'amount' => 0,
            'reason' => 'online_payment_minimum_fee'
        ];
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron            Patron
     * @param int    $amount            Amount to be registered as paid
     * @param string $transactionId     Transaction ID
     * @param int    $transactionNumber Internal transaction number
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount, $transactionId,
        $transactionNumber
    ) {
        $params
            = isset($this->config['OnlinePayment']['registrationParams'])
            ? $this->config['OnlinePayment']['registrationParams'] : []
        ;

        $required = ['host', 'port', 'userId', 'password', 'locationCode'];
        foreach ($required as $req) {
            if (!isset($params[$req]) && !empty($params[$req])) {
                $this->error("Missing SIP2 parameter $req");
                throw new ILSException("Missing SIP2 parameter $req");
            }
        }
        $currency = $this->config['OnlinePayment']['currency'];
        $userId = $patron['id'];
        $patronId = $patron['cat_username'];
        $errFun = function ($userId, $patronId, $error) {
            $this->error(
                "SIP2 payment error (user: $userId, driver: "
                . $this->dbName . ", patron: $patronId): "
                . $error
            );
            throw new ILSException($error);
        };

        $sip = new SIP2();
        $sip->error_detection = false;
        $sip->msgTerminator = "\r";
        $sip->hostname = $params['host'];
        $sip->port = $params['port'];
        $sip->AO = '';

        if ($sip->connect()) {
            $sip->scLocation = $params['locationCode'];
            $sip->UIDalgorithm = 0;
            $sip->PWDalgorithm = 0;
            $loginMsg = $sip->msgLogin(
                $params['userId'], $params['password']
            );
            $loginResponse = $sip->get_message($loginMsg);
            if (strncmp('94', $loginResponse, 2) == 0) {
                $loginResult = $sip->parseLoginResponse($loginResponse);
                if ($loginResult['fixed']['Ok'] == '1') {
                    $sip->patron = $patronId;
                    $feepaidMsg
                        = $sip->msgFeePaid(1, 0, $amount / 100.00, $currency);
                    $feepaidResponse = $sip->get_message($feepaidMsg);
                    if (strncmp('38', $feepaidResponse, 2) == 0) {
                        $feepaidResult
                            = $sip->parseFeePaidResponse($feepaidResponse);
                        if ($feepaidResult['fixed']['PaymentAccepted'] == 'Y') {
                            $sip->disconnect();

                            // Clear patron blocks cache
                            $cacheId = "blocks_$patronId";
                            $this->session->cache[$cacheId] = null;

                            return true;
                        } else {
                            $sip->disconnect();
                            $errFun(
                                $userId, $patronId, 'payment rejected'
                            );
                        }
                    } else {
                        $sip->disconnect();
                        $errFun($userId, $patronId, 'payment failed');
                    }
                } else {
                    $sip->disconnect();
                    $errFun($userId, $patronId, 'login failed');
                }
            } else {
                $sip->disconnect();
                $errFun($userId, $patronId, 'login failed');
            }
        } else {
            $errFun($userId, $patronId, 'connection error');
        }
        return false;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode   The patron barcode
     * @param string $login     The patron's last name or PIN (depending on config)
     * @param string $secondary Optional secondary login field (if enabled)
     *
     * @throws ILSException
     * @return mixed            Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $login, $secondary = null)
    {
        // First check that the login is not blacklisted
        if (!empty($this->config['Catalog']['login_password_blacklist'])
            && in_array($login, $this->config['Catalog']['login_password_blacklist'])
        ) {
            return null;
        }

        // Load the field used for verifying the login from the config file, and
        // make sure there's nothing crazy in there:
        $login_field = isset($this->config['Catalog']['login_field'])
            ? $this->config['Catalog']['login_field'] : 'LAST_NAME';
        $login_field = preg_replace('/[^\w]/', '', $login_field);
        $fallback_login_field
            = isset($this->config['Catalog']['fallback_login_field'])
            ? preg_replace(
                '/[^\w]/', '', $this->config['Catalog']['fallback_login_field']
            ) : '';

        if (!empty($this->config['Catalog']['secondary_login_field'])
            && $secondary !== null
        ) {
            list($secondaryLoginField) = explode(
                ':', $this->config['Catalog']['secondary_login_field'], 2
            );
            $secondaryLoginField = preg_replace('/[^\w]/', '', $secondaryLoginField);
        } else {
            $secondaryLoginField = '';
        }

        // Turns out it's difficult and inefficient to handle the mismatching
        // character sets of the Voyager database in the query (in theory something
        // like
        // "UPPER(UTL_I18N.RAW_TO_NCHAR(UTL_RAW.CAST_TO_RAW(field), 'WE8ISO8859P1'))"
        // could be used, but it's SLOW and ugly). We'll rely on the fact that the
        // barcode shouldn't contain any characters outside the basic latin
        // characters and check login verification fields here.

        $sql = "SELECT PATRON.PATRON_ID, PATRON.FIRST_NAME, PATRON.LAST_NAME, " .
               "PATRON.{$login_field} as LOGIN";

        if ($secondaryLoginField) {
            $sql .= ", PATRON.{$secondaryLoginField} as SECONDARY_LOGIN";
        }

        if ($fallback_login_field) {
            $sql .= ", PATRON.{$fallback_login_field} as FALLBACK_LOGIN";
        }
        $sql .= " FROM $this->dbName.PATRON, $this->dbName.PATRON_BARCODE " .
               "WHERE PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID AND " .
               "lower(PATRON_BARCODE.PATRON_BARCODE) = :barcode";

        // Limit the barcode statuses that allow logging in. By default only
        // 1 (active) and 4 (expired) are allowed.
        $allowedStatuses = preg_replace(
            '/[^:\d]*/',
            '',
            isset($this->config['Catalog']['allowed_barcode_statuses'])
                ? $this->config['Catalog']['allowed_barcode_statuses']
                : '1:4'
        );
        if ($allowedStatuses) {
            $sql .= ' AND PATRON_BARCODE.BARCODE_STATUS IN ('
                . str_replace(':', ',', $allowedStatuses) . ')';
        }

        try {
            $bindBarcode = strtolower(utf8_decode($barcode));
            $compareLogin = mb_strtolower($login, 'UTF-8');
            $compareSecondaryLogin = mb_strtolower($secondary, 'UTF-8');

            $sqlStmt = $this->executeSQL($sql, [':barcode' => $bindBarcode]);
            // For some reason barcode is not unique, so evaluate all resulting
            // rows just to be safe
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                // If enabled, verify secondary login field first
                if ($secondaryLoginField && $row['SECONDARY_LOGIN']) {
                    $secondaryLoginLower = mb_strtolower(
                        utf8_encode($row['SECONDARY_LOGIN']), 'UTF-8'
                    );
                    if ($compareSecondaryLogin != $secondaryLoginLower) {
                        continue;
                    }
                }

                $success = false;
                if (null !== $row['LOGIN']) {
                    // User has a primary login so it needs to match
                    $primary = mb_strtolower(utf8_encode($row['LOGIN']), 'UTF-8');
                    $success = $primary == $compareLogin
                        || $primary == $this->sanitizePIN($compareLogin);
                } else {
                    // No primary login so check fallback login field. Two
                    // possibilities:
                    // 1.) Secondary login field is enabled and the same as fallback
                    // field and no login was given -- no further checks needed
                    // 2.) No secondary or different field so the fallback has to
                    // match

                    $success = $secondaryLoginField
                        && $secondaryLoginField == $fallback_login_field
                        && $compareLogin == '';

                    if (!$success && $fallback_login_field) {
                        $fallback = mb_strtolower(
                            utf8_encode($row['FALLBACK_LOGIN']), 'UTF-8'
                        );
                        $success = $fallback == $compareLogin;
                    }
                }

                if ($success) {
                    return [
                        'id' => utf8_encode($row['PATRON_ID']),
                        'firstname' => utf8_encode($row['FIRST_NAME']),
                        'lastname' => utf8_encode($row['LAST_NAME']),
                        'cat_username' => $barcode,
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
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'markFeesAsPaid') {
            $required = [
                'currency', 'enabled', 'registrationMethod', 'registrationParams'
            ];

            foreach ($required as $req) {
                if (empty($this->config['OnlinePayment'][$req])) {
                    return false;
                }
            }

            if (!$this->config['OnlinePayment']['enabled']) {
                return false;
            }

            $regParams = $this->config['OnlinePayment']['registrationParams'];
            $required = ['host', 'port', 'userId', 'password', 'locationCode'];
            foreach ($required as $req) {
                if (empty($regParams[$req])) {
                    return false;
                }
            }
            return true;
        }
        return is_callable('parent::supportsMethod')
            ? parent::supportsMethod($method, $params)
            : is_callable([$this, $method]);
    }

    /**
     * Support method for getMyFines.
     *
     * Appends booleans 'accruedFine' and 'payableOnline' to a fine.
     *
     * @param array $fines Processed fines.
     *
     * @return array $fines Fines.
     */
    protected function markOnlinePayableFines($fines)
    {
        if (!isset($this->config['OnlinePayment'])) {
            return $fines;
        }

        $accruedType = 'Accrued Fine';

        $config = $this->config['OnlinePayment'];
        $nonPayable = $config['nonPayable'] ?? []
        ;
        $nonPayable[] = $accruedType;
        foreach ($fines as &$fine) {
            $payableOnline = true;
            if (isset($fine['fine'])) {
                if (in_array($fine['fine'], $nonPayable)) {
                    $payableOnline = false;
                }
            }
            $fine['accruedFine'] = ($fine['fine'] === $accruedType);
            $fine['payableOnline'] = $payableOnline;
        }

        return $fines;
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
        $startTime = microtime(true);
        try {
            $result = parent::executeSQL($sql, $bind);
        } catch (\PDOException $e) {
            if ($e->getCode() != 3135) {
                $this->error(
                    "Re-throwing PDO exception in {$this->dbName}, code: "
                    . $e->getCode() . ', message: ' . $e->getMessage()
                );
                throw $e;
            }

            $this->error(
                "PDO connection to {$this->dbName} lost ("
                . $e->getMessage() . '), retrying...'
            );
            $this->lazyDb = null;
            $result = parent::executeSQL($sql, $bind);
        }
        if (!empty($this->config['Debug']['durationLogPrefix'])) {
            list(, $caller) = debug_backtrace(false);
            file_put_contents(
                $this->config['Debug']['durationLogPrefix'] . '_'
                . $caller['function'] . '_sql.log',
                date('Y-m-d H:i:s ') . round(microtime(true) - $startTime, 4) . "\n",
                FILE_APPEND
            );
        }
        return $result;
    }
}
