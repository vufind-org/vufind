<?php

/**
 * Record versions test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * Record versions test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecordVersionsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Run test procedure for record versions.
     *
     * @param string $path Path to search from.
     *
     * @return void
     */
    protected function runVersionsTest($path)
    {
        // Search for an item known to have other versions in test data:
        $page = $this->performSearch('id:0001732009-0', null, $path);

        // Confirm that "other versions" link exists:
        $this->assertEquals(
            'Show other versions (3)',
            $this->findCssAndGetText($page, 'div.record-versions a')
        );

        // Click on the "other versions" link:
        $this->clickCss($page, 'div.record-versions a');

        // Confirm that we've landed on an other versions tab:
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Other Versions (3)',
            $this->findCssAndGetText($page, $this->activeRecordTabSelector)
        );

        // Click the "see all versions" link:
        $this->clickCss($page, 'div.search-controls a.more-link');

        // Confirm that all four versions are now visible in the versions display:
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Versions - The collected letters of Thomas and Jane Welsh Carlyle :',
            $this->findCssAndGetText($page, 'ul.breadcrumb li.active')
        );
        $results = $page->findAll('css', '.result');
        $this->assertCount(4, $results);
    }

    /**
     * Test accessing a record with multiple versions.
     *
     * @return void
     */
    public function testVersions()
    {
        $this->runVersionsTest('/Search');
    }

    /**
     * Test accessing a record with multiple versions via secondary search.
     *
     * @return void
     */
    public function testVersionsInSearch2()
    {
        $this->runVersionsTest('/Search2');
    }

    /**
     * Test that results scripts are properly initialized for the versions tab.
     *
     * @return void
     */
    public function testVersionsTabInit()
    {
        // Enable QRCodes:
        $extraConfigs = [
            'config' => [
                'QRCode' => [
                    'showInResults' => true,
                ],
            ],
        ];
        $this->changeConfigs($extraConfigs);
        $session = $this->getMinkSession();
        // Go to the tab by clicking it so that any global init in common.js doesn't
        // mask issues:
        $session->visit($this->getVuFindUrl() . '/Record/0001732009-0');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->clickCss($page, '#record-tab-versions a');
        $this->waitForPageLoad($page);
        // Click the QR code link and verify that the image gets added dynamically:
        $this->clickCss($page, '.result-links .qrcodeLink');
        $this->findCss($page, '.result-links .qrcode img');
    }

    /**
     * Confirm that links operate differently when the record versions tab is
     * disabled but other version settings are enabled.
     *
     * @return void
     */
    public function testDisabledVersionsTab()
    {
        // Disable versions tab:
        $extraConfigs = [
            'RecordTabs' => [
                'VuFind\RecordDriver\SolrMarc' => [
                    'tabs[Versions]' => false,
                ],
            ],
        ];
        $this->changeConfigs($extraConfigs);
        // Search for an item known to have other versions in test data:
        $page = $this->performSearch('id:0001732009-0', null, '/Search');

        // Confirm that "all versions" link exists:
        $this->assertEquals(
            'Show all versions (4)',
            $this->findCssAndGetText($page, 'div.record-versions a')
        );

        // Click on the "all versions" link:
        $this->clickCss($page, 'div.record-versions a');

        // Confirm that we have jumped directly to the "show all versions" screen
        // and that all four versions are now visible in the versions display:
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Versions - The collected letters of Thomas and Jane Welsh Carlyle :',
            $this->findCssAndGetText($page, 'ul.breadcrumb li.active')
        );
        $results = $page->findAll('css', '.result');
        $this->assertCount(4, $results);
    }

    /**
     * Confirm that version controls do not appear in search results when the setting
     * is disabled.
     *
     * @return void
     */
    public function testDisabledVersions()
    {
        // Disable versions:
        $extraConfigs = [
            'searches' => [
                'General' => [
                    'display_versions' => false,
                ],
            ],
        ];
        $this->changeConfigs($extraConfigs);

        // Search for an item known to have other versions in test data:
        $page = $this->performSearch('id:0001732009-0');

        // Click on the "other versions" link:
        $this->assertCount(
            0,
            $page->findAll('css', 'div.record-versions a')
        );
    }
}
