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
     * @param string $test   Name of test fixture to load
     * @param array  $config Driver configuration (null to use default)
     *
     * @return void
     */
    protected function createConnector(string $test, array $config = null): void
    {
        // Setup test responses
        $this->fixtureSteps = $this->getJsonFixture("alma/responses/$test.json");
        $this->currentFixture = $test;
        $this->currentFixtureStep = 0;
        // Create a stub for the class
        $this->driver = $this->getMockBuilder(Alma::class)
            ->setConstructorArgs(
                [
                    new \VuFind\Date\Converter(),
                ]
            )->onlyMethods(['makeRequest'])
            ->getMock();
        // Configure the stub
        $this->driver->setConfig($config ?? $this->defaultDriverConfig);
        $cache = new \Laminas\Cache\Storage\Adapter\Memory();
        $cache->setOptions(['memory_limit' => -1]);
        $this->driver->setCacheStorage($cache);
        $this->driver->expects($this->any())
            ->method('makeRequest')
            ->will($this->returnCallback([$this, 'mockMakeRequest']));
        $this->driver->init();
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
