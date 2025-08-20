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
        $driverManager = $this->getMockBuilder(\VuFind\ILS\Driver\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $driverManager->method('has')->willReturn('Demo');
        $configReader = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $this->connection = new Connection(
            $config,
            $driverManager,
            $configReader
        );
    }

    /**
     * Set TimedBlocks driver configuration
     *
     * @param array $timedBlocks timed blocks as defined in driver.ini
     *
     * @return void
     */
    public function setTimedBlocks(array $timedBlocks): void
    {
        $driver = $this->getMockBuilder(\VuFind\ILS\Driver\Demo::class)
            ->disableOriginalConstructor()->getMock();
        $driver->method('supportsMethod')->willReturn(true);
        $driver->method('getConfig')->with('TimedBlocks')->willReturn($timedBlocks);

        $this->connection->setDriver($driver);
    }

    /**
     * Test that methods are blocked with only startDate defined and the block starts now
     *
     * @return void
     */
    public function testIsMethodBlockedWithStartDate()
    {
        $timedBlocks = [
            'Renewals' => [
                'startDate' => date('Y-m-d', strtotime('now')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertTrue($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are not blocked with startDate and endDate in the future
     *
     * @return void
     */
    public function testIsMethodBlockedWithFutureStartAndEndDate()
    {
        $timedBlocks = [
            'Renewals' => [
                'startDate' => date('Y-m-d', strtotime('now + 1 days')),
                'endDate' => date('Y-m-d', strtotime('now + 2 days')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertFalse($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are blocked with startDate in the past and endDate in the future
     *
     * @return void
     */
    public function testIsMethodBlockedWithStartAndEndDate()
    {
        $timedBlocks = [
            'Renewals' => [
                'startDate' => date('Y-m-d', strtotime('now - 1 days')),
                'endDate' => date('Y-m-d', strtotime('now + 1 days')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertTrue($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are blocked with recurringStart in the past and recurringEnd in the future
     *
     * @return void
     */
    public function testIsMethodBlockedInsideRecurringLimits()
    {
        $timedBlocks = [
            'Renewals' => [
                'recurringStart' => date('H:i', strtotime('now - 1 hours')),
                'recurringEnd' => date('H:i', strtotime('now + 1 hours')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertTrue($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are not blocked with recurringStart in the future and recurringEnd in the past
     *
     * @return void
     */
    public function testIsMethodBlockedOutsideRecurringLimits()
    {
        $timedBlocks = [
            'Renewals' => [
                'recurringStart' => date('H:i', strtotime('now + 1 hours')),
                'recurringEnd' => date('H:i', strtotime('now - 1 hours')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertFalse($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are blocked with recurringStart and recurringEnd in the future
     * and recurringStart is greater than recurringEnd, simulating crossing midnight
     *
     * @return void
     */
    public function testIsMethodBlockedWithRecurringCrossingMidnight()
    {
        $timedBlocks = [
            'Renewals' => [
                'recurringStart' => date('H:i', strtotime('now + 2 hours')),
                'recurringEnd' => date('H:i', strtotime('now + 1 hours')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertTrue($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are not blocked with empty configuration
     *
     * @return void
     */
    public function testIsMethodBlockedWithEmptyConf()
    {
        $timedBlocks = [];
        $this->setTimedBlocks($timedBlocks);
        $this->assertFalse($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are blocked with only endDate defined and in the future
     *
     * @return void
     */
    public function testIsMethodBlockedWithEndDate()
    {
        $timedBlocks = [
            'Renewals' => [
                'endDate' => date('Y-m-d', strtotime('now + 1 days')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertTrue($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are not blocked with startDate and endDate in the past
     *
     * @return void
     */
    public function testIsMethodBlockedWithPastStartAndEndDate()
    {
        $timedBlocks = [
            'Renewals' => [
                'startDate' => date('Y-m-d', strtotime('now - 2 days')),
                'endDate' => date('Y-m-d', strtotime('now - 1 days')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertFalse($this->connection->isMethodBlocked('Renewals'));
    }

    /**
     * Test that methods are not blocked with startDate being after endDate
     *
     * @return void
     */
    public function testIsMethodBlockedWithStartDateAfterEndDate()
    {
        $timedBlocks = [
            'Renewals' => [
                'startDate' => date('Y-m-d', strtotime('now - 1 days')),
                'endDate' => date('Y-m-d', strtotime('now - 2 days')),
            ],
        ];
        $this->setTimedBlocks($timedBlocks);
        $this->assertFalse($this->connection->isMethodBlocked('Renewals'));
    }
}
