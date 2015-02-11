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
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Backend\Solr\QueryBuilder;

/**
 * Unit tests for SOLR query builder
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryBuilderTest extends \VuFindTest\Unit\TestCase
{
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
            array("", "*:*"),                         // empty query
            array("()", "*:*"),                       // empty parens
            array("((()))", "*:*"),                   // nested empty parens
            array("((())", "*:*"),                    // mismatched parens
            array("this that ()", "this that"),       // text mixed w/ empty parens
            array('"()"', '"()"'),                    // empty parens in quotes
            array('title - sub', 'title sub'),        // freestanding hyphen
            array('"title - sub"', '"title - sub"'),  // freestanding hyphen in quotes
            array('test~1', 'test'),                  // meaningless proximity
            array('test~1.', 'test'),                 // meaningless proximity w/dec.
            array('test~1.000', 'test'),              // meaningless proximity w/dec.
            array('test~1 fish', 'test fish'),        // meaningless proximity
            array('test~1. fish', 'test fish'),       // meaningless proximity w/dec.
            array('test~1.000 fish', 'test fish'),    // meaningless proximity w/dec.
            array('"test~1"', '"test~1"'),            // meaningless prox. in quotes
            array('test~0.9', 'test~0.9'),            // valid proximity
            array('test~10', 'test~10'),              // illegal prox. (leave alone)
            array('test~10 fish', 'test~10 fish'),    // illegal prox. (leave alone)
            array('^10 test^10', '10 test10'),        // invalid boosts
            array('^10', '10'),                       // invalid boosts
            array('test^ test^6', 'test test6'),      // invalid boosts
            array('test^1 test^2', 'test^1 test^2'),  // valid boosts
            array('this / that', 'this that'),        // freestanding slash
            array('/ this', 'this'),                  // leading slash
            array('title /', 'title'),                // trailing slash
            array('this - that', 'this that'),        // freestanding hyphen
            array('- this', 'this'),                  // leading hyphen
            array('title -', 'title'),                // trailing hyphen
            array('AND', 'and'),                      // freestanding operator
            array('OR', 'or'),                        // freestanding operator
            array('NOT', 'not'),                      // freestanding operator
            array('*bad', 'bad'),                     // leading wildcard
            array('?bad', 'bad'),                     // leading wildcard
            array("\xE2\x80\x9Ca\xE2\x80\x9D", '"a"'),// fancy quotes
            array('a:{a TO b} [ }', 'a:{a TO b}'),    // floating braces/brackets
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

    /**
     * Test that the appropriate handler gets called for a quoted search when exact
     * settings are enabled.
     *
     * @return void
     */
    public function testExactQueryHandler()
    {
        $qb = new QueryBuilder(
            array(
                'test' => array(
                    'DismaxFields' => array('a', 'b'),
                    'ExactSettings' => array(
                        'DismaxFields' => array('c', 'd')
                    )
                )
            )
        );

        // non-quoted search uses main DismaxFields
        $q = new Query('q', 'test');
        $response = $qb->build($q);
        $qf = $response->get('qf');
        $this->assertEquals('a b', $qf[0]);

        // quoted search uses ExactSettings>DismaxFields
        $q = new Query('"q"', 'test');
        $response = $qb->build($q);
        $qf = $response->get('qf');
        $this->assertEquals('c d', $qf[0]);
    }

    /**
     * Test generation with a query handler with a filter set and DisMax settings
     *
     * @return void
     */
    public function testQueryHandlerWithFilterQueryAndDisMax()
    {
        $qb = new QueryBuilder(
            array(
                'test' => array('DismaxFields' => array('a'), 'FilterQuery' => 'a:filter')
            )
        );
        $q = new Query('q', 'test');
        $response = $qb->build($q);
        $fq = $response->get('fq');
        $this->assertEquals('a:filter', $fq[0]);
    }

    /**
     * Test generation with a query handler with a filter set and no DisMax settings
     *
     * @return void
     */
    public function testQueryHandlerWithFilterQueryAndNoDisMax()
    {
        $qb = new QueryBuilder(
            array(
                'test' => array('FilterQuery' => 'a:filter')
            )
        );
        $q = new Query('q', 'test');
        $response = $qb->build($q);
        $q = $response->get('q');
        $this->assertEquals('((q) AND (a:filter))', $q[0]);
    }

    /**
     * Test generation with a query handler with a filter set and no DisMax settings
     * when the query is "all records"
     *
     * @return void
     */
    public function testMatchAllQueryWithFilterQueryAndNoDisMax()
    {
        $qb = new QueryBuilder(
            array(
                'test' => array('FilterQuery' => 'a:filter')
            )
        );
        $q = new Query('*:*', 'test');
        $response = $qb->build($q);
        $q = $response->get('q');
        $this->assertEquals('a:filter', $q[0]);
    }

    /**
     * Test generation with highlighting
     *
     * @return void
     */
    public function testHighlighting()
    {
        $qb = new QueryBuilder(
            array(
                'test' => array(
                    'DismaxFields' => array('test1'),
                    'DismaxParams' => array(array('bq', 'boost'))
                )
            )
        );

        $q = new Query('*:*', 'test');

        // No hl.q if highlighting query disabled:
        $qb->setCreateHighlightingQuery(false);
        $response = $qb->build($q);
        $hlQ = $response->get('hl.q');
        $this->assertEquals(null, $hlQ[0]);

        // hl.q if highlighting query enabled:
        $qb->setCreateHighlightingQuery(true);
        $response = $qb->build($q);
        $hlQ = $response->get('hl.q');
        $this->assertEquals('*:*', $hlQ[0]);
    }

    /**
     * Test generation with spelling
     *
     * @return void
     */
    public function testSpelling()
    {
        $qb = new QueryBuilder(
            array(
                'test' => array(
                    'DismaxFields' => array('test1'),
                    'DismaxParams' => array(array('bq', 'boost'))
                )
            )
        );

        $q = new Query('my friend', 'test');

        // No spellcheck.q if spellcheck query disabled:
        $qb->setCreateSpellingQuery(false);
        $response = $qb->build($q);
        $spQ = $response->get('spellcheck.q');
        $this->assertEquals(null, $spQ[0]);

        // spellcheck.q if spellcheck query enabled:
        $qb->setCreateSpellingQuery(true);
        $response = $qb->build($q);
        $spQ = $response->get('spellcheck.q');
        $this->assertEquals('my friend', $spQ[0]);
    }

    /**
     * Test generation from a QueryGroup
     *
     * @return void
     */
    public function testQueryGroup()
    {
        $qb = new QueryBuilder(
            array(
                'a' => array(
                    'DismaxFields' => array('field_a'),
                ),
                'b' => array(
                    'DismaxFields' => array('field_b'),
                )
            )
        );

        $q1 = new Query('value1', 'a');
        $q2 = new Query('value2', 'b');
        $q = new QueryGroup('OR', array($q1, $q2));

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('((_query_:"{!dismax qf=\"field_a\" }value1") OR (_query_:"{!dismax qf=\"field_b\" }value2"))', $processedQ[0]);
    }

    /**
     * Test generation from a QueryGroup with advanced syntax
     *
     * @return void
     */
    public function testQueryGroupWithAdvancedSyntax()
    {
        $qb = new QueryBuilder(
            array(
                'a' => array(
                    'DismaxFields' => array('field_a'),
                    'QueryFields' => array(
                        'field_a' => array(array('and', 100)),
                        'field_c' => array(array('and', 200))
                    )
                ),
                'b' => array(
                    'DismaxFields' => array('field_b'),
                )
            )
        );

        $q1 = new Query('value*', 'a');
        $q2 = new Query('value2', 'b');
        $q = new QueryGroup('OR', array($q1, $q2));

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('((field_a:(value*)^100 OR field_c:(value*)^200) OR (_query_:"{!dismax qf=\"field_b\" }value2"))', $processedQ[0]);
    }
}