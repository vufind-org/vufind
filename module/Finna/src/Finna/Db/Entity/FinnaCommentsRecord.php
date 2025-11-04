<?php

/**
 * Entity model for finna_comments_record table
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

use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Entity\CommentsEntityInterface;

/**
 * Entity model for finna_comments_record table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_comments_record')]
#[ORM\Index(name: 'comment_id', columns: ['comment_id'])]
#[ORM\Index(name: 'key_record_id', columns: ['record_id'])]
#[ORM\Index(name: 'finna_comments_record_ibfk_1', columns: ['comment_id'])]
#[ORM\Entity]
class FinnaCommentsRecord implements FinnaCommentsRecordEntityInterface
{
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
     * Record ID.
     *
     * @var string
     */
    #[ORM\Column(name: 'record_id', type: 'string', length: 255, nullable: false)]
    protected string $recordId;

    /**
     * Comment ID.
     *
     * @var CommentsEntityInterface
     */
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: CommentsEntityInterface::class)]
    protected CommentsEntityInterface $comment;

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
     * Get record ID.
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->recordId;
    }

    /**
     * Set record ID.
     *
     * @param string $recordId Record ID
     *
     * @return static
     */
    public function setRecordId(string $recordId): static
    {
        $this->recordId = $recordId;
        return $this;
    }

    /**
     * Get comment.
     *
     * @return CommentsEntityInterface
     */
    public function getComment(): CommentsEntityInterface
    {
        return $this->comment;
    }

    /**
     * Set comment.
     *
     * @param CommentsEntityInterface $comment Comment
     *
     * @return static
     */
    public function setComment(CommentsEntityInterface $comment): static
    {
        $this->comment = $comment;
        return $this;
    }
}
