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

use function is_int;

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
class UserList implements EntityInterface
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
    protected $title;

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
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
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
     * Userlist title setter
     *
     * @param string $title List title
     *
     * @return UserList
     */
    public function setTitle(string $title): UserList
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get list title
     *
     * @return ?string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set description of the list
     *
     * @param ?string $description List description
     *
     * @return UserList
     */
    public function setDescription(?string $description): UserList
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description of the list
     *
     * @return ?string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the public list flag
     *
     * @param bool $public Public flag
     *
     * @return UserList
     */
    public function setPublic(bool $public): UserList
    {
        $this->public = $public;
        return $this;
    }

    /**
     * User setter.
     *
     * @param ?User $user User object
     *
     * @return UserList
     */
    public function setUser(?User $user): UserList
    {
        $this->user = $user;
        return $this;
    }

    /**
     * User getter
     *
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Created setter.
     *
     * @param Datetime $dateTime Created date
     *
     * @return UserList
     */
    public function setCreated(DateTime $dateTime): UserList
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Created getter
     *
     * @return Datetime
     */
    public function getCreated(): Datetime
    {
        return $this->created;
    }

    /**
     * Is the current user allowed to edit this list?
     *
     * @param User|int|null $user Logged-in user (null if none)
     *
     * @return bool
     */
    public function editAllowed($user): bool
    {
        if ($user instanceof User && $user->getId() == $this->getUser()->getId()) {
            return true;
        }
        if (is_int($user) && $user == $this->getUser()->getId()) {
            return true;
        }
        return false;
    }
}
