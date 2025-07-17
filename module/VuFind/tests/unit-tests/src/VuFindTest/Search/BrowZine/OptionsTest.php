<?php

/**
 * BrowZine Options Test
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

namespace VuFindTest\Search\BrowZine;

use VuFind\Search\BrowZine\Options;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * BrowZine Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use ConfigRelatedServicesTrait;

    /**
     * Test that the Options object returns correct data .
     *
     * @return void
     */
    public function testOptions(): void
    {
        $configMgr = $this->getMockConfigPluginManager(
            [
                'BrowZine' => [],
            ]
        );
        $options = new Options($configMgr);
        $this->assertEquals('browzine-search', $options->getSearchAction());
        $this->assertEquals(['relevance' => 'Relevance'], $options->getSortOptions());
        $this->assertFalse($options->getFacetListAction());
        $this->assertFalse($options->getAdvancedSearchAction());
        $this->assertFalse($options->getAdvancedSearchAction());
    }
}
