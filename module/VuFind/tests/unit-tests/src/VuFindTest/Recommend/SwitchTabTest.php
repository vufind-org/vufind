<?php

/**
 * SwitchTab Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\SwitchTab;

/**
 * SwitchTab Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SwitchTabTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testGetActiveTab.
     *
     * @return array
     */
    public static function tabConfigProvider(): array
    {
        return [
            'First tab selected' => [
                [
                    [
                        'id' => 'A01',
                        'class' => 'class01',
                        'label' => 'label01',
                        'permission' => 'permission01',
                        'selected' => true,
                        'url' => 'http://newurl1',
                    ],
                    [
                        'id' => 'A02',
                        'class' => 'class02',
                        'label' => 'label02',
                        'permission' => 'permission02',
                        'selected' => false,
                        'url' => 'http://newurl2',
                    ],
                ],
                [
                    'id' => 'A01',
                    'class' => 'class01',
                    'label' => 'label01',
                    'permission' => 'permission01',
                    'selected' => true,
                    'url' => 'http://newurl1',
                ],
            ],
            'No tab selected' => [
                [
                    [
                        'id' => 'A01',
                        'class' => 'class01',
                        'label' => 'label01',
                        'permission' => 'permission01',
                        'selected' => false,
                        'url' => 'http://newurl1',
                    ],
                    [
                        'id' => 'A02',
                        'class' => 'class02',
                        'label' => 'label02',
                        'permission' => 'permission02',
                        'selected' => false,
                        'url' => 'http://newurl2',
                    ],
                ],
                null,
            ],
        ];
    }

    /**
     * Test getting the active tab.
     *
     * @param array $tabEnv         tabConfig
     * @param array $expectedResult expected result from getActiveTab
     *
     * @return void
     *
     * @dataProvider tabConfigProvider
     */
    public function testGetActiveTab(array $tabEnv, $expectedResult): void
    {
        $obj = new SwitchTab();
        $this->assertSame($expectedResult, $obj->getActiveTab($tabEnv));
    }

    /**
     * Data provider for testGetActiveTab.
     *
     * @return array
     */
    public static function inactiveTabConfigProvider(): array
    {
        return [
            'Test1' => [
                [
                    [
                        'id' => 'A01',
                        'class' => 'class01',
                        'label' => 'label01',
                        'permission' => 'permission01',
                        'selected' => true,
                        'url' => 'http://newurl1',
                    ],
                    [
                        'id' => 'A02',
                        'class' => 'class02',
                        'label' => 'label02',
                        'permission' => 'permission02',
                        'selected' => false,
                        'url' => 'http://newurl2',
                    ],
                ],
                [
                    [
                        'id' => 'A02',
                        'class' => 'class02',
                        'label' => 'label02',
                        'permission' => 'permission02',
                        'selected' => false,
                        'url' => 'http://newurl2',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test getting the active tab.
     *
     * @param array $tabEnv         tabConfig
     * @param array $expectedResult expected result from getInactiveTabs
     *
     * @return void
     *
     * @dataProvider inactiveTabConfigProvider
     */
    public function testGetInactiveTab(array $tabEnv, array $expectedResult): void
    {
        $obj = new SwitchTab();
        $this->assertSame($expectedResult, $obj->getInactiveTabs($tabEnv));
    }

    /**
     * Test storing the configuration of recommendation module.
     *
     * @return void
     */
    public function testSetConfig(): void
    {
        $obj = new SwitchTab();
        $this->assertNull($obj->setConfig(''));
    }

    /**
     * Test the process method.
     *
     * @return void
     */
    public function testProcess(): void
    {
        $obj = new SwitchTab();
        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assertNull($obj->process($results));
    }
}
