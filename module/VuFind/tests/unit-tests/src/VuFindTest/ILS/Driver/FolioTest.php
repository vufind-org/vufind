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
    use \VuFindTest\Feature\ReflectionTrait;

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
    protected $fixtureSteps = [];

    /**
     * Current fixture step
     *
     * @var int
     */
    protected $currentFixtureStep = 0;

    /**
     * Current fixture name
     *
     * @var string
     */
    protected $currentFixture = 'none';

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

        // Get the next step of the test, and make assertions as necessary
        // (we'll skip making assertions if the next step is empty):
        $testData = $this->fixtureSteps[$this->currentFixtureStep] ?? [];
        $this->currentFixtureStep++;
        unset($testData['comment']);
        if (!empty($testData)) {
            $msg = "Error in step {$this->currentFixtureStep} of fixture: "
                . $this->currentFixture;
            $this->assertEquals($testData['expectedMethod'] ?? 'GET', $method, $msg);
            $this->assertEquals($testData['expectedPath'] ?? '/', $path, $msg);
            if (isset($testData['expectedParamsRegEx'])) {
                $this->assertMatchesRegularExpression(
                    $testData['expectedParamsRegEx'],
                    $params,
                    $msg
                );
            } else {
                $this
                    ->assertEquals($testData['expectedParams'] ?? [], $params, $msg);
            }
            $actualHeaders = $httpHeaders->toArray();
            foreach ($testData['expectedHeaders'] ?? [] as $header => $expected) {
                $this->assertEquals($expected, $actualHeaders[$header]);
            }
        }

        // Create response
        $response = new \Laminas\Http\Response();
        $response->setStatusCode($testData['status'] ?? 200);
        $bodyType = $testData['bodyType'] ?? "string";
        $rawBody = $testData['body'] ?? '';
        $body = $bodyType === 'json' ? json_encode($rawBody) : $rawBody;
        $response->setContent($body);
        $response->getHeaders()->addHeaders($testData['headers'] ?? []);
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
        $this->fixtureSteps = $this->getJsonFixture("folio/responses/$test.json");
        $this->currentFixture = $test;
        $this->currentFixtureStep = 0;
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
        $this->driver->setCacheStorage(new \Laminas\Cache\Storage\Adapter\Memory());
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
        $this->driver->getMyProfile(['id' => 'whatever']);
    }

    /**
     * Check a valid token retrieved from session cache
     *
     * @return void
     */
    public function testCheckValidToken(): void
    {
        $this->createConnector('check-valid-token');
        $this->driver->getMyTransactions(['id' => 'whatever']);
    }

    /**
     * Check and renew an invalid token retrieved from session cache
     *
     * @return void
     */
    public function testCheckInvalidToken(): void
    {
        $this->createConnector('check-invalid-token');
        $this->driver->getPickupLocations(['username' => 'whatever']);
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
    }

    /**
     * Test an unsuccessful patron login with default settings
     *
     * @return void
     */
    public function testUnsuccessfulPatronLogin(): void
    {
        $this->createConnector('unsuccessful-patron-login');
        $this->assertNull($this->driver->patronLogin('foo', 'bar'));
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
    public function testAvailableItemGetMyHolds(): void
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
     * Test successful call to holds, one available item placed for a proxy
     *
     * @return void
     */
    public function testAvailableProxyItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-available-proxy');
        $patron = [
            'id' => 'bar'
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
            'position' => 1,
            'proxiedFor' => 'TestuserJohn, John',
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

    /**
     * Test calls to isHoldable when no excludeHoldLocationsCompareMode
     * config value is set
     *
     * @return void
     */
    public function testIsHoldableDefaultConfig(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holds']['excludeHoldLocations'] = ['reserve'];

        // Test default mode is exact
        $this->createConnector("empty", $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["reserve"]));
    }

    /**
     * Test calls to isHoldable with the exact compare mode
     *
     * @return void
     */
    public function testIsHoldableExactMode(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Positive test for exact compare mode
        $driverConfig['Holds']['excludeHoldLocations'] = ['reserve'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'exact';
        $this->createConnector("empty", $driverConfig);

        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["reserve"]));
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["Reserve"]));
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["library"]));
    }

    /**
     * Test calls to isHoldable when using regex mode
     *
     * @return void
     */
    public function testIsHoldableRegexMode(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Positive test for regex compare mode
        $driverConfig['Holds']['excludeHoldLocations'] = ['/RESERVE/i'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'regex';
        $this->createConnector("empty", $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["reserve"]));
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["Reserve"]));
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["library"]));
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["24 hour reserve desk"]));
    }

    /**
     * Test calls to isHoldable to verify handling of invalid regex
     * when in regex compare mode
     *
     * @return void
     */
    public function testIsHoldableInvalidRegex(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Negative test for regex compare mode (invalid regex)
        $driverConfig['Holds']['excludeHoldLocations'] = ['RESERVE'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'regex';
        $this->createConnector("empty", $driverConfig);
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["reserve"]));

        // Negative test for regex compare mode (non-string setting and parameter used)
        $driverConfig['Holds']['excludeHoldLocations'] = [true];
        $this->createConnector("empty", $driverConfig);
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["library"]));
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["true"]));
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", [true]));
    }

    /**
     * Test calls to isHoldable that verify that the excludeHoldLocationsCompareMode
     * config is case insensitive
     *
     * @return void
     */
    public function testIsHoldableCaseSensitivityConfig(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Test that compare mode for exact is case insensitive
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'Exact';
        $driverConfig['Holds']['excludeHoldLocations'] = ['reserve'];
        $this->createConnector("empty", $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["reserve"]));

        // Test that compare mode for regex is case insensitive
        $driverConfig['Holds']['excludeHoldLocations'] = ['/RESERVE/i'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = ' ReGeX ';
        $this->createConnector("empty", $driverConfig);
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", ["Library of Stuff"]));
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["Library of reservED Stuff"]));
    }

    /**
     * Test calls to isHoldable using exact mode with invalid
     * location values and paramter values to isHoldable
     *
     * @return void
     */
    public function testIsHoldableExactModeInvalidInput(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Negative test for exact compare mode (non-string setting and parameter used)
        $driverConfig['Holds']['excludeHoldLocations'] = [1];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'exact';
        $this->createConnector("empty", $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", [1]));
        $this->assertTrue($this->callMethod($this->driver, "isHoldable", [0]));
        $this->assertFalse($this->callMethod($this->driver, "isHoldable", ["1"]));
    }

    /**
     * Test the getMyProfile method.
     *
     * @return void
     */
    public function testGetMyProfile(): void
    {
        $this->createConnector('get-my-profile');
        $patron = [
            'id' => 'foo'
        ];
        $result = $this->driver->getMyProfile($patron);
        $expected = [
            'id' => 'foo',
            'firstname' => 'Test',
            'lastname' => 'User',
            'address1' => 'street',
            'city' => 'city',
            'country' => 'country',
            'zip' => '12345',
            'phone' => '0123456789',
            'mobile_phone' => '1234567890',
            'expiration_date' => '05-29-2030',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the getProxiedUsers method.
     *
     * @return void
     */
    public function testGetProxiedUsers(): void
    {
        $this->createConnector('get-proxied-users');
        $patron = [
            'id' => 'foo'
        ];
        $result = $this->driver->getProxiedUsers($patron);
        $expected = ['fakeid' => 'Lastname, Proxity P.'];
        $this->assertEquals($expected, $result);
    }

    /*
     * Test getHolding with HRID-based lookup
     *
     * @return void
     */
    public function testGetHoldingWithHridLookup(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $this->createConnector("get-holding", $driverConfig);
        $expected = [
            [
                'callnumber_prefix' => '',
                'callnumber' => 'PS2394 .M643 1883',
                'id' => 'foo',
                'item_id' => 'itemid',
                'holding_id' => 'holdingid',
                'number' => 1,
                'enumchron' => '',
                'barcode' => 'barcode-test',
                'status' => 'Available',
                'duedate' => '',
                'availability' => true,
                'is_holdable' => true,
                'holdings_notes' => null,
                'item_notes' => null,
                'issues' => [],
                'supplements' => [],
                'indexes' => [],
                'location' => 'Special Collections',
                'location_code' => 'DCOC',
                'reserve' => 'TODO',
                'addLink' => true,
            ]
        ];
        $this->assertEquals($expected, $this->driver->getHolding("foo"));
    }

    /**
     * Test getHolding with HRID-based lookup
     *
     * @return void
     */
    public function testGetStatuses(): void
    {
        // getStatuses is just a wrapper around getHolding, so we can test it with
        // a minor variation of the test above.
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $this->createConnector("get-holding", $driverConfig);
        $expected = [
            [
                [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'foo',
                    'item_id' => 'itemid',
                    'holding_id' => 'holdingid',
                    'number' => 1,
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => null,
                    'item_notes' => null,
                    'issues' => [],
                    'supplements' => [],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                ]
            ]
        ];
        $this->assertEquals($expected, $this->driver->getStatuses(["foo"]));
    }

    /**
     * Test getHolding with FOLIO-based sorting.
     *
     * @return void
     */
    public function testGetHoldingWithFolioSorting(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['folio_sort'] = 'volume';
        $this->createConnector("get-holding-sorted", $driverConfig);
        $expected = [
            [
                'callnumber_prefix' => '',
                'callnumber' => 'PS2394 .M643 1883',
                'id' => 'instanceid',
                'item_id' => 'itemid',
                'holding_id' => 'holdingid',
                'number' => 1,
                'enumchron' => '',
                'barcode' => 'barcode-test',
                'status' => 'Available',
                'duedate' => '',
                'availability' => true,
                'is_holdable' => true,
                'holdings_notes' => ["Fake note"],
                'item_notes' => null,
                'issues' => [],
                'supplements' => ['Fake supplement statement With a note!'],
                'indexes' => [],
                'location' => 'Special Collections',
                'location_code' => 'DCOC',
                'reserve' => 'TODO',
                'addLink' => true,
            ]
        ];
        $this->assertEquals($expected, $this->driver->getHolding("instanceid"));
    }

    /**
     * Test getHolding with checked out item.
     *
     * @return void
     */
    public function testGetHoldingWithDueDate(): void
    {
        $this->createConnector("get-holding-checkedout");
        $expected = [
            [
                'callnumber_prefix' => '',
                'callnumber' => 'PS2394 .M643 1883',
                'id' => 'instanceid',
                'item_id' => 'itemid',
                'holding_id' => 'holdingid',
                'number' => 1,
                'enumchron' => '',
                'barcode' => 'barcode-test',
                'status' => 'Checked out',
                'duedate' => '06-01-2023',
                'availability' => false,
                'is_holdable' => true,
                'holdings_notes' => ["Fake note"],
                'item_notes' => null,
                'issues' => [],
                'supplements' => ['Fake supplement statement With a note!'],
                'indexes' => [],
                'location' => 'Special Collections',
                'location_code' => 'DCOC',
                'reserve' => 'TODO',
                'addLink' => true,
            ]
        ];
        $this->assertEquals($expected, $this->driver->getHolding("instanceid"));
    }

    /**
     * Test getHolding with VuFind-based sorting.
     *
     * @return void
     */
    public function testGetHoldingMultiVolumeWithVuFindSorting(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['vufind_sort'] = 'enumchron';
        $this->createConnector("get-holding-multi-volume", $driverConfig);
        $expected = [
            [
                'callnumber_prefix' => '',
                'callnumber' => 'PS2394 .M643 1883',
                'id' => 'instanceid',
                'item_id' => 'itemid2',
                'holding_id' => 'holdingid',
                'number' => 1,
                'enumchron' => 'v.2',
                'barcode' => 'barcode-test2',
                'status' => 'Available',
                'duedate' => '',
                'availability' => true,
                'is_holdable' => true,
                'holdings_notes' => ["Fake note"],
                'item_notes' => null,
                'issues' => [],
                'supplements' => ['Fake supplement statement With a note!'],
                'indexes' => [],
                'location' => 'Special Collections',
                'location_code' => 'DCOC',
                'reserve' => 'TODO',
                'addLink' => true,
            ],
            [
                'callnumber_prefix' => '',
                'callnumber' => 'PS2394 .M643 1883',
                'id' => 'instanceid',
                'item_id' => 'itemid',
                'holding_id' => 'holdingid',
                'number' => 2,
                'enumchron' => 'v.100',
                'barcode' => 'barcode-test',
                'status' => 'Available',
                'duedate' => '',
                'availability' => true,
                'is_holdable' => true,
                'holdings_notes' => ["Fake note"],
                'item_notes' => null,
                'issues' => [],
                'supplements' => ['Fake supplement statement With a note!'],
                'indexes' => [],
                'location' => 'Special Collections',
                'location_code' => 'DCOC',
                'reserve' => 'TODO',
                'addLink' => true,
            ]
        ];
        $this->assertEquals($expected, $this->driver->getHolding("instanceid"));
    }
}
