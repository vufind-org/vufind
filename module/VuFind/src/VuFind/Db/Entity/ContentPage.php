<?php

/**
 * Entity model for content_page table.
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

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Entity model for content_page table.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'content_page')]
#[ORM\UniqueConstraint(name: 'content_page_hrid_idx', columns: ['hrid'])]
#[ORM\Entity]
class ContentPage implements ContentPageEntityInterface
{
    use DateTimeTrait;

    /**
     * Unique ID.
     *
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected int $id;

    /**
     * HRID.
     *
     * @var string
     */
    #[ORM\Column(name: 'hrid', type: 'string', length: 50, nullable: false)]
    protected string $hrid;

    /**
     * Enabled.
     *
     * @var bool
     */
    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: false, options: ['default' => true])]
    protected bool $enabled = true;

    /**
     * Content type.
     *
     * @var string
     */
    #[ORM\Column(name: 'content_type', type: 'string', length: 50, nullable: false, options: ['default' => 'text'])]
    protected string $contentType = 'text';

    /**
     * Conditions.
     *
     * @var ?array
     */
    #[ORM\Column(name: 'conditions', type: 'json', nullable: true, options: ['jsonb' => true, 'default' => null])]
    protected ?array $conditions = null;

    /**
     * Create date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false)]
    protected DateTime $created;

    /**
     * Translations.
     *
     * @var Collection<int, ContentPageTranslationEntityInterface>
     */
    #[ORM\OneToMany(mappedBy: 'contentPage', targetEntity: ContentPageTranslationEntityInterface::class)]
    protected Collection $translations;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->created = $this->getUnassignedDefaultDateTime();
        $this->translations = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get HRID.
     *
     * @return string
     */
    public function getHrid(): string
    {
        return $this->hrid;
    }

    /**
     * Set HRID.
     *
     * @param string $hrid HRID
     *
     * @return static
     */
    public function setHrid(string $hrid): static
    {
        $this->hrid = $hrid;
        return $this;
    }

    /**
     * Is content page enabled?
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set if enabled.
     *
     * @param bool $enabled If enabled
     *
     * @return static
     */
    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get content type.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Set content type.
     *
     * @param string $contentType Position
     *
     * @return static
     */
    public function setContentType(string $contentType): static
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Get conditions.
     *
     * @return ?array
     */
    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    /**
     * Set conditions.
     *
     * @param ?array $conditions Conditions
     *
     * @return static
     */
    public function setConditions(?array $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    /**
     * Get created.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set created.
     *
     * @param DateTime $created Create date
     *
     * @return static
     */
    public function setCreated(DateTime $created): static
    {
        $this->created = $created;
        return $this;
    }

    /**
     * Add translations.
     *
     * @param Collection<int, ContentPageTranslationEntityInterface> $translations Translations
     *
     * @return void
     */
    public function addTranslations(Collection $translations): void
    {
        foreach ($translations as $translation) {
            $translation->setContentPage($this);
            $this->translations->add($translation);
        }
    }

    /**
     * Remove translations.
     *
     * @param Collection<int, ContentPageTranslationEntityInterface> $translations Translations
     *
     * @return void
     */
    public function removeTranslations(Collection $translations): void
    {
        foreach ($translations as $translation) {
            $this->translations->removeElement($translation);
        }
    }

    /**
     * Get translations.
     *
     * @return Collection<int, ContentPageTranslationEntityInterface>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
