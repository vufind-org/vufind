<?php
/**
 * Voyager/VoyagerRestful Common Trait
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace Finna\ILS\Driver;
use PDO;

/**
 * Voyager/VoyagerRestful Common Trait
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
trait VoyagerFinna
{
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
                $this->debugSQL(__FUNCTION__, $sql['string'], $sql['bind']);
                $sqlStmt = $this->db->prepare($sql['string']);
                $sqlStmt->execute($sql['bind']);
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
        }

        if (is_callable('parent::getConfig')) {
            return parent::getConfig($function, $params);
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

        try {
            $bindBarcode = strtolower(utf8_decode($barcode));
            $compareLogin = mb_strtolower($login, 'UTF-8');
            $compareSecondaryLogin = mb_strtolower($secondary, 'UTF-8');

            $this->debugSQL(__FUNCTION__, $sql, [':barcode' => $bindBarcode]);
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(':barcode', $bindBarcode, PDO::PARAM_STR);
            $sqlStmt->execute();
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
                if (!is_null($row['LOGIN'])) {
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
}
