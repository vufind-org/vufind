<?php

/**
 * Entity model interface for user_list table
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
 * Entity model interface for user_list table
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface UserListEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Set title.
     *
     * @param string $title Title
     *
     * @return UserListEntityInterface
     */
    public function setTitle(string $title): UserListEntityInterface;

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Set description.
     *
     * @param ?string $description Description
     *
     * @return UserListEntityInterface
     */
    public function setDescription(?string $description): UserListEntityInterface;

    /**
     * Get description.
     *
     * @return ?string
     */
    public function getDescription(): ?string;

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserListEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserListEntityInterface;

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Set whether the list is public.
     *
     * @param bool $public Is the list public?
     *
     * @return UserListEntityInterface
     */
    public function setPublic(bool $public): UserListEntityInterface;

    /**
     * Is this a public list?
     *
     * @return bool
     */
    public function isPublic(): bool;

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User owning the list.
     *
     * @return UserListEntityInterface
     */
    public function setUser(?UserEntityInterface $user): UserListEntityInterface;

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;
}
