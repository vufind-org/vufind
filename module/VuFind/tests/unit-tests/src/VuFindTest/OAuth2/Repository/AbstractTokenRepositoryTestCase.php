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
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\AccessToken as AccessToken;
use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Service\AccessTokenService;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Db\Table\User;
use VuFind\OAuth2\Entity\ClientEntity;
use VuFind\OAuth2\Repository\AccessTokenRepository;
use VuFind\OAuth2\Repository\AuthCodeRepository;
use VuFind\OAuth2\Repository\RefreshTokenRepository;

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
    protected $accessTokenEntity;

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
     * OaiResumption service object to test.
     *
     * @param MockObject  $entityManager Mock entity manager object
     * @param MockObject  $pluginManager Mock plugin manager object
     * @param ?MockObject $oaiResumption Mock OaiResumption entity object
     *
     * @return MockObject
     */
    protected function getService(
        $entityManager,
        $pluginManager,
        $accessToken = null,
    ) {
        $serviceMock = $this->getMockBuilder(AccessTokenService::class)
            ->onlyMethods(['createEntity'])
            ->setConstructorArgs([$entityManager, $pluginManager])
            ->getMock();
        if ($accessToken) {
            $serviceMock->expects($this->once())->method('createEntity')
                ->willReturn($accessToken);
        }
        return $serviceMock;
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
        $pluginManager = $this->getMockBuilder(
            \VuFind\Db\Entity\PluginManager::class
        )->disableOriginalConstructor()
            ->getMock();
        if ($setExpectation) {
            $pluginManager->expects($this->once())->method('get')
                ->with($this->equalTo(AccessToken::class))
                ->willReturn(new AccessToken());
        }
        return $pluginManager;
    }

    /**
     * Mock entity manager.
     *
     * @param int $count Expectation count
     *
     * @return MockObject
     */
    protected function getEntityManager($count = 0)
    {
        $entityManager = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQuery','persist','flush'])
            ->getMock();
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $entityManager->expects($this->any())->method('createQuery')->willReturn($query);
        $entityManager->expects($this->exactly($count))->method('persist');
        $entityManager->expects($this->exactly($count))->method('flush');
        return $entityManager;
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

    /*
    * Create User table
    *
    * @return MockObject&User
    */
   protected function getMockUserTable(): User
   {
       $getByIdCallback = function (
           $id
       ): ?UserRow {
           $username = 'test';
           return $this->createUserRow(compact('id', 'username'));
       };

       $accessTokenTable = $this->getMockBuilder(User::class)
           ->disableOriginalConstructor()
           ->onlyMethods(['getById'])
           ->getMock();
       $accessTokenTable->expects($this->any())
           ->method('getById')
           ->willReturnCallback($getByIdCallback);

       return $accessTokenTable;
   }

    /**
 * Mock Access token entity
 *
 * @return MockObject&AccessTokenEntityInterface
 */
 protected function getMockAccessTokenEntity(): AccessTokenEntityInterface
{
    $this->accessTokenEntity = $this->createMock(AccessToken::class);
    $this->accessTokenEntity->expects($this->any())->method('getId')->willReturn((int)$this->createTokenId());
    $this->accessTokenEntity->expects($this->any())->method('getType')->willReturn('oauth2_access_token');
    //$accessTokenEntity->expects($this->any())->method('getData')->willReturn(json_encode());
    $this->accessTokenEntity->expects($this->any())->method('isRevoked')->willReturn(true);
    $user = $this->createMock(\VuFind\Db\Entity\UserEntityInterface::class);
    $user->expects($this->any())->method('getId')->willReturn(1);
    $this->accessTokenEntity->expects($this->any())->method('getUser')->willReturn($user->getId());
    return $this->accessTokenEntity;
}

    /**
     * Create User row
     *
     * @param array $data Row data
     *
     * @return MockObject&UserRow
     */
    protected function createUserRow(array $data): UserRow
    {
        $result = $this->getMockBuilder(UserRow::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['initialize'])
            ->getMock();
        $result->populate($data);
        return $result;
    }

    /**
    * Create Access token service
    *
    * @return MockObject&AccessTokenServiceInterface
    */
    protected function getMockAccessTokenService(): AccessTokenServiceInterface
    {
         $accessTokenEntity = $this->getMockAccessTokenEntity();
        // $accessTokenService = $this->getMockBuilder(AccessTokenService::class)
        //     ->disableOriginalConstructor()
        //     ->onlyMethods(
        //         [
        //             'getByIdAndType',
        //             'getNonce',
        //             'storeNonce',
        //         ]
        //     )
        //     ->getMock();
        // $accessTokenService->expects($this->any())
        //     ->method('getByIdAndType')
        //     ->willReturnCallback(function ($id, $type, $create) use ($accessTokenEntity) {
        //         return $accessTokenEntity;
        //     });                         
        // $accessTokenService->expects($this->any())
        //     ->method('getNonce')
        //     ->willReturnCallback(function () use ($accessTokenEntity) {
        //         return $accessTokenEntity;
        //     });
        
        // $accessTokenService->expects($this->any())
        //     ->method('storeNonce')
        //     ->willReturnCallback(function ($nonce) use ($accessTokenEntity) {
        //         return $accessTokenEntity;
        //     });
        $entityManager = $this->getEntityManager(1);
        $pluginManager = $this->getPluginManager(true);
        $accessTokenService = $this->getService($entityManager, $pluginManager);
        return $accessTokenService;
}

    /**
     * Create User service
     *
     * @return MockObject&UserServiceInterface
     */
    protected function getMockUserService(): UserServiceInterface
    {
        $userTable = $this->getMockUserTable();
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->any())
            ->method('getUserByField')
            ->willReturnCallback(
                function ($fieldName, $fieldValue) use ($userTable) {
                    $this->assertEquals('id', $fieldName);
                    return $userTable->getById($fieldValue);
                }
            );
        return $userService;
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