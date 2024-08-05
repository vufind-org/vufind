<?php

/**
 * CreateHierarchyTreesCommand test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Hierarchy\Driver\ConfigurationBased as HierarchyDriver;
use VuFind\Hierarchy\TreeDataSource\Solr as TreeSource;
use VuFind\Record\Loader;
use VuFind\Search\Results\PluginManager;
use VuFind\Search\Solr\Results;
use VuFindConsole\Command\Util\CreateHierarchyTreesCommand;

/**
 * CreateHierarchyTreesCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CreateHierarchyTreesCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock hierarchy driver
     *
     * @return HierarchyDriver
     */
    protected function getMockHierarchyDriver()
    {
        return $this->getMockBuilder(HierarchyDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock tree source
     *
     * @return TreeSource
     */
    protected function getMockTreeSource()
    {
        return $this->getMockBuilder(TreeSource::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock record.
     *
     * @param HierarchyDriver $driver Hierarchy driver
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getMockRecord($driver = null)
    {
        $record = new \VuFindTest\RecordDriver\TestHarness();
        $record->setRawData(
            [
                'HierarchyType' => 'foo',
                'HierarchyDriver' => $driver ?? $this->getMockHierarchyDriver(),
            ]
        );
        return $record;
    }

    /**
     * Get mock record loader.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record driver
     *
     * @return Loader
     */
    protected function getMockRecordLoader($record = null)
    {
        $loader = $this->getMockBuilder(Loader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $loader->expects($this->once())->method('load')
            ->with($this->equalTo('recordid'), $this->equalTo('foo'))
            ->will($this->returnValue($record ?? $this->getMockRecord()));
        return $loader;
    }

    /**
     * Get mock results.
     *
     * @return Results
     */
    protected function getMockResults()
    {
        $results = $this->getMockBuilder(Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $output = [
            'hierarchy_top_id' => [
                'data' => [
                    'list' => [
                        [
                            'value' => 'recordid',
                            'count' => 5,
                        ],
                    ],
                ],
            ],
        ];
        $results->expects($this->once())->method('getFullFieldFacets')
            ->with($this->equalTo(['hierarchy_top_id']))
            ->will($this->returnValue($output));
        return $results;
    }

    /**
     * Get mock results manager.
     *
     * @param Results $results Results object
     *
     * @return PluginManager
     */
    protected function getMockResultsManager($results = null)
    {
        $manager = $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->once())->method('get')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($results ?? $this->getMockResults()));
        return $manager;
    }

    /**
     * Get command to test.
     *
     * @param Loader        $loader  Record loader
     * @param PluginManager $results Search results plugin manager
     *
     * @return SuppressedCommand
     */
    protected function getCommand(
        Loader $loader = null,
        PluginManager $results = null
    ) {
        return new CreateHierarchyTreesCommand(
            $loader ?? $this->getMockRecordLoader(),
            $results ?? $this->getMockResultsManager()
        );
    }

    /**
     * Test populating everything.
     *
     * @return void
     */
    public function testPopulatingEverything()
    {
        $tree = $this->getMockTreeSource();
        $tree->expects($this->once())->method('getJSON')
            ->with($this->equalTo('recordid'), $this->equalTo(['refresh' => true]));
        $driver = $this->getMockHierarchyDriver();
        $driver->expects($this->any())->method('getTreeSource')
            ->will($this->returnValue($tree));
        $loader = $this->getMockRecordLoader($this->getMockRecord($driver));
        $command = $this->getCommand($loader);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['backend' => 'foo']);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $expectedText = "\tBuilding tree for recordid... 5 records\n"
            . "1 files\n";
        $this->assertEquals($expectedText, $commandTester->getDisplay());
    }
}
