<?php

/**
 * Record loader tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Record\Cache;
use VuFind\Record\FallbackLoader\PluginManager as FallbackLoader;
use VuFind\Record\Loader;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordDriver\PluginManager as RecordFactory;
use VuFindSearch\ParamBag;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Service as SearchService;

use function count;

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
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test exception for missing record.
     *
     * @return void
     */
    public function testMissingRecord(): void
    {
        $this->expectException(\VuFind\Exception\RecordMissing::class);
        $this->expectExceptionMessage('Record Solr:test does not exist.');

        $collection = $this->getCollection([]);
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')
            ->willReturn($collection);
        $service = $this->createMock(\VuFindSearch\Service::class);
        $arguments = ['test', new ParamBag()];
        $service->expects($this->once())->method('invoke')
                ->with($this->callback($this->getCommandChecker($arguments)))
                ->willReturn($commandObj);
        $loader = $this->getLoader($service);
        $loader->load('test');
    }

    /**
     * Test that the fallback loader gets called successfully for a missing record.
     *
     * @return void
     */
    public function testMissingRecordWithFallback(): void
    {
        $collection = $this->getCollection([]);
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')
            ->willReturn($collection);
        $service = $this->createMock(\VuFindSearch\Service::class);
        $class = \VuFindSearch\Command\RetrieveCommand::class;
        $arguments = ['test', new ParamBag()];
        $service->expects($this->once())->method('invoke')
            ->with(
                $this->callback(
                    $this->getCommandChecker($arguments, $class, 'Summon')
                )
            )->willReturn($commandObj);
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
    public function testToleratedMissingRecord(): void
    {
        $collection = $this->getCollection([]);
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')->willReturn($collection);
        $service = $this->createMock(\VuFindSearch\Service::class);
        $arguments = ['test', new ParamBag()];
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($this->getCommandChecker($arguments)))
            ->willReturn($commandObj);
        $missing = $this->getDriver('missing', 'Missing');
        $factory = $this->createMock(\VuFind\RecordDriver\PluginManager::class);
        $factory->expects($this->once())->method('get')
            ->with($this->equalTo('Missing'))
            ->willReturn($missing);
        $loader = $this->getLoader($service, $factory);
        $record = $loader->load('test', 'Solr', true);
        $this->assertEquals($missing, $record);
    }

    /**
     * Test single record.
     *
     * @return void
     */
    public function testSingleRecord(): void
    {
        $driver = $this->getDriver();
        $collection = $this->getCollection([$driver]);
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')
            ->willReturn($collection);
        $service = $this->createMock(\VuFindSearch\Service::class);
        $arguments = ['test', new ParamBag()];
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($this->getCommandChecker($arguments)))
            ->willReturn($commandObj);
        $loader = $this->getLoader($service);
        $this->assertEquals($driver, $loader->load('test'));
    }

    /**
     * Test single record with backend parameters.
     *
     * @return void
     */
    public function testSingleRecordWithBackendParameters(): void
    {
        $params = new ParamBag();
        $params->set('fq', 'id:test');

        $driver = $this->getDriver();
        $collection = $this->getCollection([$driver]);

        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')->willReturn($collection);
        $service = $this->createMock(\VuFindSearch\Service::class);
        $arguments = ['test', $params];
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($this->getCommandChecker($arguments)))
            ->willReturn($commandObj);
        $loader = $this->getLoader($service);
        $this->assertEquals($driver, $loader->load('test', 'Solr', false, $params));
    }

    /**
     * Test batch load.
     *
     * @return void
     */
    public function testBatchLoad(): void
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

        $factory = $this->createMock(\VuFind\RecordDriver\PluginManager::class);
        $factory->expects($this->once())->method('get')
            ->with($this->equalTo('Missing'))
            ->willReturn($missing);

        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->exactly(3))->method('getResult')
            ->willReturnOnConsecutiveCalls($collection1, $collection2, $collection3);

        $service = $this->createMock(\VuFindSearch\Service::class);

        $class = \VuFindSearch\Command\RetrieveBatchCommand::class;
        $arguments1 = [['test1', 'test2'], $solrParams];
        $arguments2 = [['test3'], new ParamBag()];
        $arguments3 = [['test4'], $worldCatParams];

        $this->expectConsecutiveCalls(
            $service,
            'invoke',
            [
                [$this->callback($this->getCommandChecker($arguments1, $class))],
                [$this->callback($this->getCommandChecker($arguments2, $class, 'Summon'))],
                [$this->callback($this->getCommandChecker($arguments3, $class, 'WorldCat2'))],
            ],
            $commandObj
        );

        $loader = $this->getLoader($service, $factory);
        $input = [
            ['source' => 'Solr', 'id' => 'test1'],
            'Solr|test2', 'Summon|test3', 'WorldCat2|test4',
        ];
        $this->assertEquals(
            [$driver1, $driver2, $driver3, $missing],
            $loader->loadBatch(
                $input,
                false,
                ['Solr' => $solrParams, 'WorldCat2' => $worldCatParams]
            )
        );
    }

    /**
     * Test batch load with fallback loader.
     *
     * @return void
     */
    public function testBatchLoadWithFallback(): void
    {
        $driver1 = $this->getDriver('test1', 'Solr');
        $driver2 = $this->getDriver('test2', 'Solr');
        $driver3 = $this->getDriver('test3', 'Summon');

        $collection1 = $this->getCollection([$driver1, $driver2]);
        $collection2 = $this->getCollection([]);

        $solrParams = new ParamBag();
        $solrParams->set('fq', 'id:test1');

        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->exactly(2))->method('getResult')
            ->willReturnOnConsecutiveCalls($collection1, $collection2);

        $service = $this->createMock(\VuFindSearch\Service::class);

        $arguments1 = [['test1', 'test2'], $solrParams];
        $arguments2 = [['test3'], new ParamBag()];
        $class = \VuFindSearch\Command\RetrieveBatchCommand::class;
        $this->expectConsecutiveCalls(
            $service,
            'invoke',
            [
                [$this->callback($this->getCommandChecker($arguments1, $class))],
                [$this->callback($this->getCommandChecker($arguments2, $class, 'Summon'))],
            ],
            $commandObj
        );

        $fallbackLoader = $this->getFallbackLoader([$driver3]);
        $loader = $this->getLoader($service, null, null, $fallbackLoader);
        $input = [
            ['source' => 'Solr', 'id' => 'test1'],
            'Solr|test2', 'Summon|test3',
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
     * Support method to test callbacks.
     *
     * @param array  $args   Command arguments
     * @param string $class  Command class
     * @param string $target Target identifier
     *
     * @return callable
     */
    protected function getCommandChecker(
        array $args = [],
        string $class = \VuFindSearch\Command\RetrieveCommand::class,
        string $target = 'Solr'
    ): callable {
        return function ($command) use ($class, $args, $target) {
            return $command::class === $class
                && $command->getArguments() == $args
                && $command->getTargetIdentifier() === $target;
        };
    }

    /**
     * Get test record driver object
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return MockObject&RecordDriver
     */
    protected function getDriver(string $id = 'test', string $source = 'Solr'): MockObject&RecordDriver
    {
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->any())->method('getUniqueId')->willReturn($id);
        $driver->expects($this->any())->method('getSourceIdentifier')->willReturn($source);
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
    ): Loader {
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
     * @return MockObject&FallbackLoader
     */
    protected function getFallbackLoader($records): MockObject&FallbackLoader
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
            ->willReturn($records);
        $fallbackLoader = $this->getMockBuilder(FallbackLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();
        $fallbackLoader->expects($this->once())->method('has')
            ->with($this->equalTo('Summon'))
            ->willReturn(true);
        $fallbackLoader->expects($this->once())->method('get')
            ->with($this->equalTo('Summon'))
            ->willReturn($fallbackPlugin);
        return $fallbackLoader;
    }

    /**
     * Get a fake record collection.
     *
     * @param array $records Record(s) to retrieve
     *
     * @return RecordCollectionInterface
     */
    protected function getCollection(array $records): MockObject&RecordCollectionInterface
    {
        $collection = $this->createMock(RecordCollectionInterface::class);
        $collection->expects($this->any())->method('getRecords')->willReturn($records);
        $collection->expects($this->any())->method('count')->willReturn(count($records));
        return $collection;
    }
}
