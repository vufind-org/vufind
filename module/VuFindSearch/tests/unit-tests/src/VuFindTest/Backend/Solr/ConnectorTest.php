<?php

/**
 * Unit tests for SOLR connector.
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
    use \VuFindTest\Feature\WithConsecutiveTrait;

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
        $conn->retrieve('id');
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
        $conn->retrieve('id');
    }

    /**
     * Test writing a CSV document.
     *
     * @return void
     */
    public function testWriteCSV()
    {
        $csvData = 'a,b,c';
        $client = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setEncType', 'setRawBody'])
            ->getMock();
        // The client will be reset before it is given the expected mime type:
        $this->expectConsecutiveCalls($client, 'setEncType', [['application/x-www-form-urlencoded'], ['text/csv']]);
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($csvData));
        $conn = $this->getConnectorMock(['send'], $client);
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
        $client = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setEncType', 'setRawBody'])
            ->getMock();
        // The client will be reset before it is given the expected mime type:
        $this->expectConsecutiveCalls(
            $client,
            'setEncType',
            [
                ['application/x-www-form-urlencoded'],
                ['application/json'],
            ]
        );
        $client->expects($this->once())->method('setRawBody')
            ->with($this->equalTo($jsonData));
        $conn = $this->getConnectorMock(['send'], $client);
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
        $conn = new Connector(
            $url,
            $map,
            function () {
                return new \Laminas\Http\Client();
            },
            $key
        );
        $this->assertEquals($url, $conn->getUrl());
        $this->assertEquals($map, $conn->getMap());
        $this->assertEquals($key, $conn->getUniqueKey());
    }

    /**
     * Test callWithHttpOptions.
     *
     * @return void
     */
    public function testCallWithHttpOptions()
    {
        $this->response
            = $this->getFixture('solr/response/single-record', 'VuFindSearch');

        $client = $this->getMockBuilder(HttpClient::class)
            ->onlyMethods(['setOptions'])
            ->getMock();
        $client->expects($this->exactly(1))->method('setOptions')
            ->with(['timeout' => 60]);
        $adapter = new TestAdapter();
        $adapter->setResponse($this->response);
        $client->setAdapter($adapter);
        $conn = $this->createConnector('single-record', $client);

        // Normal request:
        $resp = $conn->callWithHttpOptions([], 'retrieve', 'id');
        $this->assertIsString($resp);
        json_decode($resp, true);
        $this->assertEquals(\JSON_ERROR_NONE, json_last_error());

        // Normal request with options:
        $resp = $conn->callWithHttpOptions(['timeout' => 60], 'retrieve', 'id');
        $this->assertIsString($resp);
        json_decode($resp, true);
        $this->assertEquals(\JSON_ERROR_NONE, json_last_error());

        // Try to call a protected method:
        $this->expectException(
            \VuFindSearch\Exception\InvalidArgumentException::class
        );
        $conn->callWithHttpOptions([], 'trySolrUrls', []);
    }

    /**
     * Test that making a request calls the HTTP client factory
     *
     * @return void
     */
    public function testClientCreation()
    {
        $this->response
            = $this->getFixture('solr/response/single-record', 'VuFindSearch');

        $httpService = $this->getMockBuilder(\VuFindHttp\HttpService::class)
            ->getMock();
        $httpService->expects($this->once())
            ->method('createClient')
            ->with('http://localhost/select?q=id%3A%221%22')
            ->willReturn($this->createClient());
        $connector = new Connector(
            'http://localhost',
            new HandlerMap(['select' => ['fallback' => true]]),
            function (string $url) use ($httpService) {
                return $httpService->createClient($url);
            }
        );
        $connector->retrieve('1');
    }

    /**
     * Create connector with fixture file.
     *
     * @param string     $fixture Fixture file
     * @param HttpClient $client  HTTP client
     *
     * @return Connector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createConnector($fixture = null, $client = null)
    {
        if ($fixture) {
            $this->response
                = $this->getFixture("solr/response/$fixture", 'VuFindSearch');
        }

        $map  = new HandlerMap(['select' => ['fallback' => true]]);
        return new Connector(
            'http://localhost/',
            $map,
            function () use ($client) {
                return $client ?: $this->createClient();
            },
            'id'
        );
    }

    /**
     * Return connector mock.
     *
     * @param array      $mock   Functions to mock
     * @param HttpClient $client HTTP Client (optional)
     *
     * @return Connector
     */
    protected function getConnectorMock(array $mock = [], $client = null)
    {
        $map = new HandlerMap(['select' => ['fallback' => true]]);
        return $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs(
                [
                    'http://localhost/',
                    $map,
                    function () use ($client) {
                        // If client is provided, return it since it may have test
                        // expectations:
                        return $client ?? new \Laminas\Http\Client();
                    },
                ]
            )
            ->getMock();
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
