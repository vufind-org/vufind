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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ConnectorTest extends TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

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
     * Test InvalidArgumentException invalid adapter object.
     *
     * @return void
     */
    public function testSetAdapterThrowsInvalidObject()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AdapterInterface');

        $conn = $this->createConnector('single-record');
        $conn->setAdapter($this);
    }

    /**
     * Test InvalidArgumentException unknown serialization format.
     *
     * @return void
     */
    public function testSaveThrowsUnknownFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to serialize');

        $conn = $this->createConnector();
        $document = $this->createMock(\VuFindSearch\Backend\Solr\Document\UpdateDocument::class);
        $conn->write($document, 'unknown', 'update');
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
        $conn = new Connector('http://example.tld/', $map);
        $conn->setProxy($this);
        return $conn;
    }

    /**
     * Set test adapter with prepared response.
     *
     * @param HttpClient $client HTTP client to mock
     *
     * @return void
     */
    public function proxify(HttpClient $client)
    {
        $adapter = new TestAdapter();
        $adapter->setResponse($this->response);
        $client->setAdapter($adapter);
    }
}
