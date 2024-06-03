<?php

/**
 * Entity model interface for auth_hash table
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
 * Entity model interface for auth_hash table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface AuthHashEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get PHP session id string.
     *
     * @return ?string
     */
    public function getSessionId(): ?string;

    /**
     * Set PHP session id string.
     *
     * @param ?string $sessionId PHP Session id string
     *
     * @return AuthHashEntityInterface
     */
    public function setSessionId(?string $sessionId): AuthHashEntityInterface;

    /**
     * Get hash value.
     *
     * @return string
     */
    public function getHash(): string;

    /**
     * Set hash value.
     *
     * @param string $hash Hash Value
     *
     * @return AuthHashEntityInterface
     */
    public function setHash(string $hash): AuthHashEntityInterface;

    /**
     * Get type of hash.
     *
     * @return ?string
     */
    public function getHashType(): ?string;

    /**
     * Set type of hash.
     *
     * @param ?string $type Hash Type
     *
     * @return AuthHashEntityInterface
     */
    public function setHashType(?string $type): AuthHashEntityInterface;

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
     * @return AuthHashEntityInterface
     */
    public function setData(?string $data): AuthHashEntityInterface;

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
     * @return AuthHashEntityInterface
     */
    public function setCreated(DateTime $dateTime): AuthHashEntityInterface;
}
