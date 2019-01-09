<?php
/**
 * ILS driver test
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\ILS\Driver;

use InvalidArgumentException;

use VuFind\ILS\Driver\Folio;
use Zend\Http\Client\Adapter\Test as TestAdapter;

use Zend\Http\Response as HttpResponse;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FolioTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    protected $testConfig = [
        'API' => [
            'base_url' => 'localhost',
            'tenant' => 'config-tenant',
            'username' => 'config-username',
            'password' => 'config-password'
        ]
    ];

    protected $testResponses = null;

    protected $testRequestLog = [];

    protected $driver = null;

    /**
     * Replace makeRequest to inject test returns
     *
     * @param string $method  GET/POST/PUT/DELETE/etc
     * @param string $path    API path (with a leading /)
     * @param array  $params  Parameters object to be sent as data
     * @param array  $headers Additional headers
     *
     * @return \Zend\Http\Response
     */
    public function mockMakeRequest($method = "GET", $path = "/", $params = [], $headers = [])
    {
        // Run preRequest
        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders($headers);
        list($httpHeaders, $params) = $this->driver->preRequest($httpHeaders, $params);
        // Log request
        $this->testRequestLog[] = [
            'method' => $method,
            'path' => $path,
            'params' => $params,
            'headers' => $httpHeaders->toArray()
        ];
        // Create response
        $testResponse = array_shift($this->testResponses);
        $response = new \Zend\Http\Response();
        $response->setStatusCode($testResponse->status ?? 200);
        $response->setContent($testResponse->body ?? '');
        $respHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders((array) ($testResponse->headers ?? []));
        $response->setHeaders($respHeaders);
        return $response;
    }

    protected function createConnector($test)
    {
        // Setup test responses
        $file = realpath(
            __DIR__ .
            '/../../../../../../tests/fixtures/folio/responses/' . $test . '.json'
        );
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(
                sprintf('Unable to load fixture file: %s ', $file)
            );
        }
        $this->testResponses = json_decode(file_get_contents($file));
        // Reset log
        $this->testRequestLog = [];
        // Create a stub for the SomeClass class.
        $mock = $this->getMockBuilder(\VuFind\ILS\Driver\Folio::class)
            ->setConstructorArgs([new \VuFind\Date\Converter(), null])
            ->setMethods(['makeRequest'])
            ->getMock();
        // Configure the stub.
        $mock->setConfig($this->testConfig);
        $mock->expects($this->any())
            ->method('makeRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        return $mock;
    }

    function testTokens()
    {
        $this->driver = $this->createConnector('test-tokens');
        $this->driver->getMyProfile(['username' => 'whatever']);
        error_log(print_r($this->testRequestLog, false));
        $this->driver->assertEquals(2, 3);
    }
}
