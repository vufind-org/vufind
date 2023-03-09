<?php
/**
 * SearchTabs view helper Test Class
 *
 * PHP version 7
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

use Laminas\View\Helper\Url;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\SearchTabsHelper;
use VuFind\View\Helper\Root\SearchMemory;
use VuFind\View\Helper\Root\SearchTabs;

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
    use  \VuFindTest\Feature\ViewTrait;

    /**
     * Data provider for testGetCurrentHiddenFilterParams
     *
     * @return array
     */
    public function getCurrentHiddenFilterParamsProvider(): array
    {
        return [
            [
                [],
                2,
                [],
                1,
                ''
            ],
            [
                [
                    'first' => ['foo'],
                    'second' => ['bar'],
                ],
                1,
                [
                    'last' => ['foo']
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
     * Test search memory helper
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
            "&amp;$expected",
            $helper->getCurrentHiddenFilterParams('Solr')
        );
        $this->assertEquals(
            $expected,
            $helper->getCurrentHiddenFilterParams('Solr', false, '')
        );
    }

    /**
     * Get a SearchTabs helper
     *
     * @param array $filters          Current filters
     * @param int   $filtersCalls     Number of expected calls to get filters
     * @param array $lastFilters      Last filters
     * @param int   $lastFiltersCalls Number of expected calls to get last filters
     *
     * @return SearchTabs
     */
    protected function getHelper(
        array $filters,
        int $filtersCalls,
        array $lastFilters,
        int $lastFiltersCalls
    ): SearchTabs {
        $configManager = $this->createMock(\VuFind\Config\PluginManager::class);

        $solrOptions = $this->getMockBuilder(\VuFind\Search\Solr\Options::class)
            ->disableOriginalConstructor()
            ->getMock();
        $solr = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $solr->expects($this->any())
            ->method('getParams')
            ->willReturn(
                new \VuFind\Search\Solr\Params($solrOptions, $configManager)
            );

        $resultsPM = $this->getMockBuilder(ResultsPluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resultsPM->expects($this->any())
            ->method('get')
            ->willReturn($solr);

        $url = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $searchTabsHelper = $this->getMockBuilder(SearchTabsHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $searchTabsHelper->expects($this->exactly($filtersCalls))
            ->method('getHiddenFilters')
            ->willReturn($filters);
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
}
