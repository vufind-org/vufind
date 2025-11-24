<?php

/**
 * FOLIO ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011-2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
 * FOLIO ILS driver test
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
            'password' => 'config_password',
            'legacy_authentication' => false,
        ],
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
        string $method = 'GET',
        string $path = '/',
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
        $bodyType = $testData['bodyType'] ?? 'string';
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
     * @param ?array $config Driver configuration (null to use default)
     *
     * @return void
     */
    protected function createConnector(string $test, ?array $config = null): void
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
        $cache = new \Laminas\Cache\Storage\Adapter\Memory();
        $cache->setOptions(['memory_limit' => -1]);
        $this->driver->setCacheStorage($cache);
        $this->driver->expects($this->any())
            ->method('makeRequest')
            ->willReturnCallback([$this, 'mockMakeRequest']);
        $this->driver->init();
    }

    /**
     * Request a token where one does not exist (RTR authentication)
     *
     * @return void
     */
    public function testTokens(): void
    {
        $this->createConnector('get-tokens'); // saves to $this->driver
        $this->driver->getMyProfile(['id' => 'whatever']);
    }

    /**
     * Request a token where one does not exist (legacy authentication)
     *
     * @return void
     */
    public function testTokensWithLegacyAuth(): void
    {
        // Take default configuration, but use a different tenant (to avoid
        // session collision with other tests) and disable legacy authentication:
        $config = $this->defaultDriverConfig;
        $config['API']['tenant'] = 'legacy_tenant';
        $config['API']['legacy_authentication'] = 1;
        $this->createConnector('get-tokens-legacy', $config); // saves to $this->driver
        $this->driver->getMyProfile(['id' => 'whatever']);
    }

    /**
     * Check a valid token retrieved from session cache
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testCheckValidToken(): void
    {
        $this->createConnector('check-valid-token');
        $this->driver->getMyTransactions(['id' => 'whatever']);
    }

    /**
     * Check and renew an invalid token retrieved from session cache (RTR authentication)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testCheckInvalidToken(): void
    {
        $this->createConnector('check-invalid-token');
        // Update the token expiration date to make it invalid
        $this->setProperty($this->driver, 'tokenExpiration', null);
        $this->driver->getPickupLocations(['username' => 'whatever']);
    }

    /**
     * Check and renew an invalid token retrieved from session cache (legacy authentication)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokensWithLegacyAuth')]
    public function testCheckInvalidTokenLegacyAuth(): void
    {
        // Take default configuration, but use a different tenant (to avoid
        // session collision with other tests) and disable legacy authentication:
        $config = $this->defaultDriverConfig;
        $config['API']['tenant'] = 'legacy_tenant';
        $config['API']['legacy_authentication'] = 1;
        $this->createConnector('check-invalid-token-legacy', $config);
        $this->driver->getPickupLocations(['username' => 'whatever']);
    }

    /**
     * Confirm that cancel holds validates the current patron.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testUnsuccessfulPatronLogin(): void
    {
        $this->createConnector('unsuccessful-patron-login');
        $this->assertNull($this->driver->patronLogin('foo', 'bar'));
    }

    /**
     * Test patron login with Okapi (RTR authentication)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
            'addressTypeIds' => [],
            'major' => null,
            'college' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test patron login with Okapi (Legacy authentication)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokensWithLegacyAuth')]
    public function testSuccessfulPatronLoginWithOkapiLegacyAuth(): void
    {
        $config = $this->defaultDriverConfig;
        $config['API']['tenant'] = 'legacy_tenant';
        $config['API']['legacy_authentication'] = 1;
        $this->createConnector(
            'successful-patron-login-with-okapi-legacy',
            $config + ['User' => ['okapi_login' => true]]
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
            'addressTypeIds' => [],
            'major' => null,
            'college' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful place hold
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSuccessfulPlaceHold(): void
    {
        $this->createConnector('successful-place-hold');
        $details = [
            'requiredBy' => '2022-01-01',
            'requiredByTS' => 1641049790,
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'id' => 'instanceid',
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
     * Test successful place hold (using an old version of mod-circulation)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSuccessfulPlaceHoldLegacy(): void
    {
        $this->createConnector('successful-place-hold-legacy');
        $details = [
            'requiredBy' => '2022-01-01',
            'requiredByTS' => 1641049790,
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'id' => 'instanceid',
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
     * Test successful place hold with no expiration date
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSuccessfulPlaceHoldNoExpirationDate(): void
    {
        $this->createConnector('successful-place-hold-no-expiration-date');
        $details = [
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'id' => 'instanceid',
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
     * Test unsuccessful place hold with invalid expiration date
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testUnsuccessfulPlaceHoldInvalidExpirationDate(): void
    {
        // Validates that the requiredByTS is an of type ?int, or throws an exception
        // otherwise
        $this->createConnector('unsuccessful-place-hold');
        $details = [
            'requiredBy' => '3333-33-33',
            'requiredByTS' => '3333-33-33',
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'id' => 'instanceid',
            'status' => 'Available',
            'pickUpLocation' => 'desk1',
        ];
        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('hold_date_invalid');
        $this->driver->placeHold($details);
    }

    /**
     * Test successful place hold using request type fallback
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSuccessfulPlaceTitleLevelHoldAfterRequestTypeFallback(): void
    {
        $config = [
            'API' => $this->defaultDriverConfig['API'],
            'Holds' => [
                'default_request' => 'Recall',
                'fallback_request_type' => ['Page'],
            ],
        ];
        $this->createConnector('request-type-fallback', $config);
        $details = [
            'requiredBy' => '2000-01-01',
            'requiredByTS' => 946739390,
            'patron' => ['id' => 'user1'],
            'id' => 'record1',
            'level' => 'title',
            'pickUpLocation' => 'servicepoint1',
        ];
        $result = $this->driver->placeHold($details);
        $expected = [
            'success' => true,
            'status' => 'Open - Not yet filled',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test unsuccessful place hold
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testUnsuccessfulPlaceHold(): void
    {
        $this->createConnector('unsuccessful-place-hold');
        $details = [
            'requiredBy' => '2000-01-01',
            'requiredByTS' => 946739390,
            'patron' => ['id' => 'foo'],
            'item_id' => 'record1',
            'id' => 'instanceid',
            'status' => 'Available',
            'pickUpLocation' => 'desk1',
        ];
        $result = $this->driver->placeHold($details);
        $expected = [
            'success' => false,
            'status' => 'requestExpirationDate cannot be in the past',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful renewal
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
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
                ],
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, no items
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testNoItemsGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-none');
        $patron = [
            'id' => 'foo',
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
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testAvailableItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-available');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '12-20-2022',
            'expire' => '',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Presentation secrets : do what you never thought possible with your presentations ',
            'available' => true,
            'in_transit' => false,
            'last_pickup_date' => '12-29-2022',
            'position' => 1,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, one available item placed for a proxy
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testAvailableProxyItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-available-proxy');
        $patron = [
            'id' => 'bar',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '12-20-2022',
            'expire' => '',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
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
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testInTransitItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-in_transit');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Page',
            'create' => '11-07-2022',
            'expire' => '',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Basic economics : a common sense guide to the economy ',
            'available' => false,
            'in_transit' => true,
            'last_pickup_date' => null,
            'position' => 1,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test successful call to holds, item in queue, position x
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testSingleItemGetMyHolds(): void
    {
        $this->createConnector('get-my-holds-single');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getMyHolds($patron);
        $expected[0] = [
            'type' => 'Hold',
            'create' => '12-20-2022',
            'expire' => '12-28-2022',
            'id' => 'fake-instance-id',
            'item_id' => 'fake-item-id',
            'reqnum' => 'fake-request-num',
            'title' => 'Organic farming : everything you need to know ',
            'available' => false,
            'in_transit' => false,
            'last_pickup_date' => null,
            'position' => 3,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test calls to isHoldable when no excludeHoldLocationsCompareMode
     * config value is set
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testIsHoldableDefaultConfig(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holds']['excludeHoldLocations'] = ['reserve'];

        // Test default mode is exact
        $this->createConnector('empty', $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['reserve']));
    }

    /**
     * Test calls to isHoldable with the exact compare mode
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testIsHoldableExactMode(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Positive test for exact compare mode
        $driverConfig['Holds']['excludeHoldLocations'] = ['reserve'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'exact';
        $this->createConnector('empty', $driverConfig);

        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['reserve']));
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['Reserve']));
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['library']));
    }

    /**
     * Test calls to isHoldable when using regex mode
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testIsHoldableRegexMode(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Positive test for regex compare mode
        $driverConfig['Holds']['excludeHoldLocations'] = ['/RESERVE/i'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'regex';
        $this->createConnector('empty', $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['reserve']));
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['Reserve']));
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['library']));
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['24 hour reserve desk']));
    }

    /**
     * Test calls to isHoldable to verify handling of invalid regex
     * when in regex compare mode
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testIsHoldableInvalidRegex(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Negative test for regex compare mode (invalid regex)
        $driverConfig['Holds']['excludeHoldLocations'] = ['RESERVE'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'regex';
        $this->createConnector('empty', $driverConfig);
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['reserve']));

        // Negative test for regex compare mode (non-string setting and parameter used)
        $driverConfig['Holds']['excludeHoldLocations'] = [true];
        $this->createConnector('empty', $driverConfig);
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['library']));
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['true']));
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', [true]));
    }

    /**
     * Test calls to isHoldable that verify that the excludeHoldLocationsCompareMode
     * config is case insensitive
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testIsHoldableCaseSensitivityConfig(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Test that compare mode for exact is case insensitive
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'Exact';
        $driverConfig['Holds']['excludeHoldLocations'] = ['reserve'];
        $this->createConnector('empty', $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['reserve']));

        // Test that compare mode for regex is case insensitive
        $driverConfig['Holds']['excludeHoldLocations'] = ['/RESERVE/i'];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = ' ReGeX ';
        $this->createConnector('empty', $driverConfig);
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', ['Library of Stuff']));
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['Library of reservED Stuff']));
    }

    /**
     * Test calls to isHoldable using exact mode with invalid
     * location values and parameter values to isHoldable
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testIsHoldableExactModeInvalidInput(): void
    {
        $driverConfig = $this->defaultDriverConfig;

        // Negative test for exact compare mode (non-string setting and parameter used)
        $driverConfig['Holds']['excludeHoldLocations'] = [1];
        $driverConfig['Holds']['excludeHoldLocationsCompareMode'] = 'exact';
        $this->createConnector('empty', $driverConfig);
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', [1]));
        $this->assertTrue($this->callMethod($this->driver, 'isHoldable', [0]));
        $this->assertFalse($this->callMethod($this->driver, 'isHoldable', ['1']));
    }

    /**
     * Test the getMyProfile method.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetMyProfile(): void
    {
        $this->createConnector('get-my-profile');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getMyProfile($patron);
        $expected = [
            'id' => 'foo',
            'firstname' => 'Test',
            'lastname' => 'User',
            'birthdate' => null,
            'address1' => 'street',
            'address2' => null,
            'city' => 'city',
            'country' => 'country',
            'zip' => '12345',
            'phone' => '0123456789',
            'mobile_phone' => '1234567890',
            'expiration_date' => '05-29-2030',
            'group' => null,
            'home_library' => null,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the getProxiedUsers method.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetProxiedUsers(): void
    {
        $this->createConnector('get-proxied-users');
        $patron = [
            'id' => 'foo',
        ];
        $result = $this->driver->getProxiedUsers($patron);
        $expected = ['fakeid' => 'Lastname, Proxity P.'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the getProxyingUsers method.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetProxyingUsers(): void
    {
        $this->createConnector('get-proxying-users');
        $patron = [
            'id' => 'fakeid',
        ];
        $result = $this->driver->getProxyingUsers($patron);
        $expected = ['foo' => 'Lastname, Proxity P.'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Get expected result of get-holding fixture (shared by multiple tests).
     *
     * @return array
     */
    protected function getExpectedGetHoldingResult(): array
    {
        return [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'foo',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => 1,
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => null,
                    'item_notes' => null,
                    'summary' => ['foo', 'bar baz'],
                    'supplements' => [],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
    }

    /**
     * Test getHolding with HRID-based lookup
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingWithHridLookup(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $this->createConnector('get-holding', $driverConfig);
        $this->assertEquals($this->getExpectedGetHoldingResult(), $this->driver->getHolding('foo'));
    }

    /**
     * Get expected result of getHoldings(), used by testGetHoldingsWithMultipleIds().
     *
     * @return array
     */
    protected function getExpectedGetHoldingsWithMultipleIdsResult(): array
    {
        return [
            [
                'total' => 1,
                'holdings' => [
                    0 => [
                        'callnumber_prefix' => '',
                        'callnumber' => 'PS2394 .M643 1883',
                        'id' => 'foo',
                        'item_id' => 'itemid',
                        'holdings_id' => 'abbd2c2b-b2a1-4324-bd24-10f990cfc594',
                        'number' => 1,
                        'enumchron' => '',
                        'barcode' => 'barcode-test',
                        'status' => 'Available',
                        'duedate' => '',
                        'availability' => true,
                        'is_holdable' => true,
                        'holdings_notes' => null,
                        'item_notes' => null,
                        'summary' => ['foo', 'bar baz'],
                        'supplements' => [],
                        'indexes' => [],
                        'location' => 'Special Collections',
                        'location_code' => 'DCOC',
                        'reserve' => 'TODO',
                        'addLink' => true,
                        'bound_with_records' => [],
                        'folio_location_is_active' => true,
                        'loan_type_id' => '',
                        'loan_type_name' => '',
                    ],
                ],
                'electronic_holdings' => [],
            ],
            [
                'total' => 2,
                'holdings' => [
                    0 => [
                        'callnumber_prefix' => '',
                        'callnumber' => 'PS3551.S5 R6 1983',
                        'id' => 'bar',
                        'item_id' => '3258389f-ed1a-406f-8627-97a78d832003',
                        'holdings_id' => '2216df84-b841-490c-8bde-b83076c5c4f4',
                        'number' => 1,
                        'enumchron' => '',
                        'barcode' => '12345678901234',
                        'status' => 'Available',
                        'duedate' => '',
                        'availability' => true,
                        'is_holdable' => true,
                        'holdings_notes' => null,
                        'item_notes' => null,
                        'summary' => [],
                        'supplements' => [],
                        'indexes' => [],
                        'location' => 'Main Library',
                        'location_code' => 'mnmn',
                        'reserve' => 'TODO',
                        'addLink' => 'check',
                        'bound_with_records' => [],
                        'folio_location_is_active' => true,
                        'loan_type_id' => 'd012791f-4e26-4dc2-a279-b6a42b1df315',
                        'loan_type_name' => 'Can Circulate',
                    ],
                    1 => [
                        'callnumber_prefix' => '',
                        'callnumber' => 'PS3551.S5 R6 1983b',
                        'id' => 'bar',
                        'item_id' => '393c1119-d9b8-4f69-bb44-4a44dbe16c3e',
                        'holdings_id' => '2216df84-b841-490c-8bde-b83076c5c4f4',
                        'number' => 2,
                        'enumchron' => '',
                        'barcode' => '',
                        'status' => 'Available',
                        'duedate' => '',
                        'availability' => true,
                        'is_holdable' => true,
                        'holdings_notes' => null,
                        'item_notes' => null,
                        'summary' => [],
                        'supplements' => [],
                        'indexes' => [],
                        'location' => 'Main Library',
                        'location_code' => 'mnmn',
                        'reserve' => 'TODO',
                        'addLink' => 'check',
                        'bound_with_records' => [],
                        'folio_location_is_active' => true,
                        'loan_type_id' => 'd012791f-4e26-4dc2-a279-b6a42b1df315',
                        'loan_type_name' => 'Can Circulate',
                    ],
                ],
                'electronic_holdings' => [],
            ],
        ];
    }

    /**
     * Test getHoldings with multiple ids
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingsWithMultipleIds(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $this->createConnector('get-holdings', $driverConfig);
        $this->assertEquals(
            $this->getExpectedGetHoldingsWithMultipleIdsResult(),
            $this->driver->getHoldings(['foo', 'bar'])
        );
    }

    /**
     * Test getStatuses.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetStatuses(): void
    {
        // getStatuses is just a wrapper around getHolding, so we can test it with
        // a minor variation of the test above.
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['IDs']['type'] = 'hrid';
        $this->createConnector('get-holding', $driverConfig);
        $this->assertEquals(
            [$this->getExpectedGetHoldingResult()['holdings']],
            $this->driver->getStatuses(['foo'])
        );
    }

    /**
     * Test getHolding with FOLIO-based sorting.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingWithFolioSorting(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['folio_sort'] = 'volume';
        $this->createConnector('get-holding-sorted', $driverConfig);
        $expected = [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => 1,
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getHolding filters empty holding statements appropriately.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingFilteringOfEmptyHoldingStatements(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['folio_sort'] = 'volume';
        $this->createConnector('get-holding-empty-statements', $driverConfig);
        $expected = [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => 1,
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => ['summ1', 'summ2'],
                    'supplements' => ['supp1', 'supp2'],
                    'indexes' => ['ind1', 'ind2'],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getHolding with checked out item.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingWithDueDate(): void
    {
        $this->createConnector('get-holding-checkedout');
        $expected = [
            'total' => 1,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => 1,
                    'enumchron' => '',
                    'barcode' => 'barcode-test',
                    'status' => 'Checked out',
                    'duedate' => '06-01-2023',
                    'availability' => false,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getHolding with VuFind-based sorting.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetHoldingMultiVolumeWithVuFindSorting(): void
    {
        $driverConfig = $this->defaultDriverConfig;
        $driverConfig['Holdings']['vufind_sort'] = 'enumchron';
        $this->createConnector('get-holding-multi-volume', $driverConfig);
        $expected = [
            'total' => 2,
            'holdings' => [
                0 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid2',
                    'holdings_id' => 'holdingid',
                    'number' => 1,
                    'enumchron' => 'v.2',
                    'barcode' => 'barcode-test2',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                ],
                1 => [
                    'callnumber_prefix' => '',
                    'callnumber' => 'PS2394 .M643 1883',
                    'id' => 'instanceid',
                    'item_id' => 'itemid',
                    'holdings_id' => 'holdingid',
                    'number' => 2,
                    'enumchron' => 'v.100',
                    'barcode' => 'barcode-test',
                    'status' => 'Available',
                    'duedate' => '',
                    'availability' => true,
                    'is_holdable' => true,
                    'holdings_notes' => ['Fake note'],
                    'item_notes' => null,
                    'summary' => [],
                    'supplements' => ['Fake supplement statement With a note!'],
                    'indexes' => [],
                    'location' => 'Special Collections',
                    'location_code' => 'DCOC',
                    'reserve' => 'TODO',
                    'addLink' => true,
                    'bound_with_records' => [],
                    'folio_location_is_active' => true,
                    'loan_type_id' => '',
                    'loan_type_name' => '',
                ],
            ],
            'electronic_holdings' => [],
        ];
        $this->assertEquals($expected, $this->driver->getHolding('instanceid'));
    }

    /**
     * Test getPagedResults with less than the limit value returned
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetPagedResultsLessThanLimit(): void
    {
        $this->createConnector('get-my-holds-in_transit-limit');

        // Passing a limit of 2
        $result = $this->callMethod(
            $this->driver,
            'getPagedResults',
            [
                'requests',
                '/request-storage/requests',
                [
                    'query' => '((requesterId == "foo" or proxyUserId == "foo") and status == Open*)',
                ],
                2,
            ]
        );
        $result = iterator_to_array($result, false);

        $this->assertCount(1, $result);
    }

    /**
     * Test getPagedResults with greater than the limit value returned
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetPagedResultsGreaterThanLimit(): void
    {
        $this->createConnector('get-my-holds-in_transit-multiple');

        // Passing a limit of 2
        $result = $this->callMethod(
            $this->driver,
            'getPagedResults',
            [
                'requests',
                '/request-storage/requests',
                [
                    'query' => '((requesterId == "foo" or proxyUserId == "foo") and status == Open*)',
                ],
                2,
            ]
        );
        $result = iterator_to_array($result, false);

        $this->assertCount(3, $result);
    }

    /**
     * Test getPagedResults with results equal to the limit value returned
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetPagedResultsEqualToLimit(): void
    {
        $this->createConnector('get-my-holds-in_transit-two');

        // Passing a limit of 2
        $result = $this->callMethod(
            $this->driver,
            'getPagedResults',
            [
                'requests',
                '/request-storage/requests',
                [
                    'query' => '((requesterId == "foo" or proxyUserId == "foo") and status == Open*)',
                ],
                2,
            ]
        );
        $result = iterator_to_array($result, false);

        $this->assertCount(2, $result);
    }

    /**
     * Test getPagedResults with estimates being passed back from folio
     * for the first response. This is different from
     * testGetPagedResultsEqualToLimit since the totalRecords in the
     * response from the API is inaccurate for the first response
     * (i.e. just an estimate).
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetPagedResultsEstimatedTotal(): void
    {
        $this->createConnector('get-my-holds-in_transit-paginate-estimate');

        // Passing a limit of 1
        $result = $this->callMethod(
            $this->driver,
            'getPagedResults',
            [
                'requests',
                '/request-storage/requests',
                [
                    'query' => '((requesterId == "foo" or proxyUserId == "foo") and status == Open*)',
                ],
                1,
            ]
        );
        $result = iterator_to_array($result, false);

        $this->assertCount(2, $result);
    }

    /**
     * Test getBoundWithRecords with an item with six boundWithTitles.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testTokens')]
    public function testGetBoundWithRecords(): void
    {
        $this->createConnector('get-bound-with-records');
        $item = [
            'id' => 'bc3fd525-4254-4075-845b-1428986d811b',
        ];
        $result = $this->callMethod($this->driver, 'getBoundWithRecords', [(object)$item]);
        $expected = [
            [
                'title' => 'Slavery as it once prevailed in Massachusetts : A lecture for the Massachusetts ' .
                    'Historical Society ...',
                'bibId' => '12cb5553-c1bb-48c8-b439-aebc5202970f',
            ],
            [
                'title' => 'Ueber sclaverei, sclaven-emancipation und die einwanderung "freier neger" nach ' .
                    'den colonieen; aufzeichnungen eines weitgereisten.',
                'bibId' => 'f56d3ce3-b31f-4320-8e08-dfc2f9a96c4a',
            ],
            [
                'title' => 'Concerning a full understanding of the southern attitude toward slavery, by ' .
                    'John Douglass Van Horne.',
                'bibId' => '6abe72a5-f518-408a-8a67-fe1ec15627b8',
            ],
            [
                'title' => 'American slavery : echoes and glimpses of prophecy / by Daniel S. Whitney.',
                'bibId' => '1c4cda9b-3506-45e7-b444-0e901cc661e3',
            ],
            [
                'title' => 'The Tract society and slavery. Speeches of Chief Justice Williams, Judge Parsons, ' .
                    'and ex-Governor Ellsworth: delivered in the Center Church, Hartford, Conn., at the ' .
                    'anniversary of the Hartford branch of the American Tract Society, January 9th, 1859.',
                'bibId' => '03684060-3bc0-4a66-874c-854e50ed84fe',
            ],
            [
                'title' => 'Case of Passmore Williamson : report of the proceedings on the writ of habeas corpus, ' .
                    'issued by the Hon. John K. Kane, judge of the District Court of the United States for the ' .
                    'Eastern District of Pennsylvania, in the case of the United States of America ex rel. John H. ' .
                    'Wheeler vs. Passmore Williamson, including the several opinions delivered, and the arguments of ' .
                    'counsel / reported by Arthur Cannon.',
                'bibId' => '080e5167-7a50-4513-b0f3-0f5bf835df7b',
            ],
        ];
        $this->assertEquals($expected, $result);
    }
}
