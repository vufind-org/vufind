<?php

/**
 * Entity model for change_tracker table
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
use Doctrine\ORM\Mapping as ORM;

/**
 * ChangeTracker
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'change_tracker')]
#[ORM\Index(name: 'change_tracker_deleted_idx', columns: ['deleted'])]
#[ORM\Entity]
class ChangeTracker implements ChangeTrackerEntityInterface
{
    /**
     * Solr core containing record.
     *
     * @var string
     */
    #[ORM\Column(name: 'core', type: 'string', length: 30, nullable: false)]
    #[ORM\Id]
    protected string $core;

    /**
     * Id of record within core.
     *
     * @var string
     */
    #[ORM\Column(name: 'id', type: 'string', length: 120, nullable: false)]
    #[ORM\Id]
    protected string $id;

    /**
     * First time added to index
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'first_indexed', type: 'datetime', nullable: true)]
    protected ?DateTime $firstIndexed = null;

    /**
     * Last time changed in index.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'last_indexed', type: 'datetime', nullable: true)]
    protected ?DateTime $lastIndexed = null;

    /**
     * Last time original record was edited.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'last_record_change', type: 'datetime', nullable: true)]
    protected ?DateTime $lastRecordChange = null;

    /**
     * Time record was removed from index.
     *
     * @var ?DateTime
     */
    #[ORM\Column(name: 'deleted', type: 'datetime', nullable: true)]
    protected ?DateTime $deleted = null;

    /**
     * Setter for identifier.
     *
     * @param string $id Id
     *
     * @return static
     */
    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?string
     */
    public function getId(): ?string
    {
        return $this->id ?? null;
    }

    /**
     * Setter for index name (formerly core).
     *
     * @param string $name Index name
     *
     * @return static
     */
    public function setIndexName(string $name): static
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
     * @return static
     */
    public function setFirstIndexed(?DateTime $dateTime): static
    {
        $this->firstIndexed = $dateTime;
        return $this;
    }

    /**
     * FirstIndexed getter.
     *
     * @return ?DateTime
     */
    public function getFirstIndexed(): ?DateTime
    {
        return $this->firstIndexed;
    }

    /**
     * LastIndexed setter.
     *
     * @param ?DateTime $dateTime Last time changed in index.
     *
     * @return static
     */
    public function setLastIndexed(?DateTime $dateTime): static
    {
        $this->lastIndexed = $dateTime;
        return $this;
    }

    /**
     * LastIndexed getter.
     *
     * @return ?DateTime
     */
    public function getLastIndexed(): ?DateTime
    {
        return $this->lastIndexed;
    }

    /**
     * LastRecordChange setter.
     *
     * @param ?DateTime $dateTime Last time original record was edited
     *
     * @return static
     */
    public function setLastRecordChange(?DateTime $dateTime): static
    {
        $this->lastRecordChange = $dateTime;
        return $this;
    }

    /**
     * LastRecordChange getter.
     *
     * @return ?DateTime
     */
    public function getLastRecordChange(): ?DateTime
    {
        return $this->lastRecordChange;
    }

    /**
     * Deleted setter.
     *
     * @param ?DateTime $dateTime Time record was removed from index
     *
     * @return static
     */
    public function setDeleted(?DateTime $dateTime): static
    {
        $this->deleted = $dateTime;
        return $this;
    }

    /**
     * Deleted getter.
     *
     * @return ?DateTime
     */
    public function getDeleted(): ?DateTime
    {
        return $this->deleted;
    }
}
