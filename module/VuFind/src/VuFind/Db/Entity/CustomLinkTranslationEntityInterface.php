<?php

/**
 * Entity model interface for custom link translation.
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
 * Entity model interface for custom link translation.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface CustomLinkTranslationEntityInterface extends EntityInterface
{
    /**
     * Get custom link.
     *
     * @return CustomLinkEntityInterface
     */
    public function getCustomLink(): CustomLinkEntityInterface;

    /**
     * Set custom link.
     *
     * @param CustomLinkEntityInterface $customLink Custom link
     *
     * @return static
     */
    public function setCustomLink(CustomLinkEntityInterface $customLink): static;

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
     * Get URL.
     *
     * @return ?string
     */
    public function getUrl(): ?string;

    /**
     * Set URL.
     *
     * @param ?string $url URL
     *
     * @return static
     */
    public function setUrl(?string $url): static;
}
