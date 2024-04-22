<?php

/**
 * Solr Utils Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Solr;

use VuFind\Solr\Utils;

/**
 * Solr Utils Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UtilsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test parseRange functionality.
     *
     * @return void
     */
    public function testParseRange()
    {
        // basic range test:
        $result = Utils::parseRange('[1 TO 100]');
        $this->assertEquals('1', $result['from']);
        $this->assertEquals('100', $result['to']);

        // test whitespace handling:
        $result = Utils::parseRange('[1      TO     100]');
        $this->assertEquals('1', $result['from']);
        $this->assertEquals('100', $result['to']);

        // test invalid ranges:
        $this->assertFalse(Utils::parseRange('1 TO 100'));
        $this->assertFalse(Utils::parseRange('[not a range to me]'));
    }

    /**
     * Data provider for testSanitizeDate
     *
     * @return array
     */
    public static function sanitizeDateProvider(): array
    {
        return [
            ['[2014]', false, '2014-01-01T00:00:00Z'],
            ['n.d.', false, null],
            ['may 7, 1981', false, '1981-05-07T00:00:00Z'],
            ['July 1570', false, '1570-07-01T00:00:00Z'],
            ['incomprehensible garbage', false, null],
            ['1930/12/21', false, '1930-12-21T00:00:00Z'],
            ['1964?', false, '1964-01-01T00:00:00Z'],
            ['1947-3', false, '1947-03-01T00:00:00Z'],
            ['1973-02-31', false, '1973-02-01T00:00:00Z'],        // illegal day
            ['1973-31-31', false, '1973-01-01T00:00:00Z'],        // illegal month
            ['1964-zz', false, '1964-01-01T00:00:00Z'],
            ['1964-01-zz', false, '1964-01-01T00:00:00Z'],
            ['Winter 2012', false, '2012-01-01T00:00:00Z'],
            ['05-1901', false, '1901-05-01T00:00:00Z'],
            ['5-1901', false, '1901-05-01T00:00:00Z'],
            ['05/1901', false, '1901-05-01T00:00:00Z'],
            ['5/1901', false, '1901-05-01T00:00:00Z'],
            ['2nd Quarter 2004', false, '2004-01-01T00:00:00Z'],
            ['Nov 2009 and Dec 2009', false, '2009-01-01T00:00:00Z'],
            ['29.02.2024', false, '2024-02-29T00:00:00Z'],        // leap year
            ['29.02.2024', true, '2024-02-29T23:59:59Z'],         // leap year
            ['29.02.2023', false, '2023-03-01T00:00:00Z'],        // not a leap year
            ['29.02.2023', true, '2023-03-01T23:59:59Z'],         // not a leap year
            ['2024', true, '2024-12-31T23:59:59Z'],
            ['2024-11', true, '2024-11-30T23:59:59Z'],
            ['2024-02', true, '2024-02-29T23:59:59Z'],            // leap year
            ['2023-02', true, '2023-02-28T23:59:59Z'],            // not a leap year
        ];
    }

    /**
     * Test sanitizeDate functionality.
     *
     * @param string  $date     Date string
     * @param bool    $rangeEnd Is this the end of a range?
     * @param ?string $expected Expected result
     *
     * @dataProvider sanitizeDateProvider
     *
     * @return void
     */
    public function testSanitizeDate($date, $rangeEnd, $expected)
    {
        $this->assertEquals($expected, Utils::sanitizeDate($date, $rangeEnd));
    }
}
