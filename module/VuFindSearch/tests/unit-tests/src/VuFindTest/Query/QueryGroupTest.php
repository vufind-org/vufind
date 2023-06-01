<?php

/**
 * Unit tests for QueryGroup class.
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
use VuFindSearch\Query\QueryGroup;

/**
 * Unit tests for QueryGroup class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class QueryGroupTest extends TestCase
{
    /**
     * Test containsTerm() method
     *
     * @return void
     */
    public function testContainsTerm()
    {
        $q = $this->getSampleQueryGroup();

        // Should report true for actual contained terms:
        $this->assertTrue($q->containsTerm('test'));
        $this->assertTrue($q->containsTerm('word'));
        $this->assertTrue($q->containsTerm('query'));

        // Should not contain a non-present term:
        $this->assertFalse($q->containsTerm('garbage'));

        // Should not contain a partial term (matches on word boundaries):
        $this->assertFalse($q->containsTerm('tes'));
    }

    /**
     * Test getAllTerms() method
     *
     * @return void
     */
    public function testGetAllTerms()
    {
        $q = $this->getSampleQueryGroup();
        $this->assertEquals('test query multi word query', $q->getAllTerms());
    }

    /**
     * Test replaceTerm() method
     *
     * @return void
     */
    public function testReplaceTerm()
    {
        $q = $this->getSampleQueryGroup();
        $q->replaceTerm('query', 'question');
        $this->assertEquals('test question multi word question', $q->getAllTerms());
    }

    /**
     * Test replaceTerm() method with and without normalization using complex input
     *
     * @return void
     */
    public function testReplaceTermWithNormalization()
    {
        $normalizer = new \VuFind\Normalizer\DefaultSpellingNormalizer();
        // Without normalization we only replace the accented instance of "query":
        $q = $this->getSampleQueryGroupWithWeirdCharacters();
        $q->replaceTerm('quéry', 'quéstion');
        $this->assertEquals('tést quéstion multi WORD query', $q->getAllTerms());
        // With normalization, we replace both instances of "query":
        $q = $this->getSampleQueryGroupWithWeirdCharacters();
        $q->replaceTerm('quéry', 'quéstion', $normalizer);
        $this->assertEquals('test quéstion multi word quéstion', $q->getAllTerms());
    }

    /**
     * Test QueryGroup cloning.
     *
     * @return void
     */
    public function testClone()
    {
        $q = $this->getSampleQueryGroup();
        $qClone = clone $q;
        $q->replaceTerm('query', 'question');
        $qClone->setOperator('AND');
        $this->assertEquals('test question multi word question', $q->getAllTerms());
        $this->assertEquals('OR', $q->getOperator());
        $this->assertEquals('test query multi word query', $qClone->getAllTerms());
        $this->assertEquals('AND', $qClone->getOperator());
    }

    /**
     * Test setting/clearing of reduced handler.
     *
     * @return void
     */
    public function testReducedHandler()
    {
        $q = $this->getSampleQueryGroup();
        $q->setReducedHandler('foo');
        $this->assertEquals('foo', $q->getReducedHandler());
        $q->unsetReducedHandler();
        $this->assertEquals(null, $q->getReducedHandler());
    }

    /**
     * Test setting an invalid operator.
     *
     * @return void
     */
    public function testIllegalOperator()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown or invalid boolean operator: fizz');

        $q = $this->getSampleQueryGroup();
        $q->setOperator('fizz');
    }

    /**
     * Test detection of normalized terms.
     *
     * @return void
     */
    public function testContainsTermWithNormalization()
    {
        $normalizer = new \VuFind\Normalizer\DefaultSpellingNormalizer();
        $q = $this->getSampleQueryGroupWithWeirdCharacters();
        // regular contains will fail because of the accent:
        $this->assertFalse($q->containsTerm('test'));
        // normalized contains will succeed:
        $this->assertTrue($q->containsTerm('test', $normalizer));
    }

    /**
     * Get a test object.
     *
     * @return QueryGroup
     */
    protected function getSampleQueryGroup()
    {
        $q1 = new Query('test');
        $q2 = new Query('query');
        $q3 = new Query('multi word query');
        return new QueryGroup('OR', [$q1, $q2, $q3]);
    }

    /**
     * Get a test object with uppercase and accents.
     *
     * @return QueryGroup
     */
    protected function getSampleQueryGroupWithWeirdCharacters()
    {
        $q1 = new Query('tést');
        $q2 = new Query('Quéry');
        $q3 = new Query('multi WORD query');
        return new QueryGroup('OR', [$q1, $q2, $q3]);
    }
}
