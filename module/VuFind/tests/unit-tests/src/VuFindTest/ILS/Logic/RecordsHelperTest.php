<?php

/**
 * Unit tests for the RecordsHelper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Logic;

use PHPUnit\Framework\TestCase;
use VuFind\Config\Config;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\Record\Loader;
use VuFind\RecordDriver\AbstractBase;

/**
 * Unit tests for the RecordsHelper
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecordsHelperTest extends TestCase
{
    /**
     * Test getDrivers with empty array
     *
     * @return void
     */
    public function testGetDriversWithEmptyArray(): void
    {
        $config = $this->createMock(Config::class);
        $loader = $this->createMock(Loader::class);

        $loader->expects($this->never())
            ->method('loadBatch');

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->getDrivers([]);

        $this->assertSame([], $result);
    }

    /**
     * Test getDrivers with valid records
     *
     * @return void
     */
    public function testGetDriversWithValidRecords(): void
    {
        $config = $this->createMock(Config::class);
        $loader = $this->createMock(Loader::class);

        $records = [
            ['id' => 'record1', 'source' => 'Solr', 'title' => 'Test Book 1'],
            ['id' => 'record2', 'source' => 'Solr', 'title' => 'Test Book 2'],
            ['id' => 'record3', 'source' => 'VuFind', 'title' => 'Test Book 3'],
        ];

        $expectedIds = [
            ['id' => 'record1', 'source' => 'Solr'],
            ['id' => 'record2', 'source' => 'Solr'],
            ['id' => 'record3', 'source' => 'VuFind'],
        ];

        $driver1 = $this->createMock(AbstractBase::class);
        $driver2 = $this->createMock(AbstractBase::class);
        $driver3 = $this->createMock(AbstractBase::class);

        $mockDrivers = [$driver1, $driver2, $driver3];

        $loader->expects($this->once())
            ->method('loadBatch')
            ->with($expectedIds, true)
            ->willReturn($mockDrivers);

        $driver1->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[0]);

        $driver2->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[1]);

        $driver3->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[2]);

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->getDrivers($records);

        $this->assertSame($mockDrivers, $result);
    }

    /**
     * Test getDrivers with records missing id field
     *
     * @return void
     */
    public function testGetDriversWithMissingId(): void
    {
        $config = $this->createMock(Config::class);
        $loader = $this->createMock(Loader::class);

        $records = [
            ['source' => 'Solr', 'title' => 'Test Book'],
        ];

        $expectedIds = [
            ['id' => '', 'source' => 'Solr'],
        ];

        $driver = $this->createMock(AbstractBase::class);
        $mockDrivers = [$driver];

        $loader->expects($this->once())
            ->method('loadBatch')
            ->with($expectedIds, true)
            ->willReturn($mockDrivers);

        $driver->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[0]);

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->getDrivers($records);

        $this->assertSame($mockDrivers, $result);
    }

    /**
     * Test getDrivers with records using default search backend
     *
     * @return void
     */
    public function testGetDriversWithDefaultSearchBackend(): void
    {
        $config = $this->createMock(Config::class);
        $loader = $this->createMock(Loader::class);

        $records = [
            ['id' => 'record1', 'title' => 'Test Book'],
        ];

        $expectedIds = [
            ['id' => 'record1', 'source' => DEFAULT_SEARCH_BACKEND],
        ];

        $driver = $this->createMock(AbstractBase::class);
        $mockDrivers = [$driver];

        $loader->expects($this->once())
            ->method('loadBatch')
            ->with($expectedIds, true)
            ->willReturn($mockDrivers);

        $driver->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[0]);

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->getDrivers($records);

        $this->assertSame($mockDrivers, $result);
    }

    /**
     * Data provider for ajax configuration tests
     *
     * @return array
     */
    public static function ajaxConfigProvider(): array
    {
        return [
            'ajax explicitly enabled' => [
                ['Authentication' => ['enableAjax' => true]],
            ],
            'ajax setting not specified (default enabled)' => [
                ['Authentication' => []],
            ],
        ];
    }

    /**
     * Test collectRequestStats when ajax is enabled
     *
     * @param array $configData Configuration data
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ajaxConfigProvider')]
    public function testCollectRequestStatsWithAjaxEnabled(array $configData): void
    {
        $config = new Config($configData);
        $loader = $this->createMock(Loader::class);

        $ilsDetails1 = ['id' => 'record1', 'status' => 'available'];
        $ilsDetails2 = ['id' => 'record2', 'status' => 'pending'];

        $driver1 = $this->createMock(AbstractBase::class);
        $driver2 = $this->createMock(AbstractBase::class);

        $driver1->expects($this->once())
            ->method('getExtraDetail')
            ->with('ils_details')
            ->willReturn($ilsDetails1);

        $driver2->expects($this->once())
            ->method('getExtraDetail')
            ->with('ils_details')
            ->willReturn($ilsDetails2);

        $records = [$driver1, $driver2];

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->collectRequestStats($records);

        $expected = [
            'available' => 0,
            'in_transit' => 0,
            'other' => 2,
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * Test collectRequestStats when ajax is disabled
     *
     * @return void
     */
    public function testCollectRequestStatsWithAjaxDisabled(): void
    {
        $config = new Config(['Authentication' => ['enableAjax' => false]]);
        $loader = $this->createMock(Loader::class);

        $driver = $this->createMock(AbstractBase::class);
        $driver->expects($this->never())
            ->method('getExtraDetail');

        $records = [$driver];

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->collectRequestStats($records);

        $this->assertNull($result);
    }

    /**
     * Test collectRequestStats with empty records array
     *
     * @return void
     */
    public function testCollectRequestStatsWithEmptyRecords(): void
    {
        $config = new Config(['Authentication' => ['enableAjax' => true]]);
        $loader = $this->createMock(Loader::class);

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->collectRequestStats([]);

        $expected = [
            'available' => 0,
            'in_transit' => 0,
            'other' => 0,
        ];
        $this->assertSame($expected, $result);
    }

    /**
     * Test getDrivers maintains correct order from loadBatch
     *
     * @return void
     */
    public function testGetDriversMaintainsOrder(): void
    {
        $config = $this->createMock(Config::class);
        $loader = $this->createMock(Loader::class);

        $records = [
            ['id' => 'record3', 'source' => 'Solr'],
            ['id' => 'record1', 'source' => 'Solr'],
            ['id' => 'record2', 'source' => 'Solr'],
        ];

        $driver1 = $this->createMock(AbstractBase::class);
        $driver2 = $this->createMock(AbstractBase::class);
        $driver3 = $this->createMock(AbstractBase::class);

        $mockDrivers = [$driver1, $driver2, $driver3];

        $loader->expects($this->once())
            ->method('loadBatch')
            ->willReturn($mockDrivers);

        $driver1->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[0]);

        $driver2->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[1]);

        $driver3->expects($this->once())
            ->method('setExtraDetail')
            ->with('ils_details', $records[2]);

        $helper = new RecordsHelper($config, $loader);
        $result = $helper->getDrivers($records);

        $this->assertSame([$driver1, $driver2, $driver3], $result);
    }
}
