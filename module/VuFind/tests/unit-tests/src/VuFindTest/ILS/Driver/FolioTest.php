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

use VuFind\ILS\Driver\Folio;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FolioTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    protected $testConfig = [
        'API' => [
            'base_url' => 'localhost',
            'tenant' => 'config_tenant',
            'username' => 'config_username',
            'password' => 'config_password'
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
     * @return \Laminas\Http\Response
     */
    public function mockMakeRequest($method = "GET", $path = "/", $params = [], $headers = [])
    {
        // Run preRequest
        $httpHeaders = new \Laminas\Http\Headers();
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
        $response = new \Laminas\Http\Response();
        $response->setStatusCode($testResponse['status'] ?? 200);
        $response->setContent($testResponse['body'] ?? '');
        $response->getHeaders()->addHeaders($testResponse['headers'] ?? []);
        return $response;
    }

    /**
     * Generate a new Folio driver to return responses set in a json fixture
     *
     * Overwrites $this->driver
     * Uses session cache
     */
    protected function createConnector($test)
    {
        // Setup test responses
        $this->testResponses = $this->getJsonFixture("folio/responses/$test.json");
        // Reset log
        $this->testRequestLog = [];
        // Session factory
        $factory = function ($namespace) {
            $manager = new \Laminas\Session\SessionManager();
            return new \Laminas\Session\Container("Folio_$namespace", $manager);
        };
        // Create a stub for the SomeClass class
        $this->driver = $this->getMockBuilder(\VuFind\ILS\Driver\Folio::class)
            ->setConstructorArgs([new \VuFind\Date\Converter(), $factory])
            ->setMethods(['makeRequest'])
            ->getMock();
        // Configure the stub
        $this->driver->setConfig($this->testConfig);
        $this->driver->expects($this->any())
            ->method('makeRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        $this->driver->init();
    }

    /**
     * Request a token where one does not exist
     */
    public function testTokens()
    {
        $this->createConnector('get-tokens'); // saves to $this->driver
        $profile = $this->driver->getMyProfile(['id' => 'whatever']);
        // Get token
        // - Right URL
        $this->assertEquals('/authn/login', $this->testRequestLog[0]['path']);
        // - Right tenant
        $this->assertEquals(
            $this->testConfig['API']['tenant'],
            $this->testRequestLog[0]['headers']['X-Okapi-Tenant']
        );
        // Profile request
        // - Passed correct token
        $this->assertEquals(
            'x-okapi-token-config-tenant', // from fixtures: get-tokens.json
            $this->testRequestLog[1]['headers']['X-Okapi-Token']
        );
    }

    /**
     * Check a valid token retrieved from session cache
     */
    public function testCheckValidToken()
    {
        $this->createConnector('check-valid-token');
        $profile = $this->driver->getMyTransactions(['id' => 'whatever']);
        // Check token
        $this->assertEquals('/users', $this->testRequestLog[0]['path']);
        // Move to method call
        $this->assertEquals('/circulation/loans', $this->testRequestLog[1]['path']);
        // - Passed correct token
        $this->assertEquals(
            'x-okapi-token-config-tenant', // from fixtures: get-tokens.json (cached)
            $this->testRequestLog[1]['headers']['X-Okapi-Token']
        );
    }

    /**
     * Check and renew an invalid token retrieved from session cache
     */
    public function testCheckInvalidToken()
    {
        $this->createConnector('check-invalid-token');
        $profile = $this->driver->getPickupLocations(['username' => 'whatever']);
        // Check token
        $this->assertEquals('/users', $this->testRequestLog[0]['path']);
        // Request new token
        $this->assertEquals('/authn/login', $this->testRequestLog[1]['path']);
        // Move to method call
        $this->assertEquals('/service-points', $this->testRequestLog[2]['path']);
        // - Passed correct token
        $this->assertEquals(
            'x-okapi-token-after-invalid', // from fixtures: check-invalid-token.json
            $this->testRequestLog[2]['headers']['X-Okapi-Token']
        );
    }
}
