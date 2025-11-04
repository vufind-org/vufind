<?php

/**
 * Entity model for finna_record_view_record table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for finna_record_view_record table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_record_view_record')]
#[ORM\Entity]
class FinnaRecordViewRecord implements FinnaRecordViewRecordEntityInterface
{
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
     * Backend.
     *
     * @var string
     */
    #[ORM\Column(name: 'backend', type: 'string', length: 255, nullable: false)]
    protected string $backend;

    /**
     * Source.
     *
     * @var string
     */
    #[ORM\Column(name: 'source', type: 'string', length: 255, nullable: false)]
    protected string $source;

    /**
     * Record ID.
     *
     * @var string
     */
    #[ORM\Column(name: 'record_id', type: 'string', length: 255, nullable: false)]
    protected string $recordId;

    /**
     * Format
     *
     * @var FinnaRecordViewRecordFormatEntityInterface
     */
    #[ORM\JoinColumn(name: 'format_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: FinnaRecordViewRecordFormatEntityInterface::class)]
    protected FinnaRecordViewRecordFormatEntityInterface $format;

    /**
     * Usage rights
     *
     * @var FinnaRecordViewRecordRightsEntityInterface
     */
    #[ORM\JoinColumn(name: 'usage_rights_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: FinnaRecordViewRecordRightsEntityInterface::class)]
    protected FinnaRecordViewRecordRightsEntityInterface $usageRights;

    /**
     * Online
     *
     * @var bool
     */
    #[ORM\Column(name: 'online', type: 'boolean', length: 255, nullable: false)]
    protected bool $online;

    /**
     * Extra Metadata
     *
     * @var ?string
     */
    #[ORM\Column(name: 'extra_metadata', type: 'text', length: 16777215, nullable: true)]
    protected ?string $extraMetadata = null;

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get backend.
     *
     * @return string
     */
    public function getBackend(): string
    {
        return $this->backend;
    }

    /**
     * Set backend.
     *
     * @param string $backend Backend
     *
     * @return static
     */
    public function setBackend(string $backend): static
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * Get source.
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Set source.
     *
     * @param string $source Source
     *
     * @return static
     */
    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get record ID.
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->recordId;
    }

    /**
     * Set record ID.
     *
     * @param string $recordId Record Id
     *
     * @return static
     */
    public function setRecordId(string $recordId): static
    {
        $this->recordId = $recordId;
        return $this;
    }

    /**
     * Get format.
     *
     * @return FinnaRecordViewRecordFormatEntityInterface
     */
    public function getFormat(): FinnaRecordViewRecordFormatEntityInterface
    {
        return $this->format;
    }

    /**
     * Set format.
     *
     * @param FinnaRecordViewRecordFormatEntityInterface $format Format
     *
     * @return static
     */
    public function setFormat(FinnaRecordViewRecordFormatEntityInterface $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Get usage rights.
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    public function getUsageRights(): FinnaRecordViewRecordRightsEntityInterface
    {
        return $this->usageRights;
    }

    /**
     * Set usage rights.
     *
     * @param FinnaRecordViewRecordRightsEntityInterface $usageRights Usage rights
     *
     * @return static
     */
    public function setUsageRights(FinnaRecordViewRecordRightsEntityInterface $usageRights): static
    {
        $this->usageRights = $usageRights;
        return $this;
    }

    /**
     * Get online.
     *
     * @return bool
     */
    public function getOnline(): bool
    {
        return $this->online;
    }

    /**
     * Set online.
     *
     * @param bool $online Online
     *
     * @return static
     */
    public function setOnline(bool $online): static
    {
        $this->online = $online;
        return $this;
    }

    /**
     * Get extra metadata.
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string
    {
        return $this->extraMetadata;
    }

    /**
     * Set extra metadata.
     *
     * @param ?string $extraMetadata Extra metadata
     *
     * @return static
     */
    public function setExtraMetadata(?string $extraMetadata): static
    {
        $this->extraMetadata = $extraMetadata;
        return $this;
    }
}
