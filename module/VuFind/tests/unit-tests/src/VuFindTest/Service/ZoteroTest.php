<?php

/**
 * Zotero Service Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Laminas\Cache\Storage\Adapter\AdapterOptions;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Session\Container;
use League\OAuth1\Client\Credentials\CredentialsException;
use League\OAuth1\Client\Credentials\TemporaryCredentials;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\AccessToken;
use VuFind\Db\Entity\User;
use VuFind\Db\Service\AccessTokenService;
use VuFind\Export\Zotero\ZoteroOAuth;
use VuFind\Export\Zotero\ZoteroService;
use VuFind\Export\Zotero\ZoteroTokenCredentials;
use VuFind\Http\GuzzleService;
use VuFindTest\Feature\FixtureTrait;

/**
 * Zotero Service Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ZoteroTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * OAuth params
     *
     * @var array
     */
    protected array $oauthParams = [
        'oauth_token' => 'token',
        'oauth_verifier' => 'verifier',
    ];

    /**
     * Zotero export URL
     *
     * @var string
     */
    protected string $zoteroExportUrl = 'https://api.zotero.org/users/zotero_user_id/items';

    /**
     * Cached access token
     *
     * @var ?AccessToken
     */
    protected ?AccessToken $storedAccessToken = null;

    /**
     * Container id for session storage
     *
     * @var int
     */
    protected static int $containerId = 0;

    /**
     * Test Zotero export.
     *
     * @return void
     */
    public function testExport(): void
    {
        $zotero = $this->getZoteroService(true);
        $user = $this->getUser();
        // First export goes to authorization:
        $this->assertEquals('https://localhost/authorization', $zotero->export($user, 'https://localhost/callback'));
        // Emulate return from Zotero authorization:
        $this->assertEquals(1, $zotero->handleAuthCallback($user, $this->oauthParams));
        // Subsequent call does export directly:
        $this->assertEquals(1, $zotero->export($user, 'https://localhost/callback'));
    }

    /**
     * Test authorization denied.
     *
     * @return void
     */
    public function testAuthorizationDenied(): void
    {
        $zotero = $this->getZoteroService(false, true);
        $user = $this->getUser();
        // First export goes to authorization:
        $this->assertEquals('https://localhost/authorization', $zotero->export($user, 'https://localhost/callback'));
        // Check that emulated return from Zotero authorization would throw:
        $this->expectExceptionMessage('An error has occurred');
        $zotero->handleAuthCallback($user, $this->oauthParams);
    }

    /**
     * Test unexpected call to authorization callback.
     *
     * @return void
     */
    public function testUnexpectedAuthorization(): void
    {
        $zotero = $this->getZoteroService(false);
        $user = $this->getUser();
        // Check that emulated return from Zotero authorization would redirect to authorization again:
        $this->assertEquals('https://localhost/authorization', $zotero->handleAuthCallback($user, $this->oauthParams));
    }

    /**
     * Create Zotero service.
     *
     * @param bool $expectCounts Expect normal call counts? (otherwise testing for failure situations)
     * @param bool $denyAuth     Deny authorization?
     *
     * @return ZoteroService
     */
    protected function getZoteroService(bool $expectCounts, bool $denyAuth = false): ZoteroService
    {
        // Clear any stored access token:
        $this->storedAccessToken = null;

        $sessionContainer = new Container('Zotero' . (++static::$containerId));

        $tempCredentials = new TemporaryCredentials();
        $tempCredentials->setIdentifier('temp_id');
        $tempCredentials->setSecret('temp_secret');
        $credentials = new ZoteroTokenCredentials();
        $credentials->setIdentifier('identifier');
        $credentials->setSecret('secret');
        $credentials->setUserId('zotero_user_id');
        $zoteroOAuth = $this->createMock(ZoteroOAuth::class);
        $zoteroOAuth->expects($expectCounts ? $this->once() : $this->any())
            ->method('getTemporaryCredentials')
            ->willReturn($tempCredentials);
        $zoteroOAuth->expects($expectCounts ? $this->once() : $this->any())
            ->method('getAuthorizationUrl')
            ->with($tempCredentials)
            ->willReturn('https://localhost/authorization');
        $zoteroOAuth->expects($expectCounts ? $this->once() : $this->any())
            ->method('getTokenCredentials')
            ->with($tempCredentials, $this->oauthParams['oauth_token'], $this->oauthParams['oauth_verifier'])
            ->willReturnCallback(function () use ($denyAuth, $credentials) {
                if ($denyAuth) {
                    throw new CredentialsException('Authorization Denied');
                }
                return $credentials;
            });

        $accessTokenService = $this->createMock(AccessTokenService::class);
        $accessTokenService->expects($expectCounts ? $this->exactly(4) : $this->any())
            ->method('getByIdAndType')
            ->willReturnCallback(function ($id, $type, $create = true) {
                if (null === $this->storedAccessToken && $create) {
                    $this->storedAccessToken = new AccessToken();
                    $this->storedAccessToken->setId($id)
                        ->setType($type);
                }
                return $this->storedAccessToken;
            });
        $accessTokenService->expects($expectCounts ? $this->exactly(2) : $this->any())
            ->method('persistEntity')
            ->with($this->isInstanceOf(AccessToken::class))
            ->willReturnCallback(function ($accessToken) {
                $this->storedAccessToken = $accessToken;
            });

        $callbackClient = $this->createMock(Client::class);
        $callbackClient->expects($expectCounts ? $this->exactly(2) : $this->any())
            ->method('request')
            ->with('GET', 'https://localhost/callback')
            ->willReturnCallback(function () {
                return new Response(body: json_encode(['title' => 'Foo']));
            });

        $exportResponse = new Response(body: json_encode([
            'failed' => 0,
            'succeeded' => 1,
        ]));
        $exportClient = $this->createMock(Client::class);
        $exportClient->expects($expectCounts ? $this->exactly(2) : $this->any())
            ->method('request')
            ->with('POST', $this->zoteroExportUrl)
            ->willReturn($exportResponse);

        $guzzleService = $this->createMock(GuzzleService::class);
        $guzzleService->expects($expectCounts ? $this->exactly(4) : $this->any())
            ->method('createClient')
            ->willReturnCallback(fn ($url) => match ($url) {
                'https://localhost/callback' => $callbackClient,
                $this->zoteroExportUrl => $exportClient,
            });

        $cacheOptions = new AdapterOptions();
        $cache = $this->createMock(StorageInterface::class);
        $cache->expects($expectCounts ? $this->exactly(2) : $this->any())
            ->method('getOptions')
            ->willReturn($cacheOptions);
        $cache->expects($expectCounts ? $this->exactly(2) : $this->any())
            ->method('getItem')
            ->with('VuFind\Export\Zotero\ZoteroService_zotero-schema')
            ->willReturn([
                'time' => time(),
                'entry' => $this->getFixture('zotero/partial-schema.json'),
            ]);

        return new ZoteroService(
            $sessionContainer,
            $zoteroOAuth,
            $accessTokenService,
            $guzzleService,
            $cache
        );
    }

    /**
     * Get user entity.
     *
     * @return MockObject&User
     */
    protected function getUser(): MockObject&User
    {
        $user = $this->createMock(User::class);
        $user->expects($this->any())
            ->method('getId')
            ->willReturn(123);
        return $user;
    }
}
