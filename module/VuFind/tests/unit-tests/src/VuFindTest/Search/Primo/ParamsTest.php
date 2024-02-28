<?php

/**
 * Unit tests for Primo Params.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Primo;

use VuFind\Search\Primo\Options;
use VuFind\Search\Primo\Params;

/**
 * Unit tests for Primo Params.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test facet value normalization
     *
     * @return void
     */
    public function testFixPrimoFacetValue()
    {
        $params = $this->getParams();
        $this->assertEquals(
            'Foo Bar',
            $params->fixPrimoFacetValue('foo bar')
        );
        $this->assertEquals(
            'Foo Bar',
            $params->fixPrimoFacetValue('foo_bar')
        );
        $this->assertEquals(
            'Reference Entries',
            $params->fixPrimoFacetValue('reference_entrys')
        );
        $this->assertEquals(
            'Newsletter Articles',
            $params->fixPrimoFacetValue('newsletterarticle')
        );
        $this->assertEquals(
            'Archival Materials / Manuscripts',
            $params->fixPrimoFacetValue('archival_material_manuscripts')
        );
        $this->assertEquals(
            '维普资讯 (Chongqing)',
            $params->fixPrimoFacetValue('维普资讯 (Chongqing)')
        );
    }

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
        $params->addFilter('~format:foo');
        $params->addFilter('~format:bar');
        $params->addFilter('building:main');
        $this->assertEquals(
            [
                [
                    'field' => 'format',
                    'facetOp' => 'OR',
                    'values' => [
                        'foo',
                        'bar',
                    ],
                ],
                [
                    'field' => 'building',
                    'facetOp' => 'AND',
                    'values' => [
                        'main',
                    ],
                ],
            ],
            $params->getBackendParameters()->get('filterList')
        );

        // Remove building filter:
        $params->removeFilter('building:main');
        $this->assertEquals(
            [
                [
                    'field' => 'format',
                    'facetOp' => 'OR',
                    'values' => [
                        'foo',
                        'bar',
                    ],
                ],
            ],
            $params->getBackendParameters()->get('filterList')
        );

        // Add a filter and a hidden filter:
        $params->addFilter('building:main');
        $params->addHiddenFilter('building:sub');
        $this->assertEquals(
            [
                [
                    'field' => 'building',
                    'facetOp' => 'AND',
                    'values' => [
                        'sub',
                        'main',
                    ],
                ],
                [
                    'field' => 'format',
                    'facetOp' => 'OR',
                    'values' => [
                        'foo',
                        'bar',
                    ],
                ],
            ],
            $params->getBackendParameters()->get('filterList')
        );

        // Remove format filters:
        $params->removeAllFilters('~format');
        $this->assertEquals(
            [
                [
                    'field' => 'building',
                    'facetOp' => 'AND',
                    'values' => [
                        'sub',
                        'main',
                    ],
                ],
            ],
            $params->getBackendParameters()->get('filterList')
        );

        // Remove building:main filter:
        $params->removeFilter('building:main');
        $this->assertEquals(
            [
                [
                    'field' => 'building',
                    'facetOp' => 'AND',
                    'values' => [
                        'sub',
                    ],
                ],
            ],
            $params->getBackendParameters()->get('filterList')
        );
    }

    /**
     * Test that we get a mock search class ID while testing.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Primo', $this->getParams()->getSearchClassId());
    }

    /**
     * Get Params object
     *
     * @return Params
     */
    protected function getParams(): Params
    {
        $configMock = $this->createMock(\VuFind\Config\PluginManager::class);
        return new Params(
            new Options($configMock),
            $configMock
        );
    }
}
