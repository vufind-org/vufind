<?php

/**
 * Database service interface for Comments.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service interface for Comments.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface CommentsServiceInterface extends DbServiceInterface
{
    /**
     * Create a comments entity object.
     *
     * @return CommentsEntityInterface
     */
    public function createEntity(): CommentsEntityInterface;

    /**
     * Add a comment to the current resource. Returns comment ID on success, null on failure.
     *
     * @param string                      $comment      The comment to save.
     * @param UserEntityInterface|int     $userOrId     User object or identifier
     * @param ResourceEntityInterface|int $resourceOrId Resource object or identifier
     *
     * @return ?int
     */
    public function addComment(
        string $comment,
        UserEntityInterface|int $userOrId,
        ResourceEntityInterface|int $resourceOrId
    ): ?int;

    /**
     * Get comments associated with the specified record.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return CommentsEntityInterface[]
     */
    public function getRecordComments(string $id, string $source = DEFAULT_SEARCH_BACKEND): array;

    /**
     * Delete a comment if the owner is logged in.  Returns true on success.
     *
     * @param int                     $id       ID of row to delete
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return bool
     */
    public function deleteIfOwnedByUser(int $id, UserEntityInterface|int $userOrId): bool;

    /**
     * Deletes all comments by a user.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return void
     */
    public function deleteByUser(UserEntityInterface|int $userOrId): void;

    /**
     * Get statistics on use of comments.
     *
     * @return array
     */
    public function getStatistics(): array;

    /**
     * Get a comment row by ID (or return null for no match).
     *
     * @param int $id ID of comment to retrieve.
     *
     * @return ?CommentsEntityInterface
     */
    public function getCommentById(int $id): ?CommentsEntityInterface;

    /**
     * Change all matching comments to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int $old Original resource ID
     * @param int $new New resource ID
     *
     * @return void
     */
    public function changeResourceId(int $old, int $new): void;
}
