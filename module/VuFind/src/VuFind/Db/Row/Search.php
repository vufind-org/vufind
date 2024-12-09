<?php

/**
 * Row Definition for search
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Crypt\HMAC;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\UserServiceInterface;

use function is_resource;

/**
 * Row Definition for search
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property int     $user_id
 * @property ?string $session_id
 * @property string  $created
 * @property ?string $title
 * @property int     $saved
 * @property string  $search_object
 * @property ?int    $checksum
 * @property int     $notification_frequency
 * @property string  $last_notification_sent
 * @property string  $notification_base_url
 */
class Search extends RowGateway implements
    \VuFind\Db\Entity\SearchEntityInterface,
    \VuFind\Db\Table\DbTableAwareInterface,
    DbServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'search', $adapter);
    }

    /**
     * Support method to make sure that the search_object field is formatted as a
     * string, since PostgreSQL sometimes represents it as a resource.
     *
     * @return void
     */
    protected function normalizeSearchObject()
    {
        // Note that if we have a resource, we need to grab the contents before
        // saving -- this is necessary for PostgreSQL compatibility although MySQL
        // returns a plain string
        if (is_resource($this->search_object)) {
            $this->search_object = stream_get_contents($this->search_object);
        }
    }

    /**
     * Get the search object from the row.
     *
     * @return ?\VuFind\Search\Minified
     */
    public function getSearchObject(): ?\VuFind\Search\Minified
    {
        // We need to make sure the search object is a string before unserializing:
        $this->normalizeSearchObject();
        return $this->search_object ? unserialize($this->search_object) : null;
    }

    /**
     * Get the search object from the row, and throw an exception if it is missing.
     *
     * @return \VuFind\Search\Minified
     * @throws \Exception
     *
     * @deprecated
     */
    public function getSearchObjectOrThrowException(): \VuFind\Search\Minified
    {
        if (!($result = $this->getSearchObject())) {
            throw new \Exception('Problem decoding saved search');
        }
        return $result;
    }

    /**
     * Save
     *
     * @return int
     */
    public function save()
    {
        // We can't save if the search object is a resource; make sure it's a
        // string first:
        $this->normalizeSearchObject();
        return parent::save();
    }

    /**
     * Set last executed time for scheduled alert.
     *
     * @param string $time Time.
     *
     * @return mixed
     *
     * @deprecated
     */
    public function setLastExecuted($time)
    {
        $this->last_notification_sent = $time;
        return $this->save();
    }

    /**
     * Set schedule for scheduled alert.
     *
     * @param int    $schedule Schedule.
     * @param string $url      Site base URL
     *
     * @return mixed
     *
     * @deprecated
     */
    public function setSchedule($schedule, $url = null)
    {
        $this->notification_frequency = $schedule;
        if ($url) {
            $this->notification_base_url = $url;
        }
        return $this->save();
    }

    /**
     * Utility function for generating a token for unsubscribing a
     * saved search.
     *
     * @param HMAC                $hmac HMAC hash generator
     * @param UserEntityInterface $user User object
     *
     * @return string token
     *
     * @deprecated Use \VuFind\Crypt\SecretCalculator::getSearchUnsubscribeSecret()
     */
    public function getUnsubscribeSecret(HMAC $hmac, $user)
    {
        $data = [
            'id' => $this->id,
            'user_id' => $user->getId(),
            'created' => $user->getCreated()->format('Y-m-d H:i:s'),
        ];
        return $hmac->generate(array_keys($data), $data);
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user_id
            ? $this->getDbServiceManager()->get(UserServiceInterface::class)->getUserById($this->user_id)
            : null;
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return SearchEntityInterface
     */
    public function setUser(?UserEntityInterface $user): SearchEntityInterface
    {
        $this->user_id = $user?->getId();
        return $this;
    }

    /**
     * Get session identifier.
     *
     * @return ?string
     */
    public function getSessionId(): ?string
    {
        return $this->session_id ?? null;
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
        $this->session_id = $sessionId;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
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
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get title.
     *
     * @return ?string
     */
    public function getTitle(): ?string
    {
        return $this->title ?? null;
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
        return (bool)($this->saved ?? 0);
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
        $this->saved = $saved ? 1 : 0;
        return $this;
    }

    /**
     * Set search object.
     *
     * @param ?\VuFind\Search\Minified $searchObject Search object
     *
     * @return SearchEntityInterface
     */
    public function setSearchObject(?\VuFind\Search\Minified $searchObject): SearchEntityInterface
    {
        $this->search_object = $searchObject ? serialize($searchObject) : null;
        return $this;
    }

    /**
     * Get checksum.
     *
     * @return ?int
     */
    public function getChecksum(): ?int
    {
        return $this->checksum ?? null;
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
        return $this->notification_frequency ?? 0;
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
        $this->notification_frequency = $notificationFrequency;
        return $this;
    }

    /**
     * When was the last notification sent?
     *
     * @return DateTime
     */
    public function getLastNotificationSent(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->last_notification_sent);
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
        $this->last_notification_sent = $lastNotificationSent->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get notification base URL.
     *
     * @return string
     */
    public function getNotificationBaseUrl(): string
    {
        return $this->notification_base_url ?? '';
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
        $this->notification_base_url = $notificationBaseUrl;
        return $this;
    }
}
