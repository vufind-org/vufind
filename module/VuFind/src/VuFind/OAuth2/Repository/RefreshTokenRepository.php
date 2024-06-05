<?php

/**
 * OAuth2 refresh token repository implementation.
 *
 * PHP version 8
 *
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
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\OAuth2\Repository;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use VuFind\Auth\InvalidArgumentException;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\OAuth2\Entity\RefreshTokenEntity;

/**
 * OAuth2 refresh token repository implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RefreshTokenRepository extends AbstractTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * Constructor
     *
     * @param array                       $oauth2Config       OAuth2 configuration
     * @param AccessTokenServiceInterface $accessTokenService Access token service
     * @param UserServiceInterface        $userService        User service
     */
    public function __construct(
        array $oauth2Config,
        AccessTokenServiceInterface $accessTokenService,
        UserServiceInterface $userService
    ) {
        parent::__construct(
            'oauth2_refresh_token',
            RefreshTokenEntity::class,
            $oauth2Config,
            $accessTokenService,
            $userService
        );
    }

    /**
     * Create a new refresh token
     *
     * @return RefreshTokenEntityInterface
     */
    public function getNewRefreshToken()
    {
        return $this->getNew();
    }

    /**
     * Persists a new refresh token to permanent storage.
     *
     * @param RefreshTokenEntityInterface $entity Refresh token entity
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $entity)
    {
        $this->persistNew($entity);
    }

    /**
     * Revoke a refresh token.
     *
     * @param string $tokenId Token ID
     *
     * @return void
     */
    public function revokeRefreshToken($tokenId)
    {
        $this->revoke($tokenId);
    }

    /**
     * Check if the refresh token has been revoked.
     *
     * @param string $tokenId Token ID
     *
     * @return bool Return true if this token has been revoked
     */
    public function isRefreshTokenRevoked($tokenId)
    {
        return $this->isRevoked($tokenId);
    }
}
