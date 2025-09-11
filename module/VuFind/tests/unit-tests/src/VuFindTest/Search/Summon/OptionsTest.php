<?php

/**
 * Summon Search Object Options Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

namespace VuFindTest\Search\Summon;

use VuFind\Config\Config;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\Summon\Options;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Summon Search Object Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use ReflectionTrait;

    /**
     * Test getting facet list action.
     *
     * @return void
     */
    public function testGetFacetListAction(): void
    {
        $this->assertEquals('summon-facetlist', $this->getOptions()->getFacetListAction());
    }

    /**
     * Test getting search action.
     *
     * @return void
     */
    public function testGetSearchAction(): void
    {
        $this->assertEquals('summon-search', $this->getOptions()->getSearchAction());
    }

    /**
     * Test getting advanced search action.
     *
     * @return void
     */
    public function testGetAdvancedSearchAction(): void
    {
        $this->assertEquals('summon-advanced', $this->getOptions()->getAdvancedSearchAction());
    }

    /**
     * Test getTranslatedFacets().
     *
     * @return void
     */
    public function testGetTranslatedFacets(): void
    {
        $facets = ['foo', 'bar'];
        $config = ['Advanced_Facet_Settings' => ['translated_facets' => $facets]];
        $this->assertEquals(
            $facets,
            $this->getOptions($config)->getTranslatedFacets()
        );
    }

    /**
     * Test getSpecialAdvancedFacets().
     *
     * @return void
     */
    public function testGetSpecialAdvancedFacets(): void
    {
        $facets = 'foo,bar';
        $config = ['Advanced_Facet_Settings' => ['special_facets' => $facets]];
        $this->assertEquals(
            $facets,
            $this->getOptions($config)->getSpecialAdvancedFacets()
        );
    }

    /**
     * Test limit settings.
     *
     * @return void
     */
    public function testLimits(): void
    {
        $options = $this->getOptions(['General' => ['default_limit' => 10, 'limit_options' => '10,20']]);
        $this->assertEquals(10, $options->getDefaultLimit());
        $this->assertEquals([10, 20], $options->getLimitOptions());
    }

    /**
     * Test spelling and highlighting settings.
     *
     * @return void
     */
    public function testSpellingAndHighlighting(): void
    {
        $defaultOptions = $this->getOptions();
        $this->assertTrue($defaultOptions->spellcheckEnabled());
        $this->assertFalse($defaultOptions->highlightEnabled());
        $config = ['General' => ['highlighting' => true], 'Spelling' => ['enabled' => false]];
        $configuredOptions = $this->getOptions($config);
        $this->assertFalse($configuredOptions->spellcheckEnabled());
        $this->assertTrue($configuredOptions->highlightEnabled());
    }

    /**
     * Test default filters.
     *
     * @return void
     */
    public function testDefaultFilters(): void
    {
        $filters = ['foo', 'bar'];
        $config = ['General' => ['default_filters' => $filters]];
        $this->assertEquals($filters, $this->getOptions($config)->getDefaultFilters());
    }

    /**
     * Test result limit.
     *
     * @return void
     */
    public function testResultLimit(): void
    {
        $defaultOptions = $this->getOptions();
        $this->assertEquals(400, $this->getProperty($defaultOptions, 'resultLimit'));
        $config = ['General' => ['result_limit' => 5]];
        $this->assertEquals(5, $this->getProperty($this->getOptions($config), 'resultLimit'));
    }

    /**
     * Test handler configuration.
     *
     * @return void
     */
    public function testHandlerConfigs(): void
    {
        $config = [
            'Basic_Searches' => ['foo' => 'bar', 'baz' => 'xyzzy'],
            'Advanced_Searches' => ['afoo' => 'abar', 'abaz' => 'axyzzy'],
        ];
        $options = $this->getOptions($config);
        $this->assertEquals($config['Basic_Searches'], $options->getBasicHandlers());
        $this->assertEquals($config['Advanced_Searches'], $options->getAdvancedHandlers());
    }

    /**
     * Test sort options.
     *
     * @return void
     */
    public function testSort(): void
    {
        $config = [
            'Sorting' => ['foo' => 'bar', 'baz' => 'xyzzy'],
            'General' => ['default_sort' => 'foo'],
            'DefaultSortingByType' => ['type' => 'baz'],
        ];
        $options = $this->getOptions($config);
        $this->assertEquals($config['Sorting'], $options->getSortOptions());
        $this->assertEquals('baz', $options->getDefaultSortByHandler('type'));
        $this->assertEquals('foo', $options->getDefaultSortByHandler('test'));
    }

    /**
     * Test empty search relevance override.
     *
     * @return void
     */
    public function testEmptySearchRelevanceOverride(): void
    {
        $options = $this->getOptions(['General' => ['empty_search_relevance_override' => 'foo']]);
        $this->assertEquals('foo', $options->getEmptySearchRelevanceOverride());
    }

    /**
     * Test list views.
     *
     * @return void
     */
    public function testListView(): void
    {
        $config = ['List' => ['view' => 'foo']];
        $this->assertEquals('foo', $this->getOptions($config)->getListViewOption());
    }

    /**
     * Get Params object
     *
     * @param array $config Configuration to get from config manager
     *
     * @return Options
     */
    protected function getOptions(array $config = []): Options
    {
        $mockConfigManager = $this->createMock(ConfigManagerInterface::class);
        $configObj = new Config($config);
        $mockConfigManager->method('getConfigObject')->willReturn($configObj);
        $mockConfigManager->method('getConfigArray')->willReturn($config);
        return new Options($mockConfigManager);
    }
}
