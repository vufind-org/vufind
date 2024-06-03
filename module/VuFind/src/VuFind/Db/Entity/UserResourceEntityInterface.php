<?php

/**
 * Entity model interface for user_resource table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for user_resource table
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface UserResourceEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User
     *
     * @return UserResourceEntityInterface
     */
    public function setUser(UserEntityInterface $user): UserResourceEntityInterface;

    /**
     * Get resource.
     *
     * @return ResourceEntityInterface
     */
    public function getResource(): ResourceEntityInterface;

    /**
     * Set resource.
     *
     * @param ResourceEntityInterface $resource Resource
     *
     * @return UserResourceEntityInterface
     */
    public function setResource(ResourceEntityInterface $resource): UserResourceEntityInterface;

    /**
     * Get user list.
     *
     * @return ?UserListEntityInterface
     */
    public function getUserList(): ?UserListEntityInterface;

    /**
     * Set user list.
     *
     * @param ?UserListEntityInterface $list User list
     *
     * @return UserResourceEntityInterface
     */
    public function setUserList(?UserListEntityInterface $list): UserResourceEntityInterface;

    /**
     * Get notes.
     *
     * @return ?string
     */
    public function getNotes(): ?string;

    /**
     * Set notes.
     *
     * @param ?string $notes Notes associated with the resource
     *
     * @return UserResourceEntityInterface
     */
    public function setNotes(?string $notes): UserResourceEntityInterface;

    /**
     * Get saved date.
     *
     * @return DateTime
     */
    public function getSaved(): DateTime;

    /**
     * Set saved date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserResourceEntityInterface
     */
    public function setSaved(DateTime $dateTime): UserResourceEntityInterface;
}
