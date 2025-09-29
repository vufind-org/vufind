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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\Db\Entity\Record;
use VuFind\Db\Entity\RecordEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;

use function count;

/**
 * Database service for Records.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RecordService extends AbstractDbService implements RecordServiceInterface
{
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
        $dql = 'SELECT r '
            . 'FROM ' . RecordEntityInterface::class . ' r '
            . 'WHERE r.recordId = :id AND r.source = :source';
        $parameters = compact('id', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return count($records) > 0 ? current($records) : null;
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
        if (empty($ids)) {
            return [];
        }

        $dql = 'SELECT r '
            . 'FROM ' . RecordEntityInterface::class . ' r '
            . 'WHERE r.recordId IN (:ids) AND r.source = :source';
        $parameters = compact('ids', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return $records;
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
        $dql = 'SELECT r.id '
            . 'FROM ' . RecordEntityInterface::class . ' r '
            . 'JOIN ' . ResourceEntityInterface::class . ' re '
            . 'WITH r.recordId = re.recordId AND r.source = re.source '
            . 'LEFT JOIN ' . UserResourceEntityInterface::class . ' ur '
            . 'WITH re.id = ur.resource '
            . 'WHERE ur.id IS NULL';
        $query = $this->entityManager->createQuery($dql);
        $ids = $query->getResult();
        $dql = 'DELETE FROM ' . RecordEntityInterface::class . ' r '
            . 'WHERE r.id IN (:ids)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('ids'));
        $query->execute();
        return count($ids);
    }

    /**
     * Delete a record by source and id
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return bool
     * @throws Exception
     */
    public function deleteRecord(string $id, string $source): bool
    {
        $dql = 'DELETE FROM ' . RecordEntityInterface::class . ' r '
            . 'WHERE r.recordId = :id AND r.source = :source';
        $parameters = compact('id', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->execute();
        return $result;
    }

    /**
     * Create a record entity object.
     *
     * @return RecordEntityInterface
     */
    public function createEntity(): RecordEntityInterface
    {
        return $this->entityPluginManager->get(RecordEntityInterface::class);
    }
}
