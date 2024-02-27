<?php

/**
 * Base Search Object Parameters Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\Base;

use minSO;
use VuFind\Config\PluginManager;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;
use VuFind\Search\QueryAdapter;
use VuFindSearch\Query\Query;

/**
 * Base Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get mock Options object
     *
     * @param PluginManager $configManager Config manager for Options object (null
     * for new mock)
     *
     * @return Options
     */
    protected function getMockOptions(PluginManager $configManager = null): Options
    {
        return $this->getMockForAbstractClass(
            Options::class,
            [$configManager ?? $this->getMockConfigPluginManager([])]
        );
    }

    /**
     * Get mock Params object
     *
     * @param Options       $options       Options object to send to Params
     * constructor (null for new mock)
     * @param PluginManager $configManager Config manager for Params object (null
     * for new mock)
     *
     * @return Params
     */
    protected function getMockParams(
        ?Options $options = null,
        ?PluginManager $configManager = null
    ): Params {
        $configManager ??= $this->getMockConfigPluginManager([]);
        return $this->getMockForAbstractClass(
            Params::class,
            [$options ?? $this->getMockOptions($configManager), $configManager]
        );
    }

    /**
     * Test that getCheckboxFacets works as expected.
     *
     * @return void
     */
    public function testGetCheckboxFacets(): void
    {
        // None by default:
        $params = $this->getMockParams();
        $this->assertEquals([], $params->getCheckboxFacets());

        $expectedSelected = $expectedUnselected = [
            'desc' => 'checkbox_label',
            'filter' => 'foo:bar',
            'selected' => false,
            'alwaysVisible' => false,
            'dynamic' => false,
        ];
        $expectedSelected['selected'] = true;

        // Adding one works:
        $params->addCheckboxFacet('foo:bar', 'checkbox_label');
        $this->assertEquals([$expectedUnselected], $params->getCheckboxFacets());

        // Selecting one works:
        $params->addFilter('foo:bar');
        $this->assertEquals([$expectedSelected], $params->getCheckboxFacets());

        // Removing one works:
        $params->removeFilter('foo:bar');
        $this->assertEquals([$expectedUnselected], $params->getCheckboxFacets());
    }

    /**
     * Test that filters work as expected.
     *
     * @return void
     */
    public function testFilters(): void
    {
        $params = $this->getMockParams();
        $params->addFacet('format', 'format_label');
        $params->addFacet('building', 'building_label');

        // No filters:
        $this->assertEquals([], $params->getFilterList());

        // Add multiple filters:
        $params->addFilter('~format:bar');
        $params->addFilter('~format:baz');
        $params->addFilter('building:main');
        $params->addFilter('-building:sub');
        $this->assertTrue($params->hasFilter('~format:bar'));
        $this->assertTrue($params->hasFilter('~format:baz'));
        $this->assertTrue($params->hasFilter('building:main'));
        $this->assertEquals(
            [
                'format_label' => [
                    [
                        'value' => 'bar',
                        'displayText' => 'bar',
                        'field' => 'format',
                        'operator' => 'OR',
                    ],
                    [
                        'value' => 'baz',
                        'displayText' => 'baz',
                        'field' => 'format',
                        'operator' => 'OR',
                    ],
                ],
                'building_label' => [
                    [
                        'value' => 'main',
                        'displayText' => 'main',
                        'field' => 'building',
                        'operator' => 'AND',
                    ],
                    [
                        'value' => 'sub',
                        'displayText' => 'sub',
                        'field' => 'building',
                        'operator' => 'NOT',
                    ],
                ],
            ],
            $params->getFilterList()
        );

        // Remove format filters and verify:
        $params->removeAllFilters('format');
        $this->assertEquals(
            [
                'building_label' => [
                    [
                        'value' => 'main',
                        'displayText' => 'main',
                        'field' => 'building',
                        'operator' => 'AND',
                    ],
                    [
                        'value' => 'sub',
                        'displayText' => 'sub',
                        'field' => 'building',
                        'operator' => 'NOT',
                    ],
                ],

            ],
            $params->getFilterList()
        );

        // Remove building:main filter and verify:
        $params->removeFilter('building:main');
        $this->assertEquals(
            [
                'building_label' => [
                    [
                        'value' => 'sub',
                        'displayText' => 'sub',
                        'field' => 'building',
                        'operator' => 'NOT',
                    ],
                ],

            ],
            $params->getFilterList()
        );

        // Remove the remaining building filter with removeAllFilters and verify:
        $params->removeAllFilters('building');
        $this->assertEquals([], $params->getFilterList());

        // Test that removeAllFilters without parameters removes everything:
        $params->addFilter('~format:bar');
        $params->addFilter('format:baz');
        $params->addFilter('-building:sub');
        $this->assertTrue($params->hasFilter('~format:bar'));
        $this->assertTrue($params->hasFilter('format:baz'));
        $this->assertTrue($params->hasFilter('-building:sub'));

        $params->removeAllFilters();
        $this->assertFalse($params->hasFilter('~format:bar'));
        $this->assertFalse($params->hasFilter('format:baz'));
        $this->assertFalse($params->hasFilter('-building:main'));
        $this->assertEquals([], $params->getFilterList());
    }

    /**
     * Test that getFacetLabel works as expected.
     *
     * @return void
     */
    public function testGetFacetLabel(): void
    {
        $params = $this->getMockParams();
        // If we haven't set up any facets yet, labels will be unrecognized:
        $this->assertEquals('unrecognized_facet_label', $params->getFacetLabel('foo'));

        // Now if we add a facet, we should get the label back:
        $params->addFacet('foo', 'foo_label');
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));

        // If we add a checkbox facet for a field that already has an assigned label,
        // we expect the checkbox label to override the field label:
        $params->addCheckboxFacet('foo:bar', 'checkbox_label');
        $this->assertEquals('checkbox_label', $params->getFacetLabel('foo', 'bar'));
        $this->assertEquals('foo_label', $params->getFacetLabel('foo', 'baz'));
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));
    }

    /**
     * Test that getFacetLabel works as expected with aliases.
     *
     * @return void
     */
    public function testGetFacetLabelWithAliases(): void
    {
        $params = $this->getMockParams();
        $this->setProperty(
            $params,
            'facetAliases',
            [
                'foo_old' => 'foo',
            ]
        );

        // If we haven't set up any facets yet, labels will be unrecognized:
        $this->assertEquals('unrecognized_facet_label', $params->getFacetLabel('foo'));

        // Now if we add a facet, we should get the label back:
        $params->addFacet('foo', 'foo_label');
        $this->assertEquals('foo_label', $params->getFacetLabel('foo_old'));

        // If we add a checkbox facet for a field that already has an assigned label,
        // we expect the checkbox label to override the field label:
        $params->addCheckboxFacet('foo:bar', 'checkbox_label');
        $this->assertEquals('checkbox_label', $params->getFacetLabel('foo_old', 'bar'));
    }

    /**
     * Test that we get a mock search class ID while testing.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Mock', $this->getMockParams()->getSearchClassId());
    }

    /**
     * Test that spelling replacement works as expected.
     *
     * @return void
     */
    public function testSpellingReplacements(): void
    {
        $params = $this->getMockParams();

        // Key test: word boundaries:
        $params->setBasicSearch('go good googler');
        $this->assertEquals(
            'run good googler',
            $params->getDisplayQueryWithReplacedTerm('go', 'run')
        );

        // Key test: replacement of wildcard queries:
        $params->setBasicSearch('oftamologie*');
        $this->assertEquals(
            'ophtalmologie*',
            $params->getDisplayQueryWithReplacedTerm(
                'oftamologie*',
                'ophtalmologie*'
            )
        );
    }

    /**
     * Test query adapters
     *
     * @return void
     */
    public function testQueryAdapters(): void
    {
        $params = $this->getMockParams();
        $params->setQuery(new Query('foo'));
        $params->setLimit(50);

        $minified = $this->createMock(minSO::class);
        $params->minify($minified);
        $this->assertEquals(
            [
                [
                    'l' => 'foo',
                    'i' => null,
                    's' => 'b',
                ],
            ],
            $minified->t
        );
        $this->assertEquals(50, $minified->scp['limit']);

        $customAdapter = $this->getMockBuilder(QueryAdapter::class)->getMock();
        $customAdapter->expects($this->once())
            ->method('minify')
            ->willReturn('CUSTOM');
        $params->setQueryAdapter($customAdapter);
        $params->minify($minified);
        $this->assertEquals('CUSTOM', $minified->t);
    }
}
