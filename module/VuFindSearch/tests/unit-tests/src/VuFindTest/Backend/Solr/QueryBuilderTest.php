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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Backend\Solr;

use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Backend\Solr\QueryBuilder;

/**
 * Unit tests for SOLR query builder
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
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
        $tests = [
            ["", "*:*"],                         // empty query
            ["()", "*:*"],                       // empty parens
            ["((()))", "*:*"],                   // nested empty parens
            ["((())", "*:*"],                    // mismatched parens
            ["this that ()", "this that"],       // text mixed w/ empty parens
            ['"()"', '"()"'],                    // empty parens in quotes
            ['title - sub', 'title sub'],        // freestanding hyphen
            ['"title - sub"', '"title - sub"'],  // freestanding hyphen in quotes
            ['test~1', 'test'],                  // meaningless proximity
            ['test~1.', 'test'],                 // meaningless proximity w/dec.
            ['test~1.000', 'test'],              // meaningless proximity w/dec.
            ['test~1 fish', 'test fish'],        // meaningless proximity
            ['test~1. fish', 'test fish'],       // meaningless proximity w/dec.
            ['test~1.000 fish', 'test fish'],    // meaningless proximity w/dec.
            ['"test~1"', '"test~1"'],            // meaningless prox. in quotes
            ['test~0.9', 'test~0.9'],            // valid proximity
            ['test~10', 'test~10'],              // illegal prox. (leave alone)
            ['test~10 fish', 'test~10 fish'],    // illegal prox. (leave alone)
            ['^10 test^10', '10 test10'],        // invalid boosts
            ['^10', '10'],                       // invalid boosts
            ['test^ test^6', 'test test6'],      // invalid boosts
            ['test^1 test^2', 'test^1 test^2'],  // valid boosts
            ['this / that', 'this that'],        // freestanding slash
            ['/ this', 'this'],                  // leading slash
            ['title /', 'title'],                // trailing slash
            ['this - that', 'this that'],        // freestanding hyphen
            ['- this', 'this'],                  // leading hyphen
            ['title -', 'title'],                // trailing hyphen
            ['AND', 'and'],                      // freestanding operator
            ['OR', 'or'],                        // freestanding operator
            ['NOT', 'not'],                      // freestanding operator
            ['*bad', 'bad'],                     // leading wildcard
            ['?bad', 'bad'],                     // leading wildcard
            ["\xE2\x80\x9Ca\xE2\x80\x9D", '"a"'],// fancy quotes
            ['a:{a TO b} [ }', 'a:{a TO b}'],    // floating braces/brackets
        ];
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
     * Return array of [test query, expected result] arrays.
     *
     * @return array
     */
    protected function getQuestionTests()
    {
        // Format: [input, expected output, flags array]
        // @codingStandardsIgnoreStart
        return [
            // trailing question mark:
            ['this?', '(this?) OR (this\?)', []],
            // question mark after first word:
            ['this? that', '((this?) OR (this\?)) that', []],
            // question mark after the middle word:
            ['start this? that', 'start ((this?) OR (this\?)) that', []],
            // question mark with boolean operators:
            ['start AND this? AND that', 'start AND ((this?) OR (this\?)) AND that', []],
            // question mark as a wildcard in the middle of a word:
            ['start t?his that', 'start t?his that', []],
            // multiple ? terms:
            ['start? this?', '((start?) OR (start\?)) ((this?) OR (this\?))', []],
            // ? term in field-specific context:
            ['xyzzy:this?', 'xyzzy:((this?) OR (this\?))', []],
            // ? term in field-specific context w/ extra term:
            ['xyzzy:(this? that)', 'xyzzy:(((this?) OR (this\?)) that)', []],
            // Multiple fields, one w/ ? term:
            ['foo:this? OR bar:tha?t', 'foo:((this?) OR (this\?)) OR bar:tha?t', []],
            // repeating ? term:
            ['this? that? this?', '((this?) OR (this\?)) ((that?) OR (that\?)) ((this?) OR (this\?))', []],
            // ? terms inside quoted phrase (basic flag set to indicate that
            // this does not contain any syntax unsupported by basic Dismax):
            ['"this? that?"', '"this? that?"', ['basic' => true]],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * Run a test case through a basic query.
     *
     * @param QueryBuilder $qb      Query builder
     * @param string       $handler Search handler: dismax|edismax|standard
     * @param array        $test    Test to run
     *
     * @return void
     */
    protected function runBasicQuestionTest($qb, $handler, $test)
    {
        list($input, $output, $flags) = $test;
        if ($handler === 'standard'
            || ($handler === 'dismax' && empty($flags['basic']))
        ) {
            // We expect an extra set of parentheses to be added, unless the
            // string contains a colon, in which case some processing will be
            // skipped due to field-specific query behavior.
            $basicOutput = strstr($output, ':') ? $output : '(' . $output . ')';
        } else {
            $basicOutput = $output;
        }
        $q = new Query($input, 'test');
        $before = $q->getString();
        $response = $qb->build($q);
        // Make sure the query builder had no side effects on the query object:
        $this->assertEquals($before, $q->getString());
        $processedQ = $response->get('q');
        $this->assertEquals($basicOutput, $processedQ[0]);
    }

    /**
     * Run a test case through an advanced query.
     *
     * @param QueryBuilder $qb      Query builder
     * @param string       $handler Search handler: dismax|edismax|standard
     * @param array        $test    Test to run
     *
     * @return void
     */
    protected function runAdvancedQuestionTest($qb, $handler, $test)
    {
        list($input, $output, $flags) = $test;
        if ($handler === 'standard'
            || ($handler === 'dismax' && empty($flags['basic']))
        ) {
            $advOutput = '((' . $output . '))';
        } else {
            $mm = $handler == 'dismax' ? '100%' : '0%';
            $advOutput = "((_query_:\"{!$handler qf=\\\"foo\\\" mm=\\'$mm\\'}"
                . addslashes($output) . '"))';
        }
        $advancedQ = new QueryGroup('AND', [new Query($input, 'test')]);
        $advResponse = $qb->build($advancedQ);
        $advProcessedQ = $advResponse->get('q');
        $this->assertEquals($advOutput, $advProcessedQ[0]);
    }

    /**
     * Run the standard suite of question mark tests, accounting for differences
     * between stanard Lucene, basic Dismax and eDismax handlers.
     *
     * @param array  $builderParams Parameters for QueryBuilder constructor
     * @param string $handler       Search handler: dismax|edismax|standard
     *
     * @return void
     */
    protected function runQuestionTests($builderParams, $handler)
    {
        // Set up an array of expected inputs and outputs:
        $tests = $this->getQuestionTests();
        $qb = new QueryBuilder($builderParams);
        foreach ($tests as $test) {
            $this->runBasicQuestionTest($qb, $handler, $test);
            $this->runAdvancedQuestionTest($qb, $handler, $test);
        }
    }

    /**
     * Test generation with a query handler
     *
     * @return void
     */
    public function testQueryHandler()
    {
        $this->runQuestionTests(
            [
                'test' => []
            ], 'standard'
        );
    }

    /**
     * Test generation with a query handler with regular dismax
     *
     * @return void
     */
    public function testQueryHandlerWithDismax()
    {
        $this->runQuestionTests(
            [
                'test' => ['DismaxHandler' => 'dismax', 'DismaxFields' => ['foo']]
            ], 'dismax'
        );
    }

    /**
     * Test generation with a query handler with edismax
     *
     * @return void
     */
    public function testQueryHandlerWithEdismax()
    {
        $this->runQuestionTests(
            [
                'test' => ['DismaxHandler' => 'edismax', 'DismaxFields' => ['foo']]
            ], 'edismax'
        );
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
            [
                'test' => [
                    'DismaxFields' => ['a', 'b'],
                    'ExactSettings' => [
                        'DismaxFields' => ['c', 'd']
                    ]
                ]
            ]
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
            [
                'test' => ['DismaxFields' => ['a'], 'FilterQuery' => 'a:filter']
            ]
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
            [
                'test' => ['FilterQuery' => 'a:filter']
            ]
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
            [
                'test' => ['FilterQuery' => 'a:filter']
            ]
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
            [
                'test' => [
                    'DismaxFields' => ['test1'],
                    'DismaxParams' => [['bq', 'boost']]
                ]
            ]
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
            [
                'test' => [
                    'DismaxFields' => ['test1'],
                    'DismaxParams' => [['bq', 'boost']]
                ]
            ]
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
            [
                'a' => [
                    'DismaxFields' => ['field_a'],
                ],
                'b' => [
                    'DismaxFields' => ['field_b'],
                ]
            ]
        );

        $q1 = new Query('value1', 'a');
        $q2 = new Query('value2', 'b');
        $q = new QueryGroup('OR', [$q1, $q2]);

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('((_query_:"{!dismax qf=\"field_a\" mm=\\\'100%\\\'}value1") OR (_query_:"{!dismax qf=\"field_b\" mm=\\\'100%\\\'}value2"))', $processedQ[0]);
    }

    /**
     * Test generation from a QueryGroup with advanced syntax
     *
     * @return void
     */
    public function testQueryGroupWithAdvancedSyntax()
    {
        $qb = new QueryBuilder(
            [
                'a' => [
                    'DismaxFields' => ['field_a'],
                    'QueryFields' => [
                        'field_a' => [['and', 100]],
                        'field_c' => [['and', 200]]
                    ]
                ],
                'b' => [
                    'DismaxFields' => ['field_b'],
                ]
            ]
        );

        $q1 = new Query('value*', 'a');
        $q2 = new Query('value2', 'b');
        $q = new QueryGroup('OR', [$q1, $q2]);

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('((field_a:(value*)^100 OR field_c:(value*)^200) OR (_query_:"{!dismax qf=\"field_b\" mm=\\\'100%\\\'}value2"))', $processedQ[0]);
    }

    /**
     * Test generation with multiple quoted phrases.
     *
     * @return void
     */
    public function testMultipleQuotedPhrases()
    {
        $qb = new QueryBuilder(
            [
                'a' => [
                    'QueryFields' => [
                        'field_a' => [['or', '~']],
                    ]
                ]
            ]
        );

        $q = new Query('"foo" "bar" "baz"', 'a');

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('(field_a:("foo" OR "bar" OR "baz"))', $processedQ[0]);
    }

    /**
     * Test generation with mix of quoted and unquoted phrases
     *
     * @return void
     */
    public function testMixedQuotedPhrases()
    {
        $qb = new QueryBuilder(
            [
                'a' => [
                    'QueryFields' => [
                        'field_a' => [['or', '~']],
                    ]
                ]
            ]
        );

        $q = new Query('708396 "708398" 708399 "708400"', 'a');

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('(field_a:(708396 OR "708398" OR 708399 OR "708400"))', $processedQ[0]);
    }

    /**
     * Test generation with mix of quoted and unquoted phrases
     *
     * @return void
     */
    public function testMixedQuotedPhrasesWithEscapedQuote()
    {
        $qb = new QueryBuilder(
            [
                'a' => [
                    'QueryFields' => [
                        'field_a' => [['or', '~']],
                    ]
                ]
            ]
        );

        $q = new Query('708396 "708398" 708399 "foo\"bar"', 'a');

        $response = $qb->build($q);
        $processedQ = $response->get('q');
        $this->assertEquals('(field_a:(708396 OR "708398" OR 708399 OR "foo\"bar"))', $processedQ[0]);
    }
}
