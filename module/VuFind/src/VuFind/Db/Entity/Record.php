<?php

/**
 * Entity model for record table
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\Db\Feature\DateTimeTrait;

/**
 * Record
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'record')]
#[ORM\UniqueConstraint(
    name: 'record_record_id_source_index',
    columns: ['record_id', 'source'],
    options: ['lengths' => [140, null]]
)]
#[ORM\Entity]
class Record implements RecordEntityInterface
{
    use DateTimeTrait;

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
     * Record ID.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'record_id', type: 'string', length: 255, nullable: true)]
    protected ?string $recordId = null;

    /**
     * Record source.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'source', type: 'string', length: 50, nullable: true)]
    protected ?string $source = null;

    /**
     * Record version.
     *
     * @var string
     */
    #[ORM\Column(name: 'version', type: 'string', length: 20, nullable: false)]
    protected string $version;

    /**
     * Record Data.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'data', type: 'text', length: 0, nullable: true)]
    protected ?string $data = null;

    /**
     * Updated date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'updated', type: 'datetime', nullable: false, options: ['default' => '2000-01-01 00:00:00'])]
    protected DateTime $updated;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->updated = $this->getUnassignedDefaultDateTime();
    }

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get record id.
     *
     * @return ?string
     */
    public function getRecordId(): ?string
    {
        return $this->recordId;
    }

    /**
     * Set record id.
     *
     * @param ?string $recordId Record id
     *
     * @return static
     */
    public function setRecordId(?string $recordId): static
    {
        $this->recordId = $recordId;
        return $this;
    }

    /**
     * Get record source.
     *
     * @return ?string
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * Set record source.
     *
     * @param ?string $source Record source
     *
     * @return static
     */
    public function setSource(?string $source): static
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get record version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set record version.
     *
     * @param string $recordVersion Record version
     *
     * @return static
     */
    public function setVersion(string $recordVersion): static
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
        return $this->data;
    }

    /**
     * Set record data.
     *
     * @param ?string $recordData Record data
     *
     * @return static
     */
    public function setData(?string $recordData): static
    {
        $this->data = $recordData;
        return $this;
    }

    /**
     * Get updated date.
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime
    {
        return $this->updated;
    }

    /**
     * Set updated date.
     *
     * @param DateTime $dateTime Updated date
     *
     * @return static
     */
    public function setUpdated(DateTime $dateTime): static
    {
        $this->updated = $dateTime;
        return $this;
    }
}
