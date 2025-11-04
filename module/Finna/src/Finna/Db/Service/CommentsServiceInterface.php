<?php

/**
 * Database service interface for comments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service interface for comments.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface CommentsServiceInterface extends \VuFind\Db\Service\CommentsServiceInterface
{
    /**
     * Mark comment as inappropriate
     *
     * @param UserEntityInterface $user      User object
     * @param int                 $commentId Comment ID
     * @param string              $reason    Reason
     * @param string              $message   Expand given reason
     * @param string              $sessionId Session ID
     *
     * @return void
     */
    public function markCommentInappropriate(
        UserEntityInterface $user,
        int $commentId,
        string $reason,
        string $message,
        string $sessionId
    ): void;

    /**
     * Get inappropriate comments for a record reported by the given user.
     *
     * @param ?UserEntityInterface $user     Reporter, or null to use current session
     * @param string               $recordId Record ID
     *
     * @return CommentsEntityInterface[]
     */
    public function getInappropriateForRecord(?UserEntityInterface $user, string $recordId, string $source): array;

    /**
     * Edit comment.
     *
     * @param UserEntityInterface $user      User object or identifier
     * @param int                 $commentId Comment ID
     * @param string              $comment   Comment
     *
     * @return void
     */
    public function editComment(UserEntityInterface $user, int $commentId, string $comment);

    /**
     * Change all matching comments to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int   $commentId Comment ID
     * @param array $recordIds Record IDs
     *
     * @return void
     */
    public function addRecordLinks(int $commentId, array $recordIds): void;

    /**
     * Get a batch of entities.
     *
     * @param ?int $lastId    ID of last retrieved entity, or null to start from beginning
     * @param int  $batchSize Batch size
     *
     * @return CommentsEntityInterface[]
     */
    public function getEntityBatch(?int $lastId, int $batchSize): array;
}
