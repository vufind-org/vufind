<?php

/**
 * SolrPrefix autocomplete test class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Autocomplete;

use VuFind\Autocomplete\SolrPrefix;
use VuFind\Search\Results\PluginManager;
use VuFindTest\Feature\SearchObjectsTrait;

/**
 * SolrPrefix autocomplete test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SolrPrefixTest extends \PHPUnit\Framework\TestCase
{
    use SearchObjectsTrait;

    /**
     * Test check on order of operations.
     *
     * @return void
     */
    public function testSearchObjectValidation(): void
    {
        $handler = new SolrPrefix($this->createMock(PluginManager::class));
        $this->expectExceptionMessage('Please set configuration first.');
        $handler->getSuggestions('foo');
    }

    /**
     * Data provider for testGetSuggestions().
     *
     * @return array[]
     */
    public static function getSuggestionsProvider(): array
    {
        $autocompleteField = 'auto_str';
        $facetField = 'facet_str';
        return [
            'default limit' => [$autocompleteField, $facetField, "$autocompleteField:$facetField", 10],
            'non-default limit' => [$autocompleteField, $facetField, "$autocompleteField:$facetField:20", 20],
        ];
    }

    /**
     * Test setting up a full test scenario and running getSuggestions().
     *
     * @param string $autocompleteField Name of expected autocomplete field
     * @param string $facetField        Name of expected facet field
     * @param string $config            Configuration to test with
     * @param int    $expectedLimit     Expected limit value
     *
     * @return void
     *
     * @dataProvider getSuggestionsProvider
     */
    public function testGetSuggestions(
        string $autocompleteField,
        string $facetField,
        string $config,
        int $expectedLimit
    ): void {
        $filters = ['filter:1'];
        $options = $this->getMockOptions();
        $options->expects($this->once())->method('spellcheckEnabled')->with(false);
        $options->expects($this->once())->method('disableHighlighting');
        $params = $this->getMockParams($options);
        $params->expects($this->once())->method('setBasicSearch')->with("$autocompleteField:(foo   bar)");
        $params->expects($this->once())->method('setLimit')->with(0);
        $params->expects($this->once())->method('setFacetLimit')->with($expectedLimit);
        $params->expects($this->once())->method('addFilter')->with($filters[0]);
        $results = $this->getMockResults($params);
        $results->expects($this->once())->method('getResults');
        $facetList = [
            $facetField => [
                'list' => [
                    ['value' => '1'],
                    ['value' => '2'],
                    ['value' => '2'], // duplicate value to test deduplication
                    ['value' => '3'],
                    ['value' => '4'],
                ],
            ],
        ];
        $results->expects($this->once())->method('getFacetList')->willReturn($facetList);
        $map = ['Solr' => $results];
        $handler = new SolrPrefix($this->getMockResultsPluginManager($map));
        $handler->setConfig($config);
        $handler->addFilters($filters);
        $this->assertEquals(['1', '2', '3', '4'], array_values($handler->getSuggestions('foo(:)bar')));
    }
}
