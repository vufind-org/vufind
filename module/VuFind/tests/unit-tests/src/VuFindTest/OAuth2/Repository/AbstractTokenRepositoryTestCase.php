<?php

/**
 * Abstract base class for OAuth2 token repository tests.
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

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\AccessToken;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AccessTokenService;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\OAuth2\Entity\ClientEntity;
use VuFind\OAuth2\Repository\AccessTokenRepository;
use VuFind\OAuth2\Repository\AuthCodeRepository;
use VuFind\OAuth2\Repository\RefreshTokenRepository;

use function count;

/**
 * Abstract base class for OAuth2 token repository tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class AbstractTokenRepositoryTestCase extends \PHPUnit\Framework\TestCase
{
    protected $accessTokenTable = [];

    public $entityManager = null;

    /**
     * Create AccessTokenRepository with mocks.
     *
     * @return AccessTokenRepository
     */
    protected function getAccessTokenRepository()
    {
        return new AccessTokenRepository(
            $this->getOAuth2Config(),
            $this->getMockAccessTokenService(),
            $this->getMockUserService()
        );
    }

    /**
     * Create AuthCodeRepository with mocks.
     *
     * @return AuthCodeRepository
     */
    protected function getAuthCodeRepository()
    {
        return new AuthCodeRepository(
            $this->getOAuth2Config(),
            $this->getMockAccessTokenService(),
            $this->getMockUserService()
        );
    }

    /**
     * Create RefreshTokenRepository with mocks.
     *
     * @return RefreshTokenRepository
     */
    protected function getRefreshTokenRepository()
    {
        return new RefreshTokenRepository(
            $this->getOAuth2Config(),
            $this->getMockAccessTokenService(),
            $this->getMockUserService()
        );
    }

    /**
     * Create OAuth2 Config
     *
     * @return array
     */
    protected function getOAuth2Config(): array
    {
        return ['Server' => ['userIdentifierField' => 'id']];
    }

    /**
     * Mock entity manager.
     *
     * @return MockObject
     */
    protected function getEntityManager()
    {
        $entityManager = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQuery','persist','flush'])
            ->getMock();
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $entityManager->expects($this->any())->method('createQuery')->willReturn($query);
        $entityManager->expects($this->any())->method('persist');
        $entityManager->expects($this->any())->method('flush');
        return $entityManager;
    }

    /**
     * Mock entity plugin manager.
     *
     * @param bool $setExpectation Flag to set the method expectations.
     *
     * @return MockObject
     */
    protected function getPluginManager($setExpectation = false)
    {
        $pluginManager = $this->createMock(\VuFind\Db\Entity\PluginManager::class);
        if ($setExpectation) {
            $pluginManager->expects($this->any())->method('get')
                ->with($this->equalTo(AccessToken::class))
                ->willReturn(new AccessToken());
        }
        return $pluginManager;
    }

    /**
     * Create a mock AccessTokenEntity from an array of values.
     *
     * @param array $fields Field values
     *
     * @return AccessTokenEntityInterface&MockObject
     */
    protected function createAccessTokenEntity(array $fields): AccessTokenEntityInterface&MockObject
    {
        $i = $this->findAccessTokenTableRow($fields);
        if ($i === null) {
            $i = count($this->accessTokenTable);
            $this->accessTokenTable[] = $fields;
        }
        $mock = $this->createMock(AccessTokenEntityInterface::class);
        $mock->method('getId')->willReturnCallback(fn () => (string)$this->accessTokenTable[$i]['id']);
        $mock->method('getType')->willReturnCallback(fn () => $this->accessTokenTable[$i]['type'] ?? null);
        $mock->method('getUser')->willReturnCallback(function () use ($i) {
            $userId = $this->accessTokenTable[$i]['user_id'] ?? null;
            if ($userId) {
                return $this->getMockUserService()->getUserByField('id', $userId);
            }
            return null;
        });
        $mock->method('getData')->willReturnCallback(fn () => $this->accessTokenTable[$i]['data'] ?? null);
        $mock->method('isRevoked')->willReturnCallback(fn () => $this->accessTokenTable[$i]['revoked'] ?? false);
        $mock->method('setData')->willReturnCallback(function ($data) use ($i, $mock) {
            $this->accessTokenTable[$i]['data'] = $data;
            return $mock;
        });
        $mock->method('setType')->willReturnCallback(function ($type) use ($i, $mock) {
            $this->accessTokenTable[$i]['type'] = $type;
            return $mock;
        });
        $mock->method('setUser')->willReturnCallback(function ($user) use ($i, $mock) {
            $this->accessTokenTable[$i]['user_id'] = $user?->getId();
            return $mock;
        });
        $mock->method('setRevoked')->willReturnCallback(function ($revoked) use ($i, $mock) {
            $this->accessTokenTable[$i]['revoked'] = $revoked;
            return $mock;
        });
        return $mock;
    }

    /**
     * Find a row matching the provided data in our virtual data table; return null
     * if no match is found.
     *
     * @param array $data Data to match
     *
     * @return ?int
     */
    protected function findAccessTokenTableRow(array $data): ?int
    {
        foreach ($this->accessTokenTable as $i => $row) {
            if (
                $data['id'] === $row['id']
                && $data['type'] === $row['type']
            ) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Create Access token service
     *
     * @return MockObject&AccessTokenServiceInterface
     */
    protected function getMockAccessTokenService(): AccessTokenServiceInterface&MockObject
    {
        $accessTokenService = $this->getMockBuilder(AccessTokenService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getByIdAndType', 'persistEntity', 'storeNonce', 'getNonce'])
            ->getMock();

        $getByIdAndTypeCallback = function (
            string $id,
            string $type,
            bool $create
        ): ?AccessTokenEntityInterface {
            foreach ($this->accessTokenTable as $row) {
                if (
                    $id === $row['id']
                    && $type === $row['type']
                ) {
                    return $this->createAccessTokenEntity($row);
                }
            }
            $revoked = false;
            $user_id = null;
            return $create
                ? $this->createAccessTokenEntity(
                    compact('id', 'type', 'revoked', 'user_id')
                ) : null;
        };
        $accessTokenService->expects($this->any())
            ->method('getByIdAndType')
            ->willReturnCallback($getByIdAndTypeCallback);
        $persistEntityCallback = function (AccessTokenEntityInterface $entity): void {
            $data = [
                'id' => $entity->getId(),
                'type' => $entity->getType(),
                'revoked' => $entity->isRevoked(),
                'data' => $entity->getData(),
                'user_id' => $entity->getUser()?->getId(),
            ];
            if (null !== ($i = $this->findAccessTokenTableRow($data))) {
                $this->accessTokenTable[$i] = $data;
                return;
            }
            $this->accessTokenTable[] = $data;
        };
        $accessTokenService->expects($this->any())
            ->method('persistEntity')
            ->willReturnCallback($persistEntityCallback);

        $getNonceCallback = function (int $userId): ?string {
            foreach ($this->accessTokenTable as $row) {
                if ($userId === $row['user_id']) {
                    return $row['data'];
                }
            }
            return null;
        };
        $accessTokenService->expects($this->any())
            ->method('getNonce')
            ->willReturnCallback($getNonceCallback);

        $storeNonceCallback = function (int $userId, ?string $nonce): void {
            $data = [
                'id' => 2,
                'type' => 'oauth2_access_token',
                'revoked' => false,
                'data' => $nonce,
                'user_id' => $userId,
            ];
            if (null !== ($i = $this->findAccessTokenTableRow($data))) {
                $this->accessTokenTable[$i] = $data;
                return;
            }
            $this->accessTokenTable[] = $data;
        };
        $accessTokenService->expects($this->any())
            ->method('storeNonce')
            ->willReturnCallback($storeNonceCallback);
        return $accessTokenService;
    }

    /**
     * Create User entity mock.
     *
     * @param ?int   $id       User ID
     * @param string $username User's name
     *
     * @return MockObject&UserEntityInterface&null
     */
    protected function createMockUserEntity(?int $id, string $username): UserEntityInterface&MockObject
    {
        $mockUser = $this->createMock(UserEntityInterface::class);
        if ($id !== null) {
            $mockUser->expects($this->any())
                ->method('getId')
                ->willReturn($id);
            $mockUser->expects($this->any())
                ->method('getUsername')
                ->willReturn($username);
        }
        return $mockUser;
    }

    /**
     * Create User service
     *
     * @return MockObject&UserServiceInterface
     */
    protected function getMockUserService(): UserServiceInterface&MockObject
    {
        $mockUserService = $this->createMock(UserServiceInterface::class);
        $mockUserService->expects($this->any())
            ->method('getUserByField')
            ->willReturnCallback(function (string $fieldName, $fieldValue) {
                $this->assertEquals('id', $fieldName);
                return $this->createMockUserEntity($fieldValue, 'test');
            });

        return $mockUserService;
    }

    /**
     * Create a token ID.
     *
     * Follows OAuth2 server's generateUniqueIdentifier.
     *
     * @return string
     */
    protected function createTokenId(): string
    {
        return bin2hex(random_bytes(40));
    }

    /**
     * Create an expiry datetime.
     *
     * @return \DateTimeImmutable
     */
    protected function createExpiryDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime('now+1hour')));
    }

    /**
     * Create a client entity
     *
     * @return ClientEntity
     */
    protected function createClientEntity(): ClientEntity
    {
        return new ClientEntity(
            [
                'identifier' => 'test-client',
                'name' => 'Unit Test',
                'redirectUri' => 'https://localhost',
            ]
        );
    }
}
