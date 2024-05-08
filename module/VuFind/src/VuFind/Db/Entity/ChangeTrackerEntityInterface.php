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
     * Id setter
     *
     * @param string $id Id
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setId(string $id): ChangeTrackerEntityInterface;

    /**
     * Id getter
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Core setter
     *
     * @param string $core Core
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setCore(string $core): ChangeTrackerEntityInterface;

    /**
     * Core getter
     *
     * @return string
     */
    public function getCore(): string;

    /**
     * FirstIndexed setter.
     *
     * @param ?Datetime $dateTime Time first added to index.
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setFirstIndexed(?DateTime $dateTime): ChangeTrackerEntityInterface;

    /**
     * FirstIndexed Getter.
     *
     * @return ?Datetime
     */
    public function getFirstIndexed(): ?Datetime;

    /**
     * LastIndexed setter.
     *
     * @param ?Datetime $dateTime Last time changed in index.
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setLastIndexed(?DateTime $dateTime): ChangeTrackerEntityInterface;

    /**
     * LastIndexed Getter.
     *
     * @return ?Datetime
     */
    public function getLastIndexed(): ?Datetime;

    /**
     * LastRecordChange setter.
     *
     * @param ?Datetime $dateTime Last time original record was edited
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setLastRecordChange(?DateTime $dateTime): ChangeTrackerEntityInterface;

    /**
     * LastRecordChange Getter.
     *
     * @return ?Datetime
     */
    public function getLastRecordChange(): ?Datetime;

    /**
     * Deleted setter.
     *
     * @param ?Datetime $dateTime Time record was removed from index
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setDeleted(?DateTime $dateTime): ChangeTrackerEntityInterface;

    /**
     * Deleted Getter.
     *
     * @return ?Datetime
     */
    public function getDeleted(): ?Datetime;
}
