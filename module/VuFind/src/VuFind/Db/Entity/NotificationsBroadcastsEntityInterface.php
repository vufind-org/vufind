<?php

/**
 * Interface for representing a notifications broadcasts record.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Interface for representing a notifications broadcasts record.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface NotificationsBroadcastsEntityInterface extends EntityInterface
{
    /**
     * Primary key id getter
     *
     * @return int|null
     */
    public function getPrimaryKeyId(): ?int;

    /**
     * Setter for id
     *
     * @param int $id ID
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setId(int $id): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for id
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Setter for broadcast_id
     *
     * @param int|null $broadcastId Broadcast ID
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setBroadcastId(?int $broadcastId): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for broadcast_id
     *
     * @return int|null
     */
    public function getBroadcastId(): ?int;

    /**
     * Setter for visibility
     *
     * @param bool|null $visibility Visibility
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setVisibility(?bool $visibility): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for visibility
     *
     * @return bool|null
     */
    public function getVisibility(): ?bool;

    /**
     * Setter for visibility_global
     *
     * @param bool|null $visibilityGlobal Visibility Global
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setVisibilityGlobal(?bool $visibilityGlobal): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for visibility_global
     *
     * @return bool|null
     */
    public function getVisibilityGlobal(): ?bool;

    /**
     * Setter for priority
     *
     * @param int|null $priority Priority
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setPriority(?int $priority): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for priority
     *
     * @return int|null
     */
    public function getPriority(): ?int;

    /**
     * Setter for author_id
     *
     * @param int|null $authorId Author ID
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setAuthorId(?int $authorId): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for author_id
     *
     * @return int|null
     */
    public function getAuthorId(): ?int;

    /**
     * Setter for content
     *
     * @param string|null $content Content
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setContent(?string $content): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for content
     *
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * Setter for color
     *
     * @param string|null $color Color
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setColor(?string $color): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for color
     *
     * @return string|null
     */
    public function getColor(): ?string;

    /**
     * Setter for startdate
     *
     * @param \DateTime|null $startDate Start Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setStartDate(?\DateTime $startDate): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for startdate
     *
     * @return \DateTime|null
     */
    public function getStartDate(): ?\DateTime;

    /**
     * Setter for enddate
     *
     * @param \DateTime|null $endDate End Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setEndDate(?\DateTime $endDate): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for enddate
     *
     * @return \DateTime|null
     */
    public function getEndDate(): ?\DateTime;

    /**
     * Setter for change_date
     *
     * @param \DateTime|null $changeDate Change Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setChangeDate(?\DateTime $changeDate): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for change_date
     *
     * @return \DateTime|null
     */
    public function getChangeDate(): ?\DateTime;

    /**
     * Setter for create_date
     *
     * @param \DateTime|null $createDate Create Date
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setCreateDate(?\DateTime $createDate): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for create_date
     *
     * @return \DateTime|null
     */
    public function getCreateDate(): ?\DateTime;

    /**
     * Setter for language
     *
     * @param string|null $language Language
     *
     * @return NotificationsBroadcastsEntityInterface
     */
    public function setLanguage(?string $language): NotificationsBroadcastsEntityInterface;

    /**
     * Getter for language
     *
     * @return string|null
     */
    public function getLanguage(): ?string;
}
