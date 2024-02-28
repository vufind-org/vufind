<?php

/**
 * SearchTabsHelper unit tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016.
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

namespace VuFindTest\Search;

use VuFind\Search\SearchTabsHelper;

/**
 * SearchTabsHelper unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchTabsHelperTest extends \PHPUnit\Framework\TestCase
{
    protected $tabConfig = [
        'default_unfiltered' => [
            'Solr' => 'Local Index',
            'Solr:video' => 'Local Videos',
            'Primo' => 'Primo Central',
            'Primo:dissertation' => 'Dissertations in Primo Central',
        ],
        'default_filtered' => [
            'Solr:main' => 'Main Library',
            'Solr:mainvideo' => 'Main Library Videos',
            'Solr:branch' => 'Branch Library',
            'Primo' => 'Primo Central',
            'Primo:dissertation' => 'Dissertations in Primo Central',
        ],
        'no_tabs' => [
        ],
    ];

    protected $filterConfig = [
        'Solr:video' => ['format:video'],
        'Solr:main' => ['building:main'],
        'Solr:mainvideo' => ['building:main', 'format:video'],
        'Solr:branch' => ['building:branch'],
        'Primo:dissertation' => ['rtype:Dissertations'],
    ];

    /**
     * Test getHiddenFilters()
     *
     * @return void
     */
    public function testGetHiddenFilters()
    {
        // Default tab with no filters
        $helper = $this->getSearchTabsHelper();
        $this->assertEmpty($helper->getHiddenFilters('Solr'));
        $this->assertEmpty($helper->getHiddenFilters('Primo'));

        // Non-default tab with filters
        $helper = $this->getSearchTabsHelper('default_unfiltered', ['format:video']);
        $this->assertEquals(
            ['format' => ['video']],
            $helper->getHiddenFilters('Solr')
        );
        $this->assertEquals(
            ['format' => ['video']],
            $helper->getHiddenFilters('Solr', false)
        );

        // Default tab with filters
        $helper = $this->getSearchTabsHelper('default_filtered');
        $this->assertEquals(
            ['building' => ['main']],
            $helper->getHiddenFilters('Solr')
        );
        $this->assertEmpty($helper->getHiddenFilters('Solr', false));

        $helper = $this->getSearchTabsHelper(
            'default_unfiltered',
            ['building:main', 'format:video']
        );
        $this->assertEquals(
            ['building' => ['main'], 'format' => ['video']],
            $helper->getHiddenFilters('Solr')
        );
        $this->assertEquals(
            ['building' => ['main'], 'format' => ['video']],
            $helper->getHiddenFilters('Solr', false)
        );

        // Non-default tab with filters
        $helper = $this->getSearchTabsHelper(
            'default_unfiltered',
            ['rtype:Dissertation']
        );
        $this->assertEquals(
            ['rtype' => ['Dissertation']],
            $helper->getHiddenFilters('Primo')
        );

        // Non-tabbed config
        $helper = $this->getSearchTabsHelper('no_tabs');
        $this->assertEmpty($helper->getHiddenFilters('Solr'));
    }

    /**
     * Test getTabConfig() and getTabFilterConfig()
     *
     * @return void
     */
    public function testTabConfig()
    {
        $helper = $this->getSearchTabsHelper();
        $this->assertEquals(
            $this->tabConfig['default_unfiltered'],
            $helper->getTabConfig()
        );
        $this->assertEquals($this->filterConfig, $helper->getTabFilterConfig());
    }

    /**
     * Test extractClassName()
     *
     * @return void
     */
    public function testExtractClassName()
    {
        $helper = $this->getSearchTabsHelper();

        $this->assertEquals('Solr', $helper->extractClassName('Solr'));
        $this->assertEquals('Solr', $helper->extractClassName('Solr:foo'));
        $this->assertEquals('Primo', $helper->extractClassName('Primo:foo:bar'));
    }

    /**
     * Test filtersMatch()
     *
     * @return void
     */
    public function testFiltersMatch()
    {
        $helper = $this->getSearchTabsHelper();

        $this->assertTrue(
            $helper->filtersMatch(
                'Solr',
                ['building' => ['main']],
                ['building:main']
            )
        );
        $this->assertTrue(
            $helper->filtersMatch(
                'Solr',
                ['building' => ['main'], 'foo' => ['bar', 'baz']],
                ['building:main', 'foo:bar', 'foo:baz']
            )
        );
        $this->assertTrue(
            $helper->filtersMatch(
                'Solr',
                [],
                []
            )
        );
        $this->assertFalse(
            $helper->filtersMatch(
                'Solr',
                [],
                ['building:main']
            )
        );
        $this->assertFalse(
            $helper->filtersMatch(
                'Solr',
                ['building' => ['main']],
                []
            )
        );
    }

    /**
     * Create a SearchTabsHelper
     *
     * @param string $config  Which config set to use
     * @param array  $filters Active filters for a simulated request
     *
     * @return \VuFind\Search\SearchTabsHelper
     */
    protected function getSearchTabsHelper(
        $config = 'default_unfiltered',
        $filters = null
    ) {
        $mockRequest = $this->createMock(\Laminas\Http\Request::class);
        $mockRequest->expects($this->any())
            ->method('getQuery')
            ->with($this->equalTo('hiddenFilters'))
            ->willReturn($filters);

        $configManager = $this->createMock(\VuFind\Config\PluginManager::class);

        $mockSolrOptions = $this->getMockBuilder(\VuFind\Search\Solr\Options::class)
            ->disableOriginalConstructor()->getMock();
        $mockSolr = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $mockSolr->expects($this->any())
            ->method('getParams')
            ->willReturn(
                new \VuFind\Search\Solr\Params($mockSolrOptions, $configManager)
            );

        $mockPrimoOptions = $this->getMockBuilder(\VuFind\Search\Primo\Options::class)
            ->disableOriginalConstructor()->getMock();
        $mockPrimo = $this->getMockBuilder(\VuFind\Search\Primo\Results::class)
            ->disableOriginalConstructor()->getMock();
        $mockPrimo->expects($this->any())
            ->method('getParams')
            ->willReturn(
                new \VuFind\Search\Primo\Params($mockPrimoOptions, $configManager)
            );

        $mockResults = $this->createMock(\VuFind\Search\Results\PluginManager::class);
        $mockResults->expects($this->any())
            ->method('get')
            ->will(
                $this->returnCallback(
                    function ($backend) use ($mockSolr, $mockPrimo) {
                        switch ($backend) {
                            case 'Solr':
                                return $mockSolr;
                            case 'Primo':
                                return $mockPrimo;
                            default:
                                throw new \Exception(
                                    "Unsupported backend $backend"
                                );
                        }
                    }
                )
            );

        return new \VuFind\Search\SearchTabsHelper(
            $mockResults,
            $this->tabConfig[$config],
            $this->filterConfig,
            $mockRequest
        );
    }
}
