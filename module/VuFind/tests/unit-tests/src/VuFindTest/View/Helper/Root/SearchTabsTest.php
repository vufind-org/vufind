<?php

/**
 * SearchTabs view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\Http\Request;
use Laminas\View\Helper\Url;
use VuFind\Record\Loader;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\SearchTabsHelper;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;
use VuFind\View\Helper\Root\SearchMemory;
use VuFind\View\Helper\Root\SearchTabs;
use VuFindSearch\Service as SearchService;

/**
 * SearchTabs view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchTabsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Data provider for testGetCurrentHiddenFilterParams
     *
     * @return array
     */
    public static function getCurrentHiddenFilterParamsProvider(): array
    {
        return [
            [
                [],
                2,
                [],
                1,
                '',
            ],
            [
                [
                    'first' => 'foo',
                    'second' => 'bar',
                ],
                1,
                [
                    'last' => ['foo'],
                ],
                0,
                'hiddenFilters%5B%5D=first%3A%22foo%22'
                . '&amp;hiddenFilters%5B%5D=second%3A%22bar%22',
            ],
            [
                [],
                1,
                [
                    'last' => ['foo'],
                ],
                1,
                'hiddenFilters%5B%5D=last%3A%22foo%22',
            ],
        ];
    }

    /**
     * Test getCurrentHiddenFilterParams method
     *
     * @param array  $currentFilters   Current hidden filters
     * @param int    $filtersCalls     Number of expected calls to get filters
     * @param array  $lastFilters      Hidden filters for last search
     * @param int    $lastFiltersCalls Number of expected calls to get last filters
     * @param string $expected         Expected results
     *
     * @return void
     *
     * @dataProvider getCurrentHiddenFilterParamsProvider
     */
    public function testGetCurrentHiddenFilterParams(
        array $currentFilters,
        int $filtersCalls,
        array $lastFilters,
        int $lastFiltersCalls,
        string $expected
    ): void {
        $helper = $this->getHelper(
            $currentFilters,
            $filtersCalls,
            $lastFilters,
            $lastFiltersCalls
        );

        $this->assertEquals(
            $expected ? "&amp;$expected" : '',
            $helper->getCurrentHiddenFilterParams('Solr')
        );
        $this->assertEquals(
            $expected,
            $helper->getCurrentHiddenFilterParams('Solr', false, '')
        );
    }

    /**
     * Test getTabConfig method
     *
     * @return void
     */
    public function testGetTabConfig(): void
    {
        $helper = $this->getHelper(
            [],
            0,
            [],
            0,
            [
                'Solr' => 'Local Index',
                'Solr:filtered' => 'Local Journals',
            ],
            4,
            [
                'Solr:filtered' => [
                    'building:"main"',
                    'format:"journal"',
                ],
            ],
            4
        );

        $expected = [
            'tabs' => [
                [
                    'id' => 'Solr',
                    'class' => 'Solr',
                    'label' => 'Local Index',
                    'permission' => null,
                    'selected' => false,
                    'url' => '?hiddenFilters%5B%5D=building%3A%22main%22'
                        . '&hiddenFilters%5B%5D=format%3A%22journal%22',
                ],
                [
                    'id' => 'Solr:filtered',
                    'class' => 'Solr',
                    'label' => 'Local Journals',
                    'permission' => 'logged-in',
                    'selected' => false,
                    'url' => '?hiddenFilters%5B%5D=building%3A%22main%22'
                        . '&hiddenFilters%5B%5D=format%3A%22journal%22',
                ],
            ],
            'showCounts' => false,
        ];

        $expectedSelected = $expected;
        $expected['tabs'][0]['url'] = '';
        $expectedSelected['tabs'][0]['selected'] = true;
        $expectedSelected['selected'] = $expectedSelected['tabs'][0];

        $config = $helper->getTabConfig('', '', '', '');
        $this->assertEquals($expected, $config);

        $config = $helper->getTabConfig('Solr', '', '', 'basic');
        $this->assertEquals($expectedSelected, $config);

        $config = $helper->getTabConfig('Solr', '', '', 'advanced');
        $this->assertEquals($expectedSelected, $config);

        $config = $helper->getTabConfigForParams($this->getSolrParams());
        $this->assertEquals($expectedSelected['tabs'], $config);
    }

    /**
     * Test getHiddenFilters method
     *
     * @return void
     */
    public function testGetHiddenFilters(): void
    {
        $helper = $this->getHelper(
            [],
            0,
            [],
            0,
            [
                'Solr' => 'Local Index',
                'Dolr' => 'Local Index',
            ],
            4,
            [
                'Solr' => [
                    'building:"main"',
                    'format:"journal"',
                ],
                'Dolr' => [
                    'building:"dolr"',
                ],
            ],
            4
        );

        $this->assertEquals(
            [
                'building' => ['dolr'],
            ],
            $helper->getHiddenFilters('Dolr')
        );

        $this->assertEquals(
            [
                'building' => ['main'],
                'format' => ['journal'],
            ],
            $helper->getHiddenFilters('Solr', true, true)
        );

        $this->assertEquals([], $helper->getHiddenFilters('Folr', true, true));
    }

    /**
     * Get a SearchTabs helper
     *
     * @param array $filters              Current filters
     * @param int   $filtersCalls         Number of expected calls to get filters
     * @param array $lastFilters          Last filters
     * @param int   $lastFiltersCalls     Number of expected calls to get last filters
     * @param array $tabConfig            Tab configuration
     * @param int   $tabConfigCalls       Number of expected calls to get tab config
     * @param array $tabFilterConfig      Tab filter configuration
     * @param int   $tabFilterConfigCalls Number of expected calls to get tab filter config
     *
     * @return SearchTabs
     */
    protected function getHelper(
        array $filters,
        int $filtersCalls,
        array $lastFilters,
        int $lastFiltersCalls,
        array $tabConfig = [],
        int $tabConfigCalls = 0,
        array $tabFilterConfig = [],
        int $tabFilterConfigCalls = 0
    ): SearchTabs {
        $searchService = $this->getMockBuilder(SearchService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordLoader = $this->getMockBuilder(Loader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $solr = new Results($this->getSolrParams(), $searchService, $recordLoader);

        $resultsPM = $this->getMockBuilder(ResultsPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resultsPM->expects($this->any())
            ->method('get')
            ->willReturn($solr);

        $request = Request::fromString('GET / HTTP/1.1');
        if ($filters) {
            $queryFilters = [];
            foreach ($filters as $key => $filter) {
                $queryFilters[] = "$key:\"$filter\"";
            }
            $request->getQuery()->hiddenFilters = $queryFilters;
        }

        $url = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $searchTabsHelper = new SearchTabsHelper(
            $resultsPM,
            $tabConfig,
            $tabFilterConfig,
            $request,
            [
                'Solr:filtered' => 'logged-in',
            ]
        );
        $searchMemory = $this->getMockBuilder(SearchMemory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $searchMemory->expects($this->exactly($lastFiltersCalls))
            ->method('getLastHiddenFilters')
            ->willReturn($lastFilters);
        $plugins = compact('searchMemory');
        $helper = new SearchTabs($resultsPM, $url, $searchTabsHelper);
        $helper->setView($this->getPhpRenderer($plugins));
        return $helper;
    }

    /**
     * Get a Solr Params object
     *
     * @return Params
     */
    protected function getSolrParams(): Params
    {
        $solrOptions = $this->getMockBuilder(\VuFind\Search\Solr\Options::class)
            ->disableOriginalConstructor()
            ->getMock();
        $solrOptions->expects($this->any())
            ->method('getSearchClassId')
            ->willReturn('Solr');
        $solrOptions->expects($this->any())
            ->method('getDefaultLimit')
            ->willReturn(20);
        $configManager = $this->createMock(\VuFind\Config\PluginManager::class);
        return new \VuFind\Search\Solr\Params($solrOptions, $configManager);
    }
}
