<?php

/**
 * Row definition for cache
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2024.
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

use Datetime;
use Finna\Db\Entity\FinnaCacheEntityInterface;

/**
 * Row definition for cache
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property int $id
 * @property string $resource_id
 * @property string $created
 * @property int $mtime
 * @property string $data
 */
class FinnaCache extends \VuFind\Db\Row\RowGateway implements FinnaCacheEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_cache', $adapter);
    }

    /**
     * Id getter
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Resource ID setter
     *
     * @param string $id Resource ID
     *
     * @return static
     */
    public function setResourceId(string $id): static
    {
        $this->resource_id = $id;
        return $this;
    }

    /**
     * Resource ID getter
     *
     * @return string
     */
    public function getResourceId(): string
    {
        return $this->resource_id;
    }

    /**
     * Created setter
     *
     * @param DateTime $dateTime Created date
     *
     * @return static
     */
    public function setCreated(Datetime $dateTime): static
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Created getter
     *
     * @return DateTime
     */
    public function getCreated(): Datetime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Modification timestamp setter
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
     * Modification timestamp getter
     *
     * @return bool
     */
    public function getModificationTimestamp(): int
    {
        return $this->mtime;
    }

    /**
     * Data setter
     *
     * @param string $data Data
     *
     * @return static
     */
    public function setData(string $data): static
    {
        // Avoid messing with $this->data that RowGateway uses for storing the values:
        $this->offsetSet('data', $data);
        return $this;
    }

    /**
     * Data getter
     *
     * @return string
     */
    public function getData(): string
    {
        // Avoid messing with $this->data that RowGateway uses for storing the values:
        return $this->offsetGet('data');
    }
}
