<?php

/**
 * Unit tests for Primo connector.
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
namespace VuFindTest\Backend\Primo;

use VuFindSearch\Backend\Primo\Connector;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Client as HttpClient;

use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * Unit tests for Primo connector.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class ConnectorTest extends PHPUnit_Framework_TestCase
{
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
        $this->assertEquals('Primo API does not accept a null query', $result['error']);
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
     *
     * @expectedException        \Exception
     * @expectedExceptionMessage Unauthorized access
     */
    public function testErrorInSuccessfulResponse()
    {
        $conn = $this->createConnector('error-with-success-http');
        $terms = [
            ['index' => 'Title', 'lookfor' => 'dummy query'],
        ];
        $result = $conn->query('dummyinst', $terms, ['returnErr' => false]);
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
            $file = realpath(sprintf('%s/primo/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
            if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
                throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $file));
            }
            $response = file_get_contents($file);
            $adapter->setResponse($response);
        }
        $client = new HttpClient();
        $client->setAdapter($adapter);
        $conn = new Connector('fakeid', 'fakeinst', $client);
        return $conn;
    }
}