<?php
/**
 * Unit tests for Blender backend.
 *
 * PHP version 7
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
use Laminas\EventManager\EventManager;
use PHPUnit\Framework\TestCase;
use VuFind\RecordDriver\EDS as EDSRecord;
use VuFind\RecordDriver\SolrMarc as SolrRecord;
use VuFindSearch\Backend\Blender\Backend;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

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

    /**
     * Blender config
     *
     * @var array
     */
    protected $config = [
        'Backends' => [
            'Solr' => 'Local',
            'EDS' => 'EBSCO'
        ],
        'Blending' => [
            'initialResults' => [
                'Solr',
                'Solr',
                'EDS',
                'EDS'
            ],
            'blockSize' => 7,
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
                    'Type' => 'hierarchical',
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'building',
                        ],
                        'EDS' => [
                            'Field' => 'building',
                            'Values' => [
                                'main' => '0/Main/',
                                'sub' => '1/Sub/Fiction/',
                            ]
                        ]
                    ],
                ],
                'format' => [
                    'Mappings' => [
                        'Solr' => [
                            'Field' => 'formatSolr',
                        ],
                        'Primo' => [
                            'Field' => 'formatPrimo',
                            'Values' => [
                                'barPrimo' => 'bar',
                                'bazPrimo' => 'baz',
                                'double1' => 'double',
                                'double2' => 'double',
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
                                'y' => '1'
                            ],
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
                        'EDS' => 'AllFields'
                    ],
                ],
                'Title' => [
                    'Mappings' => [
                        'Solr' => 'Title',
                        'Primo' => 'Title',
                        'EDS' => 'TI'
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
     * Data provider for testSearch
     *
     * @return array
     */
    public function getSearchTestData(): array
    {
        $solrRecords = [
            [
                'class' => SolrRecord::class,
                'title' => 'The test /'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Of Money and Slashes'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Movie Quotes Thru The Ages'
            ],
            [
                'class' => SolrRecord::class,
                'title' => '<HTML> The Basics'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Octothorpes: Why not?'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Questions about Percents'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Pluses and Minuses of Pluses and Minuses'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'The test of the publication fields.'
            ],
            [
                'class' => SolrRecord::class,
                'title' => 'Dewey browse test'
            ],
        ];
        for ($i = 20001; $i <= 20031; $i++) {
            $solrRecords[] =             [
                'class' => SolrRecord::class,
                'title' => "Test Publication $i"
            ];
        }

        $edsRecords = [];
        for ($i = 1; $i <= 40; $i++) {
            $edsRecords[] = [
                'class' => EdsRecord::class,
                'title' => "Title $i"
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
        $adaptiveConfig = $this->config;
        $adaptiveConfig['Blending']['adaptiveBlockSizes'] = [
            '5000-100000:5'
        ];

        $expectedRecordsNoBoost = array_merge(
            array_slice($solrRecords, 0, 7),
            array_slice($edsRecords, 0, 7),
            array_slice($solrRecords, 7, 7),
            array_slice($edsRecords, 7, 7),
            array_slice($solrRecords, 14, 7),
            array_slice($edsRecords, 14, 5),
        );
        $noBoostConfig = $this->config;
        unset($noBoostConfig['Blending']['initialResults']);

        return [
            [
                0,
                0,
                []
            ],
            [
                0,
                20,
                array_slice($expectedRecords, 0, 20)
            ],
            [
                1,
                20,
                array_slice($expectedRecords, 1, 20)
            ],
            [
                2,
                20,
                array_slice($expectedRecords, 2, 20)
            ],
            [
                3,
                20,
                array_slice($expectedRecords, 3, 20)
            ],
            [
                19,
                20,
                array_slice($expectedRecords, 19, 20)
            ],
            [
                0,
                40,
                array_slice($expectedRecords, 0, 40)
            ],
            [
                0,
                40,
                array_slice($expectedRecordsNoBoost, 0, 40),
                $noBoostConfig
            ],
            [
                0,
                40,
                array_slice($expectedRecordsAdaptive, 0, 40),
                $adaptiveConfig
            ],
            [
                0,
                20,
                array_slice($solrRecords, 0, 20),
                $adaptiveConfig,
                ['blender_backend:Solr'],
                240
            ],
            [
                0,
                20,
                array_slice($solrRecords, 0, 20),
                $adaptiveConfig,
                ['-blender_backend:EDS'],
                240
            ],
            [
                0,
                20,
                array_slice($edsRecords, 0, 20),
                $adaptiveConfig,
                ['blender_backend:EDS'],
                65924
            ],
            [
                0,
                40,
                array_slice($expectedRecords, 0, 40),
                null,
                [
                    '{!tag=blender_backend_filter}blender_backend:'
                    . '(blender_backend:"Solr" OR blender_backend:"EDS")'
                ]
            ],
            [
                0,
                20,
                [],
                null,
                ['-blender_backend:Solr', '-blender_backend:EDS'],
                0
            ],
        ];
    }

    /**
     * Test search.
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
        $expectedTotal = 66164
    ) {
        $backend = $this->getBackend($config);

        $this->assertNull($backend->getRecordCollectionFactory());

        $params = $this->getSearchParams($filters);
        $result = $backend->search(new Query(), $start, $limit, $params);
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals($expectedTotal, $result->getTotal());

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
    }

    /**
     * Test retrieve.
     *
     * @return void
     */
    public function testRetrieve()
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
    public function testInvalidFilter()
    {
        $backend = $this->getBackend();
        $params = $this->getSearchParams(['blender_backend:Foo']);

        $this->expectExceptionMessage(
            'Invalid blender_backend filter: Backend Foo not enabled'
        );
        $backend->search(new Query(), 0, 20, $params);
    }

    /**
     * Data provider for testInvalidAdaptiveBlockSize
     *
     * @return array
     */
    public function getInvalidBlockSizes(): array
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
     * @dataProvider getInvalidBlockSizes
     *
     * @return void
     */
    public function testInvalidAdaptiveBlockSize($blockSizes)
    {
        $config = $this->config;
        $config['Blending']['adaptiveBlockSizes'] = $blockSizes;
        $backend = $this->getBackend($config);
        $params = $this->getSearchParams([]);

        $this->expectExceptionMessage(
            'Invalid adaptive block size: ' . $blockSizes[0]
        );
        $backend->search(new Query(), 0, 20, $params);
    }

    /**
     * Return search params
     *
     * @param array $filters Blender filters
     *
     * @return ParamBag
     */
    protected function getSearchParams(array $filters): ParamBag
    {
        return new ParamBag(
            [
                'fq' => $filters,
                'query_Solr' => [new Query()],
                'query_EDS' => [new Query()],
                'params_Solr' => [new ParamBag()],
                'params_EDS' => [new ParamBag()]
            ]
        );
    }

    /**
     * Return Blender backend.
     *
     * @param array $config   Blender configuration, overrides defaults
     * @param array $mappings Blender mappings, overrides defaults
     *
     * @return Backend
     */
    protected function getBackend($config = null, $mappings = null): Backend
    {
        $backends = [
            'Solr' => $this->getSolrBackend(),
            'EDS' => $this->getEDSBackendMock()
        ];
        $events = new EventManager();
        return new Backend(
            $backends,
            new Config($config ?? $this->config),
            $mappings,
            $events
        );
    }

    /**
     * Return Solr backend.
     *
     * @return \VuFindSearch\Backend\Solr\Backend
     */
    protected function getSolrBackend(): \VuFindSearch\Backend\Solr\Backend
    {
        $callback = function ($handler, ParamBag $params, bool $cacheable = false) {
            $start = $params->get('start')[0];
            $rows = $params->get('rows')[0];
            $results = $this->getJsonFixture(
                'blender/response/solr/search.json',
                'VuFindSearch'
            );
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
        $client = $this->createMock(\Laminas\Http\Client::class);
        $connector
            = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->onlyMethods(['query'])
            ->setConstructorArgs(['http://localhost/', $map, $client])
            ->getMock();
        $connector->expects($this->any())
            ->method('query')
            ->will($this->returnCallback($callback));

        $backend = new \VuFindSearch\Backend\Solr\Backend($connector);
        $backend->setRecordCollectionFactory(
            $this->getSolrRecordCollectionFactory()
        );
        return $backend;
    }

    /**
     * Return EDS backend mock.
     *
     * @return object
     */
    protected function getEDSBackendMock()
    {
        $callback = function (
            $baseUrl,
            $headerParams,
            $params = [],
            $method = 'GET',
            $message = null,
            $messageFormat = ""
        ) {
            $json = json_decode($message, true);
            $rows = $json['RetrievalCriteria']['ResultsPerPage'];
            $page = $json['RetrievalCriteria']['PageNumber'];
            $results = $this->getJsonFixture(
                'blender/response/eds/search.json',
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

        $cache = $this->getMockForAbstractClass(
            \Laminas\Cache\Storage\Adapter\AbstractAdapter::class
        );
        $container = $this->getMockBuilder(\Laminas\Session\Container::class)
            ->disableOriginalConstructor()->getMock();
        $params = [
            $connector,
            $this->getEDSRecordCollectionFactory(),
            $cache,
            $container,
            new Config([])
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
