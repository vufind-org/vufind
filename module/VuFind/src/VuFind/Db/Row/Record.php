<?php

/**
 * Row Definition for record
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Db_Row
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use Exception;
use VuFind\Db\Entity\RecordEntityInterface;

/**
 * Row Definition for user
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property string $record_id
 * @property string $source
 * @property string $version
 * @property string $updated
 */
class Record extends RowGateway implements RecordEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'record', $adapter);
    }

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
     * Get record id.
     *
     * @return ?string
     */
    public function getRecordId(): ?string
    {
        return $this->record_id ?? null;
    }

    /**
     * Set record id.
     *
     * @param ?string $recordId Record id
     *
     * @return RecordEntityInterface
     */
    public function setRecordId(?string $recordId): RecordEntityInterface
    {
        $this->record_id = $recordId;
        return $this;
    }

    /**
     * Get record source.
     *
     * @return ?string
     */
    public function getSource(): ?string
    {
        return $this->source ?? null;
    }

    /**
     * Set record source.
     *
     * @param ?string $recordSource Record source
     *
     * @return RecordEntityInterface
     */
    public function setSource(?string $recordSource): RecordEntityInterface
    {
        $this->source = $recordSource;
        return $this;
    }

    /**
     * Get record version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version ?? '';
    }

    /**
     * Set record version.
     *
     * @param string $recordVersion Record version
     *
     * @return RecordEntityInterface
     */
    public function setVersion(string $recordVersion): RecordEntityInterface
    {
        $this->version = $recordVersion;
        return $this;
    }

    /**
     * Get record data.
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        try {
            return $this->__get('data');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Set record data.
     *
     * @param ?string $recordData Record data
     *
     * @return RecordEntityInterface
     */
    public function setData(?string $recordData): RecordEntityInterface
    {
        $this->__set('data', $recordData);
        return $this;
    }

    /**
     * Get updated date.
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->updated);
    }

    /**
     * Set updated date.
     *
     * @param DateTime $dateTime Updated date
     *
     * @return RecordEntityInterface
     */
    public function setUpdated(DateTime $dateTime): RecordEntityInterface
    {
        $this->updated = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }
}
