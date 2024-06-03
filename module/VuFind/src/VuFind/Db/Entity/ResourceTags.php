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
 * ResourceTags
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="resource_tags")
 * @ORM\Entity
 */
class ResourceTags implements ResourceTagsEntityInterface
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
     * Posted time.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="posted",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="CURRENT_TIMESTAMP"}
     * )
     */
    protected $posted;

    /**
     * Resource ID.
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
     * Tag ID.
     *
     * @var Tags
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\Tags")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="tag_id",
     *              referencedColumnName="id")
     * })
     */
    protected $tag;

    /**
     * List ID.
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
     * @return ResourceEntityInterface
     */
    public function getResource(): ResourceEntityInterface
    {
        return $this->resource;
    }

    /**
     * Set resource.
     *
     * @param ?ResourceEntityInterface $resource Resource
     *
     * @return ResourceTagsEntityInterface
     */
    public function setResource(?ResourceEntityInterface $resource): ResourceTagsEntityInterface
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
     * @param TagsEntityInterface  $tag Tag
     *
     * @return ResourceTagsEntityInterface
     */
    public function setTag(TagsEntityInterface $tag): ResourceTagsEntityInterface
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
     * @return ResourceTagsEntityInterface
     */
    public function setUserList(?UserListEntityInterface $list): ResourceTagsEntityInterface
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
     * @return ResourceTagsEntityInterface
     */
    public function setUser(?UserEntityInterface $user): ResourceTagsEntityInterface
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
     * @return ResourceTagsEntityInterface
     */
    public function setPosted(DateTime $dateTime): ResourceTagsEntityInterface
    {
        $this->posted = $dateTime;
        return $this;
    }
}
