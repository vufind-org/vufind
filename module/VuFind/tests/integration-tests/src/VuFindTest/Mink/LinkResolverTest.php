<?php

/**
 * Mink link resolver test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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

use Behat\Mink\Element\Element;

/**
 * Mink link resolver test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LinkResolverTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Get config.ini override settings for testing ILS functions.
     *
     * @param array $openUrlExtras Extra settings for the [OpenURL] section.
     *
     * @return array
     */
    public function getConfigIniOverrides($openUrlExtras = [])
    {
        return [
            'OpenURL' => $openUrlExtras + [
                'resolver' => 'demo',
                'embed' => '1',
                'url' => 'https://vufind.org/wiki',
            ],
        ];
    }

    /**
     * Set up the record page for OpenURL testing.
     *
     * @param array $openUrlExtras Extra settings for the [OpenURL] config section.
     * @param array $extraConfigs  Top-level config.ini overrides
     *
     * @return Element
     */
    protected function setupRecordPage($openUrlExtras = [], $extraConfigs = [])
    {
        // Set up configs
        $this->changeConfigs(
            [
                'config' =>
                    $extraConfigs + $this->getConfigIniOverrides($openUrlExtras),
            ]
        );

        // Search for a known record:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Record/testsample1');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Click an OpenURL on the page and assert the expected results.
     *
     * @param Element $page  Current page object
     * @param bool    $click Should we click the link (true), or is it autoloading?
     *
     * @return void
     */
    protected function assertOpenUrl(Element $page, $click = true)
    {
        // Click the OpenURL link:
        if ($click) {
            $this->clickCss($page, '.fulltext');
        }

        // Confirm that the expected fake demo driver data is there:
        $this->waitForPageLoad($page);
        $electronic = $this->findCss($page, 'a.access-open');
        $this->assertEquals('Electronic', $electronic->getText());
        $this->assertEquals(
            'Electronic fake2 General notes Authentication notes',
            $electronic->getParent()->getText()
        );
        $openUrl = 'url_ver=Z39.88-2004&ctx_ver=Z39.88-2004'
            . '&ctx_enc=info%3Aofi%2Fenc%3AUTF-8'
            . '&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator'
            . '&rft.title=Journal+of+rational+emotive+therapy+%3A+the+journal+'
            . 'of+the+Institute+for+Rational-Emotive+Therapy.'
            . '&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Adc&rft.creator='
            . '&rft.pub=The+Institute%2C&rft.format=Journal'
            . '&rft.language=English&rft.issn=0748-1985';
        $this->assertEquals(
            'https://vufind.org/wiki?' . $openUrl . '#electronic',
            $electronic->getAttribute('href')
        );

        $print = $this->findCss($page, 'a.access-unknown');
        $this->assertEquals('Print', $print->getText());
        $this->assertEquals(
            'Print fake1 General notes',
            $print->getParent()->getText()
        );
        $this->assertEquals(
            'https://vufind.org/wiki?' . $openUrl . '#print',
            $print->getAttribute('href')
        );
    }

    /**
     * Test a link in the search results (default behavior, click required).
     *
     * @return void
     */
    public function testLinkInSearchResults()
    {
        // Set up configs
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
            ]
        );

        // Search for a known record:
        $page = $this->getSearchHomePage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:testsample1');
        $this->clickCss($page, '.btn.btn-primary');

        // Verify the OpenURL
        $this->assertOpenUrl($page);
    }

    /**
     * Test a link in the search results (optional autoloading enabled).
     *
     * @return void
     */
    public function testLinkInSearchResultsWithAutoloading()
    {
        // Set up configs
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(
                    ['embed_auto_load' => true]
                ),
            ]
        );

        // Search for a known record:
        $page = $this->performSearch('id:testsample1');

        // Verify the OpenURL
        $this->assertOpenUrl($page, false /* do not click link */);
    }

    /**
     * Test that link is missing from the record page by default.
     *
     * @return void
     */
    public function testLinkOnRecordPageWithDefaultConfig()
    {
        // By default, no OpenURL on record page:
        $page = $this->setupRecordPage();
        $this->assertNull($page->find('css', '.fulltext'));
    }

    /**
     * Test a link on the record page (in core metadata).
     *
     * @return void
     */
    public function testLinkOnRecordPageWithLinkInCore()
    {
        // By default, no OpenURL on record page:
        $page = $this->setupRecordPage(['show_in_record' => true]);
        $this->assertOpenUrl($page);
    }

    /**
     * Test a link on the record page (in holdings tab).
     *
     * @return void
     */
    public function testLinkOnRecordPageWithLinkInHoldings()
    {
        // By default, no OpenURL on record page:
        $page = $this->setupRecordPage(['show_in_holdings' => true]);
        $this->assertOpenUrl($page);
    }

    /**
     * Test a link on the record page (in holdings tab w/ AJAX loading).
     *
     * @return void
     */
    public function testLinkOnRecordPageWithLinkInHoldingsAndAjaxTabLoading()
    {
        // By default, no OpenURL on record page:
        $page = $this->setupRecordPage(
            ['show_in_holdings' => true],
            ['Site' => ['loadInitialTabWithAjax' => true]]
        );
        $this->assertOpenUrl($page);
    }
}
