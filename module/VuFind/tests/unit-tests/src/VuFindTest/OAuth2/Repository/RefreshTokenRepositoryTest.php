<?php

/**
 * OAuth2 RefreshTokenRepository tests.
 *
 * PHP version 8
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\OAuth2\Repository;

use VuFind\OAuth2\Entity\ScopeEntity;

/**
 * OAuth2 RefreshTokenRepository tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RefreshTokenRepositoryTest extends AbstractTokenRepositoryTestCase
{
    /**
     * Test refresh token repository
     *
     * @return void
     */
    public function testRefreshTokenRepository(): void
    {
        $repo = $this->getRefreshTokenRepository();
        $accessTokenRepo = $this->getAccessTokenRepository();

        $accessToken = $accessTokenRepo->getNewToken(
            $this->createClientEntity(),
            [new ScopeEntity(['identifier' => 'openid', 'description' => 'OpenID'])],
            2
        );
        $accessToken->setIdentifier($this->createTokenId());
        $token = $repo->getNewRefreshToken();
        $token->setAccessToken($accessToken);
        $tokenId = $this->createTokenId();
        $token->setIdentifier($tokenId);
        $token->setExpiryDateTime($this->createExpiryDateTime());

        $repo->persistNewRefreshToken($token);
        $this->assertEquals(
            [
                [
                    'id' => $tokenId,
                    'type' => 'oauth2_refresh_token',
                    'revoked' => false,
                    'data' => json_encode($token),
                    'user_id' => '2',
                ],
            ],
            $this->accessTokenTable
        );
        $this->assertFalse($repo->isRefreshTokenRevoked($tokenId));
        $repo->revokeRefreshToken($tokenId);
        $this->assertTrue($repo->isRefreshTokenRevoked($tokenId));
        $this->assertEquals(
            [
                [
                    'id' => $tokenId,
                    'type' => 'oauth2_refresh_token',
                    'revoked' => true,
                    'data' => json_encode($token),
                    'user_id' => '2',
                ],
            ],
            $this->accessTokenTable
        );
    }
}
