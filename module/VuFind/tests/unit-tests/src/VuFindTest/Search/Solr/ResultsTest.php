<?php

/**
 * Solr Search Object Results Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\Solr;

use VuFind\Config\PluginManager;
use VuFind\I18n\Sorter;
use VuFind\Record\Loader;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;
use VuFind\Search\Solr\SpellingProcessor;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Service as SearchService;

use function get_class;

/**
 * Solr Search Object Results Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\TranslatorTrait;

    /**
     * Default faceted search configuration
     *
     * @var array
     */
    protected $searchConfig = [
        'facets' => [
            'SpecialFacets' => [
                'hierarchical' => [
                    'building',
                ],
                'hierarchicalFacetSortOptions' => [
                    'building' => 'top',
                ],
            ],
        ],
    ];

    /**
     * Default faceted search response
     *
     * @var array
     */
    protected $searchResponse = [
        'response' => [
            'numFound' => 5,
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
                ],
            ],
        ],
    ];

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
     * Test facet translation functionality.
     *
     * @return void
     */
    public function testFacetTranslations(): void
    {
        $mockTranslator = $this->getMockTranslator(
            [
                'default' => [
                    'dewey_format_str' => '%%raw%% - %%translated%%',
                ],
                'DDC23' => [
                    '000' => 'Computer science, information, general works',
                ],
            ]
        );
        $mockConfig = $this->createMock(PluginManager::class);
        $options = new Options($mockConfig);
        $options->setTranslator($mockTranslator);
        $options->setTranslatedFacets(
            [
                'dewey-raw:DDC23:dewey_format_str',
            ]
        );
        $params = $this->getParams($options);
        $params->addFacet('dewey-raw');
        $searchService = $this->getSearchServiceWithMockSearchMethod(
            [
                'response' => ['numFound' => 5],
                'facet_counts' => [
                    'facet_fields' => [
                        'dewey-raw' => [
                            ['000', 100],
                        ],
                    ],
                ],
            ],
            [
                'spellcheck' => ['true'],
                'hl' => ['false'],
                'facet' => ['true'],
                'facet.limit' => [30],
                'facet.field' => ['dewey-raw'],
                'facet.sort' => ['count'],
                'facet.mincount' => [1],
            ]
        );
        $results = $this->getResults($params, $searchService);
        $list = $results->getFacetList();
        $this->assertEquals(
            '000 - Computer science, information, general works',
            $list['dewey-raw']['list'][0]['displayText']
        );
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
        $this->assertInstanceOf(
            SpellingProcessor::class,
            $defaultProcessor,
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
        $searchService = $this->getSearchServiceWithMockSearchMethod(
            ['response' => ['numFound' => 5]],
            [
                'spellcheck' => ['true'],
                'hl' => ['false'],
            ]
        );
        $results = $this->getResults(null, $searchService);
        $this->assertEquals(5, $results->getResultTotal());
    }

    /**
     * Get a mock search service that will return a RecordCollection.
     *
     * @param array $response       Decoded Solr response for search to return
     * @param array $expectedParams Expected ParamBag parameters
     *
     * @return SearchService
     */
    protected function getSearchServiceWithMockSearchMethod(
        array $response,
        array $expectedParams
    ): SearchService {
        $collection = new RecordCollection($response);
        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));

        $checkCommand = function ($command) use ($expectedParams) {
            return $command::class === \VuFindSearch\Command\SearchCommand::class
                && $command->getTargetIdentifier() === 'Solr'
                && get_class($command->getArguments()[0]) === \VuFindSearch\Query\Query::class
                && $command->getArguments()[1] === 0
                && $command->getArguments()[2] === 20
                && $command->getArguments()[3]->getArrayCopy() == $expectedParams;
        };
        $searchService->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        return $searchService;
    }

    /**
     * Test retrieving facets.
     *
     * @return void
     */
    public function testGetFacetList(): void
    {
        $results = $this->getResultsFromResponse();

        // No facets configured:
        $facets = $results->getFacetList();
        $this->assertIsArray($facets);
        $this->assertEmpty($facets);

        // Facet not available in results:
        $facets = $results->getFacetList(['format' => 'Format']);
        $this->assertIsArray($facets);
        $this->assertEmpty($facets);

        // Facet available in results, no configuration:
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
                ],
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
                ],
            ],
            $facets
        );

        // Add an 'OR' filter:
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
                ],
            ],
            $facets
        );

        // Test hierarchical facet:
        $expectedBuildingFacets = [
            'building' => [
                'label' => 'Building',
                'list' => [
                    [
                        'value' => '0/Main/',
                        'displayText' => 'Main',
                        'count' => 11,
                        'operator' => 'AND',
                        'isApplied' => false,
                        'level' => '0',
                        'parent' => null,
                        'hasAppliedChildren' => false,
                        'href' => '',
                        'exclude' => '',
                        'children' => [
                            [
                                'value' => '1/Main/Fiction/',
                                'displayText' => 'Fiction',
                                'count' => 5,
                                'operator' => 'AND',
                                'isApplied' => false,
                                'level' => '1',
                                'parent' => '0/Main/',
                                'hasAppliedChildren' => false,
                                'href' => '',
                                'exclude' => '',
                                'children' => [],
                            ],
                        ],
                    ],
                    [
                        'value' => '0/Sub/',
                        'displayText' => 'Sub',
                        'count' => 2,
                        'operator' => 'AND',
                        'isApplied' => false,
                        'level' => '0',
                        'parent' => null,
                        'hasAppliedChildren' => false,
                        'href' => '',
                        'exclude' => '',
                        'children' => [],
                    ],
                ],
            ],
        ];
        $facetHelper = new HierarchicalFacetHelper();
        $facetHelper->setSorter(new Sorter(new \Collator('en_US')));
        $results->setHierarchicalFacetHelper($facetHelper);
        $mockTranslator = $this->getMockTranslator(
            [
                'default' => [
                    'Main' => 'Main Library',
                ],
            ]
        );
        $results->getOptions()->setTranslator($mockTranslator);
        $facets = $results->getFacetList(['building' => 'Building']);
        $this->assertEquals($expectedBuildingFacets, $facets);

        // Make the building facet translated and add an 'AND' filter:
        $expectedBuildingFacets['building']['list'][0]['displayText'] = 'Main Library';
        $expectedBuildingFacets['building']['list'][0]['hasAppliedChildren'] = true;
        $expectedBuildingFacets['building']['list'][0]['children'][0]['isApplied'] = true;
        $results->getParams()->addFilter('building:1/Main/Fiction/');
        $results->getOptions()->setTranslatedFacets(['building']);
        $facets = $results->getFacetList(['building' => 'Building']);
        $this->assertEquals($expectedBuildingFacets, $facets);
    }

    /**
     * Test exception from missing hierarchical facet helper
     *
     * @return void
     */
    public function testMissingHierarchicalFacetHelper(): void
    {
        $results = $this->getResultsFromResponse();
        $this->expectExceptionMessage('VuFind\Search\Solr\Results: hierarchical facet helper unavailable');
        $results->getFacetList(['building' => 'Building']);
    }

    /**
     * Test exception from missing sorter
     *
     * @return void
     */
    public function testMissingSorter(): void
    {
        $results = $this->getResultsFromResponse();
        $facetHelper = new HierarchicalFacetHelper();
        $results->setHierarchicalFacetHelper($facetHelper);
        $this->expectExceptionMessage('Sorter class is not set.');
        $results->getFacetList(['building' => 'Building']);
    }

    /**
     * Get Results object
     *
     * @param Params        $params        Params object
     * @param SearchService $searchService Search service
     * @param Loader        $loader        Record loader
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
     * Get a Results objects from a response array.
     *
     * Note that this returns the response for a search request without validating
     * the request.
     *
     * @param ?array  $response Solr response array or null for default
     * @param ?Params $params   Params or null for default
     *
     * @return Results
     */
    protected function getResultsFromResponse(
        ?array $response = null,
        ?Params $params = null
    ): Results {
        $response ??= $this->searchResponse;
        $params ??= $this->getParams(
            null,
            $this->getMockConfigPluginManager($this->searchConfig)
        );

        $collection = new RecordCollection($response);
        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        // No need to validate the parameters, just return the requested results:
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));

        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\SearchCommand::class;
        };
        $searchService->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
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
        $mockConfig ??= $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }
}
