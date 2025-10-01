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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\ConfigManagerInterface;
use VuFind\RecordTab\PluginManager as RecordTabPluginManager;
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
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Default configuration for mock plugin manager
     *
     * @var array
     */
    protected array $defaultConfig = [
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
     * @param ?RecordTabPluginManager $recordTabPluginManager Plugin manager to use (null for default)
     * @param ?ConfigManagerInterface $configManager          Config manager to use (null for default)
     *
     * @return TabManager
     */
    protected function getTabManager(
        ?RecordTabPluginManager $recordTabPluginManager = null,
        ?ConfigManagerInterface $configManager = null
    ): TabManager {
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
            $recordTabPluginManager ?? $this->getMockRecordTabPluginManager(),
            $configManager
                ?? $this->getMockConfigManager($this->defaultConfig),
            $legacyConfig
        );
    }

    /**
     * Build a mock plugin manager.
     *
     * @return MockObject&RecordTabPluginManager
     */
    protected function getMockRecordTabPluginManager(): MockObject&RecordTabPluginManager
    {
        $mockTab = $this->createMock(\VuFind\RecordTab\StaffViewArray::class);
        $mockTab->expects($this->any())->method('isActive')
            ->willReturn(true);
        $pm = $this->createMock(\VuFind\RecordTab\PluginManager::class);
        $pm->expects($this->any())->method('has')
            ->willReturn(true);
        $pm->expects($this->any())->method('get')
            ->willReturn($mockTab);
        return $pm;
    }

    /**
     * Test that we get the expected tab service names.
     *
     * @return void
     */
    public function testGetTabDetailsForRecord(): void
    {
        $tabManager = $this->getTabManager();
        $driver1 = $this->createMock(\VuFind\RecordDriver\EDS::class);
        $details1 = $tabManager->getTabDetailsForRecord($driver1);
        $this->assertEquals('zip', $details1['default']);
        $this->assertEquals(['xyzzy', 'zip'], array_keys($details1['tabs']));
        $driver2 = $this->createMock(\VuFind\RecordDriver\SolrDefault::class);
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
    public function testGetBackgroundTabNames(): void
    {
        $tabManager = $this->getTabManager();
        $driver1 = $this->createMock(\VuFind\RecordDriver\EDS::class);
        $this->assertEquals(['xyzzy'], $tabManager->getBackgroundTabNames($driver1));
        $driver2 = $this->createMock(\VuFind\RecordDriver\SolrDefault::class);
        $this->assertEquals([], $tabManager->getBackgroundTabNames($driver2));
    }
}
