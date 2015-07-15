<?php

/**
 * Unit tests for EDS backend.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\EDS;

use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Query\Query;
use InvalidArgumentException;

/**
 * Unit tests for EDS backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve()
    {
        $conn = $this->getConnectorMock(['retrieve']);
        $conn->expects($this->once())
            ->method('retrieve')
            ->will($this->returnValue($this->loadResponse('retrieve')));

        $back = $this->getBackend(
            $conn, $this->getRCFactory(), null, null, [],
            ['getAuthenticationToken', 'getSessionToken']
        );
        $back->expects($this->any())
            ->method('getAuthenticationToken')
            ->will($this->returnValue('auth1234'));
        $back->expects($this->any())
            ->method('getSessionToken')
            ->will($this->returnValue('sess1234'));
        $back->setIdentifier('test');

        $coll = $back->retrieve('bwh,201407212251PR.NEWS.USPR.MM73898');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('bwh,201407212251PR.NEWS.USPR.MM73898', $rec->getUniqueID());
    }

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch()
    {
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->once())
            ->method('search')
            ->will($this->returnValue($this->loadResponse('search')));

        $back = $this->getBackend(
            $conn, $this->getRCFactory(), null, null, [],
            ['getAuthenticationToken', 'getSessionToken']
        );
        $back->expects($this->any())
            ->method('getAuthenticationToken')
            ->will($this->returnValue('auth1234'));
        $back->expects($this->any())
            ->method('getSessionToken')
            ->will($this->returnValue('sess1234'));
        $back->setIdentifier('test');

        $coll = $back->search(new Query('foobar'), 0, 3);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('bwh,201407212251PR.NEWS.USPR.MM73898', $rec->getUniqueID());
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('bwh,201407220007PR.NEWS.USPR.MM73937', $recs[1]->getUniqueID());
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('bwh,201305180751PR.NEWS.USPR.HS16615', $recs[2]->getUniqueID());
        $this->assertEquals(65924, $coll->getTotal());
        $rawFacets = $coll->getRawFacets();
        $this->assertEquals(7, count($rawFacets));
        $this->assertEquals('SourceType', $rawFacets[0]['Id']);
        $this->assertEquals('Source Type', $rawFacets[0]['Label']);
        $this->assertEquals(8, count($rawFacets[0]['AvailableFacetValues']));
        $expected = ['Value' => 'News', 'Count' => '12055', 'AddAction' => 'addfacetfilter(SourceType:News)'];
        $this->assertEquals($expected, $rawFacets[0]['AvailableFacetValues'][0]);
        $facets = $coll->getFacets();
        $this->assertEquals(count($facets), count($rawFacets));
        $this->assertEquals(8, count($facets['SourceType']['counts']));
        $this->assertEquals(0, $coll->getOffset());
    }

    /**
     * Test setting a query builder.
     *
     * @return void
     */
    public function testSetQueryBuilder()
    {
        $qb = new \VuFindSearch\Backend\EDS\QueryBuilder();
        $back = $this->getBackend($this->getConnectorMock());
        $back->setQueryBuilder($qb);
        $this->assertEquals($qb, $back->getQueryBuilder());
    }

    /**
     * Test setting a custom record collection factory.
     *
     * @return void
     */
    public function testConstructorSetters()
    {
        $fact = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        $conn = $this->getConnectorMock();
        $config = [
            'EBSCO_Account' => [
                'user_name' => 'un', 'password' => 'pw', 'ip_auth' => true,
                'profile' => 'pr', 'organization_id' => 'oi'
            ]
        ];
        $back = $this->getBackend($conn, $fact, null, null, $config);
        $this->assertEquals($fact, $back->getRecordCollectionFactory());
        $this->assertEquals($conn, $this->getProperty($back, 'client'));
        $this->assertEquals('un', $this->getProperty($back, 'userName'));
        $this->assertEquals('pw', $this->getProperty($back, 'password'));
        $this->assertEquals(true, $this->getProperty($back, 'ipAuth'));
        $this->assertEquals('pr', $this->getProperty($back, 'profile'));
        $this->assertEquals('oi', $this->getProperty($back, 'orgId'));
    }

    /// Internal API

    /**
     * Load a response as fixture.
     *
     * @param string $fixture Fixture file
     *
     * @return mixed
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture)
    {
        $file = realpath(sprintf('%s/eds/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $fixture));
        }
        return unserialize(file_get_contents($file));
    }

    /**
     * Return connector mock.
     *
     * @param array $mock Functions to mock
     *
     * @return array
     */
    protected function getConnectorMock(array $mock = [])
    {
        $client = $this->getMock('Zend\Http\Client');
        return $this->getMock(
            'VuFindSearch\Backend\EDS\Zend2', $mock, [[], $client]
        );
    }

    /**
     * Return backend
     *
     * @param \VuFindSearch\Backend\EDS\Zend2                         $connector Connector
     * @param \VuFindSearch\Response\RecordCollectionFactoryInterface $factory   Record collection factory
     * @param \Zend\Cache\Storage\Adapter\AbstractAdapter             $cache     Object cache adapter
     * @param \Zend\Session\Container                                 $container Session container
     * @param array                                                   $settings  Additional settings
     * @param array                                                   $mock      Methods to mock (or null for a real object)
     */
    protected function getBackend($connector, $factory = null, $cache = null, $container = null, $settings = [], $mock = null)
    {
        if (null === $factory) {
            $factory = $this->getMock('VuFindSearch\Response\RecordCollectionFactoryInterface');
        }
        if (null === $cache) {
            $cache = $this->getMock('Zend\Cache\Storage\Adapter\Filesystem');
        }
        if (null === $container) {
            // Using a mock here causes an error for some reason -- investigate later.
            $container = new \Zend\Session\Container('EBSCO');
        }
        if (null === $mock) {
            return new Backend($connector, $factory, $cache, $container, new \Zend\Config\Config($settings));
        } else {
            $params = [$connector, $factory, $cache, $container, new \Zend\Config\Config($settings)];
            return $this->getMock('VuFindSearch\Backend\EDS\Backend', $mock, $params);
        }
    }

    /**
     * Build a real record collection factory
     *
     * @return \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory
     */
    protected function getRCFactory()
    {
        $callback = function ($data) {
            $driver = new \VuFind\RecordDriver\EDS();
            $driver->setRawData($data);
            return $driver;
        };
        return new \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory($callback);
    }

}
