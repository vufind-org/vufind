<?php

/**
 * Row Definition for comments
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Row Definition for comments
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property ?int    $user_id
 * @property int     $resource_id
 * @property string  $comment
 * @property string  $created
 */
class Comments extends RowGateway implements CommentsEntityInterface, DbServiceAwareInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'comments', $adapter);
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
     * Comment setter
     *
     * @param string $comment Comment
     *
     * @return static
     */
    public function setComment(string $comment): static
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
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * User setter.
     *
     * @param ?UserEntityInterface $user User that created comment
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static
    {
        $this->user_id = $user ? $user->getId() : null;
        return $this;
    }

    /**
     * User getter
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user_id
            ? $this->getDbServiceManager()->get(UserServiceInterface::class)->getUserById($this->user_id)
            : null;
    }

    /**
     * Resource setter.
     *
     * @param ResourceEntityInterface $resource Resource id.
     *
     * @return static
     */
    public function setResource(ResourceEntityInterface $resource): static
    {
        $this->resource_id = $resource->getId();
        return $this;
    }
}
