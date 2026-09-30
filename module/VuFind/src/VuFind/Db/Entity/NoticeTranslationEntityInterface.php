<?php

/**
 * Entity model interface for notice translation.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

/**
 * Entity model interface for notice translation.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface NoticeTranslationEntityInterface extends EntityInterface
{
    /**
     * Get notice.
     *
     * @return NoticeEntityInterface
     */
    public function getNotice(): NoticeEntityInterface;

    /**
     * Set notice.
     *
     * @param NoticeEntityInterface $notice Notice
     *
     * @return static
     */
    public function setNotice(NoticeEntityInterface $notice): static;

    /**
     * Get language.
     *
     * @return string
     */
    public function getLanguage(): string;

    /**
     * Set language.
     *
     * @param string $language Language
     *
     * @return static
     */
    public function setLanguage(string $language): static;

    /**
     * Get content.
     *
     * @return ?string
     */
    public function getContent(): ?string;

    /**
     * Set content.
     *
     * @param ?string $content Content
     *
     * @return static
     */
    public function setContent(?string $content): static;
}
