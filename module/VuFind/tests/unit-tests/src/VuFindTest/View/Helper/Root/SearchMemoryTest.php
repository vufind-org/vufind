<?php

/**
 * SearchMemory view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\Stdlib\Parameters;
use Laminas\View\Helper\EscapeHtml;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Search\Memory;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;
use VuFind\Search\UrlQueryHelper;
use VuFind\View\Helper\Root\SearchMemory;
use VuFind\View\Helper\Root\SearchParams;

/**
 * SearchMemory view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchMemoryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Fake base path to use during tests.
     *
     * @var string
     */
    protected $searchBasePath = '/foo/bar';

    /**
     * Fake route name to use during tests.
     *
     * @var string
     */
    protected $searchRoute = 'foo-bar';

    /**
     * Get a mock Solr Params object.
     *
     * @param array $requestArray Request array to populate Params from.
     *
     * @return MockObject&Params
     */
    protected function getMockSolrParams(array $requestArray = []): MockObject&Params
    {
        $solrOptions = $this->createMock(Options::class);
        $solrOptions->expects($this->once())->method('getSearchAction')->willReturn($this->searchRoute);
        $solrParams = $this->createMock(Params::class);
        $solrParams->method('getOptions')->willReturn($solrOptions);
        return $solrParams;
    }

    /**
     * Get a mock Solr Results object.
     *
     * @return MockObject&Results
     */
    protected function getMockSolrResults(): MockObject&Results
    {
        $solrParams = $this->getMockSolrParams();
        $solrOptions = $solrParams->getOptions();
        $solrOptions->expects($this->once())->method('getSearchAction')->willReturn($this->searchRoute);
        $mockQueryHelper = $this->createMock(UrlQueryHelper::class);
        $mockQueryHelper->method('setJumpto')->willReturn($mockQueryHelper);
        $results = $this->createMock(Results::class);
        $results->method('getOptions')->willReturn($solrOptions);
        $results->method('getParams')->willReturn($solrParams);
        $results->method('getUrlQuery')->willReturn($mockQueryHelper);
        return $results;
    }

    /**
     * Get a configured view object with relevant helpers for testing.
     *
     * @param Params $solrParams Configured Solr Params object
     * @param Memory $memory     Memory helper
     *
     * @return PhpRenderer
     */
    protected function getConfiguredHelper(Params $solrParams, Memory $memory): SearchMemory
    {
        $laminasUrl = $this->createMock(\Laminas\View\Helper\Url::class);
        $laminasUrl->method('__invoke')->with($this->searchRoute)->willReturn($this->searchBasePath);
        $url = new \VuFind\View\Helper\Root\Url($laminasUrl);
        $escapeHtml = $this->createMock(EscapeHtml::class);
        $escapeHtml->method('__invoke')->willReturnCallback(function ($str) {
            return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        });
        $searchParams = $this->createMock(SearchParams::class);
        $searchParams->method('__invoke')->with('Solr')->willReturn($solrParams);
        return $this->getSearchMemoryViewHelper($memory, $url, $escapeHtml, $searchParams);
    }

    /**
     * Test search memory helper's getLastSearchParams() method.
     *
     * @param string $query                Query to parse
     * @param array  $expectedRequestArray Expected request parameters to parse
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getLastSearchParamsProvider')]
    public function testGetLastSearchParams(
        string $query,
        array $expectedRequestArray
    ): void {
        $memory = $this->createMock(Memory::class);
        $memory->expects($this->once())->method('retrieveSearch')->willReturn($this->searchBasePath . $query);
        $solrParams = $this->getMockSolrParams($expectedRequestArray);
        $expectedRequest = new Parameters($expectedRequestArray);
        $solrParams->expects($this->once())->method('initFromRequest')->with($expectedRequest);
        $helper = $this->getConfiguredHelper($solrParams, $memory);
        $this->assertEquals($solrParams, $helper->getLastSearchParams('Solr'));
    }

    /**
     * Test search memory helper's getLastSearchUrl() method with a saved search.
     *
     * @return void
     */
    public function testGetLastSearchUrlWithSavedSearch(): void
    {
        $results = $this->getMockSolrResults();
        $memory = $this->createMock(Memory::class);
        $memory->expects($this->once())->method('getLastSearch')->willReturn($results);
        $helper = $this->getConfiguredHelper($results->getParams(), $memory);
        $this->assertSame('/foo/bar', $helper->getLastSearchUrl());
    }

    /**
     * Test search memory helper's getLastSearchUrl() method with no saved search.
     *
     * @return void
     */
    public function testGetLastSearchUrlWithoutSavedSearch(): void
    {
        $memory = $this->createMock(Memory::class);
        $memory->expects($this->once())->method('getLastSearch')->willReturn(null);
        $solrParams = $this->createMock(Params::class);
        $helper = $this->getConfiguredHelper($solrParams, $memory);
        $this->assertNull($helper->getLastSearchUrl());
    }

    /**
     * Test search memory helper's getLastSearchLink() method with a saved search.
     *
     * @return void
     */
    public function testGetLastSearchLinkWithSavedSearch(): void
    {
        $results = $this->getMockSolrResults();
        $memory = $this->createMock(Memory::class);
        $memory->expects($this->once())->method('getLastSearch')->willReturn($results);
        $helper = $this->getConfiguredHelper($results->getParams(), $memory);
        $this->assertEquals(
            'prefix<a href="/foo/bar">Solr</a>suffix',
            $helper->getLastSearchLink('Solr', 'prefix', 'suffix')
        );
    }

    /**
     * Test search memory helper's getLastSearchLink() method with no saved search.
     *
     * @return void
     */
    public function testGetLastSearchLinkWithoutSavedSearch(): void
    {
        $memory = $this->createMock(Memory::class);
        $memory->expects($this->once())->method('getLastSearch')->willReturn(null);
        $solrParams = $this->createMock(Params::class);
        $helper = $this->getConfiguredHelper($solrParams, $memory);
        $this->assertEquals('', $helper->getLastSearchLink('Solr', 'prefix', 'suffix'));
    }

    /**
     * Data provider for testGetLastSearchParams()
     *
     * @return \Iterator
     */
    public static function getLastSearchParamsProvider(): \Iterator
    {
        yield 'no parameters' => ['?', []];
        yield 'lookfor parameter' => ['?lookfor=foo', ['lookfor' => 'foo']];
    }
}
