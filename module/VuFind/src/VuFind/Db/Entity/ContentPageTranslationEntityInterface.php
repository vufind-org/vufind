<?php

/**
 * Entity model interface for content page translation.
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
 * Entity model interface for content page translation.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ContentPageTranslationEntityInterface extends EntityInterface
{
    /**
     * Get content page.
     *
     * @return ContentPageEntityInterface
     */
    public function getContentPage(): ContentPageEntityInterface;

    /**
     * Set content page.
     *
     * @param ContentPageEntityInterface $contentPage Content page
     *
     * @return static
     */
    public function setContentPage(ContentPageEntityInterface $contentPage): static;

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
     * Get headline.
     *
     * @return ?string
     */
    public function getHeadline(): ?string;

    /**
     * Set headline.
     *
     * @param ?string $headline Headline
     *
     * @return static
     */
    public function setHeadline(?string $headline): static;

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
