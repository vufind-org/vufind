<?php

/**
 * Entity model for ratings table
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
 * Entity model for ratings table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="ratings")
 * @ORM\Entity
 */
class Ratings implements EntityInterface
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
     * User ID.
     *
     * @var \VuFind\Db\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="user_id",
     *              referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * Resource ID.
     *
     * @var \VuFind\Db\Entity\Resource
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\Resource")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="resource_id",
     *              referencedColumnName="id")
     * })
     */
    protected $resource;

    /**
     * Rating.
     *
     * @var int
     *
     * @ORM\Column(name="rating", type="integer", nullable=false)
     */
    protected $rating;

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
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get user.
     *
     * @return \VuFind\Db\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * User setter
     *
     * @param User $user User
     *
     * @return Ratings
     */
    public function setUser(User $user): Ratings
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Rating setter
     *
     * @param int $rating Rating
     *
     * @return Ratings
     */
    public function setRating(int $rating): Ratings
    {
        $this->rating = $rating;
        return $this;
    }

    /**
     * Rating getter
     *
     * @return int
     */
    public function getRating(): int
    {
        return $this->rating;
    }

    /**
     * Resource setter
     *
     * @param Resource $resource Resource
     *
     * @return Ratings
     */
    public function setResource(Resource $resource): Ratings
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
     * Created setter.
     *
     * @param Datetime $dateTime Created date
     *
     * @return UserList
     */
    public function setCreated(DateTime $dateTime): Ratings
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
}
