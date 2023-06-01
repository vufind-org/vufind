<?php

/**
 * RecordTab Manager Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\Config\PluginManager as ConfigManager;
use VuFind\RecordTab\PluginManager;
use VuFind\RecordTab\TabManager;

/**
 * RecordTab Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TabManagerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Default configuration for mock plugin manager
     *
     * @var array
     */
    protected $defaultConfig = [
        'RecordTabs' => [
            'VuFind\RecordDriver\EDS' => [
                'tabs' => [
                    'xyzzy' => 'yzzyx',
                    'zip' => 'line',
                ],
                'defaultTab' => 'zip',
                'backgroundLoadedTabs' => ['xyzzy'],
            ],
        ],
    ];

    /**
     * Set up a tab manager for testing.
     *
     * @param PluginManager $pluginManager Plugin manager to use (null for default)
     * @param ConfigManager $configManager Config manager to use (null for default)
     *
     * @return TabManager
     */
    protected function getTabManager(
        PluginManager $pluginManager = null,
        ConfigManager $configManager = null
    ) {
        $legacyConfig = [
            'vufind' => [
                'recorddriver_collection_tabs' => [
                    'VuFind\RecordDriver\AbstractBase' => [
                        'tabs' => [
                            'coll' => 'ection',
                        ],
                        'defaultTab' => null,
                    ],
                ],
                'recorddriver_tabs' => [
                    'VuFind\RecordDriver\AbstractBase' => [
                        'tabs' => [
                            'foo' => 'bar',
                        ],
                        'defaultTab' => null,
                    ],
                ],
            ],
        ];
        return new TabManager(
            $pluginManager ?? $this->getMockPluginManager(),
            $configManager
                ?? $this->getMockConfigPluginManager($this->defaultConfig),
            $legacyConfig
        );
    }

    /**
     * Build a mock plugin manager.
     *
     * @return PluginManager
     */
    protected function getMockPluginManager()
    {
        $mockTab = $this->getMockBuilder(\VuFind\RecordTab\StaffViewArray::class)
            ->disableOriginalConstructor()->onlyMethods(['isActive'])->getMock();
        $mockTab->expects($this->any())->method('isActive')
            ->will($this->returnValue(true));
        $pm = $this->getMockBuilder(\VuFind\RecordTab\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $pm->expects($this->any())->method('has')
            ->will($this->returnValue(true));
        $pm->expects($this->any())->method('get')
            ->will($this->returnValue($mockTab));
        return $pm;
    }

    /**
     * Test that we get the expected tab service names.
     *
     * @return void
     */
    public function testGetTabDetailsForRecord()
    {
        $tabManager = $this->getTabManager();
        $driver1 = $this->getMockBuilder(\VuFind\RecordDriver\EDS::class)
            ->disableOriginalConstructor()->getMock();
        $details1 = $tabManager->getTabDetailsForRecord($driver1);
        $this->assertEquals('zip', $details1['default']);
        $this->assertEquals(['xyzzy', 'zip'], array_keys($details1['tabs']));
        $driver2 = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()->getMock();
        $details2 = $tabManager->getTabDetailsForRecord($driver2);
        $this->assertEquals('foo', $details2['default']);
        $this->assertEquals(['foo'], array_keys($details2['tabs']));
        // Switch to collection mode to load a different configuration:
        $tabManager->setContext('collection');
        $details2b = $tabManager->getTabDetailsForRecord($driver2);
        $this->assertEquals('coll', $details2b['default']);
        $this->assertEquals(['coll'], array_keys($details2b['tabs']));
    }

    /**
     * Test getBackgroundTabNames.
     *
     * @return void
     */
    public function testGetBackgroundTabNames()
    {
        $tabManager = $this->getTabManager();
        $driver1 = $this->getMockBuilder(\VuFind\RecordDriver\EDS::class)
            ->disableOriginalConstructor()->getMock();
        $this->assertEquals(['xyzzy'], $tabManager->getBackgroundTabNames($driver1));
        $driver2 = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()->getMock();
        $this->assertEquals([], $tabManager->getBackgroundTabNames($driver2));
    }
}
