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

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Action\Install\AbstractInstallAction;
use VuFind\Action\Install\HomeAction;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFindHttp\HttpService;
use VuFindSearch\Service as SearchService;
use VuFindTest\Feature\ReflectionTrait;

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
    use ReflectionTrait;

    /**
     * Test getMinimalPhpVersion with actual composer.json file.
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithActualData(): void
    {
        // Test the method in the abstract base class by instantiating a concrete class extending it:
        $action = new HomeAction(
            $this->createMock(CacheManager::class),
            $this->createMock(Connection::class),
            $this->createMock(SearchService::class),
            $this->createMock(PathResolver::class),
            $this->createMock(ConfigManagerInterface::class),
            $this->createMock(ServerUrlHelper::class),
            $this->createMock(HttpService::class),
            $this->createMock(TagServiceInterface::class),
            $this->createMock(UserServiceInterface::class),
            $this->createMock(UserCardServiceInterface::class),
            []
        );
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
