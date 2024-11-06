<?php

/**
 * Mink test class for combined search.
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
 * Mink test class for combined search.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CombinedSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Get config settings for combined.ini.
     *
     * @return array
     */
    protected function getCombinedIniOverrides(): array
    {
        return [
            'Solr:one' => [
                'label' => 'Solr One',
                'hiddenFilter' => 'building:journals.mrc',
            ],
            'Solr:two' => [
                'label' => 'Solr Two',
                'hiddenFilter' => 'building:weird_ids.mrc',
            ],
        ];
    }

    /**
     * Start a session, perform a combined search, and return the resulting page.
     *
     * @param string $query Combined search query to perform.
     *
     * @return Element
     */
    protected function performCombinedSearch(string $query): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Combined');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')->setValue($query);
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Several different methods perform the same query against different
     * configurations of the combined feature; this support method makes a
     * standard set of assertions against the final results.
     *
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function assertResultsForDefaultQuery(Element $page): void
    {
        $expectedResults = [
            '#combined_Solr____one' => 'Journal of rational emotive therapy : '
                . 'the journal of the Institute for Rational-Emotive Therapy.',
            '#combined_Solr____two' => 'Pluses and Minuses of Pluses and Minuses',
        ];
        foreach ($expectedResults as $container => $title) {
            $this->assertEquals(
                $title,
                $this->findCssAndGetText($page, "$container a.title")
            );
            // Check for sample driver location/call number in output (this will
            // only appear after AJAX returns):
            $this->unFindCss($page, '.callnumber.ajax-availability');
            $this->unFindCss($page, '.location.ajax-availability');
            $this->assertEquals(
                'A1234.567',
                $this->findCssAndGetText($page, "$container .callnumber")
            );
            $this->assertEquals(
                '3rd Floor Main Library',
                $this->findCssAndGetText($page, "$container .location")
            );
        }
    }

    /**
     * Test that combined results work in non-AJAX mode.
     *
     * @return void
     */
    public function testCombinedSearchResults(): void
    {
        $this->changeConfigs(
            ['combined' => $this->getCombinedIniOverrides()],
            ['combined']
        );
        $page = $this->performCombinedSearch('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->unFindCss($page, '.fa-spinner.icon--spin');
        $this->assertResultsForDefaultQuery($page);
    }

    /**
     * Data provider for different combinations of AJAX columns
     *
     * @return array
     */
    public static function ajaxCombinationsProvider(): array
    {
        return [
            'no ajax' => [false, false],
            'left ajax' => [true, false],
            'right ajax' => [false, true],
            'all ajax' => [true, true],
        ];
    }

    /**
     * Test that combined results contain valid author links with appropriate filtering.
     *
     * @param bool $leftAjax  Should left column load via AJAX?
     * @param bool $rightAjax Should right column load via AJAX?
     *
     * @return void
     *
     * @dataProvider ajaxCombinationsProvider
     */
    public function testCombinedSearchResultsAuthorLinks(bool $leftAjax, bool $rightAjax): void
    {
        $config = $this->getCombinedIniOverrides();
        // Default configuration does not have authors in both columns; switch to a
        // different data set that will let us test authors:
        $config['Solr:one']['hiddenFilter'] = 'building:author_relators.mrc';
        $config['Solr:one']['ajax'] = $leftAjax;
        $config['Solr:two']['ajax'] = $rightAjax;
        $this->changeConfigs(
            ['combined' => $config],
            ['combined']
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Combined');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:"0001732009-1" OR id:"theplus+andtheminus-"');
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->unFindCss($page, '.fa-spinner.icon--spin');
        // The author link in each column should have an appropriate hidden filter applied:
        $this->assertStringContainsString(
            'hiddenFilters%5B%5D=building%3A%22author_relators.mrc%22',
            $this->findCss($page, '#combined_Solr____one .result-author')->getAttribute('href')
        );
        $this->assertStringContainsString(
            'hiddenFilters%5B%5D=building%3A%22weird_ids.mrc%22',
            $this->findCss($page, '#combined_Solr____two .result-author')->getAttribute('href')
        );
    }

    /**
     * Test that combined results work in AJAX mode.
     *
     * @return void
     */
    public function testCombinedSearchResultsAllAjax(): void
    {
        $config = $this->getCombinedIniOverrides();
        $config['Solr:one']['ajax'] = true;
        $config['Solr:two']['ajax'] = true;
        $this->changeConfigs(
            ['combined' => $config],
            ['combined']
        );
        $page = $this->performCombinedSearch('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->assertResultsForDefaultQuery($page);
    }

    /**
     * Test that combined results work in mixed AJAX mode when Explain is turned on.
     *
     * @return void
     */
    public function testCombinedSearchResultsMixedAjaxWithExplain(): void
    {
        $config = $this->getCombinedIniOverrides();
        $config['Solr:two']['ajax'] = true;
        $this->changeConfigs(
            ['combined' => $config, 'searches' => ['Explain' => ['enabled' => true]]],
            ['combined']
        );
        $page = $this->performCombinedSearch('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->assertResultsForDefaultQuery($page);
    }

    /**
     * Test that combined results work in mixed AJAX/non-AJAX mode.
     *
     * @return void
     */
    public function testCombinedSearchResultsMixedAjax(): void
    {
        $config = $this->getCombinedIniOverrides();
        $config['Solr:one']['ajax'] = true;
        $this->changeConfigs(
            ['combined' => $config],
            ['combined']
        );
        $page = $this->performCombinedSearch('id:"testsample1" OR id:"theplus+andtheminus-"');
        $this->assertResultsForDefaultQuery($page);
    }

    /**
     * Test that DOI results work in various AJAX/non-AJAX modes.
     *
     * @param bool $leftAjax  Should left column load via AJAX?
     * @param bool $rightAjax Should right column load via AJAX?
     *
     * @return void
     *
     * @dataProvider ajaxCombinationsProvider
     */
    public function testCombinedSearchResultsMixedAjaxDOIs(bool $leftAjax, bool $rightAjax): void
    {
        $config = $this->getCombinedIniOverrides();
        $config['Solr:one']['ajax'] = $leftAjax;
        $config['Solr:one']['hiddenFilter'] = 'id:fakedoi1';
        $config['Solr:two']['ajax'] = $rightAjax;
        $config['Solr:two']['hiddenFilter'] = 'id:fakedoi2';
        $this->changeConfigs(
            [
                'combined' => $config,
                'config' => [
                    'DOI' => [
                        'resolver' => 'Demo',
                    ],
                ],
            ],
            ['combined']
        );
        $page = $this->performCombinedSearch('*:*');
        // Whether the combined column was loaded inline or via AJAX, it should
        // now include a DOI link:
        $this->assertStringStartsWith(
            'Demonstrating DOI link for 10.1234/FAKETYFAKE1',
            $this->findCssAndGetText($page, '#combined_Solr____one .doiLink a')
        );
        $this->assertStringStartsWith(
            'Demonstrating DOI link for 10.1234/FAKETYFAKE2',
            $this->findCssAndGetText($page, '#combined_Solr____two .doiLink a')
        );
    }
}
