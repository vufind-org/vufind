<?php

/**
 * Unit tests for SOLR query builder
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindTest\Backend\Solr;

use VuFindSearch\Query\Query;
use VuFindSearch\Backend\Solr\QueryBuilder;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for SOLR query builder
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryBuilderTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test capitalizeBooleans functionality.
     *
     * @return void
     */
    public function testCapitalizeBooleans()
    {
        $qb = new QueryBuilder();

        // Set up an array of expected inputs and outputs:
        // @codingStandardsIgnoreStart
        $tests = array(
            array('this not that', 'this NOT that'),        // capitalize not
            array('this and that', 'this AND that'),        // capitalize and
            array('this or that', 'this OR that'),          // capitalize or
            array('apples and oranges (not that)', 'apples AND oranges (NOT that)'),
            array('"this not that"', '"this not that"'),    // do not capitalize inside quotes
            array('"this and that"', '"this and that"'),    // do not capitalize inside quotes
            array('"this or that"', '"this or that"'),      // do not capitalize inside quotes
            array('"apples and oranges (not that)"', '"apples and oranges (not that)"'),
            array('this AND that', 'this AND that'),        // don't mess up existing caps
            array('and and and', 'and AND and'),
            array('andornot noted andy oranges', 'andornot noted andy oranges'),
            array('(this or that) and (apples not oranges)', '(this OR that) AND (apples NOT oranges)'),
            array('this aNd that', 'this AND that'),        // strange capitalization of AND
            array('this nOt that', 'this NOT that'),        // strange capitalization of NOT
        );
        // @codingStandardsIgnoreEnd

        // Test all the operations:
        foreach ($tests as $current) {
            $this->assertEquals(
                $qb->capitalizeBooleans($current[0]), $current[1]
            );
        }
    }

    /**
     * Test capitalizeRanges functionality.
     *
     * @return void
     */
    public function testCapitalizeRanges()
    {
        $qb = new QueryBuilder();

        // Set up an array of expected inputs and outputs:
        // @codingStandardsIgnoreStart
        $tests = array(
            array('"{a to b}"', '"{a to b}"'),              // don't capitalize inside quotes
            array('"[a to b]"', '"[a to b]"'),
            array('[a to b]', '([a TO b] OR [A TO B])'),    // expand alphabetic cases
            array('[a TO b]', '([a TO b] OR [A TO B])'),
            array('[a To b]', '([a TO b] OR [A TO B])'),
            array('[a tO b]', '([a TO b] OR [A TO B])'),
            array('{a to b}', '({a TO b} OR {A TO B})'),
            array('{a TO b}', '({a TO b} OR {A TO B})'),
            array('{a To b}', '({a TO b} OR {A TO B})'),
            array('{a tO b}', '({a TO b} OR {A TO B})'),
            array('[1900 to 1910]', '[1900 TO 1910]'),      // don't expand numeric cases
            array('[1900 TO 1910]', '[1900 TO 1910]'),
            array('{1900 to 1910}', '{1900 TO 1910}'),
            array('{1900 TO 1910}', '{1900 TO 1910}'),
            array('[a      to      b]', '([a TO b] OR [A TO B])'),   // handle extra spaces
            // special case for timestamps:
            array('[1900-01-01t00:00:00z to 1900-12-31t23:59:59z]', '[1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z]'),
            array('{1900-01-01T00:00:00Z       TO   1900-12-31T23:59:59Z}', '{1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z}')
        );
        // @codingStandardsIgnoreEnd

        // Test all the operations:
        foreach ($tests as $current) {
            $this->assertEquals(
                $qb->capitalizeRanges($current[0]), $current[1]
            );
        }
    }

    /**
     * Test normalization of unusual queries.
     *
     * @return void
     */
    public function testNormalization()
    {
        // Set up an array of expected inputs and outputs:
        // @codingStandardsIgnoreStart
        $tests = array(
            array("", "*:*"),                       // empty query
            array("()", "*:*"),                     // empty parens
            array("((()))", "*:*"),                 // nested empty parens
            array("((())", "*:*"),                  // mismatched parens
            array("this that ()", "this that "),    // text mixed w/ empty parens
            array('"()"', '"()"'),                  // empty parens in quotes
            array('title - sub', 'title sub'),      // freestanding hyphen
            array('"title - sub"', '"title - sub"'),// freestanding hyphen in quotes
            array('test~1', 'test'),                // meaningless proximity
            array('test~1.', 'test'),               // meaningless proximity w/dec.
            array('test~1.000', 'test'),            // meaningless proximity w/dec.
            array('test~1 fish', 'test fish'),      // meaningless proximity
            array('test~1. fish', 'test fish'),     // meaningless proximity w/dec.
            array('test~1.000 fish', 'test fish'),  // meaningless proximity w/dec.
            array('"test~1"', '"test~1"'),          // meaningless prox. in quotes
            array('test~0.9', 'test~0.9'),          // valid proximity
            array('test~10', 'test~10'),            // illegal prox. (leave alone)
            array('test~10 fish', 'test~10 fish'),  // illegal prox. (leave alone)
        );
        // @codingStandardsIgnoreEnd

        $qb = new QueryBuilder();
        foreach ($tests as $test) {
            list($input, $output) = $test;
            $q = new Query($input);
            $response = $qb->build($q);
            $processedQ = $response->get('q');
            $this->assertEquals($output, $processedQ[0]);
        }
    }

    /**
     * Test generation with a query handler
     *
     * @return void
     */
    public function testQueryHandler()
    {
        // Set up an array of expected inputs and outputs:
        // @codingStandardsIgnoreStart
        $tests = array(
            array('this?', '((this?) OR (this\?))'),// trailing question mark
        );
        // @codingStandardsIgnoreEnd

        $qb = new QueryBuilder(
            array(
                'test' => array()
            )
        );
        foreach ($tests as $test) {
            list($input, $output) = $test;
            $q = new Query($input, 'test');
            $response = $qb->build($q);
            $processedQ = $response->get('q');
            $this->assertEquals($output, $processedQ[0]);
        }
    }
}