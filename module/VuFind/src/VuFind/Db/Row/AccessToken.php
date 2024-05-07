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

/**
 * Row Definition for access_token
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AccessToken extends RowGateway implements AccessTokenEntityInterface
{
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
     * Set user ID.
     *
     * @param ?UserEntityInterface $user User owning token
     *
     * @return AccessTokenEntityInterface
     */
    public function setUser(?UserEntityInterface $user): AccessTokenEntityInterface
    {
        $this->__set('user_id', $user?->getId());
        return $this;
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
        $this->__set('data', $data);
        return $this;
    }

    /**
     * Is the access token revoked?
     *
     * @return bool
     */
    public function isRevoked(): bool
    {
        return $this->__get('revoked');
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
        $this->__set('revoked', $revoked);
        return $this;
    }
}
