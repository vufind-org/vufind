<?php

/**
 * OAuth2 token repository base class.
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
use VuFind\Db\Service\AccessTokenServiceInterface;

use function is_callable;

/**
 * OAuth2 token repository base class.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AbstractTokenRepository
{
    /**
     * Token type.
     *
     * @var string
     */
    protected $tokenType;

    /**
     * Access token service
     *
     * @var AccessTokenServiceInterface
     */
    protected $accessTokenService;

    /**
     * Entity class
     *
     * @var string
     */
    protected $entityClass;

    /**
     * Constructor
     *
     * @param string                      $tokenType          Token type
     * @param string                      $entityClass        Entity class name
     * @param AccessTokenServiceInterface $accessTokenService Access token service
     */
    public function __construct(
        string $tokenType,
        string $entityClass,
        AccessTokenServiceInterface $accessTokenService
    ) {
        $this->tokenType = $tokenType;
        $this->entityClass = $entityClass;
        $this->accessTokenService = $accessTokenService;
    }

    /**
     * Persist a token in the database
     *
     * @param Object $token Token
     *
     * @return void
     */
    public function persistNew($token)
    {
        if (!is_a($token, $this->entityClass)) {
            throw new \InvalidArgumentException(
                $token::class . ' is not ' . $this->entityClass
            );
        }

        $row = $this->accessTokenService->getByIdAndType(
            $token->getIdentifier(),
            $this->tokenType
        );
        $row->setData(json_encode($token));
        $userId = null;
        if ($token instanceof RefreshTokenEntityInterface) {
            $accessToken = $token->getAccessToken();
            $userId = $accessToken->getUserIdentifier();
        } elseif (is_callable([$token, 'getUserIdentifier'])) {
            $userId = $token->getUserIdentifier();
        }
        if ($userId) {
            // Drop nonce from user id:
            [$userId] = explode('|', $userId);
        }
        $row->setUserId($userId);
        $this->accessTokenService->persistEntity($row);
    }

    /**
     * Revoke a token
     *
     * @param string $tokenId Token ID
     *
     * @return void
     */
    public function revoke($tokenId)
    {
        $token = $this->accessTokenService->getByIdAndType($tokenId, $this->tokenType, false);
        if ($token) {
            $token->setRevoked(true);
            $this->accessTokenService->persistEntity($token);
        }
    }

    /**
     * Check if a token is revoked
     *
     * @param string $tokenId Token ID
     *
     * @return bool
     */
    public function isRevoked($tokenId)
    {
        $token = $this->accessTokenService->getByIdAndType($tokenId, $this->tokenType, false);
        return $token ? $token->isRevoked() : true;
    }

    /**
     * Get a new token
     *
     * @return Object
     */
    public function getNew()
    {
        return new $this->entityClass();
    }
}
