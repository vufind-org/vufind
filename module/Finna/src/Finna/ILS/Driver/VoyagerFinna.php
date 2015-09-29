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
}
