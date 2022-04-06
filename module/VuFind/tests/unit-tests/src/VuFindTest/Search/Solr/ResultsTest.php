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

use Laminas\I18n\Translator\TranslatorInterface;
use VuFind\Config\PluginManager;
use VuFind\Record\Loader;
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
     * Test facet translation functionality.
     *
     * @return void
     */
    public function testFacetTranslations(): void
    {
        $mockTranslator = $this->createMock(TranslatorInterface::class);
        $mockTranslator->expects($this->exactly(2))
            ->method('translate')
            ->with($this->equalTo('000'))
            ->will(
                $this->returnValue('Computer science, information, general works')
            );
        $mockConfig = $this->createMock(PluginManager::class);
        $options = new Options($mockConfig);
        $options->setTranslator($mockTranslator);
        $options->setTranslatedFacets([
            'dewey-raw:DDC23:%raw% - %translated%'
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
     * @parma array $expectedParams Expected ParamBag parameters
     *
     * @return SearchService
     */
    protected function getSearchServiceWithMockSearchMethod(
        array $response,
        array $expectedParams
    ): SearchService {
        $collection = new RecordCollection($response);
        $searchService = $this->createMock(SearchService::class);
        $searchService->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo('Solr'),
                $this->equalTo(new \VuFindSearch\Query\Query()),
                $this->equalTo(0),
                $this->equalTo(20),
                $this->equalTo(new \VuFindSearch\ParamBag($expectedParams))
            )->will($this->returnValue($collection));
        return $searchService;
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
