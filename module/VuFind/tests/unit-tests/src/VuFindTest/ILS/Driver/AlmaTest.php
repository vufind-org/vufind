<?php

/**
 * Alma ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use VuFind\I18n\TranslatableString;
use VuFind\ILS\Driver\Alma;

use function func_get_args;
use function is_array;

/**
 * Alma ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AlmaTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'Catalog' => [
            'apiBaseUrl' => 'http://localhost/v1',
            'apiKey' => 'key123',
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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new Alma(new \VuFind\Date\Converter());
    }

    /**
     * Replace makeRequest to inject test returns
     *
     * @param string        $path          Path to retrieve from API (excluding base
     *                                     URL/API key)
     * @param array         $paramsGet     Additional GET params
     * @param array         $paramsPost    Additional POST params
     * @param string        $method        GET or POST. Default is GET.
     * @param string        $rawBody       Request body.
     * @param Headers|array $headers       Add headers to the call.
     * @param array         $allowedErrors HTTP status codes that are not treated as
     *                                     API errors.
     * @param bool          $returnStatus  Whether to return HTTP status in addition
     *                                     to the response.
     *
     * @return null|\SimpleXMLElement
     */
    public function mockMakeRequest(
        $path,
        $paramsGet = [],
        $paramsPost = [],
        $method = 'GET',
        $rawBody = null,
        $headers = null,
        $allowedErrors = [],
        $returnStatus = false
    ) {
        // Get the next step of the test, and make assertions as necessary
        // (we'll skip making assertions if the next step is empty):
        $testData = $this->fixtureSteps[$this->currentFixtureStep] ?? [];
        $this->currentFixtureStep++;
        unset($testData['comment']);
        if (!empty($testData['expectedParams'])) {
            $msg = "Error in step {$this->currentFixtureStep} of fixture: "
                . $this->currentFixture;
            $params = func_get_args();
            foreach ($testData['expectedParams'] as $i => $expectedParam) {
                $this->assertEquals(
                    $expectedParam,
                    $params[$i],
                    $msg . ", parameter index $i"
                );
            }
        }

        $resultFixture = $this->getFixture('alma/responses/' . $testData['resultFixture']);
        $result = simplexml_load_string($resultFixture);
        return $returnStatus
            ? [$result, $testData['status'] ?? 200]
            : $result;
    }

    /**
     * Generate a new driver to return responses set in a json fixture
     *
     * Overwrites $this->driver
     *
     * @param string  $test       Name of test fixture to load.
     * @param ?array  $config     Driver configuration (null to use default)
     * @param ?string $fixtureKey If test fixture contains multiple different tests, then setting the
     *                            fixtureKey will load the correct test. Default is null.
     *
     * @return void
     */
    protected function createConnector(string $test, ?array $config = null, ?string $fixtureKey = null): void
    {
        // Setup test responses
        $this->fixtureSteps = $this->getJsonFixture("alma/responses/$test.json");
        $this->currentFixture = $test;
        // If fixture key is provided, then try to obtain correct test data with it.
        if ($fixtureKey) {
            $this->fixtureSteps = $this->fixtureSteps[$fixtureKey];
            $this->currentFixture .= " [$fixtureKey]";
        }
        $this->currentFixtureStep = 0;
        // Create a stub for the class
        $this->driver = $this->getMockBuilder(Alma::class)
            ->setConstructorArgs(
                [
                    new \VuFind\Date\Converter(),
                ]
            )->onlyMethods(['makeRequest', 'debug'])
            ->getMock();
        $config ??= $this->defaultDriverConfig;
        // Configure the stub
        $this->driver->setConfig($config);

        // Add test for debugging function result, if enabled
        foreach ($config['Debug']['log_function_result'] ?? [] as $function => $value) {
            $this->driver->expects($this->once())->method('debug')->with("$function result:");
        }
        $cache = new \Laminas\Cache\Storage\Adapter\Memory();
        $cache->setOptions(['memory_limit' => -1]);
        $this->driver->setCacheStorage($cache);
        $this->driver->expects($this->any())
            ->method('makeRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        $this->driver->init();
    }

    /**
     * Testing getCourses
     *
     * @return void
     */
    public function testGetCourses()
    {
        $this->createConnector('get-courses');
        $result = $this->driver->getCourses();
        $expected = [
            '1234' => 'VuFind Basics',
            '5678' => 'Advanced VuFind',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing getFunds
     *
     * @return void
     */
    public function testGetFunds()
    {
        $this->createConnector('get-funds');
        $result = $this->driver->getFunds();
        $expected = [
            'FUND-01' => 'VuFind Community',
            'FUND-02' => 'VuFind Sponsors',
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getHolding
     *
     * @return void
     */
    public function testGetHolding(): void
    {
        $this->createConnector('get-holding-without-mappings');
        $result = $this->driver->getHolding('1111');
        $result['holdings'] = $this->convertTranslatableStrings($result['holdings']);
        $this->assertJsonStringEqualsJsonFile(
            $this->getFixturePath('alma/holdings-without-mappings.json'),
            json_encode($result)
        );
    }

    /**
     * Data provider for testing getMyProfile
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): \Generator
    {
        yield 'Address 2 not set' => [
            'fixtureKey' => 'getMyProfile test 1',
            'expected' => [
                'firstname' => 'John',
                'lastname' => 'Smith',
                'birthdate' => '',
                'address1' => 'A street 1',
                'address2' => '',
                'city' => 'Far away',
                'country' => 'Far',
                'zip' => '00000',
                'phone' => '0123456789',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => 'prefix_test',
                'address3' => 'Not a default field',
                'group_code' => 'test',
                'email' => null,
                'home_library' => null,
            ],
        ];

        yield 'Missing most fields' => [
            'fixtureKey' => 'getMyProfile test 2',
            'expected' => [
                'firstname' => '',
                'lastname' => 'Smith',
                'birthdate' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'country' => '',
                'zip' => '',
                'phone' => '',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => 'prefix_test',
                'address3' => '',
                'group_code' => 'test',
                'email' => null,
                'home_library' => null,
            ],
        ];
    }

    /**
     * Test getMyProfile
     *
     * @param string $fixtureKey Key which selects the correct test path in json file containing
     *                           multiple tests.
     * @param array  $expected   Expected results for the test
     *
     * @return       void
     * @dataProvider getTestGetMyProfileData
     */
    public function testGetMyProfile(string $fixtureKey, array $expected): void
    {
        $adjustedConfig = $this->defaultDriverConfig;
        $adjustedConfig['Catalog']['translationPrefix'] = 'prefix_';
        $adjustedConfig['Debug']['log_function_result'] = ['createProfileArray' => true];
        $this->createConnector('get-my-profile', $adjustedConfig, $fixtureKey);
        $result = $this->driver->getMyProfile(['id' => '1111']);
        $this->assertArrayHasKey('group', $result);
        // Alma uses Translatable strings in group field; make sure that passed through correctly.
        $this->assertTrue($result['group'] instanceof TranslatableString);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for testing patronLogin
     *
     * @return Generator
     */
    public static function getTestPatronLoginData(): \Generator
    {
        $localConfig = [
            'Catalog' => [
                'apiBaseUrl' => 'http://localhost/v1',
                'apiKey' => 'key123',
                'loginMethod' => 'email',
            ],
            'Debug' => [
                'log_function_result' => [
                    'createPatronArray' => true,
                ],
            ],
        ];
        yield 'Test with login method email' => [
            'config' => $localConfig,
            'expected' => [
                'id' => '57391',
                'firstname' => 'John',
                'lastname' => 'Smith',
                'email' => 'pref@email.if',
                'cat_username' => '1111',
                'cat_password' => '1212',
                'major' => null,
                'college' => null,
            ],
            'fixtureKey' => 'test patron login email',
        ];
        $localConfig['Catalog']['loginMethod'] = 'password';
        yield 'Test with login method password' => [
            'config' => $localConfig,
            'expected' => [
                'id' => '21991',
                'email' => null,
                'firstname' => 'Sauna',
                'lastname' => 'Tonttu',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '1212',
            ],
            'fixtureKey' => 'test patron login password',
        ];
    }

    /**
     * Test patron login when login method is set to email
     *
     * @param array  $config     Driver config
     * @param array  $expected   Expected results
     * @param string $fixtureKey Fixture key for response mapping
     *
     * @return       void
     * @dataProvider getTestPatronLoginData
     */
    public function testPatronLogin(array $config, array $expected, string $fixtureKey): void
    {
        $this->createConnector('get-patron-response', $config, $fixtureKey);
        $result = $this->driver->patronLogin('1111', '1212');
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getHolding with location type to item status mappings
     *
     * @return void
     */
    public function testGetHoldingWithMappings(): void
    {
        $config = $this->defaultDriverConfig;
        $config['Holdings'] = [
            'locationTypeItemStatus' => [
                'AVAIL' => 'Always There:available',
                'ORDER' => 'Order Only', // availability determined by other attributes
                'STAFF' => 'Staff Use:uncertain',
                'UNAVAIL' => 'Newer There:unavailable',
            ],
        ];
        $this->createConnector('get-holding-with-mappings', $config);
        $result = $this->driver->getHolding('1111', null, ['itemLimit' => 10]);
        $result['holdings'] = $this->convertTranslatableStrings($result['holdings']);
        $this->assertJsonStringEqualsJsonFile(
            $this->getFixturePath('alma/holdings-with-mappings.json'),
            json_encode($result)
        );
    }

    /**
     * Convert TranslatableString instances for easier comparison
     *
     * @param array $array Array to process
     *
     * @return array
     */
    protected function convertTranslatableStrings(array $array): array
    {
        foreach ($array as &$current) {
            if (is_array($current)) {
                $current = $this->convertTranslatableStrings($current);
            } elseif ($current instanceof TranslatableString) {
                $current = $current->getDisplayString() . '|'
                    . (string)$current;
            }
        }
        unset($current);

        return $array;
    }
}
