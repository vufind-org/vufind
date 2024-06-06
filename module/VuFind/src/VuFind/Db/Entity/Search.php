<?php

/**
 * Entity model for search table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
use Doctrine\ORM\Mapping as ORM;

/**
 * Search
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="search"),
 * @ORM\Index(name="notification_base_url",  columns={"notification_base_url"}),
 * @ORM\Index(name="notification_frequency", columns={"notification_frequency"}),
 * @ORM\Index(name="session_id",             columns={"session_id"}),
 * @ORM\Index(name="user_id",                columns={"user_id"})})
 * @ORM\Entity
 */
class Search implements SearchEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="bigint",
     *          nullable=false,
     *          options={"unsigned"=true}
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * User ID.
     *
     * @var int
     *
     * @ORM\Column(name="user_id", type="integer", nullable=false)
     */
    protected $userId = 0;

    /**
     * Session ID.
     *
     * @var ?string
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=true)
     */
    protected $sessionId;

    /**
     * Created date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $created = '2000-01-01 00:00:00';

    /**
     * Title.
     *
     * @var ?string
     *
     * @ORM\Column(name="title", type="string", length=20, nullable=true)
     */
    protected $title;

    /**
     * Saved.
     *
     * @var bool
     *
     * @ORM\Column(name="saved", type="boolean", nullable=false)
     */
    protected $saved = '0';

    /**
     * Search object.
     *
     * @var string
     *
     * @ORM\Column(name="search_object", type="blob", length=65535, nullable=true)
     */
    protected $searchObject;

    /**
     * Checksum
     *
     * @var ?int
     *
     * @ORM\Column(name="checksum", type="integer", nullable=true)
     */
    protected $checksum;

    /**
     * Notification frequency.
     *
     * @var int
     *
     * @ORM\Column(name="notification_frequency", type="integer", nullable=false)
     */
    protected $notificationFrequency = '0';

    /**
     * Date last notification is sent.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="last_notification_sent",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $lastNotificationSent = '2000-01-01 00:00:00';

    /**
     * Notification base URL.
     *
     * @var string
     *
     * @ORM\Column(name="notification_base_url",
     *          type="string",
     *          length=255, nullable=false
     * )
     */
    protected $notificationBaseUrl = '';

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get user.
     *
     * @return UserEntityInterface
     */
    public function getUser(): UserEntityInterface
    {
        throw new \Exception('TODO: not implemented yet');
    }

    /**
     * Set user.
     *
     * @param UserEntityInterface $user User
     *
     * @return SearchEntityInterface
     */
    public function setUser(UserEntityInterface $user): SearchEntityInterface
    {
        $this->userId = $user->getId();
        return $this;
    }

    /**
     * Get session identifier.
     *
     * @return ?string
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Set session identifier.
     *
     * @param ?string $sessionId Session id
     *
     * @return SearchEntityInterface
     */
    public function setSessionId(?string $sessionId): SearchEntityInterface
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return SearchEntityInterface
     */
    public function setCreated(DateTime $dateTime): SearchEntityInterface
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get title.
     *
     * @return ?string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set title.
     *
     * @param ?string $title Title
     *
     * @return SearchEntityInterface
     */
    public function setTitle(?string $title): SearchEntityInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get saved.
     *
     * @return bool
     */
    public function getSaved(): bool
    {
        return (bool)$this->saved;
    }

    /**
     * Set saved.
     *
     * @param bool $saved Saved
     *
     * @return SearchEntityInterface
     */
    public function setSaved(bool $saved): SearchEntityInterface
    {
        $this->saved = $saved ? '1' : '0';
        return $this;
    }

    /**
     * Get the search object from the row.
     *
     * @return \VuFind\Search\Minified
     */
    public function getSearchObject(): \VuFind\Search\Minified
    {
        return unserialize($this->searchObject);
    }

    /**
     * Set search object.
     *
     * @param \VuFind\Search\Minified $searchObject Search object
     *
     * @return SearchEntityInterface
     */
    public function setSearchObject(\VuFind\Search\Minified $searchObject): SearchEntityInterface
    {
        $this->searchObject = serialize($searchObject);
        return $this;
    }

    /**
     * Get checksum.
     *
     * @return ?int
     */
    public function getChecksum(): ?int
    {
        return $this->checksum;
    }

    /**
     * Set checksum.
     *
     * @param ?int $checksum Checksum
     *
     * @return SearchEntityInterface
     */
    public function setChecksum(?int $checksum): SearchEntityInterface
    {
        $this->checksum = $checksum;
        return $this;
    }

    /**
     * Get notification frequency.
     *
     * @return int
     */
    public function getNotificationFrequency(): int
    {
        return $this->notificationFrequency;
    }

    /**
     * Set notification frequency.
     *
     * @param int $notificationFrequency Notification frequency
     *
     * @return SearchEntityInterface
     */
    public function setNotificationFrequency(int $notificationFrequency): SearchEntityInterface
    {
        $this->notificationFrequency = $notificationFrequency;
        return $this;
    }

    /**
     * When was the last notification sent?
     *
     * @return DateTime
     */
    public function getLastNotificationSent(): DateTime
    {
        return $this->lastNotificationSent;
    }

    /**
     * Set when last notification was sent.
     *
     * @param DateTime $lastNotificationSent Time when last notification was sent
     *
     * @return SearchEntityInterface
     */
    public function setLastNotificationSent(Datetime $lastNotificationSent): SearchEntityInterface
    {
        $this->lastNotificationSent = $lastNotificationSent;
        return $this;
    }

    /**
     * Get notification base URL.
     *
     * @return string
     */
    public function getNotificationBaseUrl(): string
    {
        return $this->notificationBaseUrl;
    }

    /**
     * Set notification base URL.
     *
     * @param string $notificationBaseUrl Notification base URL
     *
     * @return SearchEntityInterface
     */
    public function setNotificationBaseUrl(string $notificationBaseUrl): SearchEntityInterface
    {
        $this->notificationBaseUrl = $notificationBaseUrl;
        return $this;
    }
}
