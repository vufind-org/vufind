<?php

/**
 * Entity model for shortlinks table
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
 * Shortlinks
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'shortlinks')]
#[ORM\UniqueConstraint(name: 'shortlinks_hash_IDX', columns: ['hash'])]
#[ORM\Entity]
class Shortlinks implements ShortlinksEntityInterface
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
     * Path (minus hostname) from shortened URL.
     *
     * @var string
     */
    #[ORM\Column(name: 'path', type: 'text', length: 16777215, nullable: false)]
    protected string $path;

    /**
     * Shortlinks hash.
     *
     * @var ?string
     */
    #[ORM\Column(name: 'hash', type: 'string', length: 32, nullable: true)]
    protected ?string $hash = null;

    /**
     * Creation timestamp.
     *
     * @var DateTime
     */
    #[ORM\Column(name: 'created', type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected DateTime $created;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set the default value as a \DateTime object
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
     * Get the path of the URL.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Set the path (e.g. /Search/Results?lookfor=foo) of the URL being shortened;
     * shortened URLs are always assumed to be within the hostname where VuFind is running.
     *
     * @param string $path Path
     *
     * @return static
     */
    public function setPath(string $path): static
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
        return $this->hash;
    }

    /**
     * Set shortlinks hash.
     *
     * @param ?string $hash Shortlinks hash
     *
     * @return static
     */
    public function setHash(?string $hash): static
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
        return $this->created;
    }

    /**
     * Set creation timestamp.
     *
     * @param DateTime $dateTime Creation timestamp
     *
     * @return static
     */
    public function setCreated(DateTime $dateTime): static
    {
        $this->created = $dateTime;
        return $this;
    }
}
