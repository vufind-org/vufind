<?php

/**
 * Mink test class for visualization view.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * Mink test class for visualization view.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class VisualizationTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Config overrides for visual facets.
     *
     * @var array
     */
    protected $visualConfig = [
        'searches' => [
            'General' => [
                'default_top_recommend' => ['VisualFacets'],
            ],
            'Views' => ['list' => 'List', 'visual' => 'Visual'],
        ],
    ];

    /**
     * Run the basic visualization test procedure; this allows us to do the same
     * checks in multiple configuration contexts.
     *
     * @return void
     */
    protected function doVisualizationCheck(): void
    {
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl()
            . '/Search/Results?filter[]=building%3A"journals.mrc"&view=visual'
        );
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $text = $this->findCssAndGetText($page, '#visualResults');
        // Confirm that some content has been dynamically loaded into the
        // visualization area:
        $this->assertStringContainsString('A - General Works', $text);
    }

    /**
     * Test that visualization results display correctly.
     *
     * @return void
     */
    public function testVisualization(): void
    {
        $this->changeConfigs($this->visualConfig);
        $this->doVisualizationCheck();
    }

    /**
     * Test that visualization results display correctly even when no other
     * recommendation modules are active.
     *
     * @return void
     */
    public function testVisualizationWithoutSideFacets(): void
    {
        // ONLY set up visual facets, while removing all other configs!
        $this->changeConfigs($this->visualConfig, ['searches']);
        $this->doVisualizationCheck();
    }
}
