<?php

/**
 * OpenID Connect identity repository implementation.
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

use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
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
     * Constructor
     *
     * @param UserServiceInterface        $userService        User service
     * @param AccessTokenServiceInterface $accessTokenService Access token service
     * @param ?Connection                 $ils                ILS connection
     * @param array                       $oauth2Config       OAuth2 configuration
     * @param ILSAuthenticator            $ilsAuthenticator   ILS authenticator
     */
    public function __construct(
        protected UserServiceInterface $userService,
        protected AccessTokenServiceInterface $accessTokenService,
        protected ?Connection $ils,
        protected array $oauth2Config,
        protected ILSAuthenticator $ilsAuthenticator
    ) {
    }

    /**
     * Get a user entity by identifier.
     *
     * @param int|string $identifier User Identifier
     *
     * @return ?UserEntity
     */
    public function getUserEntityByIdentifier($identifier)
    {
        $userIdentifierField = $this->oauth2Config['Server']['userIdentifierField'] ?? 'id';
        if ($user = $this->userService->getUserByField($userIdentifierField, $identifier)) {
            return new UserEntity(
                $user,
                $this->ils,
                $this->oauth2Config,
                $this->accessTokenService,
                $this->ilsAuthenticator
            );
        }
        return null;
    }
}
