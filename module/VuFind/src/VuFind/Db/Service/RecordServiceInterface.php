<?php

/**
 * Database service interface for Records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\RecordEntityInterface;

/**
 * Database service interface for Records.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface RecordServiceInterface extends DbServiceInterface
{
    /**
     * Find a record by id.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return ?RecordEntityInterface
     */
    public function findRecord($id, $source): ?RecordEntityInterface;

    /**
     * Find records by ids.
     *
     * @param array  $ids    Record IDs
     * @param string $source Record source
     *
     * @return RecordEntityInterface[] Array of record objects found
     */
    public function findRecords($ids, $source): array;

    /**
     * Update an existing entry in the record table or create a new one.
     *
     * @param string $id      Record ID
     * @param string $source  Data source
     * @param string $rawData Raw data from source
     *
     * @return RecordEntityInterface
     */
    public function updateRecord($id, $source, $rawData): RecordEntityInterface;

    /**
     * Clean up orphaned entries (i.e. entries that are not in favorites anymore)
     *
     * @return int Number of records deleted
     */
    public function cleanup(): int;

    /**
     * Delete a record by source and id
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return mixed
     */
    public function deleteRecord(string $id, string $source): void;

    /**
     * Create a record entity object.
     *
     * @return RecordEntityInterface
     */
    public function createEntity(): RecordEntityInterface;
}
