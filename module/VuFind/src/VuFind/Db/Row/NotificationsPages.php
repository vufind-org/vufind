<?php

/**
 * Row Definition for notifications pages
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
use VuFind\Db\Entity\NotificationsPagesEntityInterface;

/**
 * Row Definition for notifications pages
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int id
 * @property int page_id
 * @property int visibility
 * @property int is_external_url
 * @property int priority
 * @property int author_id
 * @property string headline
 * @property string nav_title
 * @property string content
 * @property string external_url
 * @property string change_date
 * @property string create_date
 * @property string language
 */
class NotificationsPages extends RowGateway implements NotificationsPagesEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'notifications_pages', $adapter);
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
     * @return NotificationsPagesEntityInterface
     */
    public function setId(int $id): NotificationsPagesEntityInterface
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
     * Setter for page_id
     *
     * @param int|null $pageId Page ID
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setPageId(?int $pageId): NotificationsPagesEntityInterface
    {
        $this->page_id = $pageId;
        return $this;
    }

    /**
     * Getter for page_id
     *
     * @return int|null
     */
    public function getPageId(): ?int
    {
        return $this->page_id;
    }

    /**
     * Setter for visibility
     *
     * @param bool|null $visibility Visibility
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setVisibility(?bool $visibility): NotificationsPagesEntityInterface
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
     * Setter for is_external_url
     *
     * @param bool|null $isExternalUrl Is External URL
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setIsExternalUrl(?bool $isExternalUrl): NotificationsPagesEntityInterface
    {
        $this->is_external_url = $isExternalUrl;
        return $this;
    }

    /**
     * Getter for is_external_url
     *
     * @return bool|null
     */
    public function getIsExternalUrl(): ?bool
    {
        return $this->is_external_url;
    }

    /**
     * Setter for priority
     *
     * @param int|null $priority Priority
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setPriority(?int $priority): NotificationsPagesEntityInterface
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
     * @return NotificationsPagesEntityInterface
     */
    public function setAuthorId(?int $authorId): NotificationsPagesEntityInterface
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
     * Setter for headline
     *
     * @param string|null $headline Headline
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setHeadline(?string $headline): NotificationsPagesEntityInterface
    {
        $this->headline = $headline;
        return $this;
    }

    /**
     * Getter for headline
     *
     * @return string|null
     */
    public function getHeadline(): ?string
    {
        return $this->headline ?? null;
    }

    /**
     * Setter for nav_title
     *
     * @param string|null $navTitle Navigation Title
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setNavTitle(?string $navTitle): NotificationsPagesEntityInterface
    {
        $this->nav_title = $navTitle;
        return $this;
    }

    /**
     * Getter for nav_title
     *
     * @return string|null
     */
    public function getNavTitle(): ?string
    {
        return $this->nav_title ?? null;
    }

    /**
     * Setter for content
     *
     * @param string|null $content Content
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setContent(?string $content): NotificationsPagesEntityInterface
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
     * Setter for external_url
     *
     * @param string|null $externalUrl External URL
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setExternalUrl(?string $externalUrl): NotificationsPagesEntityInterface
    {
        $this->external_url = $externalUrl;
        return $this;
    }

    /**
     * Getter for external_url
     *
     * @return string|null
     */
    public function getExternalUrl(): ?string
    {
        return $this->external_url ?? null;
    }

    /**
     * Setter for change_date
     *
     * @param DateTime|null $changeDate Change Date
     *
     * @return NotificationsPagesEntityInterface
     */
    public function setChangeDate(?DateTime $changeDate): NotificationsPagesEntityInterface
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
     * @return NotificationsPagesEntityInterface
     */
    public function setCreateDate(?DateTime $createDate): NotificationsPagesEntityInterface
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
     * @return NotificationsPagesEntityInterface
     */
    public function setLanguage(?string $language): NotificationsPagesEntityInterface
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
