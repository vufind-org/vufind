<?php

/**
 * Trait which returns pre-configured db mocks
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace FinnaTest\Traits;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Finna\Db\Entity\User;
use Finna\Db\Service\AccessTokenService;
use Laminas\Http\Client;
use Laminas\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\AccessToken;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\PersistenceManager;
use VuFindHttp\HttpService;

/**
 * Trait which returns pre-configured db mocks
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait MockServicesTrait
{
    /**
     * Get Finna access token service as a mocked service
     *
     * @return MockObject
     */
    public function getFinnaAccessTokenService(): MockObject
    {
        $accessTokens = [
            [
                'id' => 1,
                'type' => 'access_token_other',
                'user' => new User(),
                'created' => new DateTime('2020-01-01 00:00:00'),
                'data' =>  'something:else',
                'revoked' => 0,
            ],
            [
                'id' => 2,
                'type' => 'api_key',
                'user' => new User(),
                'created' => new DateTime('2020-01-01 00:00:00'),
                'data' => 'test_key_123',
                'revoked' => 0,
            ],
            [
                'id' => 3,
                'type' => 'api_key',
                'user' => new User(),
                'created' => new DateTime('2020-01-01 00:00:00'),
                'data' => 'not_going_to_work_123',
                'revoked' => 1,
            ],
        ];

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->any())
            ->method('findOneBy')
            ->willReturnCallback(
                function (array $params) use ($accessTokens): ?MockObject {
                    foreach ($accessTokens as $token) {
                        foreach ($params as $key => $value) {
                            if ($token[$key] !== $value) {
                                continue 2;
                            }
                        }
                        return $this->getMockedEntity(AccessToken::class, $token);
                    }
                    return null;
                }
            );
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects($this->any())
            ->method('getRepository')
            ->with(AccessTokenEntityInterface::class)
            ->willReturn($repository);
        $accessTokenService = $this->getMockBuilder(AccessTokenService::class)->onlyMethods([])
            ->setConstructorArgs([
                $entityManager,
                $this->createMock(EntityPluginManager::class),
                $this->createMock(PersistenceManager::class),
            ])
            ->getMock();

        return $accessTokenService;
    }

    /**
     * Get http service
     *
     * @param array $urlAndResponseMap Url and response map. Url is the parameter for createClient.
     *                                 Value is an array containing keys 'success', 'body'
     *                                 which will be returned by isSuccess and getBody functions as a response.
     *
     * @return MockObject
     */
    public function getHttpService(array $urlAndResponseMap): MockObject
    {
        $httpService = $this->getMockBuilder(HttpService::class)
            ->onlyMethods(['createClient'])->disableOriginalConstructor()->getMock();
        foreach ($urlAndResponseMap as $url => $responseData) {
            $responseMock = $this->getMockBuilder(Response::class)
                ->onlyMethods(['isSuccess', 'getBody'])->disableOriginalConstructor()->getMock();
            $responseMock->expects($this->any())->method('isSuccess')->willReturn($responseData['success']);
            $responseMock->expects($this->any())->method('getBody')->willReturn($responseData['body']);

            $clientMock = $this->getMockBuilder(Client::class)
                ->onlyMethods(['send'])->disableOriginalConstructor()->getMock();
            $clientMock->expects($this->any())->method('send')->willReturn($responseMock);
            $httpService->expects($this->any())->method('createClient')->with($url)->willReturn($clientMock);
        }

        return $httpService;
    }

    /**
     * Create a mocked entity
     *
     * @param string      $name     Entity class name
     * @param array       $data     Array containing data as key => value
     * @param ?MockObject $template Template for row object
     *
     * @return MockObject
     */
    public function getMockedEntity(string $name, array $data, ?MockObject $template = null): MockObject
    {
        if (null === $template) {
            $rowObject = $this->getMockBuilder($name)->onlyMethods([])->disableOriginalConstructor()->getMock();
        } else {
            $rowObject = clone $template;
        }

        foreach ($data as $key => $value) {
            $method = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            $rowObject->$method($value);
        }
        return $rowObject;
    }
}
