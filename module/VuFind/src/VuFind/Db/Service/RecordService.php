<?php

/**
 * Database service for Records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

use Exception;
use VuFind\Db\Entity\RecordEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

/**
 * Database service for Records.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RecordService extends AbstractDbService implements DbTableAwareInterface, RecordServiceInterface
{
    use DbTableAwareTrait;

    /**
     * Retrieve a record by id.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return ?RecordEntityInterface
     */
    public function getRecord(string $id, string $source): ?RecordEntityInterface
    {
        return $this->getDbTable('record')->findRecord($id, $source);
    }

    /**
     * Retrieve records by ids.
     *
     * @param string[] $ids    Record IDs
     * @param string   $source Record source
     *
     * @return RecordEntityInterface[] Array of record objects found
     */
    public function getRecords(array $ids, string $source): array
    {
        return $this->getDbTable('record')->findRecords($ids, $source);
    }

    /**
     * Update an existing entry in the record table or create a new one.
     *
     * @param string $id      Record ID
     * @param string $source  Data source
     * @param mixed  $rawData Raw data from source (must be serializable)
     *
     * @return RecordEntityInterface
     */
    public function updateRecord(string $id, string $source, $rawData): RecordEntityInterface
    {
        $record = $this->getRecord($id, $source);
        if (!$record) {
            $record = $this->createEntity();
        }
        $record->setRecordId($id)
            ->setSource($source)
            ->setData(serialize($rawData))
            ->setVersion(\VuFind\Config\Version::getBuildVersion())
            ->setUpdated(new \DateTime());
        $this->persistEntity($record);
        return $record;
    }

    /**
     * Clean up orphaned entries (i.e. entries that are not in favorites anymore)
     *
     * @return int Number of records deleted
     */
    public function cleanup(): int
    {
        return $this->getDbTable('record')->cleanup();
    }

    /**
     * Delete a record by source and id. Return true if found and deleted, false if not found.
     * Throws exception if something goes wrong.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return bool
     * @throws Exception
     */
    public function deleteRecord(string $id, string $source): bool
    {
        $record = $this->getDbTable('record')->findRecord($id, $source);
        if (!$record) {
            return false;
        }
        $record->delete();
        return true;
    }

    /**
     * Create a record entity object.
     *
     * @return RecordEntityInterface
     */
    public function createEntity(): RecordEntityInterface
    {
        return $this->getDbTable('record')->createRow();
    }
}
