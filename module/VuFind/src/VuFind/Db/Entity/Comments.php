<?php

/**
 * Entity model for comments table
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
 * Comments
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="comments")
 * @ORM\Entity
 */
class Comments implements EntityInterface
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
     * Comment.
     *
     * @var string
     *
     * @ORM\Column(name="comment", type="text", length=65535, nullable=false)
     */
    protected $comment;

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
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Comment setter
     *
     * @param string $comment Comment
     *
     * @return Comments
     */
    public function setComment(string $comment): Comments
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Comment getter
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Created setter.
     *
     * @param Datetime $dateTime Created date
     *
     * @return Comments
     */
    public function setCreated(DateTime $dateTime): Comments
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
     * User setter.
     *
     * @param ?User $user User that created comment
     *
     * @return Comments
     */
    public function setUser(?User $user): Comments
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
     * Resource setter.
     *
     * @param Resource $resource Resource id.
     *
     * @return Comments
     */
    public function setResource(?Resource $resource): Comments
    {
        $this->resource = $resource;
        return $this;
    }
}
