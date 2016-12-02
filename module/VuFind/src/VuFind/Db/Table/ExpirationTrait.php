<?php
/**
 * Trait for tables that support expiration
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Db\Table;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;

/**
 * Trait for tables that support expiration
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ExpirationTrait
{
    /**
     * Update the select statement to find records to delete.
     *
     * @param Select $select  Select clause
     * @param int    $daysOld Age in days of an "expired" record.
     * @param int    $idFrom  Lowest id of rows to delete.
     * @param int    $idTo    Highest id of rows to delete.
     *
     * @return void
     */
    abstract protected function expirationCallback($select, $daysOld, $idFrom = null,
        $idTo = null
    );

    /**
     * Delete expired records. Allows setting of 'from' and 'to' ID's so that rows
     * can be deleted in small batches.
     *
     * @param int $daysOld Age in days of an "expired" record.
     * @param int $idFrom  Lowest id of rows to delete.
     * @param int $idTo    Highest id of rows to delete.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired($daysOld = 2, $idFrom = null, $idTo = null)
    {
        // Determine the expiration date:
        $callback = function ($select) use ($daysOld, $idFrom, $idTo) {
            $this->expirationCallback($select, $daysOld, $idFrom, $idTo);
        };
        return $this->delete($callback);
    }

    /**
     * Get the lowest id and highest id for expired records.
     *
     * @param int $daysOld Age in days of an "expired" record.
     *
     * @return array|bool Array of lowest id and highest id or false if no expired
     * records found
     */
    public function getExpiredIdRange($daysOld = 2)
    {
        // Determine the expiration date:
        $callback = function ($select) use ($daysOld) {
            $this->expirationCallback($select, $daysOld);
        };
        $select = $this->getSql()->select();
        $select->columns(
            [
                'id' => new Expression('1'), // required for TableGateway
                'minId' => new Expression('MIN(id)'),
                'maxId' => new Expression('MAX(id)'),
            ]
        );
        $select->where($callback);
        $result = $this->selectWith($select)->current();
        if (null === $result->minId) {
            return false;
        }
        return [$result->minId, $result->maxId];
    }
}
