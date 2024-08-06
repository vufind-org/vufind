<?php

/**
 * Entity model interface for ratings table
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
 * Entity model interface for ratings table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface RatingsEntityInterface extends EntityInterface
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
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return RatingsEntityInterface
     */
    public function setUser(?UserEntityInterface $user): RatingsEntityInterface;

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
     * @return RatingsEntityInterface
     */
    public function setResource(ResourceEntityInterface $resource): RatingsEntityInterface;

    /**
     * Get rating.
     *
     * @return int
     */
    public function getRating(): int;

    /**
     * Set rating.
     *
     * @param int $rating Rating
     *
     * @return RatingsEntityInterface
     */
    public function setRating(int $rating): RatingsEntityInterface;

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return RatingsEntityInterface
     */
    public function setCreated(DateTime $dateTime): RatingsEntityInterface;
}
