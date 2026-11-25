<?php

/**
 * Entity model for notice_translation table.
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
 * Entity model for notice_translation table.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'notice_translation')]
#[ORM\Index(name: 'notice_translation_notice_id_idx', columns: ['notice_id'])]
#[ORM\Entity]
class NoticeTranslation implements NoticeTranslationEntityInterface
{
    /**
     * Notice.
     *
     * @var NoticeEntityInterface
     */
    #[ORM\JoinColumn(name: 'notice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: NoticeEntityInterface::class, inversedBy: 'translations')]
    protected NoticeEntityInterface $notice;

    /**
     * Language.
     *
     * @var string
     */
    #[ORM\Column(name: 'language', type: 'string', length: 50, nullable: false)]
    #[ORM\Id]
    protected string $language;

    /**
     * Content.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'content', type: 'text', length: 65535, nullable: true, options: ['default' => null])]
    protected ?string $content = null;

    /**
     * Get notice.
     *
     * @return NoticeEntityInterface
     */
    public function getNotice(): NoticeEntityInterface
    {
        return $this->notice;
    }

    /**
     * Set notice.
     *
     * @param NoticeEntityInterface $notice Notice
     *
     * @return static
     */
    public function setNotice(NoticeEntityInterface $notice): static
    {
        $this->notice = $notice;
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
