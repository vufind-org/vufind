<?php

/**
 * Unit tests for facet formatter.
 *
 * PHP version 5
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
namespace VuFindTest\Controller;

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
class FacetFormatterTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Get fake facet data.
     *
     * @return array
     */
    protected function getFakeFacetData()
    {
        return [
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
                        'isApplied' => false, //true,
                    ],
                ],
            ],
            /* TODO: uncomment this and adjust test(s)
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
             */
        ];
    }

    /**
     * Get fake results object.
     *
     * @param array $request Request parameters.
     *
     * @return Results
     */
    protected function getFakeResults($request)
    {
        $configManager = $this->getMock('VuFind\Config\PluginManager');
        $params = new Params(new Options($configManager), $configManager);
        $params->initFromRequest(new \Zend\Stdlib\Parameters($request));
        return new Results($params, 100, $this->getFakeFacetData());
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
            'facet' => ['foo'],
            //'filter' => ['foo:baz'], TODO: turn this filter on and adjust test!
        ];
        $formatted = $formatter->format($request, $this->getFakeResults($request), []);

        $expected = [
            'foo' => [
                [
                    'value' => 'bar',
                    'translated' => 'translated(bar)',
                    'count' => 100,
                    'href' => '?filter%5B%5D=foo%3A%22bar%22',
                ],
                [
                    'value' => 'baz',
                    'translated' => 'translated(baz)',
                    'count' => 150,
                    //'isApplied' => true,
                    'href' => '?filter%5B%5D=foo%3A%22baz%22',
                ],
            ],
        ];
        $this->assertEquals($expected, $formatted);
    }
}
