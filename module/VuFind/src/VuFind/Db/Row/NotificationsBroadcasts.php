<?php

/**
 * Row Definition for notifications broadcasts
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
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
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\NotificationsBroadcastsEntityInterface;

/**
 * Row Definition for notifications broadcasts
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int id
 * @property int broadcast_id
 * @property int visibility
 * @property int visibility_global
 * @property int priority
 * @property int author_id
 * @property string content
 * @property string color
 * @property string startdate
 * @property string enddate
 * @property string change_date
 * @property string create_date
 * @property string language
 */
class NotificationsBroadcasts extends RowGateway implements NotificationsBroadcastsEntityInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'notifications_broadcasts', $adapter);
    }

    public function getPrimaryKeyId(): ?int
    {
        if (isset($this->primaryKeyData['id'])) {
            return $this->primaryKeyData['id'];
        }
        return null;
    }


    /**
     * Setter for id
     *
     * @param int $id ID
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setId(int $id): NotificationsBroadcastsEntityInterface
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Getter for id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Setter for broadcast_id
     *
     * @param int|null $broadcastId Broadcast ID
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setBroadcastId(?int $broadcastId): NotificationsBroadcastsEntityInterface
    {
        $this->broadcast_id = $broadcastId;
        return $this;
    }

    /**
     * Getter for broadcast_id
     *
     * @return int|null
     */
    public function getBroadcastId(): ?int
    {
        return $this->broadcast_id;
    }

    /**
     * Setter for visibility
     *
     * @param bool|null $visibility Visibility
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setVisibility(?bool $visibility): NotificationsBroadcastsEntityInterface
    {
        $this->visibility = $visibility;
        return $this;
    }

    /**
     * Getter for visibility
     *
     * @return bool|null
     */
    public function getVisibility(): ?bool
    {
        return $this->visibility;
    }

    /**
     * Setter for visibility_global
     *
     * @param bool|null $visibilityGlobal Visibility Global
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setVisibilityGlobal(?bool $visibilityGlobal): NotificationsBroadcastsEntityInterface
    {
        $this->visibility_global = $visibilityGlobal;
        return $this;
    }

    /**
     * Getter for visibility_global
     *
     * @return bool|null
     */
    public function getVisibilityGlobal(): ?bool
    {
        return $this->visibility_global;
    }

    /**
     * Setter for priority
     *
     * @param int|null $priority Priority
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setPriority(?int $priority): NotificationsBroadcastsEntityInterface
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Getter for priority
     *
     * @return int|null
     */
    public function getPriority(): ?int
    {
        return $this->priority;
    }

    /**
     * Setter for author_id
     *
     * @param int|null $authorId Author ID
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setAuthorId(?int $authorId): NotificationsBroadcastsEntityInterface
    {
        $this->author_id = $authorId;
        return $this;
    }

    /**
     * Getter for author_id
     *
     * @return int|null
     */
    public function getAuthorId(): ?int
    {
        return $this->author_id;
    }

    /**
     * Setter for content
     *
     * @param string|null $content Content
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setContent(?string $content): NotificationsBroadcastsEntityInterface
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Getter for content
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content ?? null;
    }

    /**
     * Setter for color
     *
     * @param string|null $color Color
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setColor(?string $color): NotificationsBroadcastsEntityInterface
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Getter for color
     *
     * @return string|null
     */
    public function getColor(): ?string
    {
        return $this->color ?? null;
    }

    /**
     * Setter for startdate
     *
     * @param DateTime|null $startDate Start Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setStartDate(?DateTime $startDate): NotificationsBroadcastsEntityInterface
    {
        $this->startdate = $startDate->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Getter for startdate
     *
     * @return DateTime|null
     */
    public function getStartDate(): ?DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->startdate);
    }

    /**
     * Setter for enddate
     *
     * @param DateTime|null $endDate End Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setEndDate(?DateTime $endDate): NotificationsBroadcastsEntityInterface
    {
        $this->enddate = $endDate->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Getter for enddate
     *
     * @return DateTime|null
     */
    public function getEndDate(): ?DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->enddate);
    }

    /**
     * Setter for change_date
     *
     * @param DateTime|null $changeDate Change Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setChangeDate(?DateTime $changeDate): NotificationsBroadcastsEntityInterface
    {
        $this->change_date = $changeDate->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Getter for change_date
     *
     * @return DateTime|null
     */
    public function getChangeDate(): ?DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->change_date);
    }

    /**
     * Setter for create_date
     *
     * @param DateTime|null $createDate Create Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setCreateDate(?DateTime $createDate): NotificationsBroadcastsEntityInterface
    {
        $this->create_date = $createDate->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Getter for create_date
     *
     * @return DateTime|null
     */
    public function getCreateDate(): ?DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->create_date);
    }

    /**
     * Setter for language
     *
     * @param string|null $language Language
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setLanguage(?string $language): NotificationsBroadcastsEntityInterface
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Getter for language
     *
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->language ?? null;
    }
}
