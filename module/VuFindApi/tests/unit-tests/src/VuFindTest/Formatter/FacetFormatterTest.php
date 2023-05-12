<?php

/**
 * Unit tests for facet formatter.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Formatter;

use VuFindTest\Search\TestHarness\Options;
use VuFindTest\Search\TestHarness\Params;
use VuFindTest\Search\TestHarness\Results;

/**
 * Unit tests for facet formatter.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class FacetFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get fake facet data.
     *
     * @param bool $includeOr Include OR facet data?
     *
     * @return array
     */
    protected function getFakeFacetData($includeOr = false)
    {
        $data = [
            'foo' => [
                'label' => 'Foo Facet',
                'list' => [
                    [
                        'value' => 'bar',
                        'displayText' => 'translated(bar)',
                        'count' => 100,
                        'operator' => 'AND',
                        'isApplied' => false,
                    ],
                    [
                        'value' => 'baz',
                        'displayText' => 'translated(baz)',
                        'count' => 150,
                        'operator' => 'AND',
                        'isApplied' => true,
                    ],
                ],
            ],
            'xyzzy' => [
                'label' => 'Xyzzy Facet',
                'list' => [
                    [
                        'value' => 'val1',
                        'displayText' => 'translated(val1)',
                        'count' => 10,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => 'val2',
                        'displayText' => 'translated(val2)',
                        'count' => 15,
                        'operator' => 'OR',
                        'isApplied' => true,
                    ],
                    [
                        'value' => 'val3',
                        'displayText' => 'translated(val3)',
                        'count' => 5,
                        'operator' => 'OR',
                        'isApplied' => true,
                    ],
                ],
            ],
        ];
        if (!$includeOr) {
            unset($data['xyzzy']);
        }
        return $data;
    }

    /**
     * Get fake hierarchical facet data.
     *
     * @param array $request   Request params
     * @param bool  $includeOr Include OR facet data?
     *
     * @return array
     */
    protected function getFakeHierarchicalFacetData($request, $includeOr = false)
    {
        $data = [
            'hierarchical_foo' => [
                [
                    'value' => '0/bar/',
                    'displayText' => 'translated(bar)',
                    'count' => 100,
                    'operator' => 'AND',
                    'isApplied' => false,
                ],
                [
                    'value' => '1/bar/cookie/',
                    'displayText' => 'translated(cookie)',
                    'count' => 150,
                    'operator' => 'AND',
                    'isApplied' => true,
                ],
            ],
            'hierarchical_xyzzy' => [
                [
                    'value' => '0/val1/',
                    'displayText' => 'translated(val1)',
                    'count' => 10,
                    'operator' => 'OR',
                    'isApplied' => false,
                ],
                [
                    'value' => '1/val1/val2/',
                    'displayText' => 'translated(val2)',
                    'count' => 15,
                    'operator' => 'OR',
                    'isApplied' => true,
                ],
            ],
        ];
        if (!$includeOr) {
            unset($data['hierarchical_xyzzy']);
        }

        $results = [];
        $helper = new \VuFind\Search\Solr\HierarchicalFacetHelper();
        $configManager = $this->createMock(\VuFind\Config\PluginManager::class);
        $params = new Params(new Options($configManager), $configManager);
        $requestParams = new \Laminas\Stdlib\Parameters($request);
        $params->initFromRequest($requestParams);
        $factory = new \VuFind\Search\Factory\UrlQueryHelperFactory();
        $urlQuery = $factory->fromParams($params);
        foreach ($data as $facet => $values) {
            $results[$facet] = $helper->buildFacetArray(
                $facet,
                $values,
                $urlQuery,
                false
            );
        }

        return $results;
    }

    /**
     * Get fake results object.
     *
     * @param array $request   Request parameters.
     * @param array $facetData Facet data to inject into results.
     *
     * @return Results
     */
    protected function getFakeResults($request, $facetData)
    {
        $configManager = $this->createMock(\VuFind\Config\PluginManager::class);
        $params = new Params(new Options($configManager), $configManager);
        $params->initFromRequest(new \Laminas\Stdlib\Parameters($request));
        $ss = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $rl = $this->getMockBuilder(\VuFind\Record\Loader::class)
            ->disableOriginalConstructor()->getMock();
        return new Results($params, $ss, $rl, 100, $facetData);
    }

    /**
     * Test the facet formatter
     *
     * @return void
     */
    public function testFormatter()
    {
        $formatter = new \VuFindApi\Formatter\FacetFormatter();
        $request = [
            'facet' => ['foo', 'hierarchical_foo'],
            'filter' => ['foo:baz', 'hierarchical_foo:1/bar/cookie/'],
        ];
        $formatted = $formatter->format(
            $request,
            $this->getFakeResults($request, $this->getFakeFacetData()),
            $this->getFakeHierarchicalFacetData($request)
        );

        $expected = [
            'foo' => [
                [
                    'value' => 'bar',
                    'translated' => 'translated(bar)',
                    'count' => 100,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22&filter%5B%5D=foo%3A%22bar%22',
                ],
                [
                    'value' => 'baz',
                    'translated' => 'translated(baz)',
                    'count' => 150,
                    'isApplied' => 1,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22',
                ],
            ],
            'hierarchical_foo' => [
                [
                    'value' => '0/bar/',
                    'translated' => 'translated(bar)',
                    'count' => 100,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%220%2Fbar%2F%22',
                    'children' => [
                        [
                            'value' => '1/bar/cookie/',
                            'translated' => 'translated(cookie)',
                            'count' => 150,
                            'isApplied' => 1,
                            'href' => '?filter%5B%5D=foo%3A%22baz%22',
                        ],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $formatted);
    }

    /**
     * Test the facet formatter with filtering turned on
     *
     * @return void
     */
    public function testFormatterWithFiltering()
    {
        $formatter = new \VuFindApi\Formatter\FacetFormatter();
        $request = [
            'facet' => ['foo', 'xyzzy'],
            'filter' => [
                'foo:baz',
                'hierarchical_foo:1/bar/cookie/',
                '~xyzzy:val2',
                '~xyzzy:val3',
                'hierarchical_xyzzy:1/val1/val2/',
            ],
            'facetFilter' => ['foo:..z', 'xyzzy:val(2|3)'],
        ];
        $formatted = $formatter->format(
            $request,
            $this->getFakeResults($request, $this->getFakeFacetData(true)),
            $this->getFakeHierarchicalFacetData($request, true)
        );

        $expected = [
            'foo' => [
                [
                    'value' => 'baz',
                    'translated' => 'translated(baz)',
                    'count' => 150,
                    'isApplied' => 1,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                        . '&filter%5B%5D=%7Exyzzy%3A%22val2%22&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                        . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22',
                ],
            ],
            'xyzzy' => [
                [
                    'value' => 'val2',
                    'translated' => 'translated(val2)',
                    'count' => 15,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                        . '&filter%5B%5D=%7Exyzzy%3A%22val2%22&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                        . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22',
                    'isApplied' => 1,
                ],
                [
                    'value' => 'val3',
                    'translated' => 'translated(val3)',
                    'count' => 5,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                        . '&filter%5B%5D=%7Exyzzy%3A%22val2%22&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                        . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22',
                    'isApplied' => 1,
                ],
            ],
            'hierarchical_foo' => [
                [
                    'value' => '0/bar/',
                    'translated' => 'translated(bar)',
                    'count' => 100,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                        . '&filter%5B%5D=%7Exyzzy%3A%22val2%22&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                        . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%220%2Fbar%2F%22',
                    'children' => [
                        [
                            'value' => '1/bar/cookie/',
                            'translated' => 'translated(cookie)',
                            'count' => 150,
                            'isApplied' => 1,
                            'href' => '?filter%5B%5D=foo%3A%22baz%22'
                                . '&filter%5B%5D=%7Exyzzy%3A%22val2%22'
                                . '&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                                . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22',
                        ],
                    ],
                ],
            ],
            'hierarchical_xyzzy' => [
                [
                    'value' => '0/val1/',
                    'translated' => 'translated(val1)',
                    'count' => 10,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22'
                        . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                        . '&filter%5B%5D=%7Exyzzy%3A%22val2%22&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                        . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22'
                        . '&filter%5B%5D=%7Ehierarchical_xyzzy%3A%220%2Fval1%2F%22',
                    'children' => [
                        [
                            'value' => '1/val1/val2/',
                            'translated' => 'translated(val2)',
                            'count' => 15,
                            'isApplied' => 1,
                            'href' => '?filter%5B%5D=foo%3A%22baz%22'
                                . '&filter%5B%5D=hierarchical_foo%3A%221%2Fbar%2Fcookie%2F%22'
                                . '&filter%5B%5D=%7Exyzzy%3A%22val2%22&filter%5B%5D=%7Exyzzy%3A%22val3%22'
                                . '&filter%5B%5D=hierarchical_xyzzy%3A%221%2Fval1%2Fval2%2F%22',
                        ],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $formatted);
    }
}
