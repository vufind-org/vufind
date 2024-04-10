<?php

/**
 * OAuth2 authorization code repository implementation.
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

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use VuFind\Auth\InvalidArgumentException;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\OAuth2\Entity\AuthCodeEntity;

/**
 * OAuth2 authorization code repository implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthCodeRepository extends AbstractTokenRepository implements AuthCodeRepositoryInterface
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
            'oauth2_auth_code',
            AuthCodeEntity::class,
            $oauth2Config,
            $accessTokenService,
            $userService
        );
    }

    /**
     * Create a new authentication code
     *
     * @return AuthCodeEntityInterface
     */
    public function getNewAuthCode()
    {
        return $this->getNew();
    }

    /**
     * Persists a new authentication code to permanent storage.
     *
     * @param AuthCodeEntityInterface $entity Authentication code entity
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $entity)
    {
        $this->persistNew($entity);
    }

    /**
     * Revoke an authentication code.
     *
     * @param string $tokenId Token ID
     *
     * @return void
     */
    public function revokeAuthCode($tokenId)
    {
        $this->revoke($tokenId);
    }

    /**
     * Check if the authentication code has been revoked.
     *
     * @param string $tokenId Token ID
     *
     * @return bool Return true if this code has been revoked
     */
    public function isAuthCodeRevoked($tokenId)
    {
        return $this->isRevoked($tokenId);
    }
}
