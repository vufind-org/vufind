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
     * @return SearchEntityInterface
     */
    public function setUser(?UserEntityInterface $user): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setSessionId(?string $sessionId): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setCreated(DateTime $dateTime): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setTitle(?string $title): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setSaved(bool $saved): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setSearchObject(?\VuFind\Search\Minified $searchObject): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setChecksum(?int $checksum): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setNotificationFrequency(int $notificationFrequency): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setLastNotificationSent(Datetime $lastNotificationSent): SearchEntityInterface;

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
     * @return SearchEntityInterface
     */
    public function setNotificationBaseUrl(string $notificationBaseUrl): SearchEntityInterface;
}
