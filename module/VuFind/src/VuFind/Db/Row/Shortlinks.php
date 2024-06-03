<?php

/**
 * Row Definition for shortlinks
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
use VuFind\Db\Entity\ShortlinksEntityInterface;

/**
 * Row Definition for shortlinks
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property string $path
 * @property string $hash
 * @property string $created
 */
class Shortlinks extends RowGateway implements \VuFind\Db\Entity\ShortlinksEntityInterface
{
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'shortlinks', $adapter);
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
     * Get the path of the URL.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path ?? '';
    }

    /**
     * Set the path (e.g. /Search/Results?lookfor=foo) of the URL being shortened;
     * shortened URLs are always assumed to be within the hostname where VuFind is running.
     *
     * @param string $path Path
     *
     * @return ShortlinksEntityInterface
     */
    public function setPath(string $path): ShortlinksEntityInterface
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Get shortlinks hash.
     *
     * @return ?string
     */
    public function getHash(): ?string
    {
        return $this->hash ?? null;
    }

    /**
     * Set shortlinks hash.
     *
     * @param ?string $hash Shortlinks hash
     *
     * @return ShortlinksEntityInterface
     */
    public function setHash(?string $hash): ShortlinksEntityInterface
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * Get creation timestamp.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Set creation timestamp.
     *
     * @param DateTime $dateTime Creation timestamp
     *
     * @return ShortlinksEntityInterface
     */
    public function setCreated(DateTime $dateTime): ShortlinksEntityInterface
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }
}
