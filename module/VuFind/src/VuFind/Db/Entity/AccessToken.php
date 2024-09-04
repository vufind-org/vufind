<?php

/**
 * Entity model for access_token table
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
 * Entity model for access_token table
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 *
 * @ORM\Table(name="access_token")
 * @ORM\Entity
 */
class AccessToken implements AccessTokenEntityInterface
{
    /**
     * Unique ID.
     *
     * @var string
     *
     * @ORM\Column(name="id",
     *          type="string",
     *          length=255,
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    protected $id;

    /**
     * Token type.
     *
     * @var string
     *
     * @ORM\Column(name="type",
     *          type="string",
     *          length=128,
     *          nullable=false
     * )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    protected $type;

    /**
     * User.
     *
     * @var UserEntityInterface
     *
     * @ORM\ManyToOne(targetEntity="VuFind\Db\Entity\User")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="user_id",
     *              referencedColumnName="id")
     * })
     */
    protected $user;

    /**
     * Creation date.
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created",
     *          type="datetime",
     *          nullable=false,
     *          options={"default"="2000-01-01 00:00:00"}
     * )
     */
    protected $created = '2000-01-01 00:00:00';

    /**
     * Data.
     *
     * @var ?string
     *
     * @ORM\Column(name="data", type="text", length=16777215, nullable=true)
     */
    protected $data;

    /**
     * Flag indicating status of the token.
     *
     * @var bool
     *
     * @ORM\Column(name="revoked", type="boolean", nullable=false)
     */
    protected $revoked = '0';

    /**
     * Set access token identifier.
     *
     * @param string $id Access Token Identifier
     *
     * @return AccessTokenEntityInterface
     */
    public function setId(string $id): AccessTokenEntityInterface
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
        return $this->id;
    }

    /**
     * Get type of access token.
     *
     * @return ?string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set type of access token.
     *
     * @param ?string $type Access Token Type
     *
     * @return AccessTokenEntityInterface
     */
    public function setType(?string $type): AccessTokenEntityInterface
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User owning token
     *
     * @return AccessTokenEntityInterface
     */
    public function setUser(?UserEntityInterface $user): AccessTokenEntityInterface
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get user ID.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user;
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
     * @return AccessTokenEntityInterface
     */
    public function setCreated(DateTime $dateTime): AccessTokenEntityInterface
    {
        $this->created = $dateTime;
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
     * @return AccessTokenEntityInterface
     */
    public function setData(?string $data): AccessTokenEntityInterface
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Is the access token revoked?
     *
     * @return bool
     */
    public function isRevoked(): bool
    {
        return (bool)$this->revoked;
    }

    /**
     * Set revoked status.
     *
     * @param bool $revoked Revoked
     *
     * @return AccessTokenEntityInterface
     */
    public function setRevoked(bool $revoked): AccessTokenEntityInterface
    {
        $this->revoked = $revoked ? '1' : '0';
        return $this;
    }
}
