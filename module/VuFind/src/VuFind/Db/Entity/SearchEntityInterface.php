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
use \VuFind\Search\Minified;

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
     * @return int
     */
    public function getUserId(): int;

    /**
     * Set user.
     * 
     * @param int $user_id User id
     * 
     * @return SearchEntityInterface
     */
    public function setUserId(int $user_id): SearchEntityInterface;

    /**
     * Get session.
     * 
     * @return ?string
     */
    public function getSessionId(): ?string;

    /**
     * Set session.
     * 
     * @param ?string $session_id Session id
     * 
     * @return SearchEntityInterface
     */
    public function setSessionId(?string $session_id): SearchEntityInterface;

    /**
     * Get created.
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
     * @return int
     */
    public function getSaved(): int;

    /**
     * Set saved.
     * 
     * @param int $saved Saved
     * 
     * @return SearchEntityInterface
     */
    public function setSaved(int $saved): SearchEntityInterface;

    /**
     * Get search object.
     * 
     * @return \VuFind\Search\Minified
     */
    public function getSearchObject(): \VuFind\Search\Minified;

    /**
     * Set search object.
     * 
     * @param \VuFind\Search\Minified $search_object Search object
     * 
     * @return SearchEntityInterface
     */
    public function setSearchObject(\VuFind\Search\Minified $search_object): SearchEntityInterface;

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
     * @param int $notification_frequency Notification frequency
     * 
     * @return SearchEntityInterface
     */
    public function setNotificationFrequency(int $notification_frequency): SearchEntityInterface;

    /**
     * When was the last notification sent?
     * 
     * @return DateTime
     */
    public function getLastNotificationSent(): DateTime;

    /**
     * Set when last notification was sent.
     * 
     * @param DateTime $last_notification_sent Time when last notification was sent
     * 
     * @return SearchEntityInterface
     */
    public function setLastNotificationSent(Datetime $last_notification_sent): SearchEntityInterface;

    /**
     * Get notification base URL.
     *
     * @return string
     */
    public function getNotificationBaseUrl(): string;

    /**
     * Set notification base URL.
     *
     * @param string $notification_base_url Notification base URL
     *
     * @return SearchEntityInterface
     */
    public function setNotificationBaseUrl(string $notification_base_url): SearchEntityInterface;
}
