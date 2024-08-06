<?php

/**
 * Test functionality of the home page facets.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Test functionality of the home page facets.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HomePageFacetsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that normal facets work properly.
     *
     * @return void
     */
    public function testNormalFacets()
    {
        $page = $this->getSearchHomePage();
        $this->waitForPageLoad($page);
        $this->assertEquals('A - General Works', $this->findCssAndGetText($page, '.home-facet.callnumber-first a'));
        $this->clickCss($page, '.home-facet.callnumber-first a');
        $this->waitForPageLoad($page);
        $this->assertStringEndsWith(
            'Search/Results?filter%5B%5D=callnumber-first%3A%22A+-+General+Works%22',
            $this->getMinkSession()->getCurrentUrl()
        );
    }

    /**
     * Test that hierarchy facets work properly.
     *
     * @return void
     */
    public function testHierarchicalFacets()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy',
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv',
                    ],
                    'HomePage' => [
                        'hierarchical_facet_str_mv' => 'Hierarchical',
                    ],
                ],
            ]
        );
        $page = $this->getSearchHomePage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'level1a level1z',
            $this->findCssAndGetText($page, '.home-facet.hierarchical_facet_str_mv .home-facet-list')
        );
        $this->clickCss($page, '.home-facet.hierarchical_facet_str_mv .facet');
        $this->waitForPageLoad($page);
        $this->assertStringEndsWith(
            '/Search/Results?filter%5B%5D=hierarchical_facet_str_mv%3A%220%2Flevel1a%2F%22',
            $this->getMinkSession()->getCurrentUrl()
        );
    }
}
