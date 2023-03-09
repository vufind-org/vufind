<?php
/**
 * Solr Search Object Results Test
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
 * Copyright (C) The National Library of Finland 2022.
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

use Laminas\I18n\Translator\TranslatorInterface;
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
        $mockTranslator = $this->createMock(TranslatorInterface::class);
        $mockTranslator->expects($this->exactly(2))
            ->method('translate')
            ->withConsecutive(
                [$this->equalTo('000')],
                [$this->equalTo('dewey_format_str')]
            )->willReturnOnConsecutiveCalls(
                'Computer science, information, general works',
                '%%raw%% - %%translated%%'
            );
        $mockConfig = $this->createMock(PluginManager::class);
        $options = new Options($mockConfig);
        $options->setTranslator($mockTranslator);
        $options->setTranslatedFacets([
            'dewey-raw:DDC23:dewey_format_str'
        ]);
        $params = $this->getParams($options);
        $params->addFacet('dewey-raw');
        $searchService = $this->getSearchServiceWithMockSearchMethod(
            [
                'response' => ['numFound' => 5],
                'facet_counts' => [
                    'facet_fields' => [
                        'dewey-raw' => [
                            ["000", 100]
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
            $list['dewey-raw']['list'][0]['displayText'],
            '000 - Computer science, information, general works'
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
     * @param array $solrResponse   Decoded Solr response for search to return
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
            return get_class($command) === \VuFindSearch\Command\SearchCommand::class
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
        $config = $this->getMockConfigPluginManager(
            [
                'facets' => [
                    'SpecialFacets' => [
                        'hierarchical' => [
                            'building',
                        ],
                    ],
                ],
            ]
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

        // Make the building facet translated and add an 'AND' filter:
        $results->getParams()->addFilter('building:1/Main/Fiction/');
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
                            'isApplied' => true,
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
     * Get a Results objects from a response array.
     *
     * Note that this returns the response for a search request without validating
     * the request.
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
        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
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
            return get_class($command) === \VuFindSearch\Command\SearchCommand::class;
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
        $mockConfig = $mockConfig ?? $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }
}
