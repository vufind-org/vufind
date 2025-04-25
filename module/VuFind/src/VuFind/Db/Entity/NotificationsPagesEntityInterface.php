<?php

/**
 * Interface for representing a notifications pages record.
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
 * Interface for representing a notifications pages record.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface NotificationsPagesEntityInterface extends EntityInterface
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
     * @return NotificationsPagesEntityInterface
     */
    public function setId(int $id): NotificationsPagesEntityInterface;

    /**
     * Getter for id
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Setter for page_id
     *
     * @param int|null $pageId Page ID
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setPageId(?int $pageId): NotificationsPagesEntityInterface;

    /**
     * Getter for page_id
     *
     * @return int|null
     */
    public function getPageId(): ?int;

    /**
     * Setter for visibility
     *
     * @param bool|null $visibility Visibility
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setVisibility(?bool $visibility): NotificationsPagesEntityInterface;

    /**
     * Getter for visibility
     *
     * @return bool|null
     */
    public function getVisibility(): ?bool;

    /**
     * Setter for is_external_url
     *
     * @param bool|null $isExternalUrl Is External URL
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setIsExternalUrl(?bool $isExternalUrl): NotificationsPagesEntityInterface;

    /**
     * Getter for is_external_url
     *
     * @return bool|null
     */
    public function getIsExternalUrl(): ?bool;

    /**
     * Setter for priority
     *
     * @param int|null $priority Priority
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setPriority(?int $priority): NotificationsPagesEntityInterface;

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
     * @return NotificationsPagesEntityInterface
     */
    public function setAuthorId(?int $authorId): NotificationsPagesEntityInterface;

    /**
     * Getter for author_id
     *
     * @return int|null
     */
    public function getAuthorId(): ?int;

    /**
     * Setter for headline
     *
     * @param string|null $headline Headline
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setHeadline(?string $headline): NotificationsPagesEntityInterface;

    /**
     * Getter for headline
     *
     * @return string|null
     */
    public function getHeadline(): ?string;

    /**
     * Setter for nav_title
     *
     * @param string|null $navTitle Navigation Title
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setNavTitle(?string $navTitle): NotificationsPagesEntityInterface;

    /**
     * Getter for nav_title
     *
     * @return string|null
     */
    public function getNavTitle(): ?string;

    /**
     * Setter for content
     *
     * @param string|null $content Content
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setContent(?string $content): NotificationsPagesEntityInterface;

    /**
     * Getter for content
     *
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * Setter for external_url
     *
     * @param string|null $externalUrl External URL
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setExternalUrl(?string $externalUrl): NotificationsPagesEntityInterface;

    /**
     * Getter for external_url
     *
     * @return string|null
     */
    public function getExternalUrl(): ?string;

    /**
     * Setter for change_date
     *
     * @param \DateTime|null $changeDate Change Date
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setChangeDate(?\DateTime $changeDate): NotificationsPagesEntityInterface;

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
     * @return NotificationsPagesEntityInterface
     */
    public function setCreateDate(?\DateTime $createDate): NotificationsPagesEntityInterface;

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
     * @return NotificationsPagesEntityInterface
     */
    public function setLanguage(?string $language): NotificationsPagesEntityInterface;

    /**
     * Getter for language
     *
     * @return string|null
     */
    public function getLanguage(): ?string;
}
