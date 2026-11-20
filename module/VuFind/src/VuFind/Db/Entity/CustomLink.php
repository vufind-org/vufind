<?php

/**
 * Entity model for custom_link table.
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
 * Entity model for custom_link table.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'custom_link')]
#[ORM\Entity]
class CustomLink implements CustomLinkEntityInterface
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
     * Enabled.
     *
     * @var bool
     */
    #[ORM\Column(name: 'enabled', type: 'boolean', nullable: false, options: ['default' => true])]
    protected bool $enabled = true;

    /**
     * Display order.
     *
     * @var int
     */
    #[ORM\Column(name: 'display_order', type: 'integer', nullable: false, options: ['default' => 0])]
    protected int $displayOrder = 0;

    /**
     * Position.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'position', type: 'string', length: 50, nullable: true, options: ['default' => null])]
    protected ?string $position = null;

    /**
     * Type.
     *
     * @var string
     */
    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false, options: ['default' => 'url'])]
    protected string $type = 'url';

    /**
     * Page.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'page', type: 'string', length: 50, nullable: true, options: ['default' => null])]
    protected ?string $page = null;

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
     * @var Collection<int, CustomLinkTranslationEntityInterface>
     */
    #[ORM\OneToMany(mappedBy: 'customLink', targetEntity: CustomLinkTranslationEntityInterface::class)]
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
     * Is custom link enabled?
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
     * Get display order.
     *
     * @return int
     */
    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * Set display order.
     *
     * @param int $displayOrder Display order
     *
     * @return static
     */
    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    /**
     * Get position.
     *
     * @return ?string
     */
    public function getPosition(): ?string
    {
        return $this->position;
    }

    /**
     * Set position.
     *
     * @param ?string $position Position
     *
     * @return static
     */
    public function setPosition(?string $position): static
    {
        $this->position = $position;
        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get page.
     *
     * @return ?string
     */
    public function getPage(): ?string
    {
        return $this->page;
    }

    /**
     * Set page.
     *
     * @param ?string $page Page
     *
     * @return static
     */
    public function setPage(?string $page): static
    {
        $this->page = $page;
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
     * @param Collection<int, CustomLinkTranslationEntityInterface> $translations Translations
     *
     * @return void
     */
    public function addTranslations(Collection $translations): void
    {
        foreach ($translations as $translation) {
            $translation->setCustomLink($this);
            $this->translations->add($translation);
        }
    }

    /**
     * Remove translations.
     *
     * @param Collection<int, CustomLinkTranslationEntityInterface> $translations Translations
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
     * @return Collection<int, CustomLinkTranslationEntityInterface>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }
}
