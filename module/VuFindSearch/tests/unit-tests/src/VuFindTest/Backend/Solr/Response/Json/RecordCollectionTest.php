<?php

/**
 * Unit tests for simple JSON-based record collection.
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
namespace VuFindTest\Backend\Solr\Json\Response;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindTest\RecordDriver\TestHarness;
use PHPUnit_Framework_TestCase;

/**
 * Unit tests for simple JSON-based record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test that the object returns appropriate defaults for missing elements.
     *
     * @return void
     */
    public function testDefaults()
    {
        $coll = new RecordCollection([]);
        $this->assertEquals(
            'VuFindSearch\Backend\Solr\Response\Json\Spellcheck',
            get_class($coll->getSpellcheck())
        );
        $this->assertEquals(0, $coll->getTotal());
        $this->assertEquals(
            'VuFindSearch\Backend\Solr\Response\Json\Facets',
            get_class($coll->getFacets())
        );
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
                'response' => ['numFound' => 10, 'start' => 5]
            ]
        );
        for ($i = 0; $i < 5; $i++) {
            $coll->add($this->getMock('VuFindSearch\Response\RecordInterface'));
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
                ]
            ]
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
                ]
            ]
        ];
        $coll = new RecordCollection($input);
        $spell = $coll->getSpellcheck();
        $this->assertEquals(1, count($spell));
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
        $this->assertEquals(3, count($final));
        $this->assertTrue(in_array($r1, $final));
        $this->assertTrue(in_array($r2, $final));
        $this->assertTrue(in_array($r3, $final));
    }
}