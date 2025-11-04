<?php

/**
 * Entity model for finna_record_stats_log table
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
 * Entity model for finna_record_stats_log table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_record_stats_log')]
#[ORM\Index(name: 'record_backend', columns: ['backend'])]
#[ORM\Index(name: 'record_source', columns: ['source'])]
#[ORM\Entity]
class FinnaRecordStatsLog extends FinnaBaseStats implements FinnaRecordStatsLogEntityInterface
{
    /**
     * Backend
     *
     * @var string
     */
    #[ORM\Column(name: 'backend', type: 'string', length: 128, nullable: false)]
    #[ORM\Id]
    protected int $backend;

    /**
     * Source
     *
     * @var string
     */
    #[ORM\Column(name: 'source', type: 'string', length: 255, nullable: false)]
    #[ORM\Id]
    protected int $source;

    /**
     * Record ID
     *
     * @var string
     */
    #[ORM\Column(name: 'record_id', type: 'string', length: 255, nullable: false)]
    #[ORM\Id]
    protected int $recordId;

    /**
     * Formats.
     *
     * @var string
     */
    #[ORM\Column(name: 'formats', type: 'string', length: 255, nullable: false)]
    protected int $formats;

    /**
     * Usage rights
     *
     * @var string
     */
    #[ORM\Column(name: 'usage_rights', type: 'string', length: 255, nullable: false)]
    protected int $usageRights;

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
     * Backend setter
     *
     * @param string $backend Backend
     *
     * @return static
     */
    public function setBackend(string $backend): static
    {
        $this->backend = mb_substr($backend, 0, 128, 'UTF-8');
        return $this;
    }

    /**
     * Backend getter
     *
     * @return string
     */
    public function getBackend(): string
    {
        return $this->backend;
    }

    /**
     * Source setter
     *
     * @param string $source Source
     *
     * @return static
     */
    public function setSource(string $source): static
    {
        $this->source = mb_substr($source, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Source getter
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Record Id setter
     *
     * @param string $recordId Record Id
     *
     * @return static
     */
    public function setRecordId(string $recordId): static
    {
        $this->recordId = mb_substr($recordId, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Record Id getter
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->recordId;
    }

    /**
     * Formats setter
     *
     * @param string $formats Formats
     *
     * @return static
     */
    public function setFormats(string $formats): static
    {
        $this->formats = mb_substr($formats, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Formats getter
     *
     * @return string
     */
    public function getFormats(): string
    {
        return $this->formats;
    }

    /**
     * Usage rights setter
     *
     * @param string $usageRights Usage rights
     *
     * @return static
     */
    public function setUsageRights(string $usageRights): static
    {
        $this->usageRights = mb_substr($usageRights, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Usage rights getter
     *
     * @return string
     */
    public function getUsageRights(): string
    {
        return $this->usageRights;
    }

    /**
     * Online setter
     *
     * @param bool $online Online
     *
     * @return static
     */
    public function setOnline(bool $online): static
    {
        $this->online = $online ? 1 : 0;
        return $this;
    }

    /**
     * Online getter
     *
     * @return string
     */
    public function getOnline(): bool
    {
        return $this->online ? true : false;
    }

    /**
     * Extra metadata setter
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

    /**
     * Extra metadata getter
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string
    {
        return $this->extraMetadata;
    }
}
