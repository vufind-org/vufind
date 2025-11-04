<?php

/**
 * Entity model interface for finna_comments_inappropriate table
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

use DateTime;
use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Entity model interface for finna_comments_inappropriate table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface FinnaCommentsInappropriateEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User that created comment
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static;

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Get comment.
     *
     * @return ?CommentsEntityInterface
     */
    public function getComment(): ?CommentsEntityInterface;

    /**
     * Set comment.
     *
     * @param ?CommentsEntityInterface $comment Comment
     *
     * @return static
     */
    public function setComment(?CommentsEntityInterface $comment): static;

    /**
     * Created setter.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Get reason.
     *
     * @return ?string
     */
    public function getReason(): ?string;

    /**
     * Set reason.
     *
     * @param ?string $reason Reason
     *
     * @return static
     */
    public function setReason(?string $reason): static;

    /**
     * Get message.
     *
     * @return ?string
     */
    public function getMessage(): ?string;

    /**
     * Set message.
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static;

    /**
     * Get PHP session id string.
     *
     * @return ?string
     */
    public function getSessionId(): ?string;

    /**
     * Set PHP session id string.
     *
     * @param ?string $sessionId PHP Session id string
     *
     * @return static
     */
    public function setSessionId(?string $sessionId): static;
}
