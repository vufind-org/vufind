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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\Stdlib\Parameters;
use Laminas\View\Helper\Url;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Search\Memory;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;
use VuFind\Search\UrlQueryHelper;
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
        $solrParams->expects($this->any())->method('getOptions')->willReturn($solrOptions);
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
        $mockQueryHelper->expects($this->any())->method('setJumpto')->willReturn($mockQueryHelper);
        $results = $this->createMock(Results::class);
        $results->expects($this->any())->method('getOptions')->willReturn($solrOptions);
        $results->expects($this->any())->method('getParams')->willReturn($solrParams);
        $results->expects($this->any())->method('getUrlQuery')->willReturn($mockQueryHelper);
        return $results;
    }

    /**
     * Get a configured view object with relevant helpers for testing.
     *
     * @param Params $solrParams Configured Solr Params object
     *
     * @return PhpRenderer
     */
    protected function getConfiguredView(Params $solrParams): PhpRenderer
    {
        $url = $this->createMock(Url::class);
        $url->expects($this->any())->method('__invoke')
            ->with($this->equalTo($this->searchRoute))
            ->willReturn($this->searchBasePath);
        $searchParams = $this->createMock(SearchParams::class);
        $searchParams->expects($this->any())->method('__invoke')
            ->with($this->equalTo('Solr'))->willReturn($solrParams);
        $plugins = compact('searchParams', 'url');
        return $this->getPhpRenderer($plugins);
    }

    /**
     * Test search memory helper's getLastSearchParams() method.
     *
     * @param string $query                Query to parse
     * @param array  $expectedRequestArray Expected request parameters to parse
     *
     * @return void
     *
     * @dataProvider getLastSearchParamsProvider
     */
    public function testGetLastSearchParams(
        string $query,
        array $expectedRequestArray
    ): void {
        $memory = $this->createMock(Memory::class);
        $memory->expects($this->once())->method('retrieveSearch')->willReturn($this->searchBasePath . $query);
        $helper = $this->getSearchMemoryViewHelper($memory);
        $solrParams = $this->getMockSolrParams($expectedRequestArray);
        $expectedRequest = new Parameters($expectedRequestArray);
        $solrParams->expects($this->once())->method('initFromRequest')
            ->with($this->equalTo($expectedRequest));
        $helper->setView($this->getConfiguredView($solrParams));
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
        $helper = $this->getSearchMemoryViewHelper($memory);
        $helper->setView($this->getConfiguredView($results->getParams()));
        $this->assertEquals('/foo/bar', $helper->getLastSearchUrl('Solr'));
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
        $helper = $this->getSearchMemoryViewHelper($memory);
        $helper->setView($this->getConfiguredView($this->createMock(Params::class)));
        $this->assertNull($helper->getLastSearchUrl('Solr'));
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
        $helper = $this->getSearchMemoryViewHelper($memory);
        $helper->setView($this->getConfiguredView($results->getParams()));
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
        $helper = $this->getSearchMemoryViewHelper($memory);
        $helper->setView($this->getConfiguredView($this->createMock(Params::class)));
        $this->assertEquals('', $helper->getLastSearchLink('Solr', 'prefix', 'suffix'));
    }

    /**
     * Data provider for testGetLastSearchParams()
     *
     * @return array
     */
    public static function getLastSearchParamsProvider(): array
    {
        return [
            'no parameters' => ['?', []],
            'lookfor parameter' => ['?lookfor=foo', ['lookfor' => 'foo']],
        ];
    }
}
