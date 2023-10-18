<?php

/**
 * Unit tests for EDS connector.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\EDS;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\EDS\Connector;

/**
 * Unit tests for EDS connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
     * Test caching.
     *
     * @return void
     */
    public function testCaching()
    {
        $conn = $this->createConnector('retrieveEdsItem');

        $keyConstraint = new \PHPUnit\Framework\Constraint\IsType('string');

        $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $cache->expects($this->exactly(3))
            ->method('getItem')
            ->with($keyConstraint)
            ->willReturnOnConsecutiveCalls(
                null,
                json_encode($this->response),
                '{"foo": 1}'
            );
        $cache->expects($this->exactly(1))
            ->method('setItem')
            ->with($keyConstraint, json_encode($this->response))
            ->will($this->returnValue(true));

        $conn->setCache($cache);

        $resp = $conn->retrieve('id', 'db', 'token', 'session');
        $this->assertEquals($this->response, $resp);
        $resp = $conn->retrieve('id', 'db', 'token', 'session');
        $this->assertEquals($this->response, $resp);
        $resp = $conn->retrieve('id', 'db', 'token', 'session');
        $this->assertEquals(['foo' => 1], $resp);

        // Make sure that authentication and session creation don't access the cache.
        $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $cache->expects($this->never())->method('getItem');
        $cache->expects($this->never())->method('setItem');
        $conn->setCache($cache);
        $conn->authenticate('foo', 'bar');
        $conn->createSession();
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
            $this->response = unserialize(
                $this->getFixture("eds/response/$fixture", 'VuFindSearch')
            );
        }

        return new Connector(
            [
                'api_url' => 'http://example.tld/',
                'auth_url' => 'http://example.tld/',
                'orgid' => 'VuFindTest',
            ],
            $client ?: $this->createClient()
        );
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
        $response = "HTTP/1.1 200 OK\r\n"
            . "Date: Thu, 11 Oct 2012 07:56:30 GMT\r\n"
            . "Last-Modified: Thu, 11 Oct 2012 07:05:29 GMT\r\n\r\n"
            . json_encode($this->response);
        $adapter->setResponse($response);
        $client->setAdapter($adapter);
        return $client;
    }
}
