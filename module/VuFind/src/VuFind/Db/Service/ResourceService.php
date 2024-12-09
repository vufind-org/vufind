<?php

/**
 * Database service for resource.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Table\Resource;

use function count;

/**
 * Database service for resource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceService extends AbstractDbService implements ResourceServiceInterface, Feature\TransactionInterface
{
    /**
     * Constructor.
     *
     * @param Resource $resourceTable Resource table
     */
    public function __construct(protected Resource $resourceTable)
    {
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $this->resourceTable->beginTransaction();
    }

    /**
     * Commit a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function commitTransaction(): void
    {
        $this->resourceTable->commitTransaction();
    }

    /**
     * Roll back a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function rollBackTransaction(): void
    {
        $this->resourceTable->rollbackTransaction();
    }

    /**
     * Lookup and return a resource.
     *
     * @param int $id Identifier value
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceById(int $id): ?ResourceEntityInterface
    {
        return $this->resourceTable->select(['id' => $id])->current();
    }

    /**
     * Create a resource entity object.
     *
     * @return ResourceEntityInterface
     */
    public function createEntity(): ResourceEntityInterface
    {
        return $this->resourceTable->createRow();
    }

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return ResourceEntityInterface[]
     */
    public function findMissingMetadata(): array
    {
        $callback = function ($select) {
            $select->where->equalTo('title', '')
                ->OR->isNull('author')
                ->OR->isNull('year');
        };
        return iterator_to_array($this->resourceTable->select($callback));
    }

    /**
     * Retrieve a single resource row by record ID/source. Return null if it does not exist.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceByRecordId(string $id, string $source = DEFAULT_SEARCH_BACKEND): ?ResourceEntityInterface
    {
        return $this->resourceTable->select(['record_id' => $id, 'source' => $source])->current();
    }

    /**
     * Retrieve resource entities matching a set of specified records.
     *
     * @param string[] $ids    Array of IDs
     * @param string   $source Source of records to look up
     *
     * @return ResourceEntityInterface[]
     */
    public function getResourcesByRecordIds(array $ids, string $source = DEFAULT_SEARCH_BACKEND): array
    {
        $callback = function ($select) use ($ids, $source) {
            $select->where->in('record_id', $ids);
            $select->where->equalTo('source', $source);
        };
        return iterator_to_array($this->resourceTable->select($callback));
    }

    /**
     * Get a set of resources from the requested favorite list.
     *
     * @param UserEntityInterface|int          $userOrId          ID of user owning favorite list
     * @param UserListEntityInterface|int|null $listOrId          ID of list to retrieve (null for all favorites)
     * @param string[]                         $tags              Tags to use for limiting results
     * @param ?string                          $sort              Resource table field to use for sorting (null for no
     * particular sort).
     * @param int                              $offset            Offset for results
     * @param ?int                             $limit             Limit for results (null for none)
     * @param bool                             $caseSensitiveTags Treat tags as case-sensitive?
     *
     * @return ResourceEntityInterface[]
     */
    public function getFavorites(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        array $tags = [],
        ?string $sort = null,
        int $offset = 0,
        ?int $limit = null,
        bool $caseSensitiveTags = false
    ): array {
        return iterator_to_array(
            $this->resourceTable->getFavorites(
                $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId,
                $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId,
                $tags,
                $sort,
                $offset,
                $limit,
                $caseSensitiveTags
            )
        );
    }

    /**
     * Delete a resource by record id and source. Return true if found and deleted, false if not found.
     * Throws exception if something goes wrong.
     *
     * @param string $id     Resource ID
     * @param string $source Resource source
     *
     * @return bool
     * @throws Exception
     */
    public function deleteResourceByRecordId(string $id, string $source): bool
    {
        $row = $this->resourceTable->select(['source' => $source, 'record_id' => $id])->current();
        if (!$row) {
            return false;
        }
        $row->delete();
        return true;
    }

    /**
     * Globally change the name of a source value in the database; return the number of rows affected.
     *
     * @param string $old Old source value
     * @param string $new New source value
     *
     * @return int
     */
    public function renameSource(string $old, string $new): int
    {
        $resourceWhere = ['source' => $old];
        $resourceRows = $this->resourceTable->select($resourceWhere);
        if ($count = count($resourceRows)) {
            $this->resourceTable->update(['source' => $new], $resourceWhere);
        }
        return $count;
    }

    /**
     * Delete a resource entity.
     *
     * @param ResourceEntityInterface|int $resourceOrId Resource entity or ID value.
     *
     * @return void
     */
    public function deleteResource(ResourceEntityInterface|int $resourceOrId): void
    {
        $id = $resourceOrId instanceof ResourceEntityInterface ? $resourceOrId->getId() : $resourceOrId;
        $this->resourceTable->delete(['id' => $id]);
    }
}
