<?php

/**
 * Entity model for content_page_translation table.
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

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for content_page_translation table.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'content_page_translation')]
#[ORM\Index(name: 'content_page_translation_content_page_id_idx', columns: ['content_page_id'])]
#[ORM\Entity]
class ContentPageTranslation implements ContentPageTranslationEntityInterface
{
    /**
     * Content page.
     *
     * @var ContentPageEntityInterface
     */
    #[ORM\JoinColumn(name: 'content_page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: ContentPageEntityInterface::class, inversedBy: 'translations')]
    protected ContentPageEntityInterface $contentPage;

    /**
     * Language.
     *
     * @var string
     */
    #[ORM\Column(name: 'language', type: 'string', length: 50, nullable: false)]
    #[ORM\Id]
    protected string $language;

    /**
     * Headline.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'headline', type: 'string', length: 255, nullable: true, options: ['default' => null])]
    protected ?string $headline = null;

    /**
     * Content.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'content', type: 'text', length: 65535, nullable: true, options: ['default' => null])]
    protected ?string $content = null;

    /**
     * Get content page.
     *
     * @return ContentPageEntityInterface
     */
    public function getContentPage(): ContentPageEntityInterface
    {
        return $this->contentPage;
    }

    /**
     * Set content page.
     *
     * @param ContentPageEntityInterface $contentPage Content page
     *
     * @return static
     */
    public function setContentPage(ContentPageEntityInterface $contentPage): static
    {
        $this->contentPage = $contentPage;
        return $this;
    }

    /**
     * Get language.
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Set language.
     *
     * @param string $language Language
     *
     * @return static
     */
    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Get headline.
     *
     * @return ?string
     */
    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    /**
     * Set headline.
     *
     * @param ?string $headline Headline
     *
     * @return static
     */
    public function setHeadline(?string $headline): static
    {
        $this->headline = $headline;
        return $this;
    }

    /**
     * Get content.
     *
     * @return ?string
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Set content.
     *
     * @param ?string $content Content
     *
     * @return static
     */
    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }
}
