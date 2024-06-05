<?php

/**
 * Database service for resource_tags.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Laminas\Paginator\Paginator;

/**
 * Database service for resource_tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceTagsService extends AbstractDbService implements
    ResourceTagsServiceInterface,
    \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Get Resource Tags Paginator
     *
     * @param ?int    $userId     ID of user (null for any)
     * @param ?int    $resourceId ID of the resource (null for any)
     * @param ?int    $tagId      ID of the tag (null for any)
     * @param ?string $order      The order in which to return the data
     * @param ?int    $page       The page number to select
     * @param int     $limit      The number of items to fetch
     *
     * @return Paginator
     */
    public function getResourceTagsPaginator(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        ?string $order = null,
        ?int $page = null,
        int $limit = 20
    ): Paginator {
        return $this->getDbTable('ResourceTags')->getResourceTags($userId, $resourceId, $tagId, $order, $page, $limit);
    }

    /**
     * Remove links from the resource_tags table based on an array of IDs.
     *
     * @param string[] $ids Identifiers from resource_tags to delete.
     *
     * @return int          Count of $ids
     */
    public function deleteLinksByResourceTagsIdArray(array $ids): int
    {
        return $this->getDbTable('ResourceTags')->deleteByIdArray($ids);
    }

    /**
     * Gets unique tagged resources from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
     */
    public function getUniqueResources(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array {
        return $this->getDbTable('ResourceTags')->getUniqueResources($userId, $resourceId, $tagId)->toArray();
    }

    /**
     * Gets unique tags from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
     */
    public function getUniqueTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array {
        return $this->getDbTable('ResourceTags')->getUniqueTags($userId, $resourceId, $tagId)->toArray();
    }

    /**
     * Gets unique users from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
     */
    public function getUniqueUsers(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array {
        return $this->getDbTable('ResourceTags')->getUniqueUsers($userId, $resourceId, $tagId)->toArray();
    }

    /**
     * Delete resource tags rows matching specified filter(s). Return count of IDs deleted.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return int
     */
    public function deleteResourceTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): int {
        $deleted = 0;
        while (true) {
            $nextBatch = $this->getResourceTagsPaginator($userId, $resourceId, $tagId);
            if ($nextBatch->getTotalItemCount() < 1) {
                return $deleted;
            }
            $ids = [];
            foreach ($nextBatch as $row) {
                $ids[] = $row['id'];
            }
            $deleted += $this->deleteLinksByResourceTagsIdArray($ids);
        }
    }
