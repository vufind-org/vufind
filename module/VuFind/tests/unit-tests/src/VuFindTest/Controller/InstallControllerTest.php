<?php

/**
 * Class InstallControllerTest
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

declare(strict_types=1);

namespace VuFindTest\Controller;

use VuFind\Controller\InstallController;

/**
 * Class InstallControllerTest
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class InstallControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getMinimalPhpVersion with actual composer.json file
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithActualData()
    {
        $controller = new InstallController(
            new \VuFindTest\Container\MockContainer($this)
        );
        $method = $this->getMinimalPhpVersionMethod();
        $this->assertEquals(
            '8.1.0',
            $method->invokeArgs($controller, [])
        );
    }

    /**
     * Simulate missing composer.json file
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithMissingFile()
    {
        $controller = $this->mockControllerWithComposerJson([]);
        $method = $this->getMinimalPhpVersionMethod();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot find composer.json');
        $method->invokeArgs($controller, []);
    }

    /**
     * Simulate no PHP version defined in composer.json file
     *
     * @return void
     */
    public function testGetMinimalPhpVersionWithMissingPhpVersion()
    {
        $controller = $this->mockControllerWithComposerJson(['name' => 'vufind/vufind']);
        $method = $this->getMinimalPhpVersionMethod();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot parse PHP version from composer.json');
        $method->invokeArgs($controller, []);
    }

    /**
     * Test data for getMinimalPhpVersion
     *
     * @return array[]
     */
    public static function getMinimalPhpVersionProvider(): array
    {
        return [
            [
                [
                    'require' => [
                        'php' => '>=7.4.1',
                    ],
                ],
                '7.4.1',
            ],
            [
                [
                    'require' => [
                        'php' => '7.3.0',
                    ],
                ],
                '7.3.0',
            ],
            [
                [
                    'require' => [
                        'php' => '^7.2.0',
                    ],
                ],
                '7.2.0',
            ],
            [
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
            ],
            [
                [
                    'config' => [
                        'platform' => [
                            'php' => '7.0.0',
                        ],
                    ],
                ],
                '7.0.0',
            ],
            [
                [
                    'require' => [
                        'php' => '5.8.0 || 5.9.0',
                    ],
                ],
                '5.8.0',
            ],
            [
                [
                    'require' => [
                        'php' => '^5.7',
                    ],
                ],
                '5.7.0',
            ],
            [
                [
                    'require' => [
                        'php' => '^5',
                    ],
                ],
                '5.0.0',
            ],
            [
                [
                    'config' => [
                        'platform' => [
                            'php' => '4',
                        ],
                    ],
                ],
                '4.0.0',
            ],
        ];
    }

    /**
     * Test getMinimalPhpVersion with actual composer.json file
     *
     * @param array  $json     JSON data
     * @param string $expected Expected version number
     *
     * @dataProvider getMinimalPhpVersionProvider
     *
     * @return void
     */
    public function testGetMinimalPhpVersion($json, $expected)
    {
        $controller = $this->mockControllerWithComposerJson($json);
        $method = $this->getMinimalPhpVersionMethod();
        $this->assertEquals(
            $expected,
            $method->invokeArgs($controller, [])
        );
    }

    /**
     * Mock controller
     *
     * @param array $json JSON data
     *
     * @return InstallController
     */
    protected function mockControllerWithComposerJson(
        array $json
    ): InstallController {
        $controller = $this->getMockBuilder(InstallController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getComposerJson'])
            ->getMock();

        $controller->expects($this->once())->method('getComposerJson')
            ->willReturn($json);

        return $controller;
    }

    /**
     * Return method InstallController::getMinimalPhpVersion
     *
     * @return \ReflectionMethod
     */
    protected function getMinimalPhpVersionMethod(): \ReflectionMethod
    {
        $method = new \ReflectionMethod(
            InstallController::class,
            'getMinimalPhpVersion'
        );
        $method->setAccessible(true);
        return $method;
    }
}
