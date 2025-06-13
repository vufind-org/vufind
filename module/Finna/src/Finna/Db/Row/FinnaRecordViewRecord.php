<?php

/**
 * Row definition for finna_record_view_record
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Row;

use Finna\Db\Entity\FinnaRecordViewRecordEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordFormatEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordRightsEntityInterface;

/**
 * Row definition for finna_record_view_record
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property int $id
 * @property string $backend
 * @property string $source
 * @property string $record_id
 * @property int $format_id
 * @property int $usage_rights_id
 * @property int $online
 * @property string $extra_metadata
 */
class FinnaRecordViewRecord extends \VuFind\Db\Row\RowGateway implements
    FinnaRecordViewRecordEntityInterface,
    \VuFind\Db\Service\DbServiceAwareInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_record_view_record', $adapter);
    }

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

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
        $this->record_id = mb_substr($recordId, 0, 255, 'UTF-8');
        return $this;
    }

    /**
     * Record Id getter
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->record_id;
    }

    /**
     * Format setter
     *
     * @param FinnaRecordViewRecordFormatEntityInterface $format Format
     *
     * @return static
     */
    public function setFormat(FinnaRecordViewRecordFormatEntityInterface $format): static
    {
        $this->format_id = $format->getId();
        return $this;
    }

    /**
     * Format getter
     *
     * @return string
     */
    public function getFormat(): FinnaRecordViewRecordFormatEntityInterface
    {
        return $this->getDbService(\Finna\Db\Service\FinnaStatisticsServiceInterface::class)
            ->getRecordViewRecordFormatById($this->format_id);
    }

    /**
     * Usage rights setter
     *
     * @param FinnaRecordViewRecordRightsEntityInterface $usageRights Usage rights
     *
     * @return static
     */
    public function setUsageRights(
        FinnaRecordViewRecordRightsEntityInterface $usageRights
    ): static {
        $this->usage_rights_id = $usageRights->getId();
        return $this;
    }

    /**
     * Usage rights getter
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    public function getUsageRights(): FinnaRecordViewRecordRightsEntityInterface
    {
        return $this->getDbService(\Finna\Db\Service\FinnaStatisticsServiceInterface::class)
            ->getRecordViewRecordUsageRightsById($this->format_id);
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
        $this->extra_metadata = $extraMetadata;
        return $this;
    }

    /**
     * Extra metadata getter
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string
    {
        return $this->extra_metadata;
    }
}
