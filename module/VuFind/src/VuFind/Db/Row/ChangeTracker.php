<?php

/**
 * Row Definition for change_tracker
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use DateTime;
use VuFind\Db\Entity\ChangeTrackerEntityInterface;

/**
 * Row Definition for change_tracker
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property string  $core
 * @property string  $id
 * @property ?string $first_indexed
 * @property ?string $last_indexed
 * @property ?string $last_record_change
 * @property ?string $deleted
 */
class ChangeTracker extends RowGateway implements ChangeTrackerEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct(['core', 'id'], 'change_tracker', $adapter);
    }

    /**
     * Setter for identifier.
     *
     * @param string $id Id
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setId(string $id): ChangeTrackerEntityInterface
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Getter for identifier.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Setter for index name (formerly core).
     *
     * @param string $name Index name
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setIndexName(string $name): ChangeTrackerEntityInterface
    {
        $this->core = $name;
        return $this;
    }

    /**
     * Getter for index name (formerly core).
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->core;
    }

    /**
     * FirstIndexed setter.
     *
     * @param ?DateTime $dateTime Time first added to index.
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setFirstIndexed(?DateTime $dateTime): ChangeTrackerEntityInterface
    {
        $this->first_indexed = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * FirstIndexed getter.
     *
     * @return ?DateTime
     */
    public function getFirstIndexed(): ?DateTime
    {
        return $this->first_indexed ? DateTime::createFromFormat('Y-m-d H:i:s', $this->first_indexed) : null;
    }

    /**
     * LastIndexed setter.
     *
     * @param ?DateTime $dateTime Last time changed in index.
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setLastIndexed(?DateTime $dateTime): ChangeTrackerEntityInterface
    {
        $this->last_indexed = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * LastIndexed getter.
     *
     * @return ?DateTime
     */
    public function getLastIndexed(): ?DateTime
    {
        return $this->last_indexed ? DateTime::createFromFormat('Y-m-d H:i:s', $this->last_indexed) : null;
    }

    /**
     * LastRecordChange setter.
     *
     * @param ?DateTime $dateTime Last time original record was edited
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setLastRecordChange(?DateTime $dateTime): ChangeTrackerEntityInterface
    {
        $this->last_record_change = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * LastRecordChange getter.
     *
     * @return ?DateTime
     */
    public function getLastRecordChange(): ?DateTime
    {
        return $this->last_record_change ? DateTime::createFromFormat('Y-m-d H:i:s', $this->last_record_change) : null;
    }

    /**
     * Deleted setter.
     *
     * @param ?DateTime $dateTime Time record was removed from index
     *
     * @return ChangeTrackerEntityInterface
     */
    public function setDeleted(?DateTime $dateTime): ChangeTrackerEntityInterface
    {
        $this->deleted = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Deleted getter.
     *
     * @return ?DateTime
     */
    public function getDeleted(): ?DateTime
    {
        return $this->deleted ? DateTime::createFromFormat('Y-m-d H:i:s', $this->deleted) : null;
    }
}
