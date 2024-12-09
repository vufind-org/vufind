<?php

/**
 * ILS driver test
 *
 * PHP version 8
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\ILS\Driver\VoyagerRestful;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class VoyagerRestfulTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    /**
     * Default configuration for driver
     *
     * @var array
     */
    protected $defaultConfig = [
        'Catalog' => ['database' => 'foo'],
        'WebServices' => [
            'host' => 'foo',
            'port' => 1234,
            'app' => 'bar',
            'dbKey' => 'fake',
            'patronHomeUbId' => 'baz',
        ],
    ];

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new VoyagerRestful(new \VuFind\Date\Converter());
    }

    /**
     * Test encoding conversion in getPickupLocations()
     *
     * @return void
     */
    public function testGetPickupLocationsConversion(): void
    {
        $location = 'Tést';

        // Create a mock SQL response
        $mockResult = $this->createMock(PDOStatement::class);
        $mockResult->method('fetch')->willReturnCallback(function () use ($location) {
            static $called = false;
            if ($called) {
                return null;
            }
            $called = true;
            return [
                'LOCATION_ID' => 1,
                'LOCATION_NAME' => mb_convert_encoding($location, 'ISO-8859-1', 'UTF-8'),
            ];
        });

        // Use an anonymous class to override the executeSQL method for mocking purposes:
        $driver = $this->getDriverWithMockSqlResponse($mockResult);
        $this->assertEquals(
            [
                [
                    'locationID' => 1,
                    'locationDisplay' => $location,
                ],
            ],
            $driver->getPickUpLocations()
        );
    }

    /**
     * Test that request groups are disabled by default.
     *
     * @return void
     */
    public function testGetRequestGroupsDefaultBehavior(): void
    {
        $this->assertFalse($this->driver->getRequestGroups(1, []));
    }

    /**
     * Test encoding conversion in getRequestGroups()
     *
     * @return void
     */
    public function testGetRequestGroupsConversion(): void
    {
        $name = 'Tést';

        // Create a mock SQL response
        $mockResult = $this->createMock(PDOStatement::class);
        $mockResult->method('fetch')->willReturnCallback(function () use ($name) {
            static $called = false;
            if ($called) {
                return null;
            }
            $called = true;
            return [
                'GROUP_ID' => 1,
                'GROUP_NAME' => mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8'),
            ];
        });

        // Use an anonymous class to override the executeSQL method for mocking purposes:
        $driver = $this->getDriverWithMockSqlResponse($mockResult);
        // Enable request groups
        $driver->setConfig($this->defaultConfig + [
            'Holds' => ['extraHoldFields' => 'requestGroup'],
        ]);
        $driver->init();
        $this->assertEquals(
            [
                [
                    'id' => 1,
                    'name' => $name,
                ],
            ],
            $driver->getRequestGroups(1, [])
        );
    }

    /**
     * Get a VoyagerRestful driver customized to return a mock SQL response.
     *
     * @param MockObject&PDOStatement $mockResult Mock result to return from executeSQL
     *
     * @return VoyagerRestful
     */
    protected function getDriverWithMockSqlResponse(MockObject&PDOStatement $mockResult): VoyagerRestful
    {
        return new class ($mockResult) extends VoyagerRestful {
            /**
             * Constructor
             *
             * @param MockObject&PDOStatement $mockResult Mock result to return from executeSQL
             */
            public function __construct(protected MockObject&PDOStatement $mockResult)
            {
                parent::__construct(new \VuFind\Date\Converter());
            }

            /**
             * Execute an SQL query
             *
             * @param string|array $sql  SQL statement (string or array that includes
             * bind params)
             * @param array        $bind Bind parameters (if $sql is string)
             *
             * @return PDOStatement
             */
            protected function executeSQL($sql, $bind = [])
            {
                return $this->mockResult;
            }
        };
    }
}
