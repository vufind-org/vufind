<?php
/**
 * Versions Test Class
 *
 * PHP version 7
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
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $count=5;
        $som = $this->getMockPluginManager();
        $config = $this->getMockConfig();
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->any())->method('tryMethod')
            ->with($this->equalTo('getOtherVersionCount'))
            ->will($this->returnValue($count));
        $obj= new Versions($config, $som);
        $obj->setRecordDriver($recordDriver);
        $translator = $this->getMockBuilder(\Laminas\I18n\Translator\TranslatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $translator->expects($this->any())->method('translate')
            ->with($this->equalTo('other_versions_title', ['%%count%%' => $count]))
            ->will($this->returnValue("Count:5"));
        $obj->setTranslator($translator);
        $obj->getDescription();
        $this->assertEquals("Count:5", $obj->getDescription());
    }

    /**
     * Test if the tab is active.
     *
     * @return void
     */
    public function testisActive(): void
    {
        $som = $this->getMockPluginManager();
        $config = $this->getMockConfig();
        $optionsMock = $this->getMockBuilder(\VuFind\Search\Base\Options::class)
            ->disableOriginalConstructor()
            ->getMock();
        $som->expects($this->any())->method('get')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($optionsMock));
        $optionsMock->expects($this->any())->method('getVersionsAction')
            ->willReturnOnConsecutiveCalls(true, false);
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->any())->method('getSourceIdentifier')
            ->will($this->returnValue('foo'));
        $recordDriver->expects($this->any())->method('tryMethod')
            ->with($this->equalTo('getOtherVersionCount'))
            ->willReturnOnConsecutiveCalls(1, 0);
        $obj= new Versions($config, $som);
        $obj->setRecordDriver($recordDriver);
        //Test when the tab is active
        $this->assertTrue($obj->isActive());
        //Test when the tab is not active
        $this->assertFalse($obj->isActive());
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
