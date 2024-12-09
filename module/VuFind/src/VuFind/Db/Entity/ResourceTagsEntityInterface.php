<?php

/**
 * Entity model interface for resource_tags table
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for resource_tags table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ResourceTagsEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get resource.
     *
     * @return ?ResourceEntityInterface
     */
    public function getResource(): ?ResourceEntityInterface;

    /**
     * Set resource.
     *
     * @param ?ResourceEntityInterface $resource Resource
     *
     * @return ResourceTagsEntityInterface
     */
    public function setResource(?ResourceEntityInterface $resource): ResourceTagsEntityInterface;

    /**
     * Get tag.
     *
     * @return TagsEntityInterface
     */
    public function getTag(): TagsEntityInterface;

    /**
     * Set tag.
     *
     * @param TagsEntityInterface $tag Tag
     *
     * @return ResourceTagsEntityInterface
     */
    public function setTag(TagsEntityInterface $tag): ResourceTagsEntityInterface;

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
     * @return ResourceTagsEntityInterface
     */
    public function setUserList(?UserListEntityInterface $list): ResourceTagsEntityInterface;

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return ResourceTagsEntityInterface
     */
    public function setUser(?UserEntityInterface $user): ResourceTagsEntityInterface;

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getPosted(): DateTime;

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return ResourceTagsEntityInterface
     */
    public function setPosted(DateTime $dateTime): ResourceTagsEntityInterface;
}
