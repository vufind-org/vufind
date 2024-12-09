<?php

/**
 * Row Definition for user_resource
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
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Row Definition for user_resource
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property int    $user_id
 * @property int    $resource_id
 * @property int    $list_id
 * @property string $notes
 * @property string $saved
 */
class UserResource extends RowGateway implements
    \VuFind\Db\Entity\UserResourceEntityInterface,
    \VuFind\Db\Table\DbTableAwareInterface,
    DbServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'user_resource', $adapter);
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
        return $this->getDbServiceManager()->get(UserServiceInterface::class)
            ->getUserById($this->user_id);
    }

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User
     *
     * @return UserResourceEntityInterface
     */
    public function setUser(UserEntityInterface $user): UserResourceEntityInterface
    {
        $this->user_id = $user->getId();
        return $this;
    }

    /**
     * Get resource.
     *
     * @return ResourceEntityInterface
     */
    public function getResource(): ResourceEntityInterface
    {
        return $this->getDbServiceManager()->get(ResourceServiceInterface::class)
            ->getResourceById($this->resource_id);
    }

    /**
     * Set resource.
     *
     * @param ResourceEntityInterface $resource Resource
     *
     * @return UserResourceEntityInterface
     */
    public function setResource(ResourceEntityInterface $resource): UserResourceEntityInterface
    {
        $this->resource_id = $resource->getId();
        return $this;
    }

    /**
     * Get user list.
     *
     * @return ?UserListEntityInterface
     */
    public function getUserList(): ?UserListEntityInterface
    {
        return $this->list_id
        ? $this->getDbServiceManager()->get(UserListServiceInterface::class)->getUserListById($this->list_id)
        : null;
    }

    /**
     * Set user list.
     *
     * @param ?UserListEntityInterface $list User list
     *
     * @return UserResourceEntityInterface
     */
    public function setUserList(?UserListEntityInterface $list): UserResourceEntityInterface
    {
        $this->list_id = $list?->getId();
        return $this;
    }

    /**
     * Get notes.
     *
     * @return ?string
     */
    public function getNotes(): ?string
    {
        return $this->notes ?? null;
    }

    /**
     * Set notes.
     *
     * @param ?string $notes Notes associated with the resource
     *
     * @return UserResourceEntityInterface
     */
    public function setNotes(?string $notes): UserResourceEntityInterface
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
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->saved);
    }

    /**
     * Set saved date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserResourceEntityInterface
     */
    public function setSaved(DateTime $dateTime): UserResourceEntityInterface
    {
        $this->saved = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }
}
