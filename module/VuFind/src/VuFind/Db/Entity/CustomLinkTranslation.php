<?php

/**
 * Entity model for custom_link_translation table.
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
 * Entity model for custom_link_translation table.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'custom_link_translation')]
#[ORM\Index(name: 'custom_link_translation_custom_link_id_idx', columns: ['custom_link_id'])]
#[ORM\Entity]
class CustomLinkTranslation implements CustomLinkTranslationEntityInterface
{
    /**
     * Custom link.
     *
     * @var CustomLinkEntityInterface
     */
    #[ORM\JoinColumn(name: 'custom_link_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: CustomLinkEntityInterface::class, inversedBy: 'translations')]
    protected CustomLinkEntityInterface $customLink;

    /**
     * Language.
     *
     * @var string
     */
    #[ORM\Column(name: 'language', type: 'string', length: 50, nullable: false)]
    #[ORM\Id]
    protected string $language;

    /**
     * Title.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'title', type: 'string', length: 255, nullable: true, options: ['default' => null])]
    protected ?string $title = null;

    /**
     * URL.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'url', type: 'text', length: 65535, nullable: true, options: ['default' => null])]
    protected ?string $url = null;

    /**
     * Get custom link.
     *
     * @return CustomLinkEntityInterface
     */
    public function getCustomLink(): CustomLinkEntityInterface
    {
        return $this->customLink;
    }

    /**
     * Set custom link.
     *
     * @param CustomLinkEntityInterface $customLink Custom link
     *
     * @return static
     */
    public function setCustomLink(CustomLinkEntityInterface $customLink): static
    {
        $this->customLink = $customLink;
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
     * @return static
     */
    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get URL.
     *
     * @return ?string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Set URL.
     *
     * @param ?string $url URL
     *
     * @return static
     */
    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }
}
