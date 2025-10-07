<?php

/**
 * Entity model for user_resource table
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
 * Entity model for user_resource table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'user_resource')]
#[ORM\Index(name: 'user_resource_list_id_idx', columns: ['list_id'])]
#[ORM\Index(name: 'user_resource_resource_id_idx', columns: ['resource_id'])]
#[ORM\Index(name: 'user_resource_user_id_idx', columns: ['user_id'])]
#[ORM\Entity]
class UserResource implements UserResourceEntityInterface
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
     * Notes associated with the resource.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'notes', type: 'text', length: 65535, nullable: true)]
    protected ?string $notes = null;

    /**
     * Date saved.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'saved', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $saved;

    /**
     * User ID.
     *
     * @var UserEntityInterface
     */
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    protected UserEntityInterface $user;

    /**
     * Resource.
     *
     * @var ResourceEntityInterface
     */
    #[ORM\JoinColumn(name: 'resource_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: ResourceEntityInterface::class)]
    protected ResourceEntityInterface $resource;

    /**
     * User list ID.
     *
     * @var ?UserListEntityInterface
     */
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: UserListEntityInterface::class)]
    protected ?UserListEntityInterface $list = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a \DateTime object
        $this->saved = new DateTime();
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
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        return $this->user;
    }

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get resource.
     *
     * @return ResourceEntityInterface
     */
    public function getResource(): ResourceEntityInterface
    {
        return $this->resource;
    }

    /**
     * Set resource.
     *
     * @param ResourceEntityInterface $resource Resource
     *
     * @return static
     */
    public function setResource(ResourceEntityInterface $resource): static
    {
        $this->resource = $resource;
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
     * @param ?UserListEntityInterface $list User List
     *
     * @return static
     */
    public function setUserList(?UserListEntityInterface $list): static
    {
        $this->list = $list;
        return $this;
    }

    /**
     * Get notes.
     *
     * @return ?string
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * Set notes.
     *
     * @param ?string $notes Notes associated with the resource
     *
     * @return static
     */
    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Get saved date.
     *
     * @return DateTime
     */
    public function getSaved(): DateTime
    {
        return $this->saved;
    }

    /**
     * Set saved date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setSaved(DateTime $dateTime): static
    {
        $this->saved = $dateTime;
        return $this;
    }
}
