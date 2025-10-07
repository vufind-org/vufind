<?php

/**
 * Entity model interface for access tokens.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for access tokens.
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface AccessTokenEntityInterface extends EntityInterface
{
    /**
     * Set access token identifier.
     *
     * @param string $id Access Token Identifier
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
     * Get type of access token.
     *
     * @return ?string
     */
    public function getType(): ?string;

    /**
     * Set type of access token.
     *
     * @param ?string $type Access Token Type
     *
     * @return static
     */
    public function setType(?string $type): static;

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User owning token
     *
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static;

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

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
     * Get data.
     *
     * @return ?string
     */
    public function getData(): ?string;

    /**
     * Set data.
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static;

    /**
     * Is the access token revoked?
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
}
