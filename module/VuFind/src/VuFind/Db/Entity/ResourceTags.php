<?php

/**
 * Entity model for resource_tags table
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * ResourceTags
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'resource_tags')]
#[ORM\Index(name: 'resource_tags_user_id_idx', columns: ['user_id'])]
#[ORM\Index(name: 'resource_tags_resource_id_idx', columns: ['resource_id'])]
#[ORM\Index(name: 'resource_tags_tag_id_idx', columns: ['tag_id'])]
#[ORM\Index(name: 'resource_tags_list_id_idx', columns: ['list_id'])]
#[ORM\Entity]
class ResourceTags implements ResourceTagsEntityInterface
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
     * Posted time.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'posted', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $posted;

    /**
     * Resource ID.
     *
     * @var ?ResourceEntityInterface
     */
    #[ORM\JoinColumn(name: 'resource_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: ResourceEntityInterface::class)]
    protected ?ResourceEntityInterface $resource = null;

    /**
     * Tag ID.
     *
     * @var TagsEntityInterface
     */
    #[ORM\JoinColumn(
        name: 'tag_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'CASCADE',
        options: ['default' => 0]
    )]
    #[ORM\ManyToOne(targetEntity: TagsEntityInterface::class)]
    protected TagsEntityInterface $tag;

    /**
     * List ID.
     *
     * @var ?UserListEntityInterface
     */
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: UserListEntityInterface::class)]
    protected ?UserListEntityInterface $list = null;

    /**
     * User ID.
     *
     * @var ?UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected ?UserEntityInterface $user = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a \DateTime object
        $this->posted = new DateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get resource.
     *
     * @return ?ResourceEntityInterface
     */
    public function getResource(): ?ResourceEntityInterface
    {
        return $this->resource;
    }

    /**
     * Set resource.
     *
     * @param ?ResourceEntityInterface $resource Resource
     *
     * @return static
     */
    public function setResource(?ResourceEntityInterface $resource): static
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * Get tag.
     *
     * @return TagsEntityInterface
     */
    public function getTag(): TagsEntityInterface
    {
        return $this->tag;
    }

    /**
     * Set tag.
     *
     * @param TagsEntityInterface $tag Tag
     *
     * @return static
     */
    public function setTag(TagsEntityInterface $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * Get user list.
     *
     * @return ?UserListEntityInterface
     */
    public function getUserList(): ?UserListEntityInterface
    {
        return $this->list;
    }

    /**
     * Set user list.
     *
     * @param ?UserListEntityInterface $list User list
     *
     * @return static
     */
    public function setUserList(?UserListEntityInterface $list): static
    {
        $this->list = $list;
        return $this;
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
     * @param ?UserEntityInterface $user User object
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getPosted(): DateTime
    {
        return $this->posted;
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setPosted(DateTime $dateTime): static
    {
        $this->posted = $dateTime;
        return $this;
    }
}
