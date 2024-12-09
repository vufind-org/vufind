<?php

/**
 * KohaRest ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use VuFind\ILS\Driver\KohaRest;

/**
 * KohaRest ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class KohaRestTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'Catalog' => [
            'host' => 'http://localhost',
            'clientId' => 'config_id',
            'clientSecret' => 'config_secret',
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
        $this->driver = new KohaRest(
            new \VuFind\Date\Converter(),
            function () {
            },
            new \VuFind\Service\CurrencyFormatter()
        );
    }

    /**
     * Replace makeRequest to inject test returns
     *
     * @param array $request Either a path as string or non-keyed array of path
     *                       elements, or a keyed array of request parameters
     *
     * @return array
     */
    public function mockMakeRequest(array $request): array
    {
        // Get the next step of the test, and make assertions as necessary
        // (we'll skip making assertions if the next step is empty):
        $testData = $this->fixtureSteps[$this->currentFixtureStep] ?? [];
        $this->currentFixtureStep++;
        unset($testData['comment']);
        if (!empty($testData['expectedParams'])) {
            $msg = "Error in step {$this->currentFixtureStep} of fixture: "
                . $this->currentFixture;
            $this->assertEquals($testData['expectedParams'], $request, $msg);
        }

        return [
            'data' => $testData['result'],
            'code' => $testData['status'] ?? 200,
            'headers' => $testData['headers'] ?? [],
        ];
    }

    /**
     * Generate a new KohaRest driver to return responses set in a json fixture
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
        $this->fixtureSteps = $this->getJsonFixture("koharest/responses/$test.json");
        $this->currentFixture = $test;
        $this->currentFixtureStep = 0;
        // Session factory
        $factory = function ($namespace) {
            $manager = new \Laminas\Session\SessionManager();
            return new \Laminas\Session\Container("KohaRest_$namespace", $manager);
        };
        // Create a stub for the class
        $this->driver = $this->getMockBuilder(KohaRest::class)
            ->setConstructorArgs(
                [
                    new \VuFind\Date\Converter(),
                    $factory,
                    new \VuFind\Service\CurrencyFormatter(),
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
     * Test getUrlsForRecord.
     *
     * @return void
     */
    public function testGetUrlsForRecord(): void
    {
        // Default: no links
        $this->assertEmpty($this->driver->getUrlsForRecord(1234));
        // OPAC url with placeholder:
        $this->driver->setConfig(['Catalog' => ['opacURL' => 'http://foo?id=%%id%%']]);
        $this->assertEquals(
            [
                [
                    'url' => 'http://foo?id=1234',
                    'desc' => 'view_in_opac',
                ],
            ],
            $this->driver->getUrlsForRecord(1234)
        );
        // OPAC url without placeholder:
        $this->driver->setConfig(['Catalog' => ['opacURL' => 'http://foo?id=']]);
        $this->assertEquals(
            [
                [
                    'url' => 'http://foo?id=1234',
                    'desc' => 'view_in_opac',
                ],
            ],
            $this->driver->getUrlsForRecord(1234)
        );
    }

    /**
     * Test purging of transaction history
     *
     * @return void
     */
    public function testPurgeTransactionHistoryAll(): void
    {
        $this->createConnector('purge-transaction-history');
        $this->driver->purgeTransactionHistory(['id' => 'bar'], null);
    }

    /**
     * Test that selective deletion of entries from transaction history throws an exception
     *
     * @return void
     */
    public function testPurgeTransactionHistorySelected(): void
    {
        $this->createConnector('purge-transaction-history');
        $this->expectExceptionMessage('Unsupported function');
        $this->driver->purgeTransactionHistory(['id' => 'bar'], [1, 2]);
    }
}
