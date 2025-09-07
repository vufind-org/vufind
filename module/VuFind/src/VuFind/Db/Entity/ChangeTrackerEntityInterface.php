<?php

/**
 * Entity model interface for change_tracker table
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
 * Entity model interface for change_tracker table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ChangeTrackerEntityInterface extends EntityInterface
{
    /**
     * Setter for identifier.
     *
     * @param string $id Id
     *
     * @return static
     */
    public function setId(string $id): static;

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?string
     */
    public function getId(): ?string;

    /**
     * Setter for index name (formerly core).
     *
     * @param string $name Index name
     *
     * @return static
     */
    public function setIndexName(string $name): static;

    /**
     * Getter for index name (formerly core).
     *
     * @return string
     */
    public function getIndexName(): string;

    /**
     * FirstIndexed setter.
     *
     * @param ?DateTime $dateTime Time first added to index.
     *
     * @return static
     */
    public function setFirstIndexed(?DateTime $dateTime): static;

    /**
     * FirstIndexed getter.
     *
     * @return ?DateTime
     */
    public function getFirstIndexed(): ?DateTime;

    /**
     * LastIndexed setter.
     *
     * @param ?DateTime $dateTime Last time changed in index.
     *
     * @return static
     */
    public function setLastIndexed(?DateTime $dateTime): static;

    /**
     * LastIndexed getter.
     *
     * @return ?DateTime
     */
    public function getLastIndexed(): ?DateTime;

    /**
     * LastRecordChange setter.
     *
     * @param ?DateTime $dateTime Last time original record was edited
     *
     * @return static
     */
    public function setLastRecordChange(?DateTime $dateTime): static;

    /**
     * LastRecordChange getter.
     *
     * @return ?DateTime
     */
    public function getLastRecordChange(): ?DateTime;

    /**
     * Deleted setter.
     *
     * @param ?DateTime $dateTime Time record was removed from index
     *
     * @return static
     */
    public function setDeleted(?DateTime $dateTime): static;

    /**
     * Deleted getter.
     *
     * @return ?DateTime
     */
    public function getDeleted(): ?DateTime;
}
