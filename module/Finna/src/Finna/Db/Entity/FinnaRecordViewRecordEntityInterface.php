<?php

/**
 * Interface for representing a Finna record view record.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Db\Entity;

use VuFind\Db\Entity\EntityInterface;

/**
 * Interface for representing a Finna record view record.
 *
 * @category VuFind
 * @package  Db_Interface
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface FinnaRecordViewRecordEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get backend.
     *
     * @return string
     */
    public function getBackend(): string;

    /**
     * Set backend.
     *
     * @param string $backend Backend
     *
     * @return static
     */
    public function setBackend(string $backend): static;

    /**
     * Get source.
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * Set source.
     *
     * @param string $source Source
     *
     * @return static
     */
    public function setSource(string $source): static;

    /**
     * Get record ID.
     *
     * @return string
     */
    public function getRecordId(): string;

    /**
     * Set record ID.
     *
     * @param string $recordId Record Id
     *
     * @return static
     */
    public function setRecordId(string $recordId): static;

    /**
     * Get format.
     *
     * @return FinnaRecordViewRecordFormatEntityInterface
     */
    public function getFormat(): FinnaRecordViewRecordFormatEntityInterface;

    /**
     * Set format.
     *
     * @param FinnaRecordViewRecordFormatEntityInterface $format Format
     *
     * @return static
     */
    public function setFormat(FinnaRecordViewRecordFormatEntityInterface $format): static;

    /**
     * Get usage rights.
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    public function getUsageRights(): FinnaRecordViewRecordRightsEntityInterface;

    /**
     * Set usage rights.
     *
     * @param FinnaRecordViewRecordRightsEntityInterface $usageRights Usage rights
     *
     * @return static
     */
    public function setUsageRights(
        FinnaRecordViewRecordRightsEntityInterface $usageRights
    ): static;

    /**
     * Get online.
     *
     * @return bool
     */
    public function getOnline(): bool;

    /**
     * Set online.
     *
     * @param bool $online Online
     *
     * @return static
     */
    public function setOnline(bool $online): static;

    /**
     * Get extra metadata.
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string;

    /**
     * Set extra metadata.
     *
     * @param ?string $extraMetadata Extra metadata
     *
     * @return static
     */
    public function setExtraMetadata(?string $extraMetadata): static;
}
