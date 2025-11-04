<?php

/**
 * Entity model interface for finna_comments_record table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Entity;

use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\EntityInterface;

/**
 * Entity model interface for finna_comments_record table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface FinnaCommentsRecordEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get record ID.
     *
     * @return string
     */
    public function getRecordId(): string;

    /**
     * Set record ID.
     *
     * @param string $recordId Record ID
     *
     * @return static
     */
    public function setRecordId(string $recordId): static;

    /**
     * Get comment.
     *
     * @return CommentsEntityInterface
     */
    public function getComment(): CommentsEntityInterface;

    /**
     * Set comment.
     *
     * @param CommentsEntityInterface $comment Comment
     *
     * @return static
     */
    public function setComment(CommentsEntityInterface $comment): static;
}
