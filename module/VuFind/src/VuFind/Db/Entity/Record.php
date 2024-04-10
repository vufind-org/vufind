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
 * Record
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="record",
 *          uniqueConstraints={@ORM\UniqueConstraint(name="record_id_source",
 *                          columns={"record_id", "source"})})
 * @ORM\Entity
 */
class Record implements RecordEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Column(name="id",
     *          type="integer",
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Record ID.
     *
     * @var ?string
     *
     * @ORM\Column(name="record_id", type="string", length=255, nullable=true)
     */
    protected $recordId;

    /**
     * Record source.
     *
     * @var ?string
     *
     * @ORM\Column(name="source", type="string", length=50, nullable=true)
     */
    protected $source;

    /**
     * Record version.
     *
     * @var string
     *
     * @ORM\Column(name="version", type="string", length=20, nullable=false)
     */
    protected $version;

    /**
     * Record Data.
     *
     * @var ?string
     *
     * @ORM\Column(name="data", type="text", length=0, nullable=true)
     */
    protected $data;

    /**
     * Updated date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="updated",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $updated = '2000-01-01 00:00:00';

    /**
     * Record Id setter
     *
     * @param ?string $id Record Id.
     *
     * @return Record
     */
    public function setRecordId(?string $id): Record
    {
        $this->recordId = $id;
        return $this;
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
     * Record source setter
     *
     * @param ?string $source Record source.
     *
     * @return Record
     */
    public function setSource(?string $source): Record
    {
        $this->source = $source;
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
     * Record data setter
     *
     * @param ?string $data Record data.
     *
     * @return Record
     */
    public function setData(?string $data): Record
    {
        $this->data = $data;
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
     * Record version setter
     *
     * @param string $version Record version.
     *
     * @return Record
     */
    public function setVersion(string $version): Record
    {
        $this->version = $version;
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
     * Updated setter.
     *
     * @param Datetime $dateTime updated date
     *
     * @return Record
     */
    public function setUpdated(DateTime $dateTime): Record
    {
        $this->updated = $dateTime;
        return $this;
    }

    /**
     * Get record updation date.
     *
     * @return DateTime
     */
    public function getUpdated(): DateTime
    {
        return $this->updated;
    }
}
