<?php

/**
 * UserListService test class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Generator;
use VuFind\Db\Entity\PluginManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\UserListService;
use VuFind\Db\Service\UserListServiceInterface;

use function is_int;
use function is_object;

/**
 * UserListService test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserListServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get user list service
     *
     * @param ?EntityManager $entityManager Entity manager.
     * @param array          $onlyMethods   Array containing mock only methods values.
     *
     * @return UserListServiceInterface
     */
    protected function getService(
        ?EntityManager $entityManager = null,
        array $onlyMethods = ['getDoctrineReference']
    ): UserListServiceInterface {
        $service = $this->getMockBuilder(UserListService::class)->onlyMethods($onlyMethods)
            ->setConstructorArgs([
                $entityManager ?? $this->createMock(EntityManager::class),
                $this->createMock(PluginManager::class),
                $this->createMock(PersistenceManager::class),
            ])->getMock();
        return $service;
    }

    /**
     * Get entity manager
     *
     * @param array $expected Expected values for setParameters
     *
     * @return EntityManager
     */
    protected function getEntityManager(array $expected): EntityManager
    {
        $entityManager = $this->createMock(EntityManager::class);
        $queryObject = $this->createMock(Query::class);
        $queryObject->expects($this->any())->method('getSingleColumnResult')->willReturn([]);
        $queryObject->expects($this->once())->method('setParameters')->willReturnCallback(
            function ($params) use ($expected): void {
                $params = array_map(
                    fn ($param) => is_object($param) ? $param->getId() : $param,
                    $params,
                );
                $this->assertEquals($expected['params'], $params);
            }
        );
        $queryObject->expects($this->any())->method('getResult')->willReturn([]);
        $entityManager->expects($this->once())->method('createQuery')->willReturnCallback(
            function ($dql) use ($expected, $queryObject) {
                // Assert that all the set parameters have been added to the dql properly in form of :param
                foreach (array_keys($expected['params']) as $key) {
                    $this->assertStringContainsString(":$key", $dql);
                }
                return $queryObject;
            }
        );
        return $entityManager;
    }

    /**
     * Data provider
     *
     * @return Generator
     */
    public static function getTestPublicListsData(): Generator
    {
        yield 'no params' => [
            [
                [],
                [],
                [],
            ],
            [
                'params' => [
                    'public' => true,
                ],
            ],
        ];
        yield 'included in' => [
            [
                ['123', '312'],
                [],
                [],
            ],
            [
                'params' => [
                    'public' => true,
                    'includeFilter' => ['123', '312'],
                ],
            ],
        ];
        yield 'excluded in' => [
            [
                [],
                ['123', '312'],
                [],
            ],
            [
                'params' => [
                    'public' => true,
                    'excludeFilter' => ['123', '312'],
                ],
            ],
        ];
        yield 'types set' => [
            [
                [],
                [],
                ['SOME', 'TYPES'],
            ],
            [
                'params' => [
                    'public' => true,
                    'types' => ['SOME', 'TYPES'],
                ],
            ],
        ];
        yield 'all set' => [
            [
                ['123', '456'],
                ['333', '222'],
                ['yes'],
            ],
            [
                'params' => [
                    'public' => true,
                    'types' => ['yes'],
                    'includeFilter' => ['123', '456'],
                    'excludeFilter' => ['333', '222'],
                ],
            ],
        ];
    }

    /**
     * Test get public lists query
     *
     * @param array $params   Params for calling method
     * @param array $expected Expected values for createQuery and setParameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPublicListsData')]
    public function testPublicLists($params, $expected): void
    {
        $result = $this->getService($this->getEntityManager($expected))->getPublicLists(...$params);
        $this->assertEquals([], $result);
    }

    /**
     * Data provider
     *
     * @return Generator
     */
    public static function getTestUserListsAndCountsByUserData(): Generator
    {
        yield 'test with user entity mock' => [
            [
                'mock' => 123321,
                [],
            ],
            [
                'params' => [
                    'user' => 123321,
                ],
            ],
        ];
        yield 'test with user id' => [
            [
                1,
                [],
            ],
            [
                'params' => [
                    'user' => 1,
                ],
            ],
        ];
        yield 'test with user id and types' => [
            [
                1,
                ['SOME', 'TYPES'],
            ],
            [
                'params' => [
                    'user' => 1,
                    'types' => ['SOME', 'TYPES'],
                ],
            ],
        ];
    }

    /**
     * Test get user lists and counts by user
     *
     * @param array $params   Params for calling method
     * @param array $expected Expected values for setParameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestUserListsAndCountsByUserData')]
    public function testGetUserListsAndCountsByUser($params, $expected): void
    {
        $mockUser = $this->createMock(UserEntityInterface::class);
        if ($referenceId = $params['mock'] ?? false) {
            unset($params['mock']);
            array_unshift($params, $mockUser);
        } else {
            $referenceId = $params[0];
        }
        $mockUser->expects($this->any())->method('getId')->willReturn($referenceId);
        $service = $this->getService($this->getEntityManager($expected));
        $service->expects($this->once())->method('getDoctrineReference')->willReturnCallback(
            function ($className, $userOrId) use ($referenceId, $mockUser) {
                $this->assertEquals(UserEntityInterface::class, $className);
                $this->assertEquals($referenceId, is_int($userOrId) ? $userOrId : $userOrId->getId());
                return $mockUser;
            }
        );
        $result = $service->getUserListsAndCountsByUser(...$params);
        $this->assertEquals([], $result);
    }

    /**
     * Data provider
     *
     * @return Generator
     */
    public static function getTestGetUserListsByTagAndIdData(): Generator
    {
        yield 'test with multiple tags' => [
            [
                ['tag1', 'tag2', 'tag3'],
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'tag0' => 'tag1',
                    'tag1' => 'tag2',
                    'tag2' => 'tag3',
                    'public' => true,
                    'cnt' => 3,
                ],
            ],
        ];
        yield 'test with single tag and list id' => [
            [
                'tag1',
                12333,
            ],
            [
                'params' => [
                    'tag0' => 'tag1',
                    'types' => ['default'],
                    'listId' => [12333],
                    'public' => true,
                    'cnt' => 1,
                ],
            ],
        ];
        yield 'test with single tag, multiple lists and no public' => [
            [
                'tag1',
                [12333, 21322],
                false,
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'listId' => [12333, 21322],
                    'cnt' => 1,
                    'tag0' => 'tag1',
                ],
            ],
        ];
        yield 'test with no tags, no lists, public' => [
            [
                null,
                null,
                true,
                false,
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'public' => true,
                ],
            ],
        ];
        yield 'test with case sensitive tags, no lists, public' => [
            [
                ['Tags1', 'tags2'],
                null,
                true,
                false,
                true,
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'tag' => ['Tags1', 'tags2'],
                    'public' => true,
                ],
            ],
        ];
        yield 'test with case sensitive tags, no lists, public and types' => [
            [
                ['Tags1', 'tags2'],
                null,
                true,
                false,
                true,
                ['SOME', 'TEST'],
            ],
            [
                'params' => [
                    'tag' => ['Tags1', 'tags2'],
                    'types' => ['SOME', 'TEST'],
                    'public' => true,
                ],
            ],
        ];
    }

    /**
     * Test get user lists by tag and id
     *
     * @param array $params   Params for calling method
     * @param array $expected Expected values for setParameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetUserListsByTagAndIdData')]
    public function testGetUserListsByTagAndId($params, $expected): void
    {
        $service = $this->getService($this->getEntityManager($expected), ['getDoctrineReference', 'getUserListsById']);
        $service->expects($this->once())->method('getUserListsById')->willReturn([]);
        $result = $service->getUserListsByTagAndId(...$params);
        $this->assertEquals([], $result);
    }

    /**
     * Data provider
     *
     * @return Generator
     */
    public static function getTestGetListsContainingRecordData(): Generator
    {
        yield 'test with record id' => [
            [
                'test.123123',
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'recordId' => 'test.123123',
                    'source' => DEFAULT_SEARCH_BACKEND,
                ],
            ],
        ];
        yield 'test with record id and source' => [
            [
                'test.123123',
                'test_source',
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'recordId' => 'test.123123',
                    'source' => 'test_source',
                ],
            ],
        ];
        yield 'test with record id, source and user' => [
            [
                'test.123123',
                'test_source',
                'userOrId' => 554321,
            ],
            [
                'params' => [
                    'types' => ['default'],
                    'recordId' => 'test.123123',
                    'source' => 'test_source',
                    'user' => 554321,
                ],
            ],
        ];
        yield 'test with record id, source, userId and types' => [
            [
                'test.123123',
                'test_source',
                554321,
                ['TEST', 'TESTER'],
            ],
            [
                'params' => [
                    'types' => ['TEST', 'TESTER'],
                    'recordId' => 'test.123123',
                    'source' => 'test_source',
                    'user' => 554321,
                ],
            ],
        ];
    }

    /**
     * Test get lists containing record
     *
     * @param array $params   Params for calling method
     * @param array $expected Expected values for setParameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetListsContainingRecordData')]
    public function testGetListsContainingRecord($params, $expected): void
    {
        $service = $this->getService($this->getEntityManager($expected));
        if (isset($params[2]) || isset($params['userOrId'])) {
            $mockUser = $this->createMock(UserEntityInterface::class);
            if ($referenceId = $params['userOrId'] ?? false) {
                $params['userOrId'] = $mockUser;
            } else {
                $referenceId = $params[2];
            }
            $mockUser->expects($this->any())->method('getId')->willReturn($referenceId);

            $service->expects($this->once())->method('getDoctrineReference')->willReturnCallback(
                function ($className, $userOrId) use ($referenceId, $mockUser) {
                    $this->assertEquals(UserEntityInterface::class, $className);
                    $this->assertEquals($referenceId, is_int($userOrId) ? $userOrId : $userOrId->getId());
                    return $mockUser;
                }
            );
        }

        $result = $service->getListsContainingRecord(...$params);
        $this->assertEquals([], $result);
    }
}
