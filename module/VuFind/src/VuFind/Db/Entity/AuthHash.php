<?php

/**
 * Entity model for auth_hash table
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
 * AuthHash
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="auth_hash",
 * uniqueConstraints={@ORM\UniqueConstraint(name="hash_type",
 *                  columns={"hash", "type"})},
 * indexes={@ORM\Index(name="created", columns={"created"}),
 * @ORM\Index(name="session_id", columns={"session_id"})}
 * )
 * @ORM\Entity
 */
class AuthHash implements AuthHashEntityInterface
{
    /**
     * Unique ID.
     *
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id",
     *          type="bigint",
     *          nullable=false,
     *          options={"unsigned"=true}
     * )
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * Session ID.
     *
     * @var ?string
     *
     * @ORM\Column(name="session_id", type="string", length=128, nullable=true)
     */
    protected $sessionId;

    /**
     * Hash value.
     *
     * @var string
     *
     * @ORM\Column(name="hash", type="string", length=255, nullable=false)
     */
    protected $hash = '';

    /**
     * Type of the hash.
     *
     * @var ?string
     *
     * @ORM\Column(name="type", type="string", length=50, nullable=true)
     */
    protected $type;

    /**
     * Data.
     *
     * @var ?string
     *
     * @ORM\Column(name="data", type="text", length=16777215, nullable=true)
     */
    protected $data;

    /**
     * Creation date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="CURRENT_TIMESTAMP"}
     * )
     */
    protected $created = 'CURRENT_TIMESTAMP';

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
     * Get PHP session id string.
     *
     * @return ?string
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Set PHP session id string.
     *
     * @param ?string $sessionId PHP Session id string
     *
     * @return static
     */
    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Get hash value.
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Set hash value.
     *
     * @param string $hash Hash Value
     *
     * @return static
     */
    public function setHash(string $hash): static
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * Get type of hash.
     *
     * @return ?string
     */
    public function getHashType(): ?string
    {
        return $this->type;
    }

    /**
     * Set type of hash.
     *
     * @param ?string $type Hash Type
     *
     * @return static
     */
    public function setHashType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get data.
     *
     * @return ?string
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * Set data.
     *
     * @param ?string $data Data
     *
     * @return static
     */
    public function setData(?string $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * Set created date.
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
}
