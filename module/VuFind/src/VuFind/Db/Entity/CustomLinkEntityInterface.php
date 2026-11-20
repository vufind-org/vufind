<?php

/**
 * Entity model interface for custom link.
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
use Doctrine\Common\Collections\Collection;

/**
 * Entity model interface for custom link.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface CustomLinkEntityInterface extends EntityInterface
{
    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Is custom link enabled?
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Set if enabled.
     *
     * @param bool $enabled If enabled
     *
     * @return static
     */
    public function setEnabled(bool $enabled): static;

    /**
     * Get display order.
     *
     * @return int
     */
    public function getDisplayOrder(): int;

    /**
     * Set display order.
     *
     * @param int $displayOrder Display order
     *
     * @return static
     */
    public function setDisplayOrder(int $displayOrder): static;

    /**
     * Get position.
     *
     * @return ?string
     */
    public function getPosition(): ?string;

    /**
     * Set position.
     *
     * @param ?string $position Position
     *
     * @return static
     */
    public function setPosition(?string $position): static;

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set type.
     *
     * @param string $type Type
     *
     * @return static
     */
    public function setType(string $type): static;

    /**
     * Get page.
     *
     * @return ?string
     */
    public function getPage(): ?string;

    /**
     * Set page.
     *
     * @param ?string $page Page
     *
     * @return static
     */
    public function setPage(?string $page): static;

    /**
     * Get conditions.
     *
     * @return ?array
     */
    public function getConditions(): ?array;

    /**
     * Set conditions.
     *
     * @param ?array $conditions Conditions
     *
     * @return static
     */
    public function setConditions(?array $conditions): static;

    /**
     * Get created.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime;

    /**
     * Set created.
     *
     * @param DateTime $created Create date
     *
     * @return static
     */
    public function setCreated(DateTime $created): static;

    /**
     * Add translations.
     *
     * @param Collection<int, CustomLinkTranslationEntityInterface> $translations Translations
     *
     * @return void
     */
    public function addTranslations(Collection $translations): void;

    /**
     * Remove translations.
     *
     * @param Collection<int, CustomLinkTranslationEntityInterface> $translations Translations
     *
     * @return void
     */
    public function removeTranslations(Collection $translations): void;

    /**
     * Get translations.
     *
     * @return Collection<int, CustomLinkTranslationEntityInterface>
     */
    public function getTranslations(): Collection;
}
