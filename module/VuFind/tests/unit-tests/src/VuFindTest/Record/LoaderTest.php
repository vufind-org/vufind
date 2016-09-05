<?php

/**
 * Record loader tests.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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

use VuFind\Record\Loader;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordDriver\PluginManager as RecordFactory;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Service as SearchService;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Record loader tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LoaderTest extends TestCase
{
    /**
     * Test exception for missing record.
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\RecordMissing
     * @expectedExceptionMessage Record Solr:test does not exist.
     */
    public function testMissingRecord()
    {
        $collection = $this->getCollection([]);
        $service = $this->getMock('VuFindSearch\Service');
        $service->expects($this->once())->method('retrieve')
            ->with($this->equalTo('Solr'), $this->equalTo('test'))
            ->will($this->returnValue($collection));
        $loader = $this->getLoader($service);
        $loader->load('test');
    }

    /**
     * Test "tolerate missing records" feature.
     *
     * @return void
     */
    public function testToleratedMissingRecord()
    {
        $collection = $this->getCollection([]);
        $service = $this->getMock('VuFindSearch\Service');
        $service->expects($this->once())->method('retrieve')
            ->with($this->equalTo('Solr'), $this->equalTo('test'))
            ->will($this->returnValue($collection));
        $missing = $this->getDriver('missing', 'Missing');
        $factory = $this->getMock('VuFind\RecordDriver\PluginManager');
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
        $service = $this->getMock('VuFindSearch\Service');
        $service->expects($this->once())->method('retrieve')
            ->with($this->equalTo('Solr'), $this->equalTo('test'))
            ->will($this->returnValue($collection));
        $loader = $this->getLoader($service);
        $this->assertEquals($driver, $loader->load('test'));
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

        $factory = $this->getMock('VuFind\RecordDriver\PluginManager');
        $factory->expects($this->once())->method('get')
            ->with($this->equalTo('Missing'))
            ->will($this->returnValue($missing));

        $service = $this->getMock('VuFindSearch\Service');
        $service->expects($this->at(0))->method('retrieveBatch')
            ->with($this->equalTo('Solr'), $this->equalTo(['test1', 'test2']))
            ->will($this->returnValue($collection1));
        $service->expects($this->at(1))->method('retrieveBatch')
            ->with($this->equalTo('Summon'), $this->equalTo(['test3']))
            ->will($this->returnValue($collection2));
        $service->expects($this->at(2))->method('retrieveBatch')
            ->with($this->equalTo('WorldCat'), $this->equalTo(['test4']))
            ->will($this->returnValue($collection3));

        $loader = $this->getLoader($service, $factory);
        $input = [
            ['source' => 'Solr', 'id' => 'test1'],
            'Solr|test2', 'Summon|test3', 'WorldCat|test4'
        ];
        $this->assertEquals([$driver1, $driver2, $driver3, $missing], $loader->loadBatch($input));
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
        $driver = $this->getMock('VuFind\RecordDriver\AbstractBase');
        $driver->expects($this->any())->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())->method('getSourceIdentifier')
            ->will($this->returnValue($source));
        return $driver;
    }

    /**
     * Build a loader to test.
     *
     * @param SearchService $service Search service
     * @param RecordFactory $factory Record factory (optional)
     *
     * @return Loader
     */
    protected function getLoader(SearchService $service, RecordFactory $factory = null)
    {
        if (null === $factory) {
            $factory = $this->getMock('VuFind\RecordDriver\PluginManager');
        }
        return new Loader($service, $factory);
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
        $collection = $this->getMock('VuFindSearch\Response\RecordCollectionInterface');
        $collection->expects($this->any())->method('getRecords')->will($this->returnValue($records));
        $collection->expects($this->any())->method('count')->will($this->returnValue(count($records)));
        return $collection;
    }
}
