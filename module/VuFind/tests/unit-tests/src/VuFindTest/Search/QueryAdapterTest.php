<?php

/**
 * QueryAdapter unit tests.
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
namespace VuFindTest\Search;

use VuFind\Search\QueryAdapter;
use VuFindSearch\Query\Query;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * QueryAdapter unit tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class QueryAdapterTest extends TestCase
{
    /**
     * Test various conversions.
     *
     * @return void
     */
    public function testConversions()
    {
        $cases = ['basic', 'advanced'];
        $fixturePath = realpath(__DIR__ . '/../../../../fixtures/searches') . '/';
        foreach ($cases as $case) {
            // Load minified, unminified, and Query object data:
            $min = unserialize(file_get_contents($fixturePath . $case . '/min'));
            $q = unserialize(file_get_contents($fixturePath . $case . '/query'));

            // Test conversion of minified data:
            $this->assertEquals($q, QueryAdapter::deminify($min));

            // Test minification of a Query:
            $this->assertEquals($min, QueryAdapter::minify($q));
        }
    }

    /**
     * Test building an advanced query from a request.
     *
     * @return void
     */
    public function testAdvancedRequest()
    {
        $fixturePath = realpath(__DIR__ . '/../../../../fixtures/searches') . '/advanced/';
        $req = unserialize(file_get_contents($fixturePath . 'request'));
        $q = unserialize(file_get_contents($fixturePath . 'query'));
        $this->assertEquals($q, QueryAdapter::fromRequest($req, 'AllFields'));
    }

    /**
     * Test building an advanced query from an empty request.
     *
     * @return void
     */
    public function testEmptyRequest()
    {
        $req = new \Zend\Stdlib\Parameters([]);
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
            'advanced' => '(CallNumber:oranges AND toc:bananas AND ISN:pears) OR (Title:cars OR Subject:trucks) NOT ((AllFields:squid))'
        ];

        // Create simple closure to fill in for translation callbacks:
        $echo = function ($str) {
            return $str;
        };

        // Run the tests:
        $fixturePath = realpath(__DIR__ . '/../../../../fixtures/searches') . '/';
        foreach ($cases as $case => $expected) {
            $q = unserialize(file_get_contents($fixturePath . $case . '/query'));
            $this->assertEquals($expected, QueryAdapter::display($q, $echo, $echo));
        }
    }
}