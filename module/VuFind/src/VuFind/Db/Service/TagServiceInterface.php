<?php

/**
 * Database service interface for tags.
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

use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;

/**
 * Database service interface for tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface TagServiceInterface extends DbServiceInterface
{
    /**
     * Get statistics on use of tags.
     *
     * @param bool $extended Include extended (unique/anonymous) stats.
     *
     * @return array
     */
    public function getStatistics(bool $extended = false): array;

    /**
     * Get the tags that match a string
     *
     * @param string $text Tag to look up.
     *
     * @return array
     */
    public function matchText(string $text): array;

    /**
     * Get tags associated with the specified resource.
     *
     * @param string                                $id        Record ID to look up
     * @param string                                $source    Source of record to look up
     * @param int                                   $limit     Max. number of tags to return (0 = no limit)
     * @param UserListEntityInterface|int|bool|null $listOrId  List entity/ID to load tags from (null for no
     * restriction, true for on ANY list, false for on NO lists)
     * @param UserEntityInterface|int|null          $userOrId  User entity/ID to load tags from (null for all users)
     * @param string                                $sort      Sort type ('count' or 'tag')
     * @param UserEntityInterface|int|null          $ownerOrId Entity/ID representing user to check for ownership
     * (this will not filter the result list, but rows owned by this user will have an is_me column set to 1)
     *
     * @return array
     */
    public function getTagsForRecord(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND,
        int $limit = 0,
        UserListEntityInterface|int|bool|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null,
        string $sort = 'count',
        UserEntityInterface|int|null $ownerOrId = null
    ): array;

    /**
     * Delete orphaned tags (those not present in resource_tags) from the tags table.
     *
     * @return void
     */
    public function deleteOrphanedTags(): void;

    /**
     * Retrieve a tag by ID.
     *
     * @param int $id Tag ID
     *
     * @return ?TagsEntityInterface
     */
    public function getTagById(int $id): ?TagsEntityInterface;
}
