<?php

/**
 * Entity model interface for api_key table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for api_key table
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ApiKeyEntityInterface extends EntityInterface
{
    /**
     * Set API key identifier.
     *
     * @param string $id API Key Identifier
     *
     * @return static
     */
    public function setId(string $id): static;

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?string
     */
    public function getId(): ?string;

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Set title
     *
     * @param string $title Title
     *
     * @return string
     */
    public function setTitle(string $title): static;

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User owning token
     *
     * @return static
     */
    public function setUser(UserEntityInterface $user): static;

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface;

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
     * @return static
     */
    public function setCreated(DateTime $dateTime): static;

    /**
     * Get token.
     *
     * @return string
     */
    public function getToken(): string;

    /**
     * Set token.
     *
     * @param string $token Token
     *
     * @return static
     */
    public function setToken(string $token): static;

    /**
     * Is the API key revoked?
     *
     * @return bool
     */
    public function isRevoked(): bool;

    /**
     * Set revoked status.
     *
     * @param bool $revoked Revoked
     *
     * @return static
     */
    public function setRevoked(bool $revoked): static;

    /**
     * Get last used date.
     *
     * @return DateTime
     */
    public function getLastUsed(): DateTime;

    /**
     * Set last used date.
     *
     * @param DateTime $dateTime Last used date
     *
     * @return static
     */
    public function setLastUsed(DateTime $dateTime): static;
}
