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
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Backend setter
     *
     * @param string $backend Backend
     *
     * @return static
     */
    public function setBackend(string $backend): static;

    /**
     * Backend getter
     *
     * @return string
     */
    public function getBackend(): string;

    /**
     * Source setter
     *
     * @param string $source Source
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
     * Record Id setter
     *
     * @param string $recordId Record Id
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
     * Format setter
     *
     * @param FinnaRecordViewRecordFormatEntityInterface $format Format
     *
     * @return static
     */
    public function setFormat(FinnaRecordViewRecordFormatEntityInterface $format): static;

    /**
     * Format getter
     *
     * @return FinnaRecordViewRecordFormatEntityInterface
     */
    public function getFormat(): FinnaRecordViewRecordFormatEntityInterface;

    /**
     * Usage rights setter
     *
     * @param FinnaRecordViewRecordRightsEntityInterface $usageRights Usage rights
     *
     * @return static
     */
    public function setUsageRights(
        FinnaRecordViewRecordRightsEntityInterface $usageRights
    ): static;

    /**
     * Usage rights getter
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    public function getUsageRights(): FinnaRecordViewRecordRightsEntityInterface;

    /**
     * Online setter
     *
     * @param bool $online Online
     *
     * @return static
     */
    public function setOnline(bool $online): static;

    /**
     * Online getter
     *
     * @return bool
     */
    public function getOnline(): bool;

    /**
     * Extra metadata setter
     *
     * @param ?string $extraMetadata Extra metadata
     *
     * @return static
     */
    public function setExtraMetadata(?string $extraMetadata): static;

    /**
     * Extra metadata getter
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string;
}
