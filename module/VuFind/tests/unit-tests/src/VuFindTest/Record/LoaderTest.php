<?php

/**
 * Record loader tests.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Record;

use VuFind\Record\Cache;
use VuFind\Record\FallbackLoader\PluginManager as FallbackLoader;
use VuFind\Record\Loader;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordDriver\PluginManager as RecordFactory;
use VuFindSearch\ParamBag;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Service as SearchService;

/**
 * Record loader tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test exception for missing record.
     *
     * @return void
     */
    public function testMissingRecord()
    {
        $this->expectException(\VuFind\Exception\RecordMissing::class);
        $this->expectExceptionMessage('Record Solr:test does not exist.');

        $collection = $this->getCollection([]);
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $checkCommand = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveCommand::class
                && $command->getTargetIdentifier() === "Solr"
                && $command->getArguments()[0] === "test";
        };
        $service->expects($this->once())->method('invoke')
                ->with($this->callback($checkCommand))
                ->will($this->returnValue($commandObj));
        $loader = $this->getLoader($service);
        $loader->load('test');
    }

    /**
     * Test that the fallback loader gets called successfully for a missing record.
     *
     * @return void
     */
    public function testMissingRecordWithFallback()
    {
        $collection = $this->getCollection([]);
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $checkCommand = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveCommand::class
                && $command->getTargetIdentifier() === "Summon"
                && $command->getArguments()[0] === "test";
        };
        $service->expects($this->once())->method('invoke')
                ->with($this->callback($checkCommand))
                ->will($this->returnValue($commandObj));
        $driver = $this->getDriver();
        $fallbackLoader = $this->getFallbackLoader([$driver]);
        $loader = $this->getLoader($service, null, null, $fallbackLoader);
        $this->assertEquals($driver, $loader->load('test', 'Summon'));
    }

    /**
     * Test "tolerate missing records" feature.
     *
     * @return void
     */
    public function testToleratedMissingRecord()
    {
        $collection = $this->getCollection([]);
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $checkCommand = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveCommand::class
                && $command->getTargetIdentifier() === "Solr"
                && $command->getArguments()[0] === "test";
        };
        $service->expects($this->once())->method('invoke')
                ->with($this->callback($checkCommand))
                ->will($this->returnValue($commandObj));

        $missing = $this->getDriver('missing', 'Missing');
        $factory = $this->getMockBuilder(\VuFind\RecordDriver\PluginManager::class)
            ->getMock();
        $factory->expects($this->once())->method('get')
            ->with($this->equalTo('Missing'))
            ->will($this->returnValue($missing));
        $loader = $this->getLoader($service, $factory);
        $record = $loader->load('test', 'Solr', true);
        $this->assertEquals($missing, $record);
    }

    /**
     * Test single record.
     *
     * @return void
     */
    public function testSingleRecord()
    {
        $driver = $this->getDriver();
        $collection = $this->getCollection([$driver]);
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $checkCommand = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveCommand::class
                && $command->getTargetIdentifier() === "Solr"
                && $command->getArguments()[0] === "test";
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        $loader = $this->getLoader($service);
        $this->assertEquals($driver, $loader->load('test'));
    }

    /**
     * Test single record with backend parameters.
     *
     * @return void
     */
    public function testSingleRecordWithBackendParameters()
    {
        $params = new ParamBag();
        $params->set('fq', 'id:test');

        $driver = $this->getDriver();
        $collection = $this->getCollection([$driver]);

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $checkCommand = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveCommand::class
                && $command->getTargetIdentifier() === "Solr"
                && $command->getArguments()[0] === "test"
                && $command->getArguments()[1]->getArrayCopy() === ['fq'=> ['id:test']];
        };
        $service->expects($this->once())->method('invoke')
                ->with($this->callback($checkCommand))
                ->will($this->returnValue($commandObj));

        $loader = $this->getLoader($service);
        $this->assertEquals($driver, $loader->load('test', 'Solr', false, $params));
    }

    /**
     * Test batch load.
     *
     * @return void
     */
    public function testBatchLoad()
    {
        $driver1 = $this->getDriver('test1', 'Solr');
        $driver2 = $this->getDriver('test2', 'Solr');
        $driver3 = $this->getDriver('test3', 'Summon');
        $missing = $this->getDriver('missing', 'Missing');

        $collection1 = $this->getCollection([$driver1, $driver2]);
        $collection2 = $this->getCollection([$driver3]);
        $collection3 = $this->getCollection([]);

        $solrParams = new ParamBag();
        $solrParams->set('fq', 'id:test1');

        $worldCatParams = new ParamBag();
        $worldCatParams->set('fq', 'id:test4');

        $factory = $this->getMockBuilder(\VuFind\RecordDriver\PluginManager::class)
            ->getMock();
        $factory->expects($this->once())->method('get')
            ->with($this->equalTo('Missing'))
            ->will($this->returnValue($missing));

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->exactly(3))->method('getResult')
            ->willReturnOnConsecutiveCalls($collection1, $collection2, $collection3);

        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();

        $checkCommand1 = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveBatchCommand::class
                && $command->getTargetIdentifier() === "Solr"
                && $command->getArguments()[0] === ["test1", "test2"]
                && $command->getArguments()[1]->getArrayCopy() === ['fq'=> ['id:test1']];
        };

        $checkCommand2 = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveBatchCommand::class
                && $command->getTargetIdentifier() === "Summon"
                && $command->getArguments()[0] === ["test3"]
                && $command->getArguments()[1]->getArrayCopy() === [];
        };

        $checkCommand3 = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveBatchCommand::class
                && $command->getTargetIdentifier() === "WorldCat"
                && $command->getArguments()[0] === ["test4"]
                && $command->getArguments()[1]->getArrayCopy() === ['fq'=> ['id:test4']];
        };
        $service->expects($this->exactly(3))->method('invoke')
            ->withConsecutive(
                [$this->callback($checkCommand1)],
                [$this->callback($checkCommand2)],
                [$this->callback($checkCommand3)]
            )
            ->will($this->returnValue($commandObj));

        $loader = $this->getLoader($service, $factory);
        $input = [
            ['source' => 'Solr', 'id' => 'test1'],
            'Solr|test2', 'Summon|test3', 'WorldCat|test4'
        ];
        $this->assertEquals(
            [$driver1, $driver2, $driver3, $missing],
            $loader->loadBatch(
                $input,
                false,
                ['Solr' => $solrParams, 'WorldCat' => $worldCatParams]
            )
        );
    }

    /**
     * Test batch load with fallback loader.
     *
     * @return void
     */
    public function testBatchLoadWithFallback()
    {
        $driver1 = $this->getDriver('test1', 'Solr');
        $driver2 = $this->getDriver('test2', 'Solr');
        $driver3 = $this->getDriver('test3', 'Summon');

        $collection1 = $this->getCollection([$driver1, $driver2]);
        $collection2 = $this->getCollection([]);

        $solrParams = new ParamBag();
        $solrParams->set('fq', 'id:test1');

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->exactly(2))->method('getResult')
            ->willReturnOnConsecutiveCalls($collection1, $collection2);

        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();

        $checkCommand1  = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveBatchCommand::class
                && ($command->getTargetIdentifier() === "Solr")
                && $command->getArguments()[0] === ["test1", "test2"]
                && ($command->getArguments()[1]->getArrayCopy() === ['fq'=> ['id:test1']]);
        };

        $checkCommand2 = function ($command) {
            return get_class($command) === \VuFindSearch\Command\RetrieveBatchCommand::class
                && ($command->getTargetIdentifier() === "Summon")
                && $command->getArguments()[0] === ["test3"]
                && ($command->getArguments()[1]->getArrayCopy() === []);
        };

        $service->expects($this->exactly(2))->method('invoke')
            ->withConsecutive(
                [$this->callback($checkCommand1)],
                [$this->callback($checkCommand2)]
            )
            ->will($this->returnValue($commandObj));

        $fallbackLoader = $this->getFallbackLoader([$driver3]);
        $loader = $this->getLoader($service, null, null, $fallbackLoader);
        $input = [
            ['source' => 'Solr', 'id' => 'test1'],
            'Solr|test2', 'Summon|test3'
        ];
        $this->assertEquals(
            [$driver1, $driver2, $driver3],
            $loader->loadBatch(
                $input,
                false,
                ['Solr' => $solrParams]
            )
        );
    }

    /**
     * Get test record driver object
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return RecordDriver
     */
    protected function getDriver($id = 'test', $source = 'Solr')
    {
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\AbstractBase::class)
            ->getMock();
        $driver->expects($this->any())->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())->method('getSourceIdentifier')
            ->will($this->returnValue($source));
        return $driver;
    }

    /**
     * Build a loader to test.
     *
     * @param SearchService  $service        Search service
     * @param RecordFactory  $factory        Record factory (optional)
     * @param Cache          $recordCache    Record Cache
     * @param FallbackLoader $fallbackLoader Fallback record loader
     *
     * @return Loader
     */
    protected function getLoader(
        SearchService $service,
        RecordFactory $factory = null,
        Cache $recordCache = null,
        FallbackLoader $fallbackLoader = null
    ) {
        if (null === $factory) {
            $factory = $this->createMock(\VuFind\RecordDriver\PluginManager::class);
        }
        return new Loader($service, $factory, $recordCache, $fallbackLoader);
    }

    /**
     * Get a fallback loader (currently assumes Summon plugin will be used).
     *
     * @param array $records Records to return from the fallback plugin
     *
     * @return FallbackLoader
     */
    protected function getFallbackLoader($records)
    {
        $fallbackPlugin = $this
            ->getMockBuilder(\VuFind\Record\FallbackLoader\Summon::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $callback = function ($r) {
            return $r->getUniqueId();
        };
        $expectedIds = array_map($callback, $records);
        $fallbackPlugin->expects($this->once())->method('load')
            ->with($this->equalTo($expectedIds))
            ->will($this->returnValue($records));
        $fallbackLoader = $this->getMockBuilder(FallbackLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();
        $fallbackLoader->expects($this->once())->method('has')
            ->with($this->equalTo('Summon'))
            ->will($this->returnValue(true));
        $fallbackLoader->expects($this->once())->method('get')
            ->with($this->equalTo('Summon'))
            ->will($this->returnValue($fallbackPlugin));
        return $fallbackLoader;
    }

    /**
     * Get a fake record collection.
     *
     * @param array $records Record(s) to retrieve
     *
     * @return RecordCollectionInterface
     */
    protected function getCollection($records)
    {
        $collection = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->getMock();
        $collection->expects($this->any())->method('getRecords')->will($this->returnValue($records));
        $collection->expects($this->any())->method('count')->will($this->returnValue(count($records)));
        return $collection;
    }
}
