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

use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Table\Resource;

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
class ResourceService extends AbstractDbService implements ResourceServiceInterface
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
     * Lookup and return a resource.
     *
     * @param int $id Identifier value
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceById($id): ?ResourceEntityInterface
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
}
