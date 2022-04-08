<?php
/**
 * Solr Search Object Results Test
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
namespace VuFindTest\Search\Solr;

use Laminas\Config\Config;
use VuFind\Config\PluginManager;
use VuFind\I18n\TranslatableString;
use VuFind\Record\Loader;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;
use VuFind\Search\Solr\SpellingProcessor;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Service as SearchService;

/**
 * Solr Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test CursorMark functionality.
     *
     * @return void
     */
    public function testCursorMark(): void
    {
        $results = $this->getResults();
        $results->setCursorMark('foo');
        $this->assertEquals('foo', $results->getCursorMark());
    }

    /**
     * Test spelling processor support.
     *
     * @return void
     */
    public function testSpellingProcessor(): void
    {
        $results = $this->getResults();
        $defaultProcessor = $results->getSpellingProcessor();
        $this->assertTrue(
            $defaultProcessor instanceof SpellingProcessor,
            'default spelling processor was created'
        );
        $mockProcessor = $this->createMock(SpellingProcessor::class);
        $results->setSpellingProcessor($mockProcessor);
        $this->assertEquals($mockProcessor, $results->getSpellingProcessor());
        $this->assertNotEquals($defaultProcessor, $mockProcessor);
    }

    /**
     * Test retrieving a result count.
     *
     * @return void
     */
    public function testGetResultTotal(): void
    {
        $collection = new RecordCollection(['response' => ['numFound' => 5]]);
        $searchService = $this->createMock(SearchService::class);
        $searchService->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('Solr'),
                $this->equalTo(new \VuFindSearch\Query\Query()),
                $this->equalTo(0),
                $this->equalTo(20),
                $this->equalTo(
                    new \VuFindSearch\ParamBag(
                        [
                            'spellcheck' => ['true'],
                            'hl' => ['false'],
                        ]
                    )
                )
            )->will($this->returnValue($collection));
        $results = $this->getResults(null, $searchService);
        $this->assertEquals(5, $results->getResultTotal());
    }

    /**
     * Test retrieving facets.
     *
     * @return void
     */
    public function testGetFacetList(): void
    {
        $config = $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->any())
            ->method('get')
            ->will(
                $this->returnValue(
                    new Config(
                        [
                            'SpecialFacets' => [
                                'hierarchical' => [
                                    'building',
                                ],
                            ],
                        ]
                    )
                )
            );

        $results = $this->getResultsFromResponse(
            [
                'response' => [
                    'numFound' => 5
                ],
                'facet_counts' => [
                    'facet_fields' => [
                        'topic_facet' => [
                            ['Research', 16],
                            ['Psychotherapy', 8],
                        ],
                        'building' => [
                            ['0/Main/', 11],
                            ['1/Main/Fiction/', 5],
                            ['0/Sub/', 2],
                        ]
                    ]
                ]
            ],
            $this->getParams(null, $config)
        );

        // No facets configured:
        $facets = $results->getFacetList();
        $this->assertIsArray($facets);
        $this->assertEmpty($facets);

        // Invalid facet:
        $facets = $results->getFacetList(['format' => 'Format']);
        $this->assertIsArray($facets);
        $this->assertEmpty($facets);

        // Valid facet, no configuration:
        $facets = $results->getFacetList(['topic_facet' => 'Topic']);
        $this->assertEquals(
            [
                'topic_facet' => [
                    'label' => 'Topic',
                    'list' => [
                        [
                            'value' => 'Research',
                            'displayText' => 'Research',
                            'count' => 16,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                        [
                            'value' => 'Psychotherapy',
                            'displayText' => 'Psychotherapy',
                            'count' => 8,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                    ],
                ]
            ],
            $facets
        );

        // Make it an OR facet:
        $results->getParams()->addFacet('topic_facet', 'Topic', true);
        $facets = $results->getFacetList();
        $this->assertEquals(
            [
                'topic_facet' => [
                    'label' => 'Topic',
                    'list' => [
                        [
                            'value' => 'Research',
                            'displayText' => 'Research',
                            'count' => 16,
                            'operator' => 'OR',
                            'isApplied' => false,
                        ],
                        [
                            'value' => 'Psychotherapy',
                            'displayText' => 'Psychotherapy',
                            'count' => 8,
                            'operator' => 'OR',
                            'isApplied' => false,
                        ],
                    ],
                ]
            ],
            $facets
        );

        // Add a filter:
        $results->getParams()->addFilter('~topic_facet:Research');
        $facets = $results->getFacetList();
        $this->assertEquals(
            [
                'topic_facet' => [
                    'label' => 'Topic',
                    'list' => [
                        [
                            'value' => 'Research',
                            'displayText' => 'Research',
                            'count' => 16,
                            'operator' => 'OR',
                            'isApplied' => true,
                        ],
                        [
                            'value' => 'Psychotherapy',
                            'displayText' => 'Psychotherapy',
                            'count' => 8,
                            'operator' => 'OR',
                            'isApplied' => false,
                        ],
                    ],
                ]
            ],
            $facets
        );

        // Clone results so that we can test missing hierarchical facet helper later:
        $resultsNoHelper = clone $results;

        // Test hierarchical facet:
        $results->setHierarchicalFacetHelper(new HierarchicalFacetHelper());
        $facets = $results->getFacetList(['building' => 'Building']);
        $this->assertEquals(
            [
                'building' => [
                    'label' => 'Building',
                    'list' => [
                        [
                            'value' => '0/Main/',
                            'displayText'
                                => new TranslatableString('0/Main/', 'Main'),
                            'count' => 11,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                        [
                            'value' => '1/Main/Fiction/',
                            'displayText' => new TranslatableString(
                                '1/Main/Fiction/',
                                'Fiction'
                            ),
                            'count' => 5,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                        [
                            'value' => '0/Sub/',
                            'displayText'
                                => new TranslatableString('0/Sub/', 'Sub'),
                            'count' => 2,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                    ],
                ]
            ],
            $facets
        );

        // Make the building facet translated:
        $results->getOptions()->setTranslatedFacets(['building']);
        $facets = $results->getFacetList(['building' => 'Building']);
        $this->assertEquals(
            [
                'building' => [
                    'label' => 'Building',
                    'list' => [
                        [
                            'value' => '0/Main/',
                            'displayText' => 'Main',
                            'count' => 11,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                        [
                            'value' => '1/Main/Fiction/',
                            'displayText' => 'Fiction',
                            'count' => 5,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                        [
                            'value' => '0/Sub/',
                            'displayText' => 'Sub',
                            'count' => 2,
                            'operator' => 'AND',
                            'isApplied' => false,
                        ],
                    ],
                ]
            ],
            $facets
        );

        // Test missing hierarchical facet helper:
        $this->expectExceptionMessage('hierarchical facet helper unavailable');
        $facets = $resultsNoHelper->getFacetList(['building' => 'Building']);
    }

    /**
     * Get Results object
     *
     * @return Results
     */
    protected function getResults(
        Params $params = null,
        SearchService $searchService = null,
        Loader $loader = null
    ): Results {
        return new Results(
            $params ?? $this->getParams(),
            $searchService ?? $this->createMock(SearchService::class),
            $loader ?? $this->createMock(Loader::class)
        );
    }

    /**
     * Get Results objects from a response array
     *
     * @param array  $response Solr response array
     * @param Params $params   Params
     *
     * @return Results
     */
    protected function getResultsFromResponse(
        array $response,
        Params $params
    ): Results {
        $collection = new RecordCollection($response);
        $searchService = $this->createMock(SearchService::class);
        $searchService->expects($this->once())
            ->method('search')
            ->will($this->returnValue($collection));
        return $this->getResults($params, $searchService);
    }

    /**
     * Get Params object
     *
     * @param Options       $options    Options object (null to create)
     * @param PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        Options $options = null,
        PluginManager $mockConfig = null
    ): Params {
        $mockConfig = $mockConfig ?? $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }
}
