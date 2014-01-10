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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
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
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class LoaderTest extends TestCase
{
    /**
     * Test exception for missing record.
     *
     * @return void
     * @expectedException VuFind\Exception\RecordMissing
     * @expectedExceptionMessage Record VuFind:test does not exist.
     */
    public function testMissingRecord()
    {
        $collection = $this->getCollection(array());
        $service = $this->getMock('VuFindSearch\Service');
        $service->expects($this->once())->method('retrieve')
            ->with($this->equalTo('VuFind'), $this->equalTo('test'))
            ->will($this->returnValue($collection));
        $loader = $this->getLoader($service);
        $loader->load('test');
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