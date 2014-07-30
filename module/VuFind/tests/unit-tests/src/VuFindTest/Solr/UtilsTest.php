<?php
/**
 * Solr Utils Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Solr;
use VuFind\Solr\Utils;

/**
 * Solr Utils Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class UtilsTest extends \VuFindTest\Unit\TestCase
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
}