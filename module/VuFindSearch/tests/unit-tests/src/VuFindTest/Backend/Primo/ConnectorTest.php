<?php

/**
 * Unit tests for Primo connector.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Primo;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Primo\Connector;

/**
 * Unit tests for Primo connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
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
     * Test default timeout value
     *
     * @return void
     */
    public function testInstitutionCode()
    {
        $this->assertEquals('fakeinst', $this->createConnector()->getInstitutionCode());
    }

    /**
     * Test a healthy call to getRecord.
     *
     * @return void
     */
    public function testGetRecord()
    {
        $conn = $this->createConnector('record-http');
        $result = $conn->getRecord('crossref10.2202/2151-7509.1009');
        $this->assertEquals(1, $result['recordCount']);
        $this->assertEquals('Abstract Test', $result['documents'][0]['title']);
        $result = $conn->getRecord('');
        $this->assertEquals(0, $result['recordCount']);
        $this->assertEquals('empty_search_disallowed', $result['error']);
    }

    /**
     * Test that an empty query causes an error.
     *
     * @return void
     */
    public function testEmptyQueryError()
    {
        $conn = $this->createConnector();
        $terms = [];
        $result = $conn->query('dummyinst', $terms);
        $this->assertEquals(0, $result['recordCount']);
        $this->assertEquals('empty_search_disallowed', $result['error']);
    }

    /**
     * Test a healthy call to query.
     *
     * @return void
     */
    public function testQuery()
    {
        $conn = $this->createConnector('search-http');
        $terms = [
            ['index' => 'Title', 'lookfor' => 'test'],
            ['index' => 'Author', 'lookfor' => 'test'],
        ];
        $result = $conn->query('dummyinst', $terms);
        $this->assertEquals(1245, $result['recordCount']);
        $this->assertEquals('Abstract Test', $result['documents'][0]['title']);
    }

    /**
     * Test a query response with non-standard namespacing.
     *
     * @return void
     */
    public function testDifferentlyNamespacedQuery()
    {
        $conn = $this->createConnector('swansea-search-http');
        $terms = [
            ['index' => 'Title', 'lookfor' => 'dummy query'],
        ];
        $result = $conn->query('dummyinst', $terms, ['returnErr' => false]);
        $this->assertEquals(1, $result['recordCount']);
        $this->assertEquals('Lord', $result['documents'][0]['title']);
        $this->assertEquals([], $result['didYouMean']);
        $this->assertEquals(['eng' => 1], $result['facets']['lang']);
    }

    /**
     * Test a query response that contains an error message but has a successful HTTP
     * status.
     *
     * @return void
     */
    public function testErrorInSuccessfulResponse()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized access');

        $conn = $this->createConnector('error-with-success-http');
        $terms = [
            ['index' => 'Title', 'lookfor' => 'dummy query'],
        ];
        $conn->query('dummyinst', $terms, ['returnErr' => false]);
    }

    /**
     * Test caching.
     *
     * @return void
     */
    public function testCaching()
    {
        $conn = $this->createConnector('record-http');

        [, $expectedBody] = explode("\r\n\r\n", $this->response);
        $noResults = <<<EOT
            <sear:SEGMENTS xmlns:sear="http://www.exlibrisgroup.com/xsd/jaguar/search">
              <sear:JAGROOT>
                <sear:RESULT>
                  <sear:DOCSET HIT_TIME="20" TOTALHITS="0" FIRSTHIT="1" LASTHIT="1" TOTAL_TIME="49">
                  </sear:DOCSET>
                </sear:RESULT>
              </sear:JAGROOT>
            </sear:SEGMENTS>
            EOT;
        $keyConstraint = new \PHPUnit\Framework\Constraint\IsType('string');

        $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $cache->expects($this->exactly(3))
            ->method('getItem')
            ->with($keyConstraint)
            ->willReturnOnConsecutiveCalls(null, $expectedBody, $noResults);
        $cache->expects($this->exactly(1))
            ->method('setItem')
            ->with($keyConstraint, $expectedBody)
            ->will($this->returnValue(true));

        $conn->setCache($cache);

        $result = $conn->getRecord('id');
        $this->assertEquals(1, $result['recordCount']);
        $this->assertEquals('Abstract Test', $result['documents'][0]['title']);
        $result = $conn->getRecord('id');
        $this->assertEquals(1, $result['recordCount']);
        $this->assertEquals('Abstract Test', $result['documents'][0]['title']);
        $result = $conn->getRecord('id');
        $this->assertEquals(0, $result['recordCount']);
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
        $adapter = new TestAdapter();
        if ($fixture) {
            $this->response
                = $this->getFixture("primo/response/$fixture", 'VuFindSearch');
            $adapter->setResponse($this->response);
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        $conn = new Connector('http://fakeaddress.none', 'fakeinst', $client);
        return $conn;
    }
}
