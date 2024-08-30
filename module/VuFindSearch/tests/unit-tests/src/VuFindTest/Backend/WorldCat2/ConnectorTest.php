<?php

/**
 * Unit tests for WorldCat2 connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Backend\WorldCat2;

use Laminas\Http\Client;
use Laminas\Session\Container;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use VuFindSearch\Backend\WorldCat2\Connector;
use VuFindSearch\ParamBag;

/**
 * Unit tests for WorldCat2 connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ConnectorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a mock HTTP client.
     *
     * @param string $expectedUri URI expected by client.
     * @param string $body        Response body returned by client.
     * @param int    $status      Response status
     *
     * @return MockObject&Client
     */
    protected function getMockClient($expectedUri, $body = '{ "foo": "bar" }', $status = 200): MockObject&Client
    {
        $client = $this->createMock(\Laminas\Http\Client::class);
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo($expectedUri));
        $response = $this->createMock(\Laminas\Http\Response::class);
        $response->expects($this->once())->method('getBody')
            ->willReturn($body);
        $response->expects($this->any())->method('isSuccess')
            ->willReturn($status === 200);
        $response->method('getStatusCode')->willReturn($status);
        $client->expects($this->once())->method('send')
            ->willReturn($response);
        return $client;
    }

    /**
     * Get a mock OAuth2 provider.
     *
     * @param string $token Token to return
     *
     * @return MockObject&GenericProvider
     */
    protected function getMockAuthProvider($token = 'fakeToken'): MockObject&GenericProvider
    {
        $token = $this->createMock(AccessTokenInterface::class);
        $token->method('getToken')->willReturn($token);
        $mock = $this->createMock(GenericProvider::class);
        $mock->method('getAccessToken')->willReturn($token);
        return $mock;
    }

    /**
     * Get a connector.
     *
     * @param Client $client HTTP client
     *
     * @return Connector
     */
    protected function getConnector(Client $client): Connector
    {
        $container = new Container('WorldCat2Test');
        $connector = new Connector(
            $client,
            $this->getMockAuthProvider(),
            $container,
            ['base_url' => 'http://foo']
        );
        return $connector;
    }

    /**
     * Test getHoldings()
     *
     * @return void
     */
    public function testGetHoldings(): void
    {
        $expectedUri = 'http://foo/bibs-holdings?bar=baz';
        $body = '{ "test" : "example" }';
        $connector = $this->getConnector($this->getMockClient($expectedUri, $body));
        $params = new ParamBag(['bar' => 'baz']);
        $this->assertEquals(
            ['test' => 'example'],
            $connector->getHoldings($params)
        );
    }

    /**
     * Test "get record" success
     *
     * @return void
     */
    public function testGetRecordSuccess(): void
    {
        $expectedUri = 'http://foo/bibs/baz';
        $body = '{ "identifier": { "oclcNumber": "baz" }}';
        $connector = $this->getConnector($this->getMockClient($expectedUri, $body));
        $this->assertEquals(
            [
                'docs' => [['identifier' => ['oclcNumber' => 'baz']]],
                'offset' => 0,
                'total' => 1,
                'errors' => [],
            ],
            $connector->getRecord('baz')
        );
    }

    /**
     * Test "get record" 429 response
     *
     * @return void
     */
    public function testGetRecord429Response(): void
    {
        $expectedUri = 'http://foo/bibs/baz';
        $body = '{}';
        $connector = $this->getConnector($this->getMockClient($expectedUri, $body, 429));
        $this->assertEquals(
            [
                'docs' => [],
                'offset' => 0,
                'total' => 0,
                'errors' => ['nohit_busy'],
            ],
            $connector->getRecord('baz')
        );
    }

    /**
     * Test "get record" with error
     *
     * @return void
     */
    public function testGetRecordWithError(): void
    {
        $expectedUri = 'http://foo/bibs/baz';
        $body = '{"type": "error", "title": "foo"}';
        $connector = $this->getConnector($this->getMockClient($expectedUri, $body));
        $this->assertEquals(
            [
                'docs' => [],
                'offset' => 0,
                'total' => 0,
                'errors' => [],
            ],
            $connector->getRecord('baz')
        );
    }

    /**
     * Test successful search
     *
     * @return void
     */
    public function testSearch(): void
    {
        $expectedUri = 'http://foo/bibs?foo=bar&foo=baz&offset=0&limit=5';
        $body = '{"numberOfRecords": 0, "bibRecords": []}';
        $connector = $this->getConnector(
            $this->getMockClient($expectedUri, $body)
        );
        $params = new ParamBag(['foo' => ['bar', 'baz']]);
        $this->assertEquals(
            ['docs' => [], 'offset' => 0, 'total' => 0, 'facets' => [], 'errors' => []],
            $connector->search($params, 0, 5)
        );
    }

    /**
     * Test unsuccessful search
     *
     * @return void
     */
    public function testSearchWithError(): void
    {
        $expectedUri = 'http://foo/bibs?foo=bar&foo=baz&offset=0&limit=5';
        $body = '{"type": "error", "title": "foo"}';
        $container = new Container('WorldCat2Test');
        $connector = new Connector(
            $this->getMockClient($expectedUri, $body),
            $this->getMockAuthProvider(),
            $container,
            ['base_url' => 'http://foo']
        );
        $params = new ParamBag(['foo' => ['bar', 'baz']]);
        $this->expectExceptionMessage('type: error; title: foo');
        $connector->search($params, 0, 5);
    }
}
