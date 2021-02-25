<?php
/**
 * Trait for tables that support expiration
 *
 * PHP version 7
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

use Laminas\Db\Sql\Select;

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
     *
     * @return void
     */
    abstract protected function expirationCallback($select, $daysOld);

    /**
     * Delete expired records. Allows setting of 'from' and 'to' ID's so that rows
     * can be deleted in small batches.
     *
     * @param int      $daysOld Age in days of an "expired" record.
     * @param int|null $limit   Maximum number of rows to delete or null for no
     * limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired($daysOld, $limit = null)
    {
        // Determine the expiration parameters:
        $lastId = $limit ? $this->getExpiredBatchLastId($daysOld, $limit) : null;
        $callback = function ($select) use ($daysOld, $lastId) {
            $this->expirationCallback($select, $daysOld);
            if (null !== $lastId) {
                $select->where->and->lessThanOrEqualTo('id', $lastId);
            }
        };
        return $this->delete($callback);
    }

    /**
     * Get the highest id to delete in a batch.
     *
     * @param int $daysOld Age in days of an "expired" record.
     * @param int $limit   Maximum number of rows to delete.
     *
     * @return int|null Highest id value to delete or null if a limiting id is not
     * available
     */
    protected function getExpiredBatchLastId($daysOld, $limit)
    {
        // Determine the expiration date:
        $callback = function ($select) use ($daysOld, $limit) {
            $this->expirationCallback($select, $daysOld);
            $select->columns(['id'])->order('id')->offset($limit - 1)->limit(1);
        };
        $result = $this->select($callback)->current();
        return $result ? $result->id : null;
    }
}
