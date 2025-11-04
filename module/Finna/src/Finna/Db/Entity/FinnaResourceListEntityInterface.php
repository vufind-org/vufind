<?php

/**
 * Finna resource list entity interface
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Entity;

use DateTime;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Finna resource list entity interface
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
interface FinnaResourceListEntityInterface extends EntityInterface
{
    /**
     * Get the ID of the list.
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get user entity
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;

    /**
     * Set user
     *
     * @param UserEntityInterface $user User entity
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;

    /**
     * Set title.
     *
     * @param string $title Title
     *
     * @return static
     */
    public function setTitle(string $title): static;

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Set description.
     *
     * @param string $description Description
     *
     * @return static
     */
    public function setDescription(string $description = ''): static;

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Get the institution
     *
     * @return string
     */
    public function getInstitution(): string;

    /**
     * Set the institution
     *
     * @param string $institution Institution
     *
     * @return static
     */
    public function setInstitution(string $institution): static;

    /**
     * Get the list configuration identifier
     *
     * @return string
     */
    public function getListConfigIdentifier(): string;

    /**
     * Set the list configuration identifier
     *
     * @param string $listConfigIdentifier List configuration identifier
     *
     * @return static
     */
    public function setListConfigIdentifier(string $listConfigIdentifier): static;

    /**
     * Get the list type
     *
     * @return string
     */
    public function getListType(): string;

    /**
     * Set the list type
     *
     * @param string $listType List type
     *
     * @return static
     */
    public function setListType(string $listType): static;

    /**
     * Get the ordered flag
     *
     * @return ?DateTime
     */
    public function getOrdered(): ?DateTime;

    /**
     * Set the ordered flag
     *
     * @return static
     */
    public function setOrdered(): static;

    /**
     * Get the pickup date
     *
     * @return ?DateTime
     */
    public function getPickupDate(): ?DateTime;

    /**
     * Set the pickup date
     *
     * @param DateTime $pickupDate Pickup date
     *
     * @return static
     */
    public function setPickupDate(DateTime $pickupDate): static;

    /**
     * Get the connection
     *
     * @return string
     */
    public function getConnection(): string;

    /**
     * Set the connection
     *
     * @param string $connection Connection
     *
     * @return static
     */
    public function setConnection(string $connection): static;

    /**
     * Get external id
     *
     * @return ?string
     */
    public function getExternalId(): ?string;

    /**
     * Set external id
     *
     * @param ?string $id External id
     *
     * @return static
     */
    public function setExternalId(?string $id): static;
}
