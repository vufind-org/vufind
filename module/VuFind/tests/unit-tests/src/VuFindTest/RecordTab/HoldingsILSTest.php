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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Brad Busenius <bbusenius@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

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
}
