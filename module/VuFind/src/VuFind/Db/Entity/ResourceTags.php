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
        $this->posted = new \DateTime();
    }

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Resource setter.
     *
     * @param ?Resource $resource Resource
     *
     * @return ResourceTags
     */
    public function setResource(?Resource $resource): ResourceTags
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

    /**
     * Tags setter.
     *
     * @param ?Tags $tag Tag object
     *
     * @return ResourceTags
     */
    public function setTag(?Tags $tag): ResourceTags
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * Tag getter
     *
     * @return Tags
     */
    public function getTag(): Tags
    {
        return $this->tag;
    }

    /**
     * List setter.
     *
     * @param ?UserList $list UserList object
     *
     * @return ResourceTags
     */
    public function setList(?UserList $list): ResourceTags
    {
        $this->list = $list;
        return $this;
    }

    /**
     * List getter
     *
     * @return ?UserList
     */
    public function getList(): ?UserList
    {
        return $this->list;
    }

    /**
     * User setter.
     *
     * @param ?User $user User object
     *
     * @return ResourceTags
     */
    public function setUser(?User $user): ResourceTags
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
     * Posted setter.
     *
     * @param ?Datetime $dateTime Posted date
     *
     * @return ResourceTags
     */
    public function setPosted(?DateTime $dateTime): ResourceTags
    {
        $this->posted = $dateTime;
        return $this;
    }

    /**
     * Posted getter
     *
     * @return Datetime
     */
    public function getPosted(): ?Datetime
    {
        return $this->posted;
    }
}
