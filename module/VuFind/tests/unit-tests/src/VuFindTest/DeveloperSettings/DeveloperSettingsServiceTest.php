<?php

/**
 * DeveloperSettingsService Test Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\DeveloperSettings;

use DateTime;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\ApiKey;
use VuFind\Db\Entity\ApiKeyEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\ApiKeyServiceInterface;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\DeveloperSettings\DeveloperSettingsStatus;

use function is_bool;

/**
 * DeveloperSettingsService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DeveloperSettingsServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a FavoritesService object.
     *
     * @param array                   $config        Configuration file presenting the API_Keys section
     * @param ?ApiKeyServiceInterface $apiKeyService ApiKeyService mocked with methods
     *
     * @return DeveloperSettingsService
     */
    protected function getService(
        array $config,
        ?ApiKeyServiceInterface $apiKeyService = null
    ): DeveloperSettingsService {
        return new DeveloperSettingsService(
            $apiKeyService ?? $this->createMock(ApiKeyServiceInterface::class),
            $config
        );
    }

    /**
     * Create a mock of a class with methods.
     *
     * An example of how to use the method to create a mocked user entity with three methods:
     *
     * ```
     * $mockUser = $this->createMockWithMethods(
     *  UserEntityInterface::class,
     *  [
     *    'getId' => 1,
     *    'getFirstname' => 'Test',
     *    'getLastname' => 'Tester',
     *  ]
     * );
     * ```
     *
     * @param class-string<T> $name              Class name.
     * @param array           $methodsAndReturns Methods and returns for the mock as an associative array.
     *
     * @template T
     * @return   T
     */
    protected function createMockWithMethods(string $name, array $methodsAndReturns = []): MockObject
    {
        $mockObject = $this->createMock($name);
        foreach ($methodsAndReturns as $method => $return) {
            $mockObject->expects($this->any())->method($method)->willReturn($return);
        }
        return $mockObject;
    }

    /**
     * Get test generate new key data
     *
     * @return Generator
     */
    public static function getTestGenerateApiKeyForUserData(): Generator
    {
        $user = [
            'getEmailVerified' => new DateTime('1990-1-1'),
            'getFirstname' => 'Test',
            'getLastname' => 'Tester',
        ];
        yield 'user has no existing tokens' => [
            ['token_salt' => 'RandomTestSalt'],
            [],
            $user,
            [
                'result' => ApiKeyEntityInterface::class,
            ],
        ];
        yield 'user has no revoked tokens' => [
            ['token_salt' => 'RandomTestSalt'],
            [
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => false,
                ],
            ],
            $user,
            [
                'result' => ApiKeyEntityInterface::class,
            ],
        ];
        yield 'user has five tokens' => [
            ['token_salt' => 'RandomTestSalt'],
            [
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => false,
                ],
            ],
            $user,
            [
                'result' => false,
            ],
        ];
        yield 'user has a revoked token' => [
            ['token_salt' => 'RandomTestSalt'],
            [
                [
                    'isRevoked' => false,
                ],
                [
                    'isRevoked' => true,
                ],
                [
                    'isRevoked' => false,
                ],
            ],
            $user,
            [
                'result' => false,
            ],
        ];
        yield 'salt is missing from the configuration' => [
            [],
            [],
            $user,
            [
                'error' => 'DeveloperSettingsService: Invalid token_salt provided',
            ],
        ];
        yield 'salt is under 10 characters long' => [
            ['token_salt' => '123456'],
            [],
            $user,
            [
                'error' => 'DeveloperSettingsService: Invalid token_salt provided',
            ],
        ];
    }

    /**
     * Test generating new apiKey for user
     *
     * @param array $config   Config
     * @param array $tokens   Token methods and returns
     * @param array $user     User data
     * @param array $expected Expected value in result key, omit when error expected.
     *
     * @dataProvider getTestGenerateApiKeyForUserData
     * @return       void
     */
    public function testGenerateApiKeyForUser(
        array $config,
        array $tokens,
        array $user,
        array $expected
    ): void {
        if (isset($expected['error'])) {
            $this->expectExceptionMessage($expected['error']);
        }
        $apiKeyNew = $this->createMockWithMethods(ApiKeyEntityInterface::class, ['getTitle' => 'test']);

        $apiKeys = array_map(
            fn ($apiKey) => $this->createMockWithMethods(ApiKeyEntityInterface::class, $apiKey),
            $tokens
        );
        $apiKeyService = $this->createMockWithMethods(
            ApiKeyServiceInterface::class,
            [
                'getApiKeysForUser' => $apiKeys,
                'createEntity' => $apiKeyNew,
            ]
        );

        $userEntity = $this->createMockWithMethods(UserEntityInterface::class, $user);

        $result = $this->getService($config, $apiKeyService)->generateApiKeyForUser($userEntity, 'test');
        // No need to assert if test expects an error to be thrown.
        if (isset($expected['error'])) {
            return;
        }
        if (is_bool($result)) {
            $this->assertEquals($expected['result'], $result);
        } else {
            $this->assertEquals('test', $result->getTitle());
        }
    }

    /**
     * Get testIsApiKeyAllowed data
     *
     * @return Generator
     */
    public static function getTestIsApiKeyAllowedData(): Generator
    {
        yield 'API keys disabled' => [
            null,
            [
                'mode' => DeveloperSettingsStatus::DISABLED->value,
            ],
            null,
            true,
        ];
        yield 'API keys disabled and provided' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::DISABLED->value,
            ],
            null,
            true,
        ];
        yield 'API keys optional and not provided' => [
            null,
            [
                'mode' => DeveloperSettingsStatus::OPTIONAL->value,
            ],
            null,
            true,
        ];
        yield 'API keys optional and provided' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::OPTIONAL->value,
            ],
            [
                'isRevoked' => false,
            ],
            true,
        ];
        yield 'API keys optional and revoked' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::OPTIONAL->value,
            ],
            [
                'isRevoked' => true,
            ],
            false,
        ];
        yield 'API keys optional and provided missing' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::OPTIONAL->value,
            ],
            null,
            false,
        ];
        yield 'API keys enforced and not provided' => [
            null,
            [
                'mode' => DeveloperSettingsStatus::ENFORCED->value,
            ],
            null,
            false,
        ];
        yield 'API keys enforced and provided' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::ENFORCED->value,
            ],
            [
                'isRevoked' => false,
            ],
            true,
        ];
        yield 'API keys enforced and revoked' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::ENFORCED->value,
            ],
            [
                'isRevoked' => true,
            ],
            false,
        ];
        yield 'API keys enforced and provided missing' => [
            'testtoken',
            [
                'mode' => DeveloperSettingsStatus::ENFORCED->value,
            ],
            null,
            false,
        ];
    }

    /**
     * Test token is valid method
     *
     * @param ?string $token    Token provided in request or null
     * @param array   $config   Config
     * @param ?array  $apiKey   Methods and returns for API key entity
     * @param bool    $expected Expected value
     *
     * @dataProvider getTestIsApiKeyAllowedData
     * @return       void
     */
    public function testIsApiKeyAllowed(?string $token, array $config, ?array $apiKey, bool $expected): void
    {
        $apiKey = $apiKey ? $this->createMockWithMethods(ApiKeyEntityInterface::class, $apiKey) : null;
        $apiKeyService = $this->createMockWithMethods(ApiKeyServiceInterface::class, ['getByToken' => $apiKey]);
        $result = $this->getService($config, $apiKeyService)->isApiKeyAllowed($token);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test update last used.
     *
     * @return void
     */
    public function testUpdateLastUsed(): void
    {
        $config = [
            'mode' => DeveloperSettingsStatus::OPTIONAL->value,
            'token_salt' => 'test_salt_thing',
        ];
        $apiKey = new ApiKey();
        $date = new DateTime('01-01-1999');
        $apiKey->setTitle('heitest')->setCreated($date)->setLastUsed($date)->setRevoked(false);
        $apiKeyService = $this->createMockWithMethods(ApiKeyServiceInterface::class, ['getByToken' => $apiKey]);
        $apiKeyService->expects($this->once())->method('persistEntity')->willReturnCallback(
            function ($apiKey) use ($date): void {
                $this->assertNotEquals(
                    $apiKey->getLastUsed()->getTimestamp(),
                    $date->getTimestamp()
                );
            }
        );
        $result = $this->getService($config, $apiKeyService)->isApiKeyAllowed('test');
        $this->assertTrue($result);
    }
}
