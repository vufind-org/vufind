<?php

/**
 * Random Recommend tests.
 *
 * PHP version 7
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Recommend;

use PHPUnit\Framework\Assert;
use VuFind\Recommend\RandomRecommend as Random;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Random Recommend tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RandomRecommendTest extends TestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setup()
    {
        $this->recommend = new Random(
            $this->createMock('VuFindSearch\Service'),
            $this->createMock('VuFind\Search\Params\PluginManager')
        );
    }

    /**
     * Test settings
     *
     * @return void
     */
    public function testCanSetSettings()
    {
        //[backend]:[limit]:[display mode]:[random mode]:[minimumset]:[facet1]:[facetvalue1]
        $this->recommend->setConfig("SolrWeb:5:mixed:disregard:20:facet1:value1:facet2:value2");
        $this->assertEquals(
            "SolrWeb", Assert::readAttribute($this->recommend, 'backend')
        );
        $this->assertEquals(
            "5", Assert::readAttribute($this->recommend, 'limit')
        );
        $this->assertEquals(
            "mixed", Assert::readAttribute($this->recommend, 'displayMode')
        );
        $this->assertEquals(
            "disregard", Assert::readAttribute($this->recommend, 'mode')
        );
        $this->assertEquals(
            "20", Assert::readAttribute($this->recommend, 'minimum')
        );
        $filters = Assert::readAttribute($this->recommend, 'filters');
        $this->assertInternalType("array", $filters);
        $this->assertCount(2, $filters);
        $this->assertEquals("facet1:value1", $filters[0]);
        $this->assertEquals("facet2:value2", $filters[1]);
    }

    /**
     * Test default settings
     *
     * @return void
     */
    public function testDefaultSettings()
    {
        //[backend]:[limit]:[display mode]:[random mode]:[minimumset]:[facet1]:[facetvalue1]
        $this->recommend->setConfig('');
        $this->assertEquals(
            "Solr", Assert::readAttribute($this->recommend, 'backend')
        );
        $this->assertEquals(
            "10", Assert::readAttribute($this->recommend, 'limit')
        );
        $this->assertEquals(
            "standard", Assert::readAttribute($this->recommend, 'displayMode')
        );
        $this->assertEquals(
            "retain", Assert::readAttribute($this->recommend, 'mode')
        );
        $this->assertEquals(
            "0", Assert::readAttribute($this->recommend, 'minimum')
        );
        $this->assertEquals(
            [], Assert::readAttribute($this->recommend, 'filters')
        );
    }

    /**
     * Test initialisation
     *
     * @return void
     */
    public function testCanInitialise()
    {
        $service = $this->createMock('VuFindSearch\Service');
        $paramManager = $this->createMock('VuFind\Search\Params\PluginManager');
        $recommend = new Random($service, $paramManager);

        // Use Solr since some Base components are abstract:
        $params = $this->getServiceManager()
            ->get('VuFind\Search\Params\PluginManager')->get('Solr');
        $query = $this->getFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock('\Zend\StdLib\Parameters');

        $service->expects($this->once())->method('random')
            ->with(
                $this->equalTo("Solr"),
                $this->equalTo($params->getQuery()),
                $this->equalTo(10)
            )->will($this->returnValue($this->createMock('VuFindSearch\Response\RecordCollectionInterface')));

        $recommend->setConfig("Solr:10:mixed:retain:20:facet1:value1:facet2:value2");
        $recommend->init($params, $request);
    }

    /**
     * Test initialisation
     *
     * @return void
     */
    public function testCanInitialiseInDisregardMode()
    {
        $service = $this->createMock('VuFindSearch\Service');
        $paramManager = $this->createMock('VuFind\Search\Params\PluginManager');
        $recommend = new Random($service, $paramManager);

        $paramManager->expects($this->once())->method('get')
            ->with($this->equalTo("Solr"))
            ->will(
                $this->returnValue(
                    $this->getServiceManager()
                        ->get('VuFind\Search\Params\PluginManager')->get('Solr')
                )
            );

        // Use Solr since some Base components are abstract:
        $params = $this->getServiceManager()
            ->get('VuFind\Search\Params\PluginManager')->get('Solr');
        $query = $this->getFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock('\Zend\StdLib\Parameters');

        $service->expects($this->once())->method('random')
            ->with($this->equalTo("Solr"))
            ->will($this->returnValue($this->createMock('VuFindSearch\Response\RecordCollectionInterface')));

        $recommend->setConfig("Solr:10:mixed:disregard:20:facet1:value1:facet2:value2");
        $recommend->init($params, $request);
    }

    /**
     * Test minimum result limit feature
     *
     * @return void
     */
    public function testWillReturnEmptyForMinimumResultLimit()
    {
        $service = $this->createMock('VuFindSearch\Service');
        $paramManager = $this->createMock('VuFind\Search\Params\PluginManager');
        $recommend = new Random($service, $paramManager);
        $records = ["1", "2", "3", "4", "5"];

        // Use Solr since some Base components are abstract:
        $results = $this->getServiceManager()
            ->get('VuFind\Search\Results\PluginManager')->get('Solr');
        $params = $results->getParams();
        $query = $this->getFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock('\Zend\StdLib\Parameters');

        $results = $this->createMock('VuFindSearch\Response\RecordCollectionInterface');
        $results->expects($this->once())->method('getRecords')
            ->will($this->returnValue($records));

        $service->expects($this->once())->method('random')
            ->with(
                $this->equalTo("Solr"),
                $this->equalTo($params->getQuery()),
                $this->equalTo(10)
            )->will($this->returnValue($results));

        $recommend->setConfig("Solr:10:mixed:retain:20:facet1:value1:facet2:value2");
        $recommend->init($params, $request);
        $recommend->process($results);
        $output = $recommend->getResults();
        $this->assertEmpty($output);
    }

    /**
     * Test results coming back
     *
     * @return void
     */
    public function testWillReturnResults()
    {
        $service = $this->createMock('VuFindSearch\Service');
        $paramManager = $this->createMock('VuFind\Search\Params\PluginManager');
        $recommend = new Random($service, $paramManager);
        $records = ["1", "2", "3", "4", "5"];

        // Use Solr since some Base components are abstract:
        $results = $this->getServiceManager()
            ->get('VuFind\Search\Results\PluginManager')->get('Solr');
        $params = $results->getParams();
        $query = $this->getFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock('\Zend\StdLib\Parameters');

        $results = $this->createMock('VuFindSearch\Response\RecordCollectionInterface');
        $results->expects($this->once())->method('getRecords')
            ->will($this->returnValue($records));

        $service->expects($this->once())->method('random')
            ->with(
                $this->equalTo("Solr"),
                $this->equalTo($params->getQuery()),
                $this->equalTo(10)
            )->will($this->returnValue($results));

        $recommend->setConfig("Solr:10:mixed:retain:0:facet1:value1:facet2:value2");
        $recommend->init($params, $request);
        $recommend->process($results);
        $output = $recommend->getResults();
        $this->assertEquals($records, $output);
    }

    /**
     * Test displaymode
     *
     * @return void
     */
    public function testCanSetDisplayMode()
    {
        $this->recommend->setConfig("Solr:10:mixed");
        $this->assertEquals("mixed", $this->recommend->getDisplayMode());
    }

    /**
     * Get a fixture object
     *
     * @return mixed
     */
    protected function getFixture($file)
    {
        $fixturePath = realpath(__DIR__ . '/../../../../fixtures/searches/basic') . '/';
        return unserialize(file_get_contents($fixturePath . $file));
    }
}
