<?php

/**
 * OAuth2 access token repository implementation.
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

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use VuFind\Auth\InvalidArgumentException;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\OAuth2\Entity\AccessTokenEntity;

/**
 * OAuth2 access token repository implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AccessTokenRepository extends AbstractTokenRepository implements AccessTokenRepositoryInterface
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
            'oauth2_access_token',
            AccessTokenEntity::class,
            $oauth2Config,
            $accessTokenService,
            $userService
        );
    }

    /**
     * Create a new access token
     *
     * @param ClientEntityInterface  $clientEntity   Client entity
     * @param ScopeEntityInterface[] $scopes         Scopes
     * @param mixed                  $userIdentifier User identifier
     *
     * @return AccessTokenEntityInterface
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null
    ) {
        $accessToken = $this->getNew();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);
        return $accessToken;
    }

    /**
     * Persists a new access token to permanent storage.
     *
     * @param AccessTokenEntityInterface $entity Access token entity
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $entity)
    {
        $this->persistNew($entity);
    }

    /**
     * Revoke an access token.
     *
     * @param string $tokenId Token ID
     *
     * @return void
     */
    public function revokeAccessToken($tokenId)
    {
        $this->revoke($tokenId);
    }

    /**
     * Check if the access token has been revoked.
     *
     * @param string $tokenId Token ID
     *
     * @return bool Return true if this token has been revoked
     */
    public function isAccessTokenRevoked($tokenId)
    {
        return $this->isRevoked($tokenId);
    }
}
