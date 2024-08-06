<?php

/**
 * Entity model interface for login_token table
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
 * Entity model interface for login_token table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface LoginTokenEntityInterface extends EntityInterface
{
    /**
     * Getter for ID.
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Setter for User.
     *
     * @param UserEntityInterface $user User to set
     *
     * @return LoginTokenEntityInterface
     */
    public function setUser(UserEntityInterface $user): LoginTokenEntityInterface;

    /**
     * User getter (only null if entity has not been populated yet).
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface;

    /**
     * Set token string.
     *
     * @param string $token Token
     *
     * @return LoginTokenEntityInterface
     */
    public function setToken(string $token): LoginTokenEntityInterface;

    /**
     * Get token string.
     *
     * @return string
     */
    public function getToken(): string;

    /**
     * Set series string.
     *
     * @param string $series Series
     *
     * @return LoginTokenEntityInterface
     */
    public function setSeries(string $series): LoginTokenEntityInterface;

    /**
     * Get series string.
     *
     * @return string
     */
    public function getSeries(): string;

    /**
     * Set last login date/time.
     *
     * @param DateTime $dateTime Last login date/time
     *
     * @return LoginTokenEntityInterface
     */
    public function setLastLogin(DateTime $dateTime): LoginTokenEntityInterface;

    /**
     * Get last login date/time.
     *
     * @return DateTime
     */
    public function getLastLogin(): DateTime;

    /**
     * Set browser details (or null for none).
     *
     * @param ?string $browser Browser details (or null for none)
     *
     * @return LoginTokenEntityInterface
     */
    public function setBrowser(?string $browser): LoginTokenEntityInterface;

    /**
     * Get browser details (or null for none).
     *
     * @return ?string
     */
    public function getBrowser(): ?string;

    /**
     * Set platform details (or null for none).
     *
     * @param ?string $platform Platform details (or null for none)
     *
     * @return LoginTokenEntityInterface
     */
    public function setPlatform(?string $platform): LoginTokenEntityInterface;

    /**
     * Get platform details (or null for none).
     *
     * @return ?string
     */
    public function getPlatform(): ?string;

    /**
     * Set expiration timestamp.
     *
     * @param int $expires Expiration timestamp
     *
     * @return LoginTokenEntityInterface
     */
    public function setExpires(int $expires): LoginTokenEntityInterface;

    /**
     * Get expiration timestamp.
     *
     * @return int
     */
    public function getExpires(): int;

    /**
     * Set last session ID (or null for none).
     *
     * @param ?string $sid Last session ID (or null for none)
     *
     * @return LoginTokenEntityInterface
     */
    public function setLastSessionId(?string $sid): LoginTokenEntityInterface;

    /**
     * Get last session ID (or null for none).
     *
     * @return ?string
     */
    public function getLastSessionId(): ?string;
}
