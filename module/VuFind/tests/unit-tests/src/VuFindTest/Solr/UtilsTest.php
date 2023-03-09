<?php

/**
 * Solr Utils Test Class
 *
 * PHP version 7
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
        $result = Utils::parseRange("[1 TO 100]");
        $this->assertEquals('1', $result['from']);
        $this->assertEquals('100', $result['to']);

        // test whitespace handling:
        $result = Utils::parseRange("[1      TO     100]");
        $this->assertEquals('1', $result['from']);
        $this->assertEquals('100', $result['to']);

        // test invalid ranges:
        $this->assertFalse(Utils::parseRange('1 TO 100'));
        $this->assertFalse(Utils::parseRange('[not a range to me]'));
    }

    /**
     * Test sanitizeDate functionality.
     *
     * @return void
     */
    public function testSanitizeDate()
    {
        $tests = [
            '[2014]' => '2014-01-01',
            'n.d.' => null,
            'may 7, 1981' => '1981-05-07',
            'July 1570' => '1570-07-01',
            'incomprehensible garbage' => null,
            '1930/12/21' => '1930-12-21',
            '1964?' => '1964-01-01',
            '1947-3' => '1947-03-01',
            '1973-02-31' => '1973-02-01',       // illegal day
            '1973-31-31' => '1973-01-01',       // illegal month
            '1964-zz' => '1964-01-01',
            '1964-01-zz' => '1964-01-01',
            'Winter 2012' => '2012-01-01',
            '05-1901' => '1901-05-01',
            '5-1901' => '1901-05-01',
            '05/1901' => '1901-05-01',
            '5/1901' => '1901-05-01',
            '2nd Quarter 2004' => '2004-01-01',
            'Nov 2009 and Dec 2009' => '2009-01-01',
        ];

        foreach ($tests as $in => $out) {
            $this->assertEquals(
                $out === null ? null : $out . 'T00:00:00Z', // append standard time value unless null
                Utils::sanitizeDate($in)
            );
        }
    }
}
