<?php

/**
 * SwitchType Test Class
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

use VuFind\Recommend\SwitchType;

/**
 * SwitchType Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SwitchTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testGetNewHandlerName.
     *
     * @return array
     */
    public static function newHandlerNameProvider(): array
    {
        return ['Test1' => ['foo:bar', 'bar'],
                'Test2' => ['foo', 'All Fields'],
            ];
    }

    /**
     * Test the description of new search handler.
     *
     * @param string $settings       Settings from searches.ini
     * @param string $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider newHandlerNameProvider
     */
    public function testGetNewHandlerName(string $settings, string $expectedResult): void
    {
        $obj = new SwitchType();
        $obj->setConfig($settings);
        $this->assertSame($expectedResult, $obj->getNewHandlerName());
    }

    /**
     * Data provider for testGetNewHandler.
     *
     * @return array
     */
    public static function newHandlerProvider(): array
    {
        return ['Test1' => ['foo:bar', 'foo', false],
                'Test2' => ['', 'foo', 'AllFields'],
                'Test3' => ['foo:bar', 'abc', 'foo'],
            ];
    }

    /**
     * Test getting the new search handler.
     *
     * @param string      $settings       Settings from searches.ini
     * @param string      $searchHandler  Settings from searches.ini
     * @param bool|string $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider newHandlerProvider
     */
    public function testGetNewHandler(string $settings, string $searchHandler, $expectedResult): void
    {
        $obj = new SwitchType();
        $obj->setConfig($settings);

        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parms = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->expects($this->once())->method('getParams')
            ->will($this->returnValue($parms));
        $parms->expects($this->once())->method('getSearchHandler')
            ->will($this->returnValue($searchHandler));
        $obj->process($results);
        $this->assertSame($expectedResult, $obj->getNewHandler());
    }

    /**
     * Test get results stored in the object.
     *
     * @return void
     */
    public function testGetResults(): void
    {
        $obj = new SwitchType();
        $obj->setConfig('foo');
        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parms = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->expects($this->once())->method('getParams')
            ->will($this->returnValue($parms));
        $parms->expects($this->once())->method('getSearchHandler')
            ->will($this->returnValue('bar'));
        $obj->process($results);
        $this->assertSame($results, $obj->getResults());
    }
}
