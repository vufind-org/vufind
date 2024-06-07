<?php

/**
 * Entity model for user_list table
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * UserList
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="user_list",
 *          indexes={@ORM\Index(name="user_id", columns={"user_id"})}
 * )
 * @ORM\Entity
 */
class UserList implements UserListEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false
     * )
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Title of the list.
     *
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=200, nullable=false)
     */
    protected $title = '';

    /**
     * Description of the list.
     *
     * @var ?string
     *
     * @ORM\Column(name="description", type="text", length=65535, nullable=true)
     */
    protected $description;

    /**
     * Creation date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $created = '2000-01-01 00:00:00';

    /**
     * Flag to indicate whether or not the list is public.
     *
     * @var bool
     *
     * @ORM\Column(name="public", type="boolean", nullable=false)
     */
    protected $public = false;

    /**
     * User ID.
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="user_id",
     *              referencedColumnName="id"
     * )})
     */
    protected $user;

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
     * Set title.
     *
     * @param string $title Title
     *
     * @return UserListEntityInterface
     */
    public function setTitle(string $title): UserListEntityInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set description.
     *
     * @param ?string $description Description
     *
     * @return UserListEntityInterface
     */
    public function setDescription(?string $description): UserListEntityInterface
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description.
     *
     * @return ?string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserListEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserListEntityInterface
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set whether the list is public.
     *
     * @param bool $public Is the list public?
     *
     * @return UserListEntityInterface
     */
    public function setPublic(bool $public): UserListEntityInterface
    {
        $this->public = $public;
        return $this;
    }

    /**
     * Is this a public list?
     *
     * @return bool
     */
    public function isPublic(): bool
    {
        return (bool)($this->public ?? false);
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User object
     *
     * @return UserListEntityInterface
     */
    public function setUser(?UserEntityInterface $user): UserListEntityInterface
    {
        $this->user = $user;
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
}
