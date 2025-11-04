<?php

/**
 * Entity model for finna_cache table
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

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for finna_cache table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_cache')]
#[ORM\Index(name: 'resource_id', columns: ['resource_id'])]
#[ORM\Entity]
class FinnaCache implements FinnaCacheEntityInterface
{
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
     * Resource ID.
     *
     * @var string
     */
    #[ORM\Column(name: 'resource_id', type: 'string', length: 255, nullable: false)]
    protected string $resourceId;

    /**
     * Creation date.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $created;

    /**
     * Modification UNIX timestamp.
     *
     * @var int
     */
    #[ORM\Column(name: 'mtime', type: 'integer', nullable: false)]
    protected int $mtime;

    /**
     * Data.
     *
     * @var mixed
     */
    #[ORM\Column(name: 'data', type: 'blob', length: 65535, nullable: true)]
    protected mixed $data = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a DateTime object
        $this->created = new DateTime();
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
     * Get resource ID.
     *
     * @return string
     */
    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    /**
     * Set resource ID.
     *
     * @param string $id Resource ID
     *
     * @return static
     */
    public function setResourceId(string $id): static
    {
        $this->resourceId = $id;
        return $this;
    }

    /**
     * Get creation date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set creation date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }

    /**
     * Get modification UNIX timestamp.
     *
     * @return int
     */
    public function getModificationTimestamp(): int
    {
        return $this->mtime;
    }

    /**
     * Set modification UNIX timestamp.
     *
     * @param int $mtime Unix timestamp of modification
     *
     * @return static
     */
    public function setModificationTimestamp(int $mtime): static
    {
        $this->mtime = $mtime;
        return $this;
    }

    /**
     * Get data.
     *
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Set data.
     *
     * @param string $data Data
     *
     * @return static
     */
    public function setData(string $data): static
    {
        $this->data = $data;
        return $this;
    }
}
