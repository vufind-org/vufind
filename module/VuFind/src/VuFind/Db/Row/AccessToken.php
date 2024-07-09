<?php

/**
 * Row Definition for access_token
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2022-2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;

/**
 * Row Definition for access_token
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 * 
 * @property string $id
 * @property string $type
 * @property int    $user_id
 * @property string $created
 * @property string $data
 * @property int    $revoked
 */
class AccessToken extends RowGateway implements AccessTokenEntityInterface, DbServiceAwareTrait
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use DbServiceAwareTrait;
    
    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct(['id', 'type'], 'access_token', $adapter);
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
     * Set user ID.
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
     * User getter (only null if entity has not been populated yet).
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
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
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
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;;
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
     * @param string $data Data
     *
     * @return AccessTokenEntityInterface
     */
    public function setData(string $data): AccessTokenEntityInterface
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
