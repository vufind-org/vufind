<?php

/**
 * Unit tests for EDS backend.
 *
 * PHP version 8
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\EDS;

use InvalidArgumentException;
use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Query\Query;

use function count;

/**
 * Unit tests for EDS backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test performing an autocomplete
     *
     * @return void
     */
    public function testAutocomplete()
    {
        $conn = $this->getConnectorMock(['call']);
        $expectedUri = 'http://foo?idx=rawdata&token=auth1234'
            . '&filters=%5B%7B%22name%22%3A%22custid%22%2C%22values%22%3A%5B%22foo%22%5D%7D%5D&term=bla';
        $conn->expects($this->once())
            ->method('call')
            ->with($this->equalTo($expectedUri))
            ->will($this->returnValue($this->loadResponse('autocomplete')));

        $back = $this->getBackend(
            $conn,
            $this->getEdsRCFactory(),
            null,
            null,
            [],
            ['getAutocompleteData']
        );
        $autocompleteData = [
            'custid' => 'foo', 'url' => 'http://foo', 'token' => 'auth1234',
        ];
        $back->expects($this->any())
            ->method('getAutocompleteData')
            ->will($this->returnValue($autocompleteData));

        $coll = $back->autocomplete('bla', 'rawdata');
        // check count
        $this->assertCount(10, $coll);
        foreach ($coll as $value) {
            $this->assertEquals('bla', substr($value, 0, 3));
        }
    }

    /**
     * Test retrieving an EDS record.
     *
     * @return void
     */
    public function testRetrieveEdsItem()
    {
        $conn = $this->getConnectorMock(['retrieveEdsItem']);
        $conn->expects($this->once())
            ->method('retrieveEdsItem')
            ->will($this->returnValue($this->loadResponse('retrieveEdsItem')));

        $back = $this->getBackend(
            $conn,
            $this->getEdsRCFactory(),
            null,
            null,
            [],
            ['getAuthenticationToken', 'getSessionToken']
        );
        $back->setBackendType('EDS');
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
     * Test retrieving an EPF record.
     *
     * @return void
     */
    public function testRetrieveEpfItem()
    {
        $conn = $this->getConnectorMock(['retrieveEpfItem']);
        $conn->expects($this->once())
            ->method('retrieveEpfItem')
            ->will($this->returnValue($this->loadResponse('retrieveEpfItem')));

        $back = $this->getBackend(
            $conn,
            $this->getEpfRCFactory(),
            null,
            null,
            [],
            ['getAuthenticationToken', 'getSessionToken']
        );
        $back->setBackendType('EPF');
        $back->expects($this->any())
            ->method('getAuthenticationToken')
            ->will($this->returnValue('auth1234'));
        $back->expects($this->any())
            ->method('getSessionToken')
            ->will($this->returnValue('sess1234'));
        $back->setIdentifier('test');

        $coll = $back->retrieve('edp297646');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('edp297646', $rec->getUniqueID());
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
            $conn,
            $this->getEdsRCFactory(),
            null,
            null,
            [],
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
        $this->assertEquals(0, $coll->getOffset());
        $rawFacets = $coll->getRawFacets();
        $this->assertCount(7, $rawFacets);
        $this->assertEquals('SourceType', $rawFacets[0]['Id']);
        $this->assertEquals('Source Type', $rawFacets[0]['Label']);
        $this->assertCount(8, $rawFacets[0]['AvailableFacetValues']);
        $expected = ['Value' => 'News', 'Count' => '12055', 'AddAction' => 'addfacetfilter(SourceType:News)'];
        $this->assertEquals($expected, $rawFacets[0]['AvailableFacetValues'][0]);
        $facets = $coll->getFacets();
        $this->assertEquals(count($facets), count($rawFacets));
        $this->assertEquals(
            [
                'News' => 12055,
                'Academic Journals' => 2855,
                'Magazines' => 783,
                'Books' => 226,
                'eBooks' => 208,
                'Reports' => 47,
                'Reviews' => 5,
                'Conference Materials' => 2,
            ],
            $facets['SourceType']
        );
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
        $fact = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        $conn = $this->getConnectorMock();
        $config = [
            'EBSCO_Account' => [
                'user_name' => 'un', 'password' => 'pw', 'ip_auth' => true,
                'profile' => 'pr', 'organization_id' => 'oi',
            ],
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
        return unserialize(
            $this->getFixture('eds/response/' . $fixture, 'VuFindSearch')
        );
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
        $client = $this->createMock(\Laminas\Http\Client::class);
        return $this->getMockBuilder(\VuFindSearch\Backend\EDS\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs([[], $client])
            ->getMock();
    }

    /**
     * Return backend
     *
     * @param \VuFindSearch\Backend\EDS\Connector                     $connector Connector
     * @param \VuFindSearch\Response\RecordCollectionFactoryInterface $factory   Record collection factory
     * @param \Laminas\Cache\Storage\StorageInterface                 $cache     Object cache adapter
     * @param \Laminas\Session\Container                              $container Session container
     * @param array                                                   $settings  Additional settings
     * @param array                                                   $mock      Methods to mock (or null for a
     * real object)
     *
     * @return \VuFindSearch\Backend\EDS\Backend
     */
    protected function getBackend(
        $connector,
        $factory = null,
        $cache = null,
        $container = null,
        $settings = [],
        $mock = null
    ) {
        if (null === $factory) {
            $factory = $this->createMock(\VuFindSearch\Response\RecordCollectionFactoryInterface::class);
        }
        if (null === $cache) {
            $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        }
        if (null === $container) {
            $container = $this->getMockBuilder(\Laminas\Session\Container::class)
                ->disableOriginalConstructor()->getMock();
        }
        if (null === $mock) {
            return new Backend($connector, $factory, $cache, $container, new \Laminas\Config\Config($settings));
        } else {
            $params = [$connector, $factory, $cache, $container, new \Laminas\Config\Config($settings)];
            return $this->getMockBuilder(\VuFindSearch\Backend\EDS\Backend::class)
                ->onlyMethods($mock)
                ->setConstructorArgs($params)
                ->getMock();
        }
    }

    /**
     * Build a real record collection factory for EDS records
     *
     * @return \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory
     */
    protected function getEdsRCFactory()
    {
        $driverClass = \VuFind\RecordDriver\EDS::class;
        return $this->getRCFactory($driverClass);
    }

    /**
     * Build a real record collection factory for EPF records
     *
     * @return \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory
     */
    protected function getEpfRCFactory()
    {
        $driverClass = \VuFind\RecordDriver\EPF::class;
        return $this->getRCFactory($driverClass);
    }

    /**
     * Build a real record collection factory
     *
     * @param string $driverClass class of the RecordDriver to create
     *
     * @return \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory
     */
    protected function getRCFactory($driverClass)
    {
        $callback = function ($data) use ($driverClass) {
            $driver = new $driverClass();
            $driver->setRawData($data);
            return $driver;
        };
        return new \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory($callback);
    }
}
