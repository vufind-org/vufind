<?php

/**
 * Connection test
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS;

use VuFind\Config\Config;
use VuFind\ILS\Connection;

/**
 * Connnection test
 *
 * @category VuFind
 * @package  Tests
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ConnectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Connection object
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $config = new Config(['driver' => 'Demo']);
        $driverManager = $this->createMock(\VuFind\ILS\Driver\PluginManager::class);
        $driverManager->method('has')->willReturn('Demo');
        $configReader = $this->createMock(\VuFind\Config\PluginManager::class);
        $this->connection = new Connection(
            $config,
            $driverManager,
            $configReader
        );
    }

    /**
     * Set TimedBlocks driver configuration
     *
     * @param array $timedBlocks timed blocks as defined in Demo.ini
     *
     * @return void
     */
    public function setTimedBlocks(array $timedBlocks): void
    {
        $driver = $this->createMock(\VuFind\ILS\Driver\Demo::class);
        $driver->method('supportsMethod')->willReturn(true);
        $driver->method('getConfig')->with('TimedBlocks')->willReturn($timedBlocks);

        $this->connection->setDriver($driver);
    }

    /**
     * Data provider for testIsMethodBlocked
     *
     * @return array
     */
    public static function isMethodBlockedProvider()
    {
        return [
            'only startDate' => [
                [
                    'Renewals' => [
                        'startDate' => date('Y-m-d', strtotime('now')),
                    ],
                ],
                true,
            ],
            'only endDate' => [
                [
                    'Renewals' => [
                        'endDate' => date('Y-m-d', strtotime('now + 1 days')),
                    ],
                ],
                true,
            ],
            'future startDate' => [
                [
                    'Renewals' => [
                        'startDate' => date('Y-m-d', strtotime('now + 1 days')),
                        'endDate' => date('Y-m-d', strtotime('now + 2 days')),
                    ],
                ],
                false,
            ],
            'startDate in the past and endDate in the future' => [
                [
                    'Renewals' => [
                        'startDate' => date('Y-m-d', strtotime('now - 1 days')),
                        'endDate' => date('Y-m-d', strtotime('now + 1 days')),
                    ],
                ],
                true,
            ],
            'inside recurring limits' => [
                [
                    'Renewals' => [
                        'recurringStart' => date('H:i', strtotime('now - 1 hours')),
                        'recurringEnd' => date('H:i', strtotime('now + 1 hours')),
                    ],
                ],
                true,
            ],
            'outside recurring limits' => [
                [
                    'Renewals' => [
                        'recurringStart' => date('H:i', strtotime('now + 1 hours')),
                        'recurringEnd' => date('H:i', strtotime('now - 1 hours')),
                    ],
                ],
                false,
            ],
            'recurring crossing midnight' => [
                [
                    'Renewals' => [
                        'recurringStart' => date('H:i', strtotime('now + 2 hours')),
                        'recurringEnd' => date('H:i', strtotime('now + 1 hours')),
                    ],
                ],
                true,
            ],
            'empty configuration' => [
                [],
                false,
            ],
            'startDate and endDate in the past' => [
                [
                    'Renewals' => [
                        'startDate' => date('Y-m-d', strtotime('now - 2 days')),
                        'endDate' => date('Y-m-d', strtotime('now - 1 days')),
                    ],
                ],
                false,
            ],
            'startDate after endDate' => [
                [
                    'Renewals' => [
                        'startDate' => date('Y-m-d', strtotime('now - 1 days')),
                        'endDate' => date('Y-m-d', strtotime('now - 2 days')),
                    ],
                ],
                false,
            ],
            'invalid values' => [
                [
                    'Renewals' => [
                        'startDate' => 'testing string',
                        'endDate' => 'true',
                        'recurringStart' => 'starting',
                        'recurringEnd' => 'ending',
                    ],
                ],
                false,
            ],
        ];
    }

    /**
     * Test that methods are blocked correctly according to configuration
     *
     * @param array $timedBlocks    timedBlocks as defined in Demo.ini
     * @param bool  $expectedResult The expected result
     *
     * @dataProvider isMethodBlockedProvider
     *
     * @return void
     */
    public function testIsMethodBlocked(array $timedBlocks, bool $expectedResult): void
    {
        $this->setTimedBlocks($timedBlocks);
        $this->assertEquals($this->connection->isMethodBlocked('Renewals'), $expectedResult);
    }
}
