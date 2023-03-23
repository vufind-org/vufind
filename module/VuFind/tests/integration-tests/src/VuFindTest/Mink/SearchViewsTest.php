<?php

/**
 * Mink test class for search views.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2023.
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
 * Mink test class for search views.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class SearchViewsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that AJAX availability status is working in grid view.
     *
     * @return void
     */
    public function testGridAjaxStatus()
    {
        $this->changeConfigs(
            ['searches' => ['Views' => ['list' => 'List', 'grid' => 'Grid']]]
        );

        // Search for a known record:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:testsample1');
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);

        // Switch to grid view:
        $this->clickCss($page, '.view-buttons a[title="Switch view to Grid"]');
        $this->waitForPageLoad($page);

        // Check for sample driver location/call number in output (this will
        // only appear after AJAX returns):
        $this->unFindCss($page, '.ajax-availability');
        $this->assertEquals(
            'Available',
            $this->findCss($page, '.grid-body .result-formats.status .label.label-success')->getText()
        );
    }
}
