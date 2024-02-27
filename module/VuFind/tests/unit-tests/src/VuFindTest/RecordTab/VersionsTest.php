<?php

/**
 * Versions Test Class
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

namespace VuFindTest\RecordTab;

use Laminas\Config\Config;
use VuFind\RecordTab\Versions;

/**
 * Versions Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VersionsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\TranslatorTrait;

    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $count = 5;
        $som = $this->getMockPluginManager();
        $config = $this->getMockConfig();
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->any())->method('tryMethod')
            ->with($this->equalTo('getOtherVersionCount'))
            ->will($this->returnValue($count));
        $obj = new Versions($config, $som);
        $obj->setRecordDriver($recordDriver);
        $translator = $this->getMockTranslator(
            [
                'default' => [
                    'other_versions_title' => 'Count:%%count%%',
                ],
            ]
        );
        $obj->setTranslator($translator);
        $obj->getDescription();
        $this->assertEquals("Count:$count", $obj->getDescription());
    }

    /**
     * Data provider for testIsActive.
     *
     * @return array
     */
    public static function isActiveProvider(): array
    {
        return ['Test1' => [true, 1, true],
                'Test2' => [true, 0, false],
                'Test3' => [false, 1, false],
                'Test4' => [true, 0, false],
            ];
    }

    /**
     * Test if the tab is active.
     *
     * @param bool $versionAction  Action from Plugin
     * @param int  $versionCount   Version count from Record Driver
     * @param bool $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isActiveProvider
     */
    public function testisActive(bool $versionAction, int $versionCount, bool $expectedResult): void
    {
        $som = $this->getMockPluginManager();
        $config = $this->getMockConfig();
        $optionsMock = $this->getMockBuilder(\VuFind\Search\Base\Options::class)
            ->disableOriginalConstructor()
            ->getMock();
        $som->expects($this->any())->method('get')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($optionsMock));
        $optionsMock->expects($this->once())->method('getVersionsAction')
            ->will($this->returnValue($versionAction));
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('getSourceIdentifier')
            ->will($this->returnValue('foo'));
        $recordDriver->expects($this->any())->method('tryMethod')
            ->with($this->equalTo('getOtherVersionCount'))
            ->will($this->returnValue($versionCount));
        $obj = new Versions($config, $som);
        $obj->setRecordDriver($recordDriver);
        $this->assertSame($expectedResult, $obj->isActive());
    }

    /**
     * Build a mock plugin manager.
     *
     * @return PluginManager
     */
    protected function getMockPluginManager()
    {
        $som = $this->getMockBuilder(\VuFind\Search\Options\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        return $som;
    }

    /**
     * Build a mock Config.
     *
     * @return Config
     */
    protected function getMockConfig()
    {
        $config = $this->getMockBuilder(\Laminas\Config\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        return $config;
    }
}
