<?php

/**
 * Unit tests for spelling processor.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTest\Search\Solr;

use VuFind\Search\Solr\SpellingProcessor;
use VuFindTest\Unit\TestCase;
use Zend\Config\Config;

/**
 * Unit tests for spelling processor.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SpellingProcessorTest extends TestCase
{
    /**
     * Test defaults.
     *
     * @return void
     */
    public function testDefaultConfigs()
    {
        $sp = new SpellingProcessor();
        $this->assertEquals(true, $sp->shouldSkipNumericSpelling());
        $this->assertEquals(3, $sp->getSpellingLimit());
    }

    /**
     * Test non-default configs.
     *
     * @return void
     */
    public function testNonDefaultConfigs()
    {
        $config = new Config(['limit' => 5, 'skip_numeric' => false]);
        $sp = new SpellingProcessor($config);
        $this->assertEquals(false, $sp->shouldSkipNumericSpelling());
        $this->assertEquals(5, $sp->getSpellingLimit());
    }

    /**
     * Test suggestion processing.
     *
     * @return void
     */
    public function testSuggestionProcessing()
    {
        $sp = new SpellingProcessor();
        $spelling = $this->getFixture('spell1');
        $query = $this->getFixture('query1');
        $this->assertEquals(
            $this->getExpectedQuery1Suggestions(),
            $sp->getSuggestions($spelling, $query)
        );
    }

    /**
     * Test suggestion processing.
     *
     * @return void
     */
    public function testSuggestionProcessingWithNonDefaultLimit()
    {
        $config = new Config(['limit' => 5]);
        $sp = new SpellingProcessor($config);
        $spelling = $this->getFixture('spell1');
        $query = $this->getFixture('query1');
        $this->assertEquals(
            [
                'grumble' => [
                    'freq' => 2,
                    'suggestions' => [
                        'grumbler' => 4,
                        'rumble' => 40,
                        'crumble' => 15,
                        'trumble' => 13,
                        'brumble' => 3,
                    ],
                ],
                'grimble' => [
                    'freq' => 7,
                    'suggestions' => [
                        'trimble' => 110,
                        'gribble' => 21,
                        'grimsley' => 24,
                        'grimalde' => 8,
                    ],
                ],
            ],
            $sp->getSuggestions($spelling, $query)
        );
    }

    /**
     * Test basic suggestions.
     *
     * @return void
     */
    public function testBasicSuggestions()
    {
        $spelling = $this->getFixture('spell1');
        $query = $this->getFixture('query1');
        $params = $this->getServiceManager()->get('VuFind\SearchParamsPluginManager')
            ->get('Solr');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $sp = new SpellingProcessor();
        $this->assertEquals(
            [
                'grumble' => [
                    'freq' => 2,
                    'suggestions' => [
                        'grumbler' => [
                            'freq' => 4,
                            'new_term' => 'grumbler',
                            'expand_term' => '(grumble OR grumbler)',
                        ],
                        'rumble' => [
                            'freq' => 40,
                            'new_term' => 'rumble',
                            'expand_term' => '(grumble OR rumble)',
                        ],
                        'crumble' => [
                            'freq' => 15,
                            'new_term' => 'crumble',
                            'expand_term' => '(grumble OR crumble)',
                        ],
                    ],
                ],
                'grimble' => [
                    'freq' => 7,
                    'suggestions' => [
                        'trimble' => [
                            'freq' => 110,
                            'new_term' => 'trimble',
                            'expand_term' => '(grimble OR trimble)',
                        ],
                        'gribble' => [
                            'freq' => 21,
                            'new_term' => 'gribble',
                            'expand_term' => '(grimble OR gribble)',
                        ],
                        'grimsley' => [
                            'freq' => 24,
                            'new_term' => 'grimsley',
                            'expand_term' => '(grimble OR grimsley)',
                        ],
                    ],
                ],
            ],
            $sp->processSuggestions(
                $this->getExpectedQuery1Suggestions(), $spelling->getQuery(), $params
            )
        );
    }

    /**
     * Test basic suggestions with expansions disabled and phrase display on.
     *
     * @return void
     */
    public function testBasicSuggestionsWithNonDefaultSettings()
    {
        $spelling = $this->getFixture('spell1');
        $query = $this->getFixture('query1');
        $params = $this->getServiceManager()->get('VuFind\SearchParamsPluginManager')
            ->get('Solr');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $config = new Config(['expand' => false, 'phrase' => true]);
        $sp = new SpellingProcessor($config);
        $this->assertEquals(
            [
                'grumble' => [
                    'freq' => 2,
                    'suggestions' => [
                        'grumbler grimble' => [
                            'freq' => 4,
                            'new_term' => 'grumbler',
                        ],
                        'rumble grimble' => [
                            'freq' => 40,
                            'new_term' => 'rumble',
                        ],
                        'crumble grimble' => [
                            'freq' => 15,
                            'new_term' => 'crumble',
                        ],
                    ],
                ],
                'grimble' => [
                    'freq' => 7,
                    'suggestions' => [
                        'grumble trimble' => [
                            'freq' => 110,
                            'new_term' => 'trimble',
                        ],
                        'grumble gribble' => [
                            'freq' => 21,
                            'new_term' => 'gribble',
                        ],
                        'grumble grimsley' => [
                            'freq' => 24,
                            'new_term' => 'grimsley',
                        ],
                    ],
                ],
            ],
            $sp->processSuggestions(
                $this->getExpectedQuery1Suggestions(), $spelling->getQuery(), $params
            )
        );
    }

    /**
     * Test a shingle suggestion.
     *
     * @return void
     */
    public function testShingleSuggestion()
    {
        $this->runSpellingTest(
            2,
            [
                'preamble gribble' => [
                    'freq' => 0,
                    'suggestions' => [
                        'preamble article' => [
                            'freq' => 1,
                            'new_term' => 'preamble article',
                            'expand_term' => '((preamble gribble) OR (preamble article))',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Test an advanced search -- this is important because advanced searches
     * sometimes generate false positive phrase suggestions due to the way
     * flattened spelling queries are created; this test exercises the code
     * that fails over to a secondary query when the main query fails to turn
     * up any relevant suggestions.
     *
     * @return void
     */
    public function testAdvancedQuerySuggestions()
    {
        $this->runSpellingTest(
            4,
            [
                'lake' => [
                    'freq' => 2719,
                    'suggestions' => [
                        'late' => [
                            'freq' => 30753,
                            'new_term' => 'late',
                            'expand_term' => '(lake OR late)',
                        ],
                        'lane' => [
                            'freq' => 8054,
                            'new_term' => 'lane',
                            'expand_term' => '(lake OR lane)',
                        ],
                        'make' => [
                            'freq' => 5735,
                            'new_term' => 'make',
                            'expand_term' => '(lake OR make)',
                        ]
                    ]
                ],
                'geneve' => [
                    'freq' => 662,
                    'suggestions' => [
                        'geneva' => [
                            'freq' => 1170,
                            'new_term' => 'geneva',
                            'expand_term' => '(geneve OR geneva)',
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * Test that spelling tokenization works correctly.
     *
     * @return void
     */
    public function testSpellingTokenization()
    {
        $sp = new SpellingProcessor();
        $this->assertEquals(['single'], $sp->tokenize('single'));
        $this->assertEquals(['two', 'terms'], $sp->tokenize('two terms'));
        $this->assertEquals(['two', 'terms'], $sp->tokenize('two    terms'));
        $this->assertEquals(['apples', 'oranges'], $sp->tokenize('apples OR oranges'));
        $this->assertEquals(['"word"'], $sp->tokenize('"word"'));
        $this->assertEquals(['"word"', 'second'], $sp->tokenize('"word" second'));
        $this->assertEquals([], $sp->tokenize(''));
        $this->assertEquals(['0', 'is', 'zero'], $sp->tokenize('0 is zero'));
        $this->assertEquals(["'twas", 'successful'], $sp->tokenize("'twas successful"));
        $this->assertEquals(['word'], $sp->tokenize('(word)'));
        $this->assertEquals(['word', 'second'], $sp->tokenize('(word) second'));
        $this->assertEquals(['apples', 'oranges', 'pears'], $sp->tokenize('(apples OR oranges) AND pears'));
        $this->assertEquals(['two', 'terms'], $sp->tokenize("two\tterms"));
        $this->assertEquals(
            ['"two words"', 'single', '"three word phrase"', 'single'],
            $sp->tokenize('((("two words" OR single) NOT "three word phrase") AND single)')
        );
        $this->assertEquals(['"unfinished phrase'], $sp->tokenize('"unfinished phrase'));
        $this->assertEquals(['"'], $sp->tokenize('"'));
        $this->assertEquals(['""'], $sp->tokenize('""'));
    }

    /**
     * Test inclusion of numeric terms.
     *
     * @return void
     */
    public function testNumericInclusion()
    {
        $this->runSpellingTest(
            3,
            [
                '1234567980' => [
                    'freq' => 0,
                    'suggestions' => [
                        '12345678' => [
                            'freq' => 1,
                            'new_term' => '12345678'
                        ]
                    ]
                ],
                'sqid' => [
                    'freq' => 0,
                    'suggestions' => [
                        'squid' => [
                            'freq' => 34,
                            'new_term' => 'squid'
                        ]
                    ]
                ],
            ],
            ['limit' => 1, 'skip_numeric' => false, 'expand' => false]
        );
    }

    /**
     * Test exclusion of numeric terms.
     *
     * @return void
     */
    public function testNumericExclusion()
    {
        $this->runSpellingTest(
            3,
            [
                'sqid' => [
                    'freq' => 0,
                    'suggestions' => [
                        'squid' => [
                            'freq' => 34,
                            'new_term' => 'squid'
                        ]
                    ]
                ],
            ],
            ['limit' => 1, 'skip_numeric' => true, 'expand' => false]
        );
    }

    /**
     * Test detection of bad Solr response format.
     *
     * @return void
     *
     * @expectedException        \Exception
     * @expectedExceptionMessage Unexpected suggestion format; spellcheck.extendedResults must be set to true.
     */
    public function testDetectionOfMissingExtendedResultsSetting()
    {
        $sp = new SpellingProcessor(new Config([]));
        $spelling = $this->getFixture('spell5');
        $query = $this->getFixture('query5');
        $sp->getSuggestions($spelling, $query);
    }

    /**
     * Generic test.
     *
     * @param int   $testNum  Test data number to load
     * @param array $expected Expected output
     * @param array $config   SpellingProcessor configuration
     *
     * @return void
     */
    protected function runSpellingTest($testNum, $expected, $config = [])
    {
        $spelling = $this->getFixture('spell' . $testNum);
        $query = $this->getFixture('query' . $testNum);
        $params = $this->getServiceManager()->get('VuFind\SearchParamsPluginManager')
            ->get('Solr');
        $this->setProperty($params, 'query', $query);
        $sp = new SpellingProcessor(new Config($config));
        $suggestions = $sp->getSuggestions($spelling, $query);
        $this->assertEquals(
            $expected,
            $sp->processSuggestions(
                $suggestions, $spelling->getQuery(), $params
            )
        );
    }

    /**
     * Get expected suggestions for the "query1" example.
     *
     * @return array
     */
    protected function getExpectedQuery1Suggestions()
    {
        return [
            'grumble' => [
                'freq' => 2,
                'suggestions' => [
                    'grumbler' => 4,
                    'rumble' => 40,
                    'crumble' => 15,
                ],
            ],
            'grimble' => [
                'freq' => 7,
                'suggestions' => [
                    'trimble' => 110,
                    'gribble' => 21,
                    'grimsley' => 24
                ],
            ],
        ];
    }

    /**
     * Get a fixture object
     *
     * @return mixed
     */
    protected function getFixture($file)
    {
        $fixturePath = realpath(__DIR__ . '/../../../../../fixtures/spell') . '/';
        return unserialize(file_get_contents($fixturePath . $file));
    }
}
