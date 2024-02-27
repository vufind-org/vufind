<?php

/**
 * Unit tests for simple JSON-based record collection.
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

namespace VuFindTest\Backend\Solr\Json\Response;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use VuFindTest\RecordDriver\TestHarness;

use function in_array;

/**
 * Unit tests for simple JSON-based record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionTest extends TestCase
{
    /**
     * Test that the object returns appropriate defaults for missing elements.
     *
     * @return void
     */
    public function testDefaults()
    {
        $coll = new RecordCollection([]);
        $this->assertTrue($coll->getSpellcheck() instanceof Spellcheck);
        $this->assertEquals(0, $coll->getTotal());
        $this->assertIsArray($coll->getFacets());
        $this->assertIsArray($coll->getQueryFacets());
        $this->assertIsArray($coll->getPivotFacets());
        $this->assertEquals([], $coll->getGroups());
        $this->assertEquals([], $coll->getHighlighting());
        $this->assertEquals(0, $coll->getOffset());
    }

    /**
     * Test that the object returns appropriate defaults when given a null response
     * element.
     *
     * @return void
     */
    public function testDefaultsWithNullResponse()
    {
        $coll = new RecordCollection(['response' => null]);
        $this->assertTrue($coll->getSpellcheck() instanceof Spellcheck);
        $this->assertEquals(0, $coll->getTotal());
        $this->assertIsArray($coll->getFacets());
        $this->assertIsArray($coll->getQueryFacets());
        $this->assertIsArray($coll->getPivotFacets());
        $this->assertEquals([], $coll->getGroups());
        $this->assertEquals([], $coll->getHighlighting());
        $this->assertEquals(0, $coll->getOffset());
    }

    /**
     * Test that the object handles offsets properly.
     *
     * @return void
     */
    public function testOffsets()
    {
        $coll = new RecordCollection(
            [
                'response' => ['numFound' => 10, 'start' => 5],
            ]
        );
        for ($i = 0; $i < 5; $i++) {
            $coll->add($this->createMock(\VuFindSearch\Response\RecordInterface::class));
        }
        $coll->rewind();
        $this->assertEquals(5, $coll->key());
        $coll->next();
        $this->assertEquals(6, $coll->key());
    }

    /**
     * Test spelling query retrieval.
     *
     * @return void
     */
    public function testSpellingQuery()
    {
        $input = [
            'responseHeader' => [
                'params' => [
                    'spellcheck.q' => 'foo',
                    'q' => 'bar',
                ],
            ],
        ];
        $coll = new RecordCollection($input);
        $this->assertEquals('foo', $coll->getSpellcheck()->getQuery());
        unset($input['responseHeader']['params']['spellcheck.q']);
        $coll = new RecordCollection($input);
        $this->assertEquals('bar', $coll->getSpellcheck()->getQuery());
    }

    /**
     * Test spelling suggestion retrieval.
     *
     * @return void
     */
    public function testSpellingSuggestions()
    {
        $input = [
            'spellcheck' => [
                'suggestions' => [
                    [
                        'frunkensteen',
                        [
                            'numFound' => 6,
                            'startOffset' => 0,
                            'endOffset' => 12,
                            'origFreq' => 0,
                            'suggestion' => [
                                [
                                'word' => 'frankenstein',
                                'freq' => 218,
                                ],
                                [
                                'word' => 'funkenstein',
                                'freq' => 10,
                                ],
                            ],
                        ],
                    ],
                    ['correctlySpelled', false],
                ],
            ],
        ];
        $coll = new RecordCollection($input);
        $spell = $coll->getSpellcheck();
        $this->assertCount(1, $spell);
    }

    /**
     * Test the replace method.
     *
     * @return void
     */
    public function testReplace()
    {
        $coll = new RecordCollection([]);
        $r1 = new TestHarness();
        $r1->setRawData(['UniqueId' => 1]);
        $r2 = new TestHarness();
        $r2->setRawData(['UniqueId' => 2]);
        $r3 = new TestHarness();
        $r3->setRawData(['UniqueId' => 3]);
        $coll->add($r1);
        $coll->add($r2);
        $coll->replace($r1, $r3);
        $this->assertEquals([$r3, $r2], $coll->getRecords());
    }

    /**
     * Test the shuffle method.
     *
     * @return void
     */
    public function testShuffle()
    {
        // Since shuffle is random, there is no 100% reliable way to test its
        // behavior, but we can at least test that it doesn't corrupt anything.
        $coll = new RecordCollection([]);
        $r1 = new TestHarness();
        $r1->setRawData(['UniqueId' => 1]);
        $r2 = new TestHarness();
        $r2->setRawData(['UniqueId' => 2]);
        $r3 = new TestHarness();
        $r3->setRawData(['UniqueId' => 3]);
        $coll->add($r1);
        $coll->add($r2);
        $coll->add($r3);
        $coll->shuffle();
        $final = $coll->getRecords();
        $this->assertCount(3, $final);
        $this->assertTrue(in_array($r1, $final));
        $this->assertTrue(in_array($r2, $final));
        $this->assertTrue(in_array($r3, $final));
    }

    /**
     * Test that the object handles offsets properly.
     *
     * @return void
     */
    public function testAdd()
    {
        $coll = new RecordCollection(
            [
                'response' => ['numFound' => 10, 'start' => 5],
            ]
        );
        $record = $this->createMock(\VuFindSearch\Response\RecordInterface::class);
        $coll->add($record);
        for ($i = 0; $i < 4; $i++) {
            $coll->add($this->createMock(\VuFindSearch\Response\RecordInterface::class));
        }
        $this->assertEquals(5, $coll->count());
        $coll->add($record);
        $this->assertEquals(5, $coll->count());
        $coll->add($record, false);
        $this->assertEquals(6, $coll->count());
    }

    /**
     * Test facet methods.
     *
     * @return void
     */
    public function testFacets()
    {
        $coll = new RecordCollection(
            [
                'facet_counts' => [
                    'facet_fields' => [
                        'format' => [
                            ['Book', 123],
                            ['Journal', 234],
                            ['Map', 1],
                        ],
                    ],
                ],
            ]
        );
        $facets = $coll->getFacets();
        $this->assertEquals(
            [
                'format' => [
                    'Book' => 123,
                    'Journal' => 234,
                    'Map' => 1,
                ],
            ],
            $facets
        );
        unset($facets['format']['Journal']);
        $coll->setFacets($facets);
        $this->assertEquals(
            [
                'format' => [
                    'Book' => 123,
                    'Map' => 1,
                ],
            ],
            $coll->getFacets()
        );
    }
}
