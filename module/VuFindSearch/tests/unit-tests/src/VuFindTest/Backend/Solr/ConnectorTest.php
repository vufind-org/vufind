<?php

/**
 * Unit tests for SOLR connector.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Backend\Solr;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\HandlerMap;

/**
 * Unit tests for SOLR connector.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ConnectorTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Current response.
     *
     * @var string
     */
    protected $response;

    /**
     * Test record retrieval.
     *
     * @return void
     */
    public function testRetrieve()
    {
        $conn = $this->createConnector('single-record');
        $resp = $conn->retrieve('id');
        $this->assertIsString($resp);
        json_decode($resp, true);
        $this->assertEquals(\JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Test retrieving a non-existent record returns a response.
     *
     * @return void
     */
    public function testRetrieveMissingRecord()
    {
        $conn = $this->createConnector('no-match');
        $resp = $conn->retrieve('id');
        $this->assertIsString($resp);
    }

    /**
     * Test RemoteErrorException is thrown on a remote 5xx error.
     *
     * @return void
     */
    public function testInternalServerError()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\RemoteErrorException::class);
        $this->expectExceptionCode(500);

        $conn = $this->createConnector('internal-server-error');
        $resp = $conn->retrieve('id');
    }

    /**
     * Test RequestErrorException is thrown on a remote 4xx error.
     *
     * @return void
     */
    public function testBadRequestError()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\RequestErrorException::class);
        $this->expectExceptionCode(400);

        $conn = $this->createConnector('bad-request');
        $resp = $conn->retrieve('id');
    }

    /**
     * Test writing a CSV document.
     *
     * @return void
     */
    public function testWriteCSV()
    {
        $csvData = 'a,b,c';
        $map = new HandlerMap();
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setEncType', 'setRawBody'])
            ->getMock();
        // The client will be reset before it is given the expected mime type:
        $client->expects($this->exactly(2))->method('setEncType')
            ->withConsecutive(['application/x-www-form-urlencoded'], ['text/csv']);
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($csvData));
        $conn = $this->getMockBuilder(Connector::class)
            ->onlyMethods(['send'])
            ->setConstructorArgs(['http://foo', $map, 'id', $client])
            ->getMock();
        $conn->expects($this->once())->method('send')
            ->with($this->equalTo($client));
        $csv = new \VuFindSearch\Backend\Solr\Document\RawCSVDocument($csvData);
        $conn->write($csv, 'csv');
    }

    /**
     * Test writing a JSON document.
     *
     * @return void
     */
    public function testWriteJSON()
    {
        $jsonData = '[1,2,3]';
        $map = new HandlerMap();
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setEncType', 'setRawBody'])
            ->getMock();
        // The client will be reset before it is given the expected mime type:
        $client->expects($this->exactly(2))->method('setEncType')->withConsecutive(
            ['application/x-www-form-urlencoded'],
            ['application/json']
        );
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($jsonData));
        $conn = $this->getMockBuilder(Connector::class)
            ->onlyMethods(['send'])
            ->setConstructorArgs(['http://foo', $map, 'id', $client])
            ->getMock();
        $conn->expects($this->once())->method('send')
            ->with($this->equalTo($client));
        $json = new \VuFindSearch\Backend\Solr\Document\RawJSONDocument($jsonData);
        $conn->write($json, 'json');
    }

    /**
     * Test caching.
     *
     * @return void
     */
    public function testCaching()
    {
        $conn = $this->createConnector('single-record');

        [, $expectedBody] = explode("\n\n", $this->response);
        $keyConstraint = new \PHPUnit\Framework\Constraint\IsType('string');

        $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $cache->expects($this->exactly(3))
            ->method('getItem')
            ->with($keyConstraint)
            ->willReturnOnConsecutiveCalls(null, $expectedBody, 'foo');
        $cache->expects($this->exactly(1))
            ->method('setItem')
            ->with($keyConstraint, $expectedBody)
            ->will($this->returnValue(true));

        $conn->setCache($cache);

        $resp = $conn->retrieve('id');
        $this->assertEquals($expectedBody, $resp);
        $resp = $conn->retrieve('id');
        $this->assertEquals($expectedBody, $resp);
        $resp = $conn->retrieve('id');
        $this->assertEquals('foo', $resp);

        // Make sure that write() doesn't access the cache.
        $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $cache->expects($this->never())->method('getItem');
        $cache->expects($this->never())->method('setItem');
        $conn->setCache($cache);
        $doc = new \VuFindSearch\Backend\Solr\Document\UpdateDocument();
        $conn->write($doc);
    }

    /**
     * Test simple getters.
     *
     * @return void
     */
    public function testGetters()
    {
        $url = 'http://example.tld/';
        $map  = new HandlerMap(['select' => ['fallback' => true]]);
        $key = 'foo';
        $conn = new Connector($url, $map, $key);
        $this->assertEquals($url, $conn->getUrl());
        $this->assertEquals($map, $conn->getMap());
        $this->assertEquals($key, $conn->getUniqueKey());
    }

    /**
     * Test default timeout value
     *
     * @return void
     */
    public function testDefaultTimeout()
    {
        $this->assertEquals(30, $this->createConnector()->getTimeout());
    }

    /**
     * Create connector with fixture file.
     *
     * @param string $fixture Fixture file
     *
     * @return Connector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createConnector($fixture = null)
    {
        if ($fixture) {
            $this->response
                = $this->getFixture("solr/response/$fixture", 'VuFindSearch');
        }

        $map  = new HandlerMap(['select' => ['fallback' => true]]);
        return new Connector('http://example.tld/', $map, 'id', $this->createClient());
    }

    /**
     * Set up HTTP client using test adapter with prepared response.
     *
     * @return HttpClient
     */
    protected function createClient()
    {
        $client = new HttpClient();
        $adapter = new TestAdapter();
        $adapter->setResponse($this->response);
        $client->setAdapter($adapter);
        return $client;
    }
}
