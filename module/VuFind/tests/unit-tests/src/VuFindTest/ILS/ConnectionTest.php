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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFindTest\Feature\ConfigRelatedServicesTrait;

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
    use ConfigRelatedServicesTrait;

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
        $mockConfigManager = $this->getMockConfigManager();
        $this->connection = new Connection(
            $config,
            $driverManager,
            $mockConfigManager
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
                        date('Y-m-d', strtotime('now')) . '/',
                    ],
                ],
                [
                    'start' => new \DateTime('today'),
                    'end' => null,
                    'recurring' => false,
                ],
            ],
            'only endDate' => [
                [
                    'Renewals' => [
                        '/' . date('Y-m-d', strtotime('now + 1 days')),
                    ],
                ],
                [
                    'start' => null,
                    'end' => new \DateTime('tomorrow 23:59:59'),
                    'recurring' => false,
                ],
            ],
            'future startDate' => [
                [
                    'Renewals' => [
                        date('Y-m-d', strtotime('now + 1 days')) . '/' . date('Y-m-d', strtotime('now + 2 days')),
                    ],
                ],
                [],
            ],
            'startDate in the past and endDate in the future' => [
                [
                    'Renewals' => [
                        date('Y-m-d', strtotime('now - 1 days')) . '/' . date('Y-m-d', strtotime('now + 1 days')),
                    ],
                ],
                [
                    'start' => new \DateTime('yesterday'),
                    'end' => new \DateTime('tomorrow 23:59:59'),
                    'recurring' => false,
                ],
            ],
            'inside recurring limits' => [
                [
                    'Renewals' => [
                        date('H:i', strtotime('now - 1 hours')) . '/' . date('H:i', strtotime('now + 1 hours')),
                    ],
                ],
                [
                    'start' => new \DateTime(date('H:i', strtotime('now - 1 hours'))),
                    'end' => new \DateTime(date('H:i', strtotime('now + 1 hours'))),
                    'recurring' => true,
                ],
            ],
            'outside recurring limits' => [
                [
                    'Renewals' => [
                        date('H:i', strtotime('now + 1 hours')) . '/' . date('H:i', strtotime('now - 1 hours')),
                    ],
                ],
                [],
            ],
            'recurring block active, fixed date block inactive' => [
                [
                    'Renewals' => [
                        date('H:i', strtotime('now - 1 hours')) . '/' . date('H:i', strtotime('now + 1 hours')),
                        date('Y-m-d', strtotime('now - 2 days')) . '/' . date('Y-m-d', strtotime('now - 1 days')),
                    ],
                ],
                [
                    'start' => new \DateTime(date('H:i', strtotime('now - 1 hours'))),
                    'end' => new \DateTime(date('H:i', strtotime('now + 1 hours'))),
                    'recurring' => true,
                ],
            ],
            'recurring block inactive, fixed date block active' => [
                [
                    'Renewals' => [
                        date('H:i', strtotime('now + 1 hours')) . '/' . date('H:i', strtotime('now + 2 hours')),
                        date('Y-m-d', strtotime('now - 1 days')) . '/' . date('Y-m-d', strtotime('now + 1 days')),
                    ],
                ],
                [
                    'start' => new \DateTime(date('Y-m-d', strtotime('now - 1 days'))),
                    'end' => new \DateTime('tomorrow 23:59:59'),
                    'recurring' => false,
                ],
            ],
            'empty configuration' => [
                [],
                [],
            ],
            'startDate and endDate in the past' => [
                [
                    'Renewals' => [
                        date('Y-m-d', strtotime('now - 2 days')) . '/' . date('Y-m-d', strtotime('now - 1 days')),
                    ],
                ],
                [],
            ],
            'startDate after endDate' => [
                [
                    'Renewals' => [
                        date('Y-m-d', strtotime('now - 1 days')) . '/' . date('Y-m-d', strtotime('now - 2 days')),
                    ],
                ],
                [],
            ],
            'only startTime defined' => [
                [
                    'Renewals' => [
                        date('H:i', strtotime('now')) . '/',
                    ],
                ],
                [],
            ],
            'only endTime defined' => [
                [
                    'Renewals' => [
                        '/' . date('H:i', strtotime('now')),
                    ],
                ],
                [],
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
    public function testIsMethodBlocked(array $timedBlocks, array $expectedResult): void
    {
        $this->setTimedBlocks($timedBlocks);
        $this->assertEquals($expectedResult, $this->connection->getMethodBlock('Renewals'));
    }
}
