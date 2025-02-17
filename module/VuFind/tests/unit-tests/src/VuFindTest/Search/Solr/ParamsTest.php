<?php

/**
 * Solr Search Object Parameters Test
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\Solr;

use VuFind\Config\PluginManager;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;

/**
 * Solr Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test that filters work as expected.
     *
     * @return void
     */
    public function testFilters(): void
    {
        $params = $this->getParams();
        $params->addFacet('format', 'format_label');
        $params->addFacet('building', 'building_label');

        // No filters:
        $this->assertEquals(null, $params->getBackendParameters()->get('fq'));

        // Add multiple filters:
        $params->addFilter('~format:bar');
        $params->addFilter('~format:baz');
        $params->addFilter('building:main');
        $this->assertEquals(
            [
                'building:"main"',
                '{!tag=format_filter}format:(format:"bar" OR format:"baz")',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Add a hidden filter:
        $params->addHiddenFilter('building:sub');
        $this->assertEquals(
            [
                'building:"sub"',
                'building:"main"',
                '{!tag=format_filter}format:(format:"bar" OR format:"baz")',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Remove format filters:
        $params->removeAllFilters('~format');
        $this->assertEquals(
            [
                'building:"sub"',
                'building:"main"',
            ],
            $params->getBackendParameters()->get('fq')
        );

        // Remove building filter:
        $params->removeFilter('building:main');
        $this->assertEquals(
            [
                'building:"sub"',
            ],
            $params->getBackendParameters()->get('fq')
        );
    }

    /**
     * Test that we get a mock search class ID while testing.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Solr', $this->getParams()->getSearchClassId());
    }

    /**
     * Test that checkbox filters are always visible (or not) as appropriate.
     *
     * @return void
     */
    public function testCheckboxVisibility()
    {
        $config = [
            'facets' => [
                'CheckboxFacets' => [
                    'format:book' => 'Book filter',
                    'vufind:inverted' => 'Inverted filter',
                ],
                'CustomFilters' => [
                    'inverted_filters' => [
                        'inverted' => 'foo:bar',
                    ],
                ],
            ],
        ];
        $configManager = $this->getMockConfigPluginManager($config);
        $params = $this->getParams(null, $configManager);
        // We expect "normal" filters to NOT be always visible, and inverted
        // filters to be always visible.
        $this->assertEquals(
            [
                [
                    'desc' => 'Book filter',
                    'filter' => 'format:book',
                    'selected' => false,
                    'alwaysVisible' => false,
                    'dynamic' => false,
                ],
                [
                    'desc' => 'Inverted filter',
                    'filter' => 'vufind:inverted',
                    'selected' => false,
                    'alwaysVisible' => true,
                    'dynamic' => false,
                ],
            ],
            $params->getCheckboxFacets()
        );
    }

    /**
     * Data provider for testSortTieBreakerParameter.
     *
     * @return array
     */
    public static function sortValueProvider(): array
    {
        return ['Test1' => ['year', 'id', 'publishDateSort desc,id asc'],
                'Test2' => ['year', 'id desc', 'publishDateSort desc,id desc'],
                'Test3' => ['year', '', 'publishDateSort desc'],
                'Test4' => ['year', 'title desc,id asc', 'publishDateSort desc,title_sort desc,id asc'],
                'Test5' => ['year', 'title desc,id', 'publishDateSort desc,title_sort desc,id asc'],
                'Test6' => ['year,id', 'id desc', 'publishDateSort desc,id asc'],
            ];
    }

    /**
     * Test sort tie-breaker parameter.
     *
     * @param string $sort           Sort parameter of normalizeSort method
     * @param string $tieBreaker     Sort tie breaker form Searches.ini
     * @param string $expectedResult Expected return value from normalizeSort
     *
     * @return void
     *
     * @dataProvider sortValueProvider
     */
    public function testSortTieBreakerParameter(
        string $sort,
        string $tieBreaker,
        string $expectedResult
    ): void {
        $options = $this->getMockBuilder(\VuFind\Search\Solr\Options::class)
                ->disableOriginalConstructor()
                ->getMock();
        $options->expects($this->once())->method('getSortTieBreaker')
                ->will($this->returnValue($tieBreaker));
        $params = $this->getParams($options);
        $this->assertEquals(
            $expectedResult,
            $this->callMethod($params, 'normalizeSort', [$sort])
        );
    }

    /**
     * Data provider for testSortList
     *
     * @return array
     */
    public static function sortListDataProvider(): array
    {
        $searchConfig = [
            'Sorting' => [
                'relevance' => 'Relevance',
                'title' => 'Title',
            ],
            'HiddenSorting' => [
                'pattern' => [
                    '[Tt]est',
                ],
            ],
        ];

        return [
            'relevance' => [
                $searchConfig,
                'relevance',
                [
                    'relevance' => [
                        'desc' => 'Relevance',
                        'selected' => true,
                        'default' => true,
                    ],
                    'title' => [
                        'desc' => 'Title',
                        'selected' => false,
                        'default' => false,
                    ],
                ],
            ],
            'title' => [
                $searchConfig,
                'title',
                [
                    'relevance' => [
                        'desc' => 'Relevance',
                        'selected' => false,
                        'default' => true,
                    ],
                    'title' => [
                        'desc' => 'Title',
                        'selected' => true,
                        'default' => false,
                    ],
                ],
            ],
            'hidden' => [
                $searchConfig,
                'footestbar',
                [
                    'relevance' => [
                        'desc' => 'Relevance',
                        'selected' => false,
                        'default' => true,
                    ],
                    'title' => [
                        'desc' => 'Title',
                        'selected' => false,
                        'default' => false,
                    ],
                    'footestbar' => [
                        'desc' => 'unrecognized_sort_option',
                        'selected' => true,
                        'default' => false,
                    ],
                ],
            ],
            'invalid' => [
                $searchConfig,
                'foobar',
                [
                    'relevance' => [
                        'desc' => 'Relevance',
                        'selected' => true,
                        'default' => true,
                    ],
                    'title' => [
                        'desc' => 'Title',
                        'selected' => false,
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Test sort option list handling
     *
     * @param array  $searchConfig     Search configuration
     * @param string $sort             Selected sort option
     * @param string $expectedSortList Expected sort list
     *
     * @return void
     *
     * @dataProvider sortListDataProvider
     */
    public function testSortList(array $searchConfig, string $sort, array $expectedSortList): void
    {
        $params = $this->getParams(mockConfig: $this->getMockConfigPluginManager(['searches' => $searchConfig]));
        $params->setSort($sort);
        $this->assertEquals($expectedSortList, $params->getSortList());
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
