<?php

/**
 * Entity model interface for access tokens.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Entity;

/**
 * Entity model interface for access tokens.
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface AccessTokenEntityInterface extends EntityInterface
{
    /**
     * Set user ID.
     *
     * @param ?UserEntityInterface $user User owning token
     *
     * @return AccessTokenEntityInterface
     */
    public function setUser(?UserEntityInterface $user): AccessTokenEntityInterface;

    /**
     * Set data.
     *
     * @param string $data Data
     *
     * @return AccessTokenEntityInterface
     */
    public function setData(string $data): AccessTokenEntityInterface;

    /**
     * Is the access token revoked?
     *
     * @return bool
     */
    public function isRevoked(): bool;

    /**
     * Set revoked status.
     *
     * @param bool $revoked Revoked
     *
     * @return AccessTokenEntityInterface
     */
    public function setRevoked(bool $revoked): AccessTokenEntityInterface;
}
