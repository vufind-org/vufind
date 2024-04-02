<?php

/**
 * Unit tests for Blender backend.
 *
 * PHP version 8
 *
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Blender;

use Laminas\Config\Config;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use Laminas\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use VuFind\RecordDriver\EDS as EDSRecord;
use VuFind\RecordDriver\SolrMarc as SolrRecord;
use VuFindSearch\Backend\Blender\Backend;
use VuFindSearch\Backend\Blender\Response\Json\RecordCollection;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection as SolrRecordCollection;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

use function array_slice;
use function count;
use function in_array;

/**
 * Unit tests for Blender backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Blender config
     *
     * @var array
     */
    protected static $config = [
        'Backends' => [
            'Solr' => 'Local',
            'EDS' => 'Electronic Stuff',
        ],
        'Blending' => [
            'initialResults' => [
                'Solr',
                'Solr',
                'EDS',
                'EDS',
            ],
            'blockSize' => 7,
        ],
        'Advanced_Settings' => [
            'delimiter' => '::',
            'delimited_facets' => [
                'blender_backend',
            ],
        ],
    ];

    /**
     * Mappings
     *
     * @var array
     */
    protected $mappings = [
        'Facets' => [
            'Fields' => [
                'building' => [
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'building',
                        ],
                        'EDS' => [
                            'Field' => 'ContentProvider',
                            'Unmapped' => 'drop',
                            'Hierarchical' => true,
                            'Values' => [
                                'Business Source Premier' => 'Main',
                                'Communication Abstracts' => '1/Main/Sub/',
                                'EconLit with Full Text' => '1/Econlit/Foo/',
                            ],
                        ],
                    ],
                ],
                'format' => [
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'format',
                        ],
                        'EDS' => [
                            'Field' => 'SourceType',
                            'Values' => [
                                'Academic Journals' => 'Journal',
                                'Magazines' => 'Article',
                                'Books' => 'Book',
                                'Conference Materials' => 'Conference Proceeding',
                                'Reviews' => 'Review',
                                'Trade Publications' => 'Trade Publications',
                                'Reports' => 'Report',
                                'Electronic Resources' => 'Electronic',
                                'eBooks' => 'eBook',
                                'Non-print Resources' => 'Text',
                                'Biographies' => 'Biography',
                                'Dissertations' => 'Thesis',
                                'Audio' => 'Audio',
                                'Music Scores' => 'Music Score',
                                'Video' => 'Video',
                                'Primary Source Documents'
                                    => 'Primary Source Document',
                                'Maps' => 'Map',
                                'Research Starters' => 'Research Starter',
                                'Audiobooks' => 'Audiobook',
                                'News' => '',
                            ],
                        ],
                    ],
                ],
                'fulltext' => [
                    'Type' => 'boolean',
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'fulltext_boolean',
                        ],
                        'Primo' => [
                            'Field' => 'pcAvailability',
                            'Values' => [
                                'false' => '1',

                            ],
                            'DefaultValue' => 'true',
                        ],
                        'EDS' => [
                            'Field' => 'LIMIT|FT',
                            'Values' => [
                                'y' => '1',
                            ],
                        ],
                    ],
                ],
                'language' => [
                    'Mappings' => [
                        'EDS' => [
                            'Field' => '',
                        ],
                    ],
                ],
            ],
        ],
        'Search' => [
            'Fields' => [
                'AllFields' => [
                    'Mappings' => [
                        'Solr' => 'AllFields',
                        'Primo' => 'AllFields',
                        'EDS' => 'AllFields',
                    ],
                ],
                'Title' => [
                    'Mappings' => [
                        'Solr' => 'Title',
                        'Primo' => 'Title',
                        'EDS' => 'TI',
                    ],
                ],
            ],
        ],
        'Sorting' => [
            'Fields' => [
                'relevance' => [
                    'Mappings' => [
                        'Solr' => 'relevance',
                        'Primo' => 'relevance',
                        'EDS' => 'relevance',
                    ],
                ],
                'year' => [
                    'Mappings' => [
                        'Solr' => 'year',
                        'Primo' => 'scdate',
                        'EDS' => 'date',
                    ],
                ],
            ],
        ],
    ];

    /**
     * Event manager
     *
     * @var SharedEventManager
     */
    protected $sharedEventManager = null;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->sharedEventManager = new SharedEventManager();
    }

    /**
     * Data provider for testSearch
     *
     * @return array
     */
    public static function getSearchTestData(): array
    {
        $solrRecords = [
            [
                'class' => SolrRecord::class,
                'title' => 'The test /',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Of Money and Slashes',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Movie Quotes Thru The Ages',
            ],
            [
                'class' => SolrRecord::class,
                'title' => '<HTML> The Basics',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Octothorpes: Why not?',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Questions about Percents',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Pluses and Minuses of Pluses and Minuses',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'The test of the publication fields.',
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Dewey browse test',
            ],
        ];
        for ($i = 20001; $i <= 20031; $i++) {
            $solrRecords[] =             [
                'class' => SolrRecord::class,
                'title' => "Test Publication $i",
            ];
        }

        $edsRecords = [];
        for ($i = 1; $i <= 40; $i++) {
            $edsRecords[] = [
                'class' => EdsRecord::class,
                'title' => "Title $i",
            ];
        }

        $expectedRecords = array_merge(
            array_slice($solrRecords, 0, 2),
            array_slice($edsRecords, 0, 2),
            array_slice($solrRecords, 2, 3),
            array_slice($edsRecords, 2, 7),
            array_slice($solrRecords, 5, 7),
            array_slice($edsRecords, 9, 7),
            array_slice($solrRecords, 12, 7),
            array_slice($edsRecords, 16, 5)
        );

        $expectedRecordsAdaptive = array_merge(
            array_slice($solrRecords, 0, 2),
            array_slice($edsRecords, 0, 2),
            array_slice($solrRecords, 2, 1),
            array_slice($edsRecords, 2, 5),
            array_slice($solrRecords, 3, 5),
            array_slice($edsRecords, 7, 5),
            array_slice($solrRecords, 8, 5),
            array_slice($edsRecords, 12, 5),
            array_slice($solrRecords, 13, 5),
            array_slice($edsRecords, 17, 5)
        );
        $adaptiveConfig = static::$config;
        $adaptiveConfig['Blending']['adaptiveBlockSizes'] = [
            '5000-100000:5',
        ];
        $adaptiveConfigWithOrFacet = $adaptiveConfig;
        $adaptiveConfigWithOrFacet['Results_Settings']['orFacets']
            = 'blender_backend';

        $expectedRecordsNoBoost = array_merge(
            array_slice($solrRecords, 0, 7),
            array_slice($edsRecords, 0, 7),
            array_slice($solrRecords, 7, 7),
            array_slice($edsRecords, 7, 7),
            array_slice($solrRecords, 14, 7),
            array_slice($edsRecords, 14, 5)
        );
        $noBoostConfig = static::$config;
        unset($noBoostConfig['Blending']['initialResults']);

        $expectedRecordsTitleSearch = array_merge(
            array_slice($solrRecords, 0, 2),
            array_slice($edsRecords, 0, 2),
            array_slice($solrRecords, 2, 3),
            array_slice($edsRecords, 2, 13)
        );

        $expectedRecordsAuthorSearch = array_merge(
            array_slice($solrRecords, 0, 2),
            array_slice($edsRecords, 0, 6)
        );

        return [
            [
                0,
                0,
                [],
            ],
            [
                0,
                20,
                array_slice($expectedRecords, 0, 20),
            ],
            [
                1,
                20,
                array_slice($expectedRecords, 1, 20),
            ],
            [
                2,
                20,
                array_slice($expectedRecords, 2, 20),
            ],
            [
                3,
                20,
                array_slice($expectedRecords, 3, 20),
            ],
            [
                19,
                20,
                array_slice($expectedRecords, 19, 20),
            ],
            [
                0,
                40,
                array_slice($expectedRecords, 0, 40),
            ],
            [
                0,
                40,
                array_slice($expectedRecordsNoBoost, 0, 40),
                $noBoostConfig,
            ],
            [
                0,
                40,
                array_slice($expectedRecordsAdaptive, 0, 40),
                $adaptiveConfig,
            ],
            [
                0,
                20,
                array_slice($solrRecords, 0, 20),
                $adaptiveConfig,
                ['blender_backend:Solr'],
                240,
                null,
            ],
            [
                0,
                20,
                array_slice($solrRecords, 0, 20),
                $adaptiveConfigWithOrFacet,
                ['-blender_backend:EDS'],
                240,
                0,
            ],
            [
                0,
                20,
                array_slice($edsRecords, 0, 20),
                $adaptiveConfigWithOrFacet,
                ['blender_backend:EDS'],
                0,
                65924,
            ],
            [
                0,
                40,
                array_slice($expectedRecords, 0, 40),
                null,
                [
                    '{!tag=blender_backend_filter}blender_backend:'
                    . '(blender_backend:"Solr" OR blender_backend:"EDS")',
                ],
            ],
            [
                0,
                20,
                [],
                null,
                ['-blender_backend:Solr', '-blender_backend:EDS'],
                0,
                0,
            ],
            [
                0,
                20,
                $expectedRecordsTitleSearch,
                null,
                [],
                5,
                65924,
                new Query('foo', 'Title'),
            ],
            [
                0,
                20,
                $expectedRecordsAuthorSearch,
                null,
                [],
                2,
                6,
                new Query('foo', 'Author'),
            ],
        ];
    }

    /**
     * Test search.
     *
     * @param int    $start           Start position
     * @param int    $limit           Result limit
     * @param array  $expectedRecords Expected records
     * @param ?array $config          Blender configuration, overrides defaults
     * @param array  $filters         Filters
     * @param int    $expectedSolr    Expected Solr count
     * @param int    $expectedEDS     Expected EDS count
     * @param Query  $query           Override query
     *
     * @dataProvider getSearchTestData
     *
     * @return void
     */
    public function testSearch(
        $start,
        $limit,
        $expectedRecords,
        $config = null,
        $filters = [],
        $expectedSolr = 240,
        $expectedEDS = 65924,
        $query = null
    ): void {
        $backend = $this->getBackend($config);

        $params = $this->getSearchParams($filters, $query);
        $result = $backend->search($query ?? new Query(), $start, $limit, $params);
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals($expectedSolr + $expectedEDS, $result->getTotal());

        $records = $result->getRecords();
        $this->assertIsArray($records);
        $this->assertCount(count($expectedRecords), $records);
        foreach ($expectedRecords as $i => $expected) {
            $this->assertInstanceOf(
                $expected['class'],
                $records[$i],
                "Record $i class"
            );
            $this->assertEquals(
                $expected['title'],
                $records[$i]->getTitle(),
                "Record $i title"
            );
        }

        // Check facet counts if we expect results:
        if ($expectedSolr + $expectedEDS > 0) {
            $facets = $result->getFacets();
            $this->assertIsArray($facets);
            $backendFacet = $facets['blender_backend'];
            $this->assertEquals($expectedSolr, $backendFacet['Solr::Local']);
            if (null === $expectedEDS) {
                $this->assertNotContains('EDS::Electronic Stuff', $backendFacet);
            } else {
                $this->assertEquals(
                    $expectedEDS,
                    $backendFacet['EDS::Electronic Stuff']
                );
            }

            $expectedFacets = [
                'building' => [
                    'Solr' => [
                        '0/Main/' => 231,
                        '1/Main/Sub/' => 7,
                        '0/Sub/' => 1,
                        '1/Sub/Foo/' => 1,
                    ],
                    'EDS' => [
                        '0/Main/' => 1861 + 2,
                        '1/Main/Sub/' => 2,
                        '0/Econlit/' => 402,
                        '1/Econlit/Foo/' => 402,
                    ],
                ],
                'format' => [
                    'Solr' => [
                        'Journal' => 0,
                        'Article' => 0,
                        'Book Chapter' => 177,
                        'Book' => 63,
                        'eBook' => 0,
                        'Report' => 0,
                        'Conference Proceeding' => 0,
                    ],
                    'EDS' => [
                        'Journal' => 2855,
                        'Article' => 783,
                        'Book Chapter' => 0,
                        'Book' => 226,
                        'eBook' => 208,
                        'Report' => 47,
                        'Conference Proceeding' => 2,
                        'Review' => 5,
                    ],
                ],
            ];

            $active = [];
            if ($expectedSolr > 0) {
                $active[] = 'Solr';
            }
            if ($expectedEDS > 0) {
                $active[] = 'EDS';
            }

            foreach ($expectedFacets as $facet => $expectedCountsForSources) {
                $this->assertIsArray($facets[$facet]);
                $facetCounts = $facets[$facet];
                $expectedCounts = [];
                foreach ($active as $source) {
                    foreach ($expectedCountsForSources[$source] as $field => $count) {
                        $expectedCounts[$field] = ($expectedCounts[$field] ?? 0) + $count;
                    }
                }
                $expectedCounts = array_filter($expectedCounts);
                $this->assertEquals($expectedCounts, $facetCounts, $facet);
            }
        }
    }

    /**
     * Test limits used for search requests
     *
     * @return void
     */
    public function testSearchLimit(): void
    {
        $query = new Query();
        $edsParams = new ParamBag();
        $collection = new \VuFindSearch\Backend\EDS\Response\RecordCollection([]);

        $eds = $this->getMockBuilder(\VuFindSearch\Backend\EDS\Backend::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectConsecutiveCalls(
            $eds,
            'search',
            [
                [$query, 0, 20, $edsParams],
                [$query, 0, 20, $edsParams],
                [$query, 0, 0, $edsParams],
            ],
            $collection
        );
        $backends = [
            'EDS' => $eds,
        ];
        $eventManager = new EventManager($this->sharedEventManager);
        $backend = new Backend(
            $backends,
            new Config(static::$config),
            $this->mappings,
            $eventManager
        );
        $backend->setIdentifier('Blender');

        $params = new ParamBag(
            [
                'query_EDS' => [$query],
                'params_EDS' => [$edsParams],
            ]
        );
        $backend->search($query, 0, 20, $params);
        $backend->search($query, 380, 20, $params);
        $backend->search($query, 0, 0, $params);
    }

    /**
     * Test non-delimited blender_backend facet field.
     *
     * @return void
     */
    public function testNonDelimitedBlenderBackendFacet(): void
    {
        $config = static::$config;
        unset($config['Advanced_Settings']);
        $backend = $this->getBackend($config);
        $expectedSolr = 240;
        $expectedEDS = 65924;

        $params = $this->getSearchParams([]);
        $result = $backend->search(new Query(), 0, 0, $params);
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals($expectedSolr + $expectedEDS, $result->getTotal());

        $facets = $result->getFacets();
        $this->assertIsArray($facets);
        $backendFacet = $facets['blender_backend'];
        $this->assertEquals($expectedSolr, $backendFacet['Solr']);
        $this->assertEquals($expectedEDS, $backendFacet['EDS']);
    }

    /**
     * Test getRecordCollectionFactory.
     *
     * @return void
     */
    public function testGetRecordCollectionFactory()
    {
        $this->expectExceptionMessage(
            'getRecordCollectionFactory not supported in Blender'
        );
        $this->getBackend()->getRecordCollectionFactory();
    }

    /**
     * Test search with a partial failure
     *
     * @return void
     */
    public function testSearchPartialFailure(): void
    {
        $backend = $this->getBackend(
            null,
            null,
            [
                'Solr' => $this->getSolrBackend(),
                'EDS' => $this->getEDSBackendMock(''),
            ]
        );

        $params = $this->getSearchParams([]);
        $result = $backend->search(new Query(), 0, 20, $params);
        $this->assertEquals(
            [
                [
                    'msg' => 'search_backend_partial_failure',
                    'tokens' => ['%%sources%%' => 'Electronic Stuff'],
                ],
            ],
            $result->getErrors()
        );
        $this->assertEquals(240, $result->getTotal());

        $records = $result->getRecords();
        $this->assertIsArray($records);
        $this->assertCount(20, $records);
        foreach ($records as $record) {
            $this->assertInstanceOf(SolrRecord::class, $record);
        }
    }

    /**
     * Test search with a total failure
     *
     * @return void
     */
    public function testSearchTotalFailure()
    {
        $backend = $this->getBackend(
            null,
            null,
            [
                'Solr' => $this->getSolrBackend(''),
                'EDS' => $this->getEDSBackendMock(''),
            ]
        );

        $params = $this->getSearchParams([]);

        $this->expectExceptionMessage('Simulated Solr failure');
        $backend->search(new Query(), 0, 20, $params);
    }

    /**
     * Test search with a error returned in a collection
     *
     * @return void
     */
    public function testSearchCollectionError(): void
    {
        $backend = $this->getBackend(
            null,
            null,
            [
                'Solr' => $this->getSolrBackend(),
                'EDS' => $this->getBackendForFacetsAndErrors([], ['Example Error']),
            ]
        );

        $params = $this->getSearchParams([]);
        $result = $backend->search(new Query(), 0, 20, $params);
        $this->assertEquals(
            [
                [
                    'msg' => '%%error%% -- %%label%%',
                    'tokens' => [
                        '%%error%%' => 'Example Error',
                        '%%label%%' => 'Electronic Stuff',
                    ],
                    'translate' => true,
                    'translateTokens' => true,
                ],
            ],
            $result->getErrors()
        );
    }

    /**
     * Test search with array facet format
     *
     * @return void
     */
    public function testArrayFacetFormat(): void
    {
        $backend = $this->getBackend(
            null,
            null,
            [
                'Solr' => $this->getSolrBackend(),
                'EDS' => $this->getBackendForFacetsAndErrors([], []),
            ]
        );

        $params = $this->getSearchParams([]);

        $results = $backend->search(new Query(), 0, 20, $params);
        $this->assertIsArray($results->getFacets());
    }

    /**
     * Test retrieve.
     *
     * @return void
     */
    public function testRetrieve(): void
    {
        $backend = $this->getBackend();

        $this->expectExceptionMessage('Blender does not support retrieve');
        $backend->retrieve('1');
    }

    /**
     * Test invalid backend filter.
     *
     * @return void
     */
    public function testInvalidFilter(): void
    {
        $backend = $this->getBackend();
        $params = $this->getSearchParams(['blender_backend:Foo']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warn')
            ->with(
                'VuFindSearch\Backend\Blender\Backend:'
                . ' Invalid blender_backend filter: Backend Foo not enabled',
                []
            )
            ->willReturn(null);
        $backend->setLogger($logger);
        $backend->search(new Query(), 0, 20, $params);
    }

    /**
     * Data provider for testInvalidAdaptiveBlockSize
     *
     * @return array
     */
    public static function getInvalidBlockSizes(): array
    {
        return [
            [
                ['5000:5'],
            ],
            [
                ['5000-100:5'],
            ],
            [
                ['5-10:0'],
            ],
        ];
    }

    /**
     * Test invalid adaptive block size configuration.
     *
     * @param array $blockSizes Adaptive block size configuration
     *
     * @dataProvider getInvalidBlockSizes
     *
     * @return void
     */
    public function testInvalidAdaptiveBlockSize($blockSizes): void
    {
        $config = static::$config;
        $config['Blending']['adaptiveBlockSizes'] = $blockSizes;
        $backend = $this->getBackend($config);
        $params = $this->getSearchParams([]);

        $this->expectExceptionMessage(
            'Invalid adaptive block size: ' . $blockSizes[0]
        );
        $backend->search(new Query(), 0, 20, $params);
    }

    /**
     * Test event handling
     *
     * @return void
     */
    public function testEvents(): void
    {
        $preEventParams = [];
        $postEventParams = [];

        $onSearchPre = function (EventInterface $event) use (&$preEventParams) {
            $command = $event->getParam('command');
            $params = $command->getSearchParameters();
            $backend = $event->getParam('backend');

            if ('Solr' === $backend) {
                $params->add('fq', 'SolrFilter');
            } elseif ('EDS' === $backend) {
                $query = $command->getQuery();
                $query->setString('EDSQuery');
            }

            $preEventParams[$command->getTargetIdentifier()] = [
                'target' => $event->getTarget(),
                'query' => $command->getQuery(),
                'params' => $params,
                'backend' => $backend,
            ];
        };

        $onSearchPost = function (EventInterface $event) use (&$postEventParams) {
            $command = $event->getParam('command');
            $postEventParams[$command->getTargetIdentifier()] = [
                'target' => $event->getTarget(),
                'params' => $command->getSearchParameters(),
                'backend' => $event->getParam('backend'),
            ];
        };

        $this->sharedEventManager->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_PRE,
            $onSearchPre
        );
        $this->sharedEventManager->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_POST,
            $onSearchPost
        );

        $solr = $this->getSolrBackend();
        $eds = $this->getEDSBackendMock();
        $backend = $this->getBackend(
            null,
            null,
            [
                'Solr' => $solr,
                'EDS' => $eds,
            ]
        );
        $this->sharedEventManager->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_PRE,
            [$backend, 'onSearchPre']
        );
        $this->sharedEventManager->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_POST,
            [$backend, 'onSearchPost']
        );

        $params = $this->getSearchParams([]);
        $command = new SearchCommand(
            'Blender',
            new Query(),
            0,
            20,
            $params
        );
        $args = [
            'backend' => 'Blender',
            'command' => $command,
            'params' => $params,
        ];
        $eventManager = new EventManager($this->sharedEventManager);
        $eventManager->setIdentifiers([\VuFindSearch\Service::class]);
        $eventManager->trigger(
            \VuFindSearch\Service::EVENT_PRE,
            $backend,
            $args
        );

        $eventManager->trigger(
            \VuFindSearch\Service::EVENT_POST,
            $backend,
            $args
        );

        $this->assertNotEmpty($preEventParams);
        $this->assertSame($backend, $preEventParams['Blender']['target']);
        $this->assertSame($solr, $preEventParams['Solr']['target']);
        $this->assertSame($eds, $preEventParams['EDS']['target']);
        $this->assertEquals(
            ['SolrFilter'],
            $preEventParams['Solr']['params']->get('fq')
        );
        $this->assertEquals(
            'EDSQuery',
            $preEventParams['EDS']['query']->getString()
        );
        $this->assertEquals(
            ['SolrFilter'],
            $command->getSearchParameters()->get('params_Solr')[0]->get('fq')
        );
        $this->assertEquals(
            'EDSQuery',
            $command->getSearchParameters()->get('query_EDS')[0]->getString()
        );

        $this->assertNotEmpty($postEventParams);
        $this->assertSame($backend, $postEventParams['Blender']['target']);
        $this->assertSame($solr, $postEventParams['Solr']['target']);
        $this->assertSame($eds, $postEventParams['EDS']['target']);
    }

    /**
     * Test initialization of an empty collection array
     *
     * @return void
     */
    public function testEmptyCollectionArray(): void
    {
        $collection = new RecordCollection();
        $remaining = $collection->initBlended([], 20, 7, 20);
        $this->assertIsArray($remaining);
        $this->assertEmpty($remaining);
        $this->assertEquals(20, $collection->getTotal());
    }

    /**
     * Create a backend that returns the given values for facets and errors
     *
     * @param array $facets Facet data
     * @param array $errors Error data
     *
     * @return object
     */
    protected function getBackendForFacetsAndErrors($facets, $errors)
    {
        $collection = $this->getMockBuilder(SolrRecordCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $collection->expects($this->once())
            ->method('getErrors')
            ->will($this->returnValue($errors));
        $collection->expects($this->once())
            ->method('getRecords')
            ->will($this->returnValue([]));
        $collection->expects($this->any())
            ->method('getFacets')
            ->will($this->returnValue($facets));
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\EDS\Backend::class)
            ->disableOriginalConstructor()
            ->getMock();
        $backend->expects($this->once())
            ->method('search')
            ->will($this->returnValue($collection));

        return $backend;
    }

    /**
     * Return search params
     *
     * @param array $filters Blender filters
     * @param Query $query   Query
     *
     * @return ParamBag
     */
    protected function getSearchParams(
        array $filters,
        Query $query = null
    ): ParamBag {
        return new ParamBag(
            [
                'fq' => $filters,
                'query_Solr' => [$query ?? new Query()],
                'query_EDS' => [$query ?? new Query()],
                'params_Solr' => [new ParamBag()],
                'params_EDS' => [new ParamBag()],
            ]
        );
    }

    /**
     * Return Blender backend.
     *
     * @param array $config   Blender configuration, overrides defaults
     * @param array $mappings Blender mappings, overrides defaults
     * @param array $backends Actual backends, overrides defaults
     *
     * @return Backend
     */
    protected function getBackend(
        $config = null,
        $mappings = null,
        $backends = []
    ): Backend {
        if (!$backends) {
            $backends = [
                'Solr' => $this->getSolrBackend(),
                'EDS' => $this->getEDSBackendMock(),
            ];
        }
        $eventManager = new EventManager($this->sharedEventManager);
        $backend = new Backend(
            $backends,
            new Config($config ?? static::$config),
            $mappings ?? $this->mappings,
            $eventManager
        );
        $backend->setIdentifier('Blender');
        return $backend;
    }

    /**
     * Return Solr connector mock.
     *
     * @param string $fixture Fixture to use for results, overrides default. Use
     * empty string for failure.
     *
     * @return object
     */
    protected function getSolrConnector(
        $fixture = null
    ) {
        $callback = function (
            $handler,
            ParamBag $params,
            bool $cacheable = false
        ) use ($fixture) {
            if ('' === $fixture) {
                throw new BackendException('Simulated Solr failure');
            }
            if (null === $fixture) {
                $field = $params->get('qf')[0] ?? '';
                $type = '';
                if (in_array($field, ['title', 'author'])) {
                    $type = "-$field";
                }
                $fixture = "blender/response/solr/search$type.json";
            }
            $start = $params->get('start')[0];
            $rows = $params->get('rows')[0];
            $results = $this->getJsonFixture($fixture, 'VuFindSearch');
            $results['response']['docs'] = array_slice(
                $results['response']['docs'],
                $start,
                $rows
            );
            return json_encode($results);
        };
        $map = new \VuFindSearch\Backend\Solr\HandlerMap(
            ['select' => ['fallback' => true]]
        );
        $connector
            = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->onlyMethods(['query'])
            ->setConstructorArgs(
                [
                    'http://localhost/',
                    $map,
                    function () {
                        return $this->createMock(\Laminas\Http\Client::class);
                    },
                ]
            )
            ->getMock();
        $connector->expects($this->any())
            ->method('query')
            ->will($this->returnCallback($callback));

        return $connector;
    }

    /**
     * Return Solr backend.
     *
     * @param string $fixture Fixture to use for results, overrides default. Use
     * empty string for failure.
     *
     * @return \VuFindSearch\Backend\Solr\Backend
     */
    protected function getSolrBackend(
        $fixture = null
    ): \VuFindSearch\Backend\Solr\Backend {
        $backend = new \VuFindSearch\Backend\Solr\Backend(
            $this->getSolrConnector($fixture)
        );
        $backend->setRecordCollectionFactory(
            $this->getSolrRecordCollectionFactory()
        );
        $qb = new QueryBuilder(
            [
                'Title' => [
                    'DismaxFields' => [
                        'title',
                    ],
                    'DismaxHandler' => 'edismax',
                ],
                'Author' => [
                    'DismaxFields' => [
                        'author',
                    ],
                    'DismaxHandler' => 'edismax',
                ],
            ]
        );
        $backend->setQueryBuilder($qb);
        $backend->setIdentifier('Solr');
        return $backend;
    }

    /**
     * Return EDS backend mock.
     *
     * @param string $fixture Fixture to use for results, overrides default. Use
     * empty string for failure.
     *
     * @return object
     */
    protected function getEDSBackendMock($fixture = null)
    {
        $callback = function (
            $baseUrl,
            $headerParams,
            $params = [],
            $method = 'GET',
            $message = null,
            $messageFormat = ''
        ) use ($fixture) {
            if ('' === $fixture) {
                throw new BackendException('Simulated EDS failure');
            }
            $json = json_decode($message, true);
            if (null === $fixture) {
                $field = $json['SearchCriteria']['Queries'][0]['FieldCode'] ?? '';
                $type = 'Author' === $field ? '-author' : '';
                $fixture = "blender/response/eds/search$type.json";
            }
            $rows = $json['RetrievalCriteria']['ResultsPerPage'];
            $page = $json['RetrievalCriteria']['PageNumber'];
            $results = $this->getJsonFixture(
                $fixture,
                'VuFindSearch'
            );
            $results['SearchResult']['Data']['Records'] = array_slice(
                $results['SearchResult']['Data']['Records'],
                ($page - 1) * $rows,
                $rows
            );

            return $results;
        };
        $client = $this->createMock(\Laminas\Http\Client::class);
        $connector = $this
            ->getMockBuilder(\VuFindSearch\Backend\EDS\Connector::class)
            ->onlyMethods(['call'])
            ->setConstructorArgs([[], $client])
            ->getMock();
        $connector->expects($this->any())
            ->method('call')
            ->will($this->returnCallback($callback));

        $cache = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $container = $this->getMockBuilder(\Laminas\Session\Container::class)
            ->disableOriginalConstructor()->getMock();
        $params = [
            $connector,
            $this->getEDSRecordCollectionFactory(),
            $cache,
            $container,
            new Config([]),
        ];
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\EDS\Backend::class)
            ->onlyMethods(['getAuthenticationToken', 'getSessionToken'])
            ->setConstructorArgs($params)
            ->getMock();

        $backend->expects($this->any())
            ->method('getAuthenticationToken')
            ->will($this->returnValue('auth1234'));
        $backend->expects($this->any())
            ->method('getSessionToken')
            ->will($this->returnValue('sess1234'));

        $backend->setIdentifier('EDS');
        return $backend;
    }

    /**
     * Return Solr record collection factory.
     *
     * @return \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory
     */
    protected function getSolrRecordCollectionFactory()
    {
        $callback = function ($data) {
            $driver = new \VuFind\RecordDriver\SolrMarc();
            $driver->setRawData($data);
            return $driver;
        };
        return new \VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory(
            $callback
        );
    }

    /**
     * Return EDS record collection factory.
     *
     * @return \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory
     */
    protected function getEDSRecordCollectionFactory()
    {
        $callback = function ($data) {
            $driver = new \VuFind\RecordDriver\EDS();
            $driver->setRawData($data);
            return $driver;
        };
        return new \VuFindSearch\Backend\EDS\Response\RecordCollectionFactory(
            $callback
        );
    }
}
