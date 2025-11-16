<?php

/**
 * HoldingsILS Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Brad Busenius <bbusenius@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordDriver\EDS;
use VuFind\RecordTab\HoldingsILS;

/**
 * HoldingsILS Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Brad Busenius <bbusenius@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HoldingsILSTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Default test configuration Patron Empowerment Framework (PEF)
     *
     * @var array
     */
    protected $defaultDriverConfigPEF = [
        'General' => [
            'default_sort' => 'relevance',
        ],
        'ItemGlobalOrder' => [],
        'Catalog' => [
            'EDSHasCatalog' => true,
            'CatalogDatabaseId' => 'cat012345a',
            'CatalogANRegex' => [
                '/^demo\.oai\.edge\.demo\.folio\.provider\.com\.fs00000000\./',
                '/\./',
            ],
            'CatalogANReplace' => [
                '',
                '-',
            ],
        ],
    ];

    /**
     * Generate a new Eds driver to return responses set in a json fixture
     *
     * Overwrites $this->driver
     * Uses session cache
     *
     * @param ?string $test   Name of test fixture to load
     * @param ?array  $config Driver configuration (null to use default)
     *
     * @return EDS
     */
    protected function getDriver(?string $test = null, ?array $config = null): EDS
    {
        $record = new EDS(null, new \VuFind\Config\Config($this->defaultDriverConfigPEF));
        if (null !== $test) {
            $json = $this->getJsonFixture('eds/' . $test . '.json');
            $record->setRawData($json);
        }
        return $record;
    }

    /**
     * Test getUniqueCallNumbers.
     *
     * @return void
     */
    public function testGetUniqueCallNumbers()
    {
        $obj = new HoldingsILS();

        // Display call number is created by combining prefix and call number
        $items1 = [['callnumber' => 'b', 'callnumber_prefix' => 'a']];
        $expected1 = [['callnumber' => 'b', 'display' => 'a b', 'prefix' => 'a']];
        $this->assertSame($expected1, $obj->getUniqueCallNumbers($items1, true));

        // Equal call numbers are deduped
        $items2 = [
            ['callnumber' => 'b', 'callnumber_prefix' => ''],
            ['callnumber' => 'b', 'callnumber_prefix' => ''],
            ['callnumber' => 'b', 'callnumber_prefix' => ''],
        ];
        $expected2 = [['callnumber' => 'b', 'display' => 'b', 'prefix' => '']];
        $this->assertSame($expected2, $obj->getUniqueCallNumbers($items2, true));

        // Unique call numbers are not deduped. They are sorted correctly
        $items3 = [
            ['callnumber' => 'a', 'callnumber_prefix' => ''],
            ['callnumber' => 'b', 'callnumber_prefix' => 'c'],
            ['callnumber' => 'b', 'callnumber_prefix' => ''],
        ];
        $expected3 = [
            0 => ['callnumber' => 'a', 'display' => 'a', 'prefix' => ''],
            2 => ['callnumber' => 'b', 'display' => 'b', 'prefix' => ''],
            1 => ['callnumber' => 'b', 'display' => 'c b', 'prefix' => 'c'],
        ];
        $this->assertSame($expected3, $obj->getUniqueCallNumbers($items3, true));

        // Legacy style call numbers are returned and deduped without prefixes
        $items4 = [
            ['callnumber' => 'b', 'callnumber_prefix' => ''],
            ['callnumber' => 'b', 'callnumber_prefix' => 'a'],
            ['callnumber' => 'b', 'callnumber_prefix' => 'c'],
        ];
        $expected4 = ['b'];
        $this->assertSame($expected4, $obj->getUniqueCallNumbers($items4, false));
    }

    /**
     * Test isVisible true, when driver supports holdings tab
     *
     * @return void
     */
    public function testIsVisibleTrue()
    {
        $searchObj = $this->createMock(\VuFind\ILS\Connection::class);
        $obj = new HoldingsILS($searchObj);

        // Create a mock driver that supports holdings tab
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->once())
            ->method('tryMethod')
            ->with('supportsHoldingsTab', [], true)
            ->willReturn(true);

        $obj->setRecordDriver($driver);
        $this->assertTrue($obj->isVisible());
    }

    /**
     * Test isVisible false, when driver doesn't support holdings tab
     *
     * @return void
     */
    public function testIsVisibleFalse()
    {
        $searchObj = $this->createMock(\VuFind\ILS\Connection::class);
        $obj = new HoldingsILS($searchObj);

        // Create a mock driver that doesn't support holdings tab
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->once())
            ->method('tryMethod')
            ->with('supportsHoldingsTab', [], true)
            ->willReturn(false);

        $obj->setRecordDriver($driver);
        $this->assertFalse($obj->isVisible());
    }
}
