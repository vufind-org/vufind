<?php

/**
 * Class AbstractInstallActionTest.
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

declare(strict_types=1);

namespace VuFindTest\Action\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Action\Install\AbstractInstallAction;
use VuFind\Action\Install\HomeAction;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFindTest\Feature\AutowireTrait;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\ReflectionTrait;

use function strlen;

/**
 * Class AbstractInstallActionTest.
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AbstractInstallActionTest extends \PHPUnit\Framework\TestCase
{
    use AutowireTrait;
    use ConfigRelatedServicesTrait;
    use ReflectionTrait;

    /**
     * Test getMinimalPhpVersion with actual composer.json file.
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithActualData(): void
    {
        // Test the method in the abstract base class by instantiating a concrete class extending it:
        $action = $this->getAutowiredObject(HomeAction::class);
        $this->assertEquals(
            '8.2.0',
            $this->callMethod($action, 'getMinimalPhpVersion')
        );
    }

    /**
     * Simulate missing composer.json file.
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithMissingFile(): void
    {
        $action = $this->getMockActionWithComposerJson([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot find composer.json');
        $this->callMethod($action, 'getMinimalPhpVersion');
    }

    /**
     * Simulate no PHP version defined in composer.json file.
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithMissingPhpVersion(): void
    {
        $action = $this->getMockActionWithComposerJson(['name' => 'vufind/vufind']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot parse PHP version from composer.json');
        $this->callMethod($action, 'getMinimalPhpVersion');
    }

    /**
     * Test data for getMinimalPhpVersion.
     *
     * @return \Iterator
     */
    public static function getMinimalPhpVersionProvider(): \Iterator
    {
        yield [
            [
                'require' => [
                    'php' => '>=7.4.1',
                ],
            ],
            '7.4.1',
        ];
        yield [
            [
                'require' => [
                    'php' => '7.3.0',
                ],
            ],
            '7.3.0',
        ];
        yield [
            [
                'require' => [
                    'php' => '^7.2.0',
                ],
            ],
            '7.2.0',
        ];
        yield [
            [
                'require' => [
                    'php' => '~7.1.0',
                ],
                'config' => [
                    'platform' => [
                        'php' => '5.6.0',
                    ],
                ],
            ],
            '7.1.0',
        ];
        yield [
            [
                'config' => [
                    'platform' => [
                        'php' => '7.0.0',
                    ],
                ],
            ],
            '7.0.0',
        ];
        yield [
            [
                'require' => [
                    'php' => '5.8.0 || 5.9.0',
                ],
            ],
            '5.8.0',
        ];
        yield [
            [
                'require' => [
                    'php' => '^5.7',
                ],
            ],
            '5.7.0',
        ];
        yield [
            [
                'require' => [
                    'php' => '^5',
                ],
            ],
            '5.0.0',
        ];
        yield [
            [
                'config' => [
                    'platform' => [
                        'php' => '4',
                    ],
                ],
            ],
            '4.0.0',
        ];
    }

    /**
     * Test getMinimalPhpVersion with actual composer.json file.
     *
     * @param array  $json     JSON data
     * @param string $expected Expected version number
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getMinimalPhpVersionProvider')]
    public function testGetMinimalPhpVersion($json, $expected): void
    {
        $action = $this->getMockActionWithComposerJson($json);
        $this->assertEquals(
            $expected,
            $this->callMethod($action, 'getMinimalPhpVersion')
        );
    }

    /**
     * Test that an insecure configuration is repaired with hashing/encryption settings and a fresh 32-character key.
     *
     * @return void
     */
    public function testGetFixedSecurityConfigurationForInsecureConfig(): void
    {
        $action = $this->getAutowiredObject(HomeAction::class);
        $fixed = $this->callMethod($action, 'getFixedSecurityConfiguration', [[]])['Authentication'];
        $this->assertTrue($fixed['hash_passwords']);
        $this->assertTrue($fixed['encrypt_ils_password']);
        $this->assertSame('aes', $fixed['ils_encryption_algo']);
        $this->assertSame(32, strlen($fixed['ils_encryption_key']));
    }

    /**
     * Test that a secure configuration missing only an encryption key gets a key without rewriting the
     * hashing/encryption flags.
     *
     * @return void
     */
    public function testGetFixedSecurityConfigurationAddsMissingKeyOnly(): void
    {
        $action = $this->getAutowiredObject(HomeAction::class);
        $config = ['Authentication' => ['hash_passwords' => true, 'encrypt_ils_password' => true]];
        $fixed = $this->callMethod($action, 'getFixedSecurityConfiguration', [$config])['Authentication'];
        $this->assertArrayNotHasKey('hash_passwords', $fixed);
        $this->assertArrayNotHasKey('encrypt_ils_password', $fixed);
        $this->assertSame(32, strlen($fixed['ils_encryption_key']));
    }

    /**
     * Test that a fully secure configuration needs no changes.
     *
     * @return void
     */
    public function testGetFixedSecurityConfigurationForSecureConfig(): void
    {
        $action = $this->getAutowiredObject(HomeAction::class);
        $config = [
            'Authentication' => [
                'hash_passwords' => true,
                'encrypt_ils_password' => true,
                'ils_encryption_key' => 'already-set',
            ],
        ];
        $this->assertSame([], $this->callMethod($action, 'getFixedSecurityConfiguration', [$config]));
    }

    /**
     * Data provider for testHasSecureDatabase().
     *
     * @return \Iterator
     */
    public static function hasSecureDatabaseProvider(): \Iterator
    {
        yield 'insecure configuration' => [false, 0, false];

        yield 'secure config, clean database' => [true, 0, true];

        yield 'secure config, insecure rows' => [true, 1, false];
    }

    /**
     * Test that database security depends on both the configuration and the absence of insecure rows.
     *
     * @param bool $secureConfig Whether hashing/encryption are enabled in the configuration
     * @param int  $insecureRows Number of insecure rows reported by the user database service
     * @param bool $expected     Expected result
     *
     * @return void
     */
    #[DataProvider('hasSecureDatabaseProvider')]
    public function testHasSecureDatabase(bool $secureConfig, int $insecureRows, bool $expected): void
    {
        $rows = array_fill(0, $insecureRows, $this->createStub(UserEntityInterface::class));
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->method('getInsecureRows')->willReturn($rows);
        $userCardService = $this->createMock(UserCardServiceInterface::class);
        $userCardService->method('getInsecureRows')->willReturn([]);

        $config = $secureConfig
            ? ['Authentication' => ['hash_passwords' => true, 'encrypt_ils_password' => true]]
            : [];
        $action = $this->getAutowiredObject(
            HomeAction::class,
            [
                ConfigManagerInterface::class => $this->getMockConfigManager(compact('config')),
                UserServiceInterface::class => $userService,
                UserCardServiceInterface::class => $userCardService,
            ]
        );
        $this->assertSame($expected, $this->callMethod($action, 'hasSecureDatabase'));
    }

    /**
     * Test that a database error while checking security is treated as insecure.
     *
     * @return void
     */
    public function testHasSecureDatabaseTreatsErrorsAsInsecure(): void
    {
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->method('getInsecureRows')->willThrowException(new \RuntimeException('no db'));

        $config = ['Authentication' => ['hash_passwords' => true, 'encrypt_ils_password' => true]];
        $action = $this->getAutowiredObject(
            HomeAction::class,
            [
                ConfigManagerInterface::class => $this->getMockConfigManager(compact('config')),
                UserServiceInterface::class => $userService,
            ]
        );
        $this->assertFalse($this->callMethod($action, 'hasSecureDatabase'));
    }

    /**
     * Mock controller.
     *
     * @param array $json JSON data
     *
     * @return MockObject&AbstractInstallAction
     */
    protected function getMockActionWithComposerJson(
        array $json
    ): AbstractInstallAction {
        // Test the abstract base class by instantiating a concrete class extending it:
        $action = $this->getMockBuilder(HomeAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getComposerJson'])
            ->getMock();

        $action->expects($this->once())->method('getComposerJson')
            ->willReturn($json);

        return $action;
    }
}
