<?php

/**
 * Entity model for finna_comments_inappropriate table
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
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Entity model for finna_comments_inappropriate table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_comments_inappropriate')]
#[ORM\Index(name: 'user_id', columns: ['user_id'])]
#[ORM\Index(name: 'comment_id', columns: ['comment_id'])]
#[ORM\Index(name: 'session_id', columns: ['session_id'])]
#[ORM\Index(name: 'finna_comments_inappropriate_ibfk_1', columns: ['comment_id'])]
#[ORM\Entity]
class FinnaCommentsInappropriate implements FinnaCommentsInappropriateEntityInterface
{
    use DateTimeTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * User ID.
     *
     * @var ?UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected ?UserEntityInterface $user = null;

    /**
     * Comment ID.
     *
     * @var ?CommentsEntityInterface
     */
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: CommentsEntityInterface::class)]
    protected ?CommentsEntityInterface $comment = null;

    /**
     * Creation date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $created;

    /**
     * Reason.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'reason', type: 'string', length: 1000, nullable: true)]
    protected ?string $reason = null;

    /**
     * Message.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'message', type: 'string', length: 1000, nullable: true)]
    protected ?string $message = null;

    /**
     * Session ID.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'session_id', type: 'string', length: 128, nullable: true)]
    protected ?string $sessionId = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->created = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user;
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User that created comment
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get comment.
     *
     * @return ?CommentsEntityInterface
     */
    public function getComment(): ?CommentsEntityInterface
    {
        return $this->comment;
    }

    /**
     * Set comment.
     *
     * @param ?CommentsEntityInterface $comment Comment
     *
     * @return static
     */
    public function setComment(?CommentsEntityInterface $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Get creation date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set creation date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get reason.
     *
     * @return ?string
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Set reason.
     *
     * @param ?string $reason Reason
     *
     * @return static
     */
    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * Get message.
     *
     * @return ?string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Set message.
     *
     * @param ?string $message Message
     *
     * @return static
     */
    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get PHP session id string.
     *
     * @return ?string
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Set PHP session id string.
     *
     * @param ?string $sessionId PHP Session id string
     *
     * @return static
     */
    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }
}
