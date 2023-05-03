<?php

/**
 * QueryAdapter unit tests.
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

namespace VuFindTest\Search;

use VuFind\Search\QueryAdapter;
use VuFindSearch\Query\Query;

/**
 * QueryAdapter unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class QueryAdapterTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test various conversions.
     *
     * @return void
     */
    public function testConversions()
    {
        $cases = ['basic', 'advanced'];
        foreach ($cases as $case) {
            // Load minified, unminified, and Query object data:
            $min = unserialize($this->getFixture('searches/' . $case . '/min'));
            $q = unserialize($this->getFixture('searches/' . $case . '/query'));

            // Test conversion of minified data:
            $this->assertEquals($q, QueryAdapter::deminify($min));

            // Test minification of a Query:
            $this->assertEquals($min, QueryAdapter::minify($q));
        }
    }

    /**
     * Test that when one part of the query contains an operator, ALL parts of the
     * query contain an operator. (We want to be sure that in cases where the first
     * part of the query has no operator associated with it, a blank value is filled
     * in as a placeholder.
     *
     * @return void
     */
    public function testOperatorDefinedEverywhere()
    {
        $q = unserialize($this->getFixture('searches/operators'));
        $minified = QueryAdapter::minify($q);

        // First, check that count of 'o' values matches count of queries in group:
        $callback = function ($carry, $item) {
            return $carry + (isset($item['o']) ? 1 : 0);
        };
        $this->assertEquals(
            count($minified[0]['g']),
            array_reduce($minified[0]['g'], $callback, 0)
        );

        // Next, confirm that first operator is set to empty (filler) value:
        $this->assertEquals('', $minified[0]['g'][0]['o']);

        // Finally, make sure that we can round-trip back to the input.
        $this->assertEquals($q, QueryAdapter::deminify($minified));
    }

    /**
     * Test building an advanced query from a request.
     *
     * @return void
     */
    public function testAdvancedRequest()
    {
        $req = unserialize($this->getFixture('searches/advanced/request'));
        $q = unserialize($this->getFixture('searches/advanced/query'));
        $this->assertEquals($q, QueryAdapter::fromRequest($req, 'AllFields'));
    }

    /**
     * Test building an advanced query from an empty request.
     *
     * @return void
     */
    public function testEmptyRequest()
    {
        $req = new \Laminas\Stdlib\Parameters([]);
        $this->assertEquals(new Query(), QueryAdapter::fromRequest($req, 'AllFields'));
    }

    /**
     * Test display capabilities.
     *
     * @return void
     */
    public function testDisplay()
    {
        // Array of fixture directory => expected display query
        $cases = [
            'basic' => 'john smith',
            'advanced' => '(CallNumber:oranges AND toc:bananas AND ISN:pears) OR '
                . '(Title:cars OR Subject:trucks) NOT ((AllFields:squid))',
        ];

        // Create simple closure to fill in for translation callbacks:
        $echo = function ($str) {
            return $str;
        };

        // Run the tests:
        foreach ($cases as $case => $expected) {
            $q = unserialize($this->getFixture('searches/' . $case . '/query'));
            $this->assertEquals($expected, QueryAdapter::display($q, $echo, $echo));
        }
    }
}
