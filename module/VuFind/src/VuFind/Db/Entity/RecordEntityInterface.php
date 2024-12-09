<?php

/**
 * Entity model interface for record table
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

use DateTime;

/**
 * Entity model interface for record table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface RecordEntityInterface extends EntityInterface
{
    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int;

    /**
     * Get record id.
     *
     * @return ?string
     */
    public function getRecordId(): ?string;

    /**
     * Set record id.
     *
     * @param ?string $recordId Record id
     *
     * @return RecordEntityInterface
     */
    public function setRecordId(?string $recordId): RecordEntityInterface;

    /**
     * Get record source.
     *
     * @return ?string
     */
    public function getSource(): ?string;

    /**
     * Set record source.
     *
     * @param ?string $recordSource Record source
     *
     * @return RecordEntityInterface
     */
    public function setSource(?string $recordSource): RecordEntityInterface;

    /**
     * Get record version.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Set record version.
     *
     * @param string $recordVersion Record version
     *
     * @return RecordEntityInterface
     */
    public function setVersion(string $recordVersion): RecordEntityInterface;

    /**
     * Get record data.
     *
     * @return ?string
     */
    public function getData(): ?string;

    /**
     * Set record data.
     *
     * @param ?string $recordData Record data
     *
     * @return RecordEntityInterface
     */
    public function setData(?string $recordData): RecordEntityInterface;

    /**
     * Get updated date.
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime;

    /**
     * Set updated date.
     *
     * @param DateTime $dateTime Updated date
     *
     * @return RecordEntityInterface
     */
    public function setUpdated(DateTime $dateTime): RecordEntityInterface;
}
