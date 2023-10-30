<?php

/**
 * OpenID Connect identity repository implementation.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\OAuth2\Repository;

use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use VuFind\Db\Table\AccessToken;
use VuFind\Db\Table\User;
use VuFind\ILS\Connection;
use VuFind\OAuth2\Entity\UserEntity;

/**
 * OpenID Connect repository implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class IdentityRepository implements IdentityProviderInterface
{
    /**
     * User table
     *
     * @var User
     */
    protected $userTable;

    /**
     * Access token table
     *
     * @var AccessToken
     */
    protected $accessTokenTable;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * OAuth2 configuration
     *
     * @var array
     */
    protected $oauth2Config;

    /**
     * Constructor
     *
     * @param User        $userTable  User table
     * @param AccessToken $tokenTable Access token table
     * @param Connection  $ils        ILS connection
     * @param array       $config     OAuth2 configuration
     */
    public function __construct(
        User $userTable,
        AccessToken $tokenTable,
        Connection $ils,
        array $config
    ) {
        $this->userTable = $userTable;
        $this->accessTokenTable = $tokenTable;
        $this->ils = $ils;
        $this->oauth2Config = $config;
    }

    /**
     * Get a user entity by identifier.
     *
     * @param int $identifier User ID
     *
     * @return ?UserEntity
     */
    public function getUserEntityByIdentifier($identifier)
    {
        if ($user = $this->userTable->getById($identifier)) {
            return new UserEntity(
                $user,
                $this->ils,
                $this->oauth2Config,
                $this->accessTokenTable
            );
        }
        return null;
    }
}
