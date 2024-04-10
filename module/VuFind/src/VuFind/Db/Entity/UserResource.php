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

use Doctrine\ORM\Mapping as ORM;

/**
 * UserResource
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="user_resource",
 *          indexes={@ORM\Index(name="list_id", columns={"list_id"}),
 * @ORM\Index(name="resource_id",   columns={"resource_id"}),
 * @ORM\Index(name="user_id",       columns={"user_id"})}
 * )
 * @ORM\Entity
 */
class UserResource implements UserResourceEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Notes associated with the resource.
     *
     * @var ?string
     *
     * @ORM\Column(name="notes", type="text", length=65535, nullable=true)
     */
    protected $notes;

    /**
     * Date saved.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="saved",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $saved;

    /**
     * User ID.
     *
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="user_id",
     *              referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * Resource.
     *
     * @var Resource
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\Resource")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="resource_id",
     *              referencedColumnName="id")
     * })
     */
    protected $resource;

    /**
     * User list ID.
     *
     * @var UserList
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\UserList")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="list_id",
     *              referencedColumnName="id")
     * })
     */
    protected $list;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a \DateTime object
        $this->saved = new \DateTime();
    }

    /**
     * Notes setter
     *
     * @param string $notes Notes
     *
     * @return UserResource
     */
    public function setNotes(string $notes): UserResource
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Notes getter
     *
     * @return string
     */
    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * User setter
     *
     * @param User $user User
     *
     * @return UserResource
     */
    public function setUser(User $user): UserResource
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
     * User List setter
     *
     * @param UserList $list User List
     *
     * @return UserResource
     */
    public function setList(UserList $list): UserResource
    {
        $this->list = $list;
        return $this;
    }

    /**
     * User List getter
     *
     * @return UserList
     */
    public function getList(): UserList
    {
        return $this->list;
    }

    /**
     * Resource setter
     *
     * @param Resource $resource Resource
     *
     * @return UserResource
     */
    public function setResource(Resource $resource): UserResource
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * Resource getter
     *
     * @return Resource
     */
    public function getResource(): Resource
    {
        return $this->resource;
    }
}
