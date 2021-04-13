<?php
/**
 * Mink test class for visualization view.
 *
 * PHP version 7
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
 * @retry    4
 */
class VisualizationTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that combined results work in mixed AJAX/non-AJAX mode.
     *
     * @return void
     */
    public function testVisualization()
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'default_top_recommend' => ['VisualFacets'],
                    ],
                    'Views' => ['list' => 'List', 'visual' => 'Visual'],
                ]
            ]
        );
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl()
            . '/Search/Results?filter[]=building%3A"journals.mrc"&view=visual'
        );
        $page = $session->getPage();
        $this->snooze();
        $text = $this->findCss($page, '#visualResults')->getText();
        // Confirm that some content has been dynamically loaded into the
        // visualization area:
        $this->assertStringContainsString('A - General Works', $text);
    }
}
