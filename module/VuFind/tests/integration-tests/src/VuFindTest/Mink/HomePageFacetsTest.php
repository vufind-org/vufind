<?php

/**
 * Test functionality of the home page facets.
 *
 * PHP version 7
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
 * @retry    4
 */
class HomePageFacetsTest extends \VuFindTest\Integration\MinkTestCase
{
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
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $container = $this->findCss($page, "#facet_hierarchical_facet_str_mv");
        $this->assertEquals('level1a level1z', $container->getText());
    }
}
