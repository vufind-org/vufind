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
     * Add a comment to the current resource. Returns comment ID on success, null on failure.
     *
     * @param string                      $comment  The comment to save.
     * @param int|UserEntityInterface     $user     User object or identifier
     * @param int|ResourceEntityInterface $resource Resource object or identifier
     *
     * @return ?int
     */
    public function addComment(
        string $comment,
        int|UserEntityInterface $user,
        int|ResourceEntityInterface $resource
    ): ?int;

    /**
     * Get comments associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return CommentsEntityInterface[]
     */
    public function getForResource(string $id, $source = DEFAULT_SEARCH_BACKEND): array;

    /**
     * Delete a comment if the owner is logged in.  Returns true on success.
     *
     * @param int                     $id   ID of row to delete
     * @param int|UserEntityInterface $user User object or identifier
     *
     * @return bool
     */
    public function deleteIfOwnedByUser(int $id, int|UserEntityInterface $user): bool;

    /**
     * Deletes all comments by a user.
     *
     * @param int|UserEntityInterface $user User object or identifier
     *
     * @return void
     */
    public function deleteByUser(int|UserEntityInterface $user): void;

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
}
