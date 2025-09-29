<?php

/**
 * Entity model interface for resource table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

/**
 * Entity model interface for resource table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ResourceEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Record Id setter
     *
     * @param string $recordId recordId
     *
     * @return static
     */
    public function setRecordId(string $recordId): static;

    /**
     * Record Id getter
     *
     * @return string
     */
    public function getRecordId(): string;

    /**
     * Title setter
     *
     * @param string $title Title of the record.
     *
     * @return static
     */
    public function setTitle(string $title): static;

    /**
     * Title getter
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Author setter
     *
     * @param ?string $author Author of the title.
     *
     * @return static
     */
    public function setAuthor(?string $author): static;

    /**
     * Year setter
     *
     * @param ?int $year Year title is published.
     *
     * @return static
     */
    public function setYear(?int $year): static;

    /**
     * Source setter
     *
     * @param string $source Source (a search backend ID).
     *
     * @return static
     */
    public function setSource(string $source): static;

    /**
     * Source getter
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * Extra Metadata setter
     *
     * @param ?string $extraMetadata ExtraMetadata.
     *
     * @return static
     */
    public function setExtraMetadata(?string $extraMetadata): static;

    /**
     * Extra Metadata getter
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string;
}
