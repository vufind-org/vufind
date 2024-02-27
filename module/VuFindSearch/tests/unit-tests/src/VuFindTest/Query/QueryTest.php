<?php

/**
 * Unit tests for Query class.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Query;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Query\Query;

/**
 * Unit tests for Query class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryTest extends TestCase
{
    /**
     * Test containsTerm() method
     *
     * @return void
     */
    public function testContainsTerm()
    {
        $q = new Query('test query we<(ird and/or');

        // Should contain all actual terms (even those containing regex chars):
        $this->assertTrue($q->containsTerm('test'));
        $this->assertTrue($q->containsTerm('query'));
        $this->assertTrue($q->containsTerm('we<(ird'));
        // A slash can be a word boundary but also a single term
        $this->assertTrue($q->containsTerm('and'));
        $this->assertTrue($q->containsTerm('or'));
        $this->assertTrue($q->containsTerm('and/or'));

        // Should not contain a non-present term:
        $this->assertFalse($q->containsTerm('garbage'));

        // Should not contain a partial term (matches on word boundaries):
        $this->assertFalse($q->containsTerm('tes'));
    }

    /**
     * Test replaceTerm() method
     *
     * @return void
     */
    public function testReplaceTerm()
    {
        $q = new Query('test query we<(ird and/or');
        $q->replaceTerm('we<(ird', 'we>(ird');
        $q->replaceTerm('and/or', 'and-or');
        $this->assertEquals('test query we>(ird and-or', $q->getString());

        $q = new Query('test query we<(ird and/or');
        $q->replaceTerm('and', 'not');
        $this->assertEquals('test query we<(ird not/or', $q->getString());

        $q = new Query('th\bbbt');
        $q->replaceTerm('th\bbbt', 'that');
        $this->assertEquals('that', $q->getString());
    }

    /**
     * Test replacing a term containing punctuation; this exercises a special case
     * in the code.
     *
     * @return void
     */
    public function testReplacePunctuatedTerm()
    {
        $q = new Query('this, that');
        $q->replaceTerm('this,', 'the other,');
        $this->assertEquals('the other, that', $q->getString());
    }

    /**
     * Test multiple replacements -- this simulates the scenario discussed in the
     * VUFIND-1423 JIRA ticket.
     *
     * @return void
     */
    public function testMultipleReplacements()
    {
        $normalizer = new \VuFind\Normalizer\DefaultSpellingNormalizer();
        $q = new Query('color code');
        $q->replaceTerm(
            'color code',
            '((color code) OR (color codes))',
            $normalizer
        );
        $this->assertEquals('((color code) OR (color codes))', $q->getString());
        $q->replaceTerm(
            'color code',
            '((color code) OR (color coded))',
            $normalizer
        );
        $this->assertEquals(
            '((((color code) OR (color coded))) OR (color codes))',
            $q->getString()
        );
    }

    /**
     * Test normalization-related logic
     *
     * @return void
     */
    public function testNormalization()
    {
        $q = new Query('this is a tést OF THINGS');
        $normalizer = new \VuFind\Normalizer\DefaultSpellingNormalizer();
        $this->assertFalse($q->containsTerm('test'));
        $this->assertTrue($q->containsTerm('test', $normalizer));
        $this->assertEquals(
            'this is a test of things',
            $q->getString($normalizer)
        );
        $q->replaceTerm('test', 'mess', $normalizer);
        $this->assertEquals('this is a mess of things', $q->getString());

        // Test UNICODE characters ("composers" in Northern Sámi):
        $q = new Query('šuokŋadahkkit');
        $this->assertTrue($q->containsTerm('šuokŋadahkkit', $normalizer));
        $this->assertTrue($q->containsTerm('suokŋadahkkit', $normalizer));
    }

    /**
     * Test setHandler() method
     *
     * @return void
     */
    public function testSetHandler()
    {
        $q = new Query('foo', 'bar');
        $q->setHandler('baz');
        $this->assertEquals('baz', $q->getHandler());
    }

    /**
     * Test setOperator() method
     *
     * @return void
     */
    public function testSetOperator()
    {
        $q = new Query('foo', 'bar');
        $q->setOperator('baz');
        $this->assertEquals('baz', $q->getOperator());
    }
}
