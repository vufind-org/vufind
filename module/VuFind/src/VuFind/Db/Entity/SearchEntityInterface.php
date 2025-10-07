<?php

/**
 * Entity model interface for search table
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
 * Entity model interface for search table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface SearchEntityInterface extends EntityInterface
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
     * @return static
     */
    public function setUser(?UserEntityInterface $user): static;

    /**
     * Get session identifier.
     *
     * @return ?string
     */
    public function getSessionId(): ?string;

    /**
     * Set session identifier.
     *
     * @param ?string $sessionId Session id
     *
     * @return static
     */
    public function setSessionId(?string $sessionId): static;

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
     * Get title.
     *
     * @return ?string
     */
    public function getTitle(): ?string;

    /**
     * Set title.
     *
     * @param ?string $title Title
     *
     * @return static
     */
    public function setTitle(?string $title): static;

    /**
     * Get saved.
     *
     * @return bool
     */
    public function getSaved(): bool;

    /**
     * Set saved.
     *
     * @param bool $saved Saved
     *
     * @return static
     */
    public function setSaved(bool $saved): static;

    /**
     * Get the search object from the row.
     *
     * @return ?\VuFind\Search\Minified
     */
    public function getSearchObject(): ?\VuFind\Search\Minified;

    /**
     * Set search object.
     *
     * @param ?\VuFind\Search\Minified $searchObject Search object
     *
     * @return static
     */
    public function setSearchObject(?\VuFind\Search\Minified $searchObject): static;

    /**
     * Get checksum.
     *
     * @return ?int
     */
    public function getChecksum(): ?int;

    /**
     * Set checksum.
     *
     * @param ?int $checksum Checksum
     *
     * @return static
     */
    public function setChecksum(?int $checksum): static;

    /**
     * Get notification frequency.
     *
     * @return int
     */
    public function getNotificationFrequency(): int;

    /**
     * Set notification frequency.
     *
     * @param int $notificationFrequency Notification frequency
     *
     * @return static
     */
    public function setNotificationFrequency(int $notificationFrequency): static;

    /**
     * When was the last notification sent?
     *
     * @return DateTime
     */
    public function getLastNotificationSent(): DateTime;

    /**
     * Set when last notification was sent.
     *
     * @param DateTime $lastNotificationSent Time when last notification was sent
     *
     * @return static
     */
    public function setLastNotificationSent(Datetime $lastNotificationSent): static;

    /**
     * Get notification base URL.
     *
     * @return string
     */
    public function getNotificationBaseUrl(): string;

    /**
     * Set notification base URL.
     *
     * @param string $notificationBaseUrl Notification base URL
     *
     * @return static
     */
    public function setNotificationBaseUrl(string $notificationBaseUrl): static;
}
