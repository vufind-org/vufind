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

use Laminas\Http\Response;
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
class FolioTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'API' => [
            'base_url' => 'localhost',
            'tenant' => 'config_tenant',
            'username' => 'config_username',
            'password' => 'config_password'
        ]
    ];

    /**
     * Test data for simulated HTTP responses (reset by each test)
     *
     * @var array
     */
    protected $testResponses = [];

    /**
     * Log of requests made during test (reset by each test)
     *
     * @var array
     */
    protected $testRequestLog = [];

    /**
     * Driver under test
     *
     * @var Folio
     */
    protected $driver = null;

    /**
     * Replace makeRequest to inject test returns
     *
     * @param string       $method  GET/POST/PUT/DELETE/etc
     * @param string       $path    API path (with a leading /)
     * @param string|array $params  Parameters object to be sent as data
     * @param array        $headers Additional headers
     *
     * @return Response
     */
    public function mockMakeRequest(
        string $method = "GET",
        string $path = "/",
        $params = [],
        array $headers = []
    ): Response {
        // Run preRequest
        $httpHeaders = new \Laminas\Http\Headers();
        $httpHeaders->addHeaders($headers);
        [$httpHeaders, $params] = $this->driver->preRequest($httpHeaders, $params);
        // Log request
        $this->testRequestLog[] = compact('method', 'path', 'params') + [
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
     *
     * @param string $test   Name of test fixture to load
     * @param array  $config Driver configuration (null to use default)
     *
     * @return void
     */
    protected function createConnector(string $test, array $config = null): void
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
        $this->driver = $this->getMockBuilder(Folio::class)
            ->setConstructorArgs([new \VuFind\Date\Converter(), $factory])
            ->onlyMethods(['makeRequest'])
            ->getMock();
        // Configure the stub
        $this->driver->setConfig($config ?? $this->defaultDriverConfig);
        $this->driver->expects($this->any())
            ->method('makeRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        $this->driver->init();
    }

    /**
     * Request a token where one does not exist
     *
     * @return void
     */
    public function testTokens(): void
    {
        $this->createConnector('get-tokens'); // saves to $this->driver
        $profile = $this->driver->getMyProfile(['id' => 'whatever']);
        // Get token
        // - Right URL
        $this->assertEquals('/authn/login', $this->testRequestLog[0]['path']);
        // - Right tenant
        $this->assertEquals(
            $this->defaultDriverConfig['API']['tenant'],
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
     *
     * @return void
     */
    public function testCheckValidToken(): void
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
     *
     * @return void
     */
    public function testCheckInvalidToken(): void
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

    /**
     * Confirm that cancel holds validates the current patron.
     *
     * @return void
     */
    public function testCancelHoldsPatronValidation(): void
    {
        $this->createConnector('cancel-holds-bad-patron');
        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('Invalid Request');
        $this->driver->cancelHolds(
            ['details' => ['request1'], 'patron' => ['id' => 'bar']]
        );
    }

    /**
     * Confirm that cancel holds processes various statuses appropriately.
     *
     * @return void
     */
    public function testCancelHoldsMixedStatuses(): void
    {
        $this->createConnector('cancel-holds-mixed-statuses');
        $result = $this->driver->cancelHolds(
            ['details' => ['request1', 'request2'], 'patron' => ['id' => 'foo']]
        );
        $expected = [
            'count' => 1,
            'items' => [
                'item1' => ['success' => true, 'status' => 'hold_cancel_success'],
                'item2' => ['success' => false, 'status' => 'hold_cancel_fail'],
            ],
        ];
        $this->assertEquals($expected, $result);
        $this->assertEquals(
            '/circulation/requests/request1',
            $this->testRequestLog[2]['path']
        );
        $this->assertEquals(
            '{"requesterId":"foo","itemId":"item1","status":"Closed - Cancelled","cancellationReasonId":"75187e8d-e25a-47a7-89ad-23ba612338de"}',
            $this->testRequestLog[2]['params']
        );
        $this->assertEquals(
            '/circulation/requests/request2',
            $this->testRequestLog[4]['path']
        );
        $this->assertEquals(
            '{"requesterId":"foo","itemId":"item2","status":"Closed - Cancelled","cancellationReasonId":"75187e8d-e25a-47a7-89ad-23ba612338de"}',
            $this->testRequestLog[4]['params']
        );
    }

    /**
     * Test an unsuccessful patron login with default settings
     *
     * @return void
     */
    public function testUnsuccessfulPatronLogin(): void
    {
        $this->createConnector('empty');
        $this->assertNull($this->driver->patronLogin('foo', 'bar'));
        $this->assertEquals(
            '/users',
            $this->testRequestLog[1]['path']
        );
        $this->assertEquals(
            ['query' => 'username == "foo"'],
            $this->testRequestLog[1]['params']
        );
    }

    /**
     * Test patron login with Okapi
     *
     * @return void
     */
    public function testSuccessfulPatronLoginWithOkapi(): void
    {
        $this->createConnector(
            'successful-patron-login-with-okapi',
            $this->defaultDriverConfig + ['User' => ['okapi_login' => true]]
        );
        $result = $this->driver->patronLogin('foo', 'bar');
        $expected = [
            'id' => 'fake-id',
            'username' => 'foo',
            'cat_username' => 'foo',
            'cat_password' => 'bar',
            'firstname' => 'first',
            'lastname' => 'last',
            'email' => 'fake@fake.com',
        ];
        $this->assertEquals($expected, $result);
        $this->assertEquals(
            '/authn/login',
            $this->testRequestLog[1]['path']
        );
        $this->assertEquals(
            '{"tenant":"config_tenant","username":"foo","password":"bar"}',
            $this->testRequestLog[1]['params']
        );
        $this->assertEquals(
            '/users',
            $this->testRequestLog[2]['path']
        );
        $this->assertEquals(
            ['query' => 'username == foo'],
            $this->testRequestLog[2]['params']
        );
    }

    /**
     * Test successful place hold
     *
     * @return void
     */
    public function testSuccessfulPlaceHold(): void
    {
        $this->createConnector('successful-place-hold');
        $details = [
            'requiredBy' => '2022-01-01',
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'status' => 'Available',
            'pickUpLocation' => 'desk1',
        ];
        $result = $this->driver->placeHold($details);
        $expected = [
            'success' => true,
            'status' => 'success',
        ];
        $this->assertEquals($expected, $result);
        $this->assertEquals(
            '/circulation/requests',
            $this->testRequestLog[1]['path']
        );
        $request = json_decode($this->testRequestLog[1]['params'], true);
        // Request date changes on every request, so let's not assert about it:
        unset($request['requestDate']);
        $this->assertEquals(
            [
                'itemId' => 'record1',
                'requestType' => 'Page',
                'requesterId' => 'foo',
                'fulfilmentPreference' => 'Hold Shelf',
                'requestExpirationDate' => '2022-01-01',
                'pickupServicePointId' => 'desk1',
            ],
            $request
        );
    }

    /**
     * Test successful place hold with no expiration date
     *
     * @return void
     */
    public function testSuccessfulPlaceHoldNoExpirationDate(): void
    {
        $this->createConnector('successful-place-hold');
        $details = [
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'status' => 'Available',
            'pickUpLocation' => 'desk1',
        ];
        $result = $this->driver->placeHold($details);
        $expected = [
            'success' => true,
            'status' => 'success',
        ];
        $this->assertEquals($expected, $result);
        $this->assertEquals(
            '/circulation/requests',
            $this->testRequestLog[1]['path']
        );
        $request = json_decode($this->testRequestLog[1]['params'], true);
        // Request date changes on every request, so let's not assert about it:
        unset($request['requestDate']);
        $this->assertEquals(
            [
                'itemId' => 'record1',
                'requestType' => 'Page',
                'requesterId' => 'foo',
                'fulfilmentPreference' => 'Hold Shelf',
                'requestExpirationDate' => null,
                'pickupServicePointId' => 'desk1',
            ],
            $request
        );
    }

    /**
     * Test unsuccessful place hold with invalid expiration date
     *
     * @return void
     */
    public function testUnsuccessfulPlaceHoldInvalidExpirationDate(): void
    {
        $this->createConnector('unsuccessful-place-hold');
        $details = [
            'requiredBy' => '1234-33-11',
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'status' => 'Available',
            'pickUpLocation' => 'desk1',
        ];
        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage("hold_date_invalid");
        $result = $this->driver->placeHold($details);
    }

    /**
     * Test unsuccessful place hold
     *
     * @return void
     */
    public function testUnsuccessfulPlaceHold(): void
    {
        $this->createConnector('unsuccessful-place-hold');
        $details = [
            'requiredBy' => '2000-01-01',
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'status' => 'Available',
            'pickUpLocation' => 'desk1',
        ];
        $result = $this->driver->placeHold($details);
        $expected = [
            'success' => false,
            'status' => 'requestExpirationDate cannot be in the past',
        ];
        $this->assertEquals($expected, $result);
        $this->assertEquals(
            '/circulation/requests',
            $this->testRequestLog[1]['path']
        );
        $request = json_decode($this->testRequestLog[1]['params'], true);
        // Request date changes on every request, so let's not assert about it:
        unset($request['requestDate']);
        $this->assertEquals(
            [
                'itemId' => 'record1',
                'requestType' => 'Page',
                'requesterId' => 'foo',
                'fulfilmentPreference' => 'Hold Shelf',
                'requestExpirationDate' => '2000-01-01',
                'pickupServicePointId' => 'desk1',
            ],
            $request
        );
    }

    /**
     * Test successful renewal
     *
     * @return void
     */
    public function testSuccessfulRenewMyItems(): void
    {
        $this->createConnector('successful-renew-my-items');
        $details = [
            'patron' => ['id' => 'foo'],
            'details' => ['record1'],
        ];
        $result = $this->driver->renewMyItems($details);
        $expected = [
            'details' => [
                'record1' => [
                    'success' => true,
                    'sysMessage' => 'success',
                    'new_date' => '01-01-2022',
                    'new_time' => '00:00',
                    'item_id' => 'record1',
                ]
            ]
        ];
        $this->assertEquals($expected, $result);
        $this->assertEquals(
            '/circulation/renew-by-id',
            $this->testRequestLog[1]['path']
        );
        $this->assertEquals(
            '{"itemId":"record1","userId":"foo"}',
            $this->testRequestLog[1]['params']
        );
    }

    /**
     * Test successful call to holds, no items
     *
     * @return void
     */
    public function testNoItemsGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-none');
        $patron = [
            'id' => 'foo'
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected = [];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, one available item
     *
     * @return void
     */
    public function testAvailbleItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-available');
        $patron = [
            'id' => 'foo'
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '12-20-2022',
            'expire' => '',
            'id' => '3311d5df-731f-4e2c-8000-00960a9d8bf7',
            'item_id' => 'fc0064b4-e2e4-4be0-8251-7ca93282c9b4',
            'reqnum' => 'c5a8af9d-9877-453c-bbcb-f63cb5ccb3b4',
            'title' => 'Presentation secrets : do what you never thought possible with your presentations ',
            'available' => true,
            'in_transit' => false,
            'last_pickup_date' => '12-29-2022',
            'position' => 1
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, one in_transit item
     *
     * @return void
     */
    public function testInTransitItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-in_transit');
        $patron = [
            'id' => 'foo'
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '11-07-2022',
            'expire' => '',
            'id' => 'c112b154-720c-486c-890d-81e1c288c097',
            'item_id' => '795759ad-0b33-41dd-a658-947405261360',
            'reqnum' => '074c0f3d-e8a0-47b5-b598-74a45c29d3d7',
            'title' => 'Basic economics : a common sense guide to the economy ',
            'available' => false,
            'in_transit' => true,
            'last_pickup_date' => null,
            'position' => 1
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, item in queue, position x
     *
     * @return void
     */
    public function testSingleItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-single');
        $patron = [
            'id' => 'foo'
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Hold',
            'create' => '12-20-2022',
            'expire' => '12-28-2022',
            'id' => 'c7a7df0d-36a2-486c-85f5-008191e6b32d',
            'item_id' => '26532648-67a3-4459-a97f-9b54b4c5ebd9',
            'reqnum' => 'bb07eb2c-bf3a-449f-8e8b-a114ce410c7f',
            'title' => 'Organic farming : everything you need to know ',
            'available' => false,
            'in_transit' => false,
            'last_pickup_date' => null,
            'position' => 3
        ];
        $this->assertEquals($expected, $result);
    }
}
