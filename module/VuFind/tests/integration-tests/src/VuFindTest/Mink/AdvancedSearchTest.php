<?php

/**
 * Mink test class to test advanced search.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014.
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;
use Behat\Mink\Session;
use VuFindTest\Feature\CacheManagementTrait;
use VuFindTest\Feature\SearchFacetFilterTrait;

/**
 * Mink test class to test advanced search.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AdvancedSearchTest extends \VuFindTest\Integration\MinkTestCase
{
    use CacheManagementTrait;
    use SearchFacetFilterTrait;

    /**
     * Go to the advanced search page.
     *
     * @param Session $session Mink session
     *
     * @return Element
     */
    protected function goToAdvancedSearch(Session $session): Element
    {
        $path = '/Search/Advanced';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Test persistent
     *
     * @return void
     */
    public function testPersistent(): void
    {
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);
        // Submit empty search form
        $this->findCss($page, '[type=submit]')->press();
        // Test edit search
        $links = $page->findAll('css', '.adv_search_links a');
        $isAdv = false;
        foreach ($links as $link) {
            if (
                $this->checkVisibility($link)
                && $link->getHtml() == 'Edit this Advanced Search'
            ) {
                $isAdv = true;
                break;
            }
        }
        $this->assertTrue($isAdv);
    }

    /**
     * Find the "edit advanced search link" and click it.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function editAdvancedSearch(Element $page)
    {
        $links = $page->findAll('css', '.adv_search_links a');
        foreach ($links as $link) {
            if (
                $this->checkVisibility($link)
                && $link->getHtml() == 'Edit this Advanced Search'
            ) {
                $link->click();
                break;
            }
        }
    }

    /**
     * Test that the advanced search form is operational.
     *
     * @return void
     */
    public function testAdvancedSearchForm()
    {
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);

        // Add a group
        $session->executeScript('addGroup()');
        $this->findCss($page, '#group1');

        // Add a search term
        $session->executeScript('addSearch(0)'); // add_search_link_0 click
        $this->findCss($page, '#search0_3');
        // No visible x next to lonely search term
        $this->findCss($page, '#search1_0 .adv-term-remove.hidden');
        // Add a search term in another group
        $session->executeScript('addSearch(1)'); // add_search_link_1 click
        $this->findCss($page, '#search1_1');
        // Visible x next to lonely search term
        $this->findCss($page, '#search1_0 .adv-term-remove:not(.hidden)');

        // Enter search for bride of the tomb
        $this->findCssAndSetValue($page, '#search_lookfor0_0', 'bride');
        $this->findCssAndSetValue($page, '#search_lookfor0_1', 'tomb');
        $this->findCss($page, '#search_type0_1')->selectOption('Title');
        $this->findCssAndSetValue($page, '#search_lookfor0_2', 'garbage');
        $this->findCssAndSetValue($page, '#search_lookfor0_3', '1883');
        $this->findCss($page, '#search_type0_3')->selectOption('year');
        $this->findCssAndSetValue($page, '#search_lookfor1_0', 'miller');

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search
        $this->assertEquals(
            '(All Fields:bride AND Title:tomb AND All Fields:garbage AND Year of Publication:1883) AND '
            . '(All Fields:miller)',
            $this->findCssAndGetHtml($page, '.adv_search_terms strong')
        );

        // Test edit search
        $this->editAdvancedSearch($page);
        $this->assertEquals('bride', $this->findCssAndGetValue($page, '#search_lookfor0_0'));
        $this->assertEquals('tomb', $this->findCssAndGetValue($page, '#search_lookfor0_1'));
        $this->assertEquals('Title', $this->findCssAndGetValue($page, '#search_type0_1'));
        $this->assertEquals('garbage', $this->findCssAndGetValue($page, '#search_lookfor0_2'));
        $this->assertEquals('1883', $this->findCssAndGetValue($page, '#search_lookfor0_3'));
        $this->assertEquals('year', $this->findCssAndGetValue($page, '#search_type0_3'));
        $this->assertEquals('miller', $this->findCssAndGetValue($page, '#search_lookfor1_0'));

        // Term removal
        $session->executeScript('deleteSearch(0, 2)'); // search0_2 x click
        $this->assertNull($page->findById('search0_3'));
        // Terms collapsing up
        $this->assertEquals('1883', $this->findCssAndGetValue($page, '#search_lookfor0_2'));
        $this->assertEquals('year', $this->findCssAndGetValue($page, '#search_type0_2'));

        // Group removal
        $session->executeScript('deleteGroup(0)');

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search (second group only)
        $this->assertEquals(
            '(All Fields:miller)',
            $this->findCssAndGetHtml($page, '.adv_search_terms strong')
        );

        // Test edit search (modified search is restored properly)
        $this->editAdvancedSearch($page);
        $this->assertEquals('miller', $this->findCssAndGetValue($page, '#search_lookfor0_0'));

        // Clear test
        $multiSel = $this->findCss($page, '#limit_callnumber-first');
        $multiSel->selectOption('~callnumber-first:"A - General Works"', true);
        $multiSel->selectOption('~callnumber-first:"D - World History"', true);
        $this->assertCount(2, $multiSel->getValue());

        $this->findCss($page, '.adv-submit .clear-btn')->press();
        $this->assertEquals('', $this->findCssAndGetValue($page, '#search_lookfor0_0'));
        $this->assertCount(0, $multiSel->getValue());
    }

    /**
     * Test that the advanced search form works correctly with a NOT group combined
     * with another group.
     *
     * @return void
     */
    public function testAdvancedMultiGroupSearchWithNotOperator()
    {
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);

        // Add a group
        $session->executeScript('addGroup()');
        $this->findCss($page, '#group1');

        // Enter search criteria
        $this->findCssAndSetValue($page, '#search_lookfor0_0', 'building:"journals.mrc"');
        $this->findCss($page, '#search_type1_0')->selectOption('Title');
        $this->findCssAndSetValue($page, '#search_lookfor1_0', 'rational');
        $this->findCss($page, '#search_bool1')->selectOption('NOT');

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search and result count
        $this->assertEquals(
            '(All Fields:building:"journals.mrc") NOT ((Title:rational))',
            $this->findCssAndGetHtml($page, '.adv_search_terms strong')
        );
        $this->assertMatchesRegularExpression(
            '/Showing 1 - 7 results of 7/',
            trim($this->findCssAndGetText($page, '.search-stats'))
        );
    }

    /**
     * Test that a pure NOT search gives us results.
     *
     * @return void
     */
    public function testAdvancedSingleGroupSearchWithNotOperator()
    {
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);

        // Enter search criteria
        $this->findCss($page, '#search_type0_0')->selectOption('Title');
        $this->findCssAndSetValue($page, '#search_lookfor0_0', 'rational');
        $this->findCss($page, '#search_bool0')->selectOption('NOT');

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search and result count
        $this->assertEquals(
            '() NOT ((Title:rational))',
            $this->findCssAndGetHtml($page, '.adv_search_terms strong')
        );
        preg_match(
            '/Showing \d+ - \d+ results of (\d+)/',
            trim($this->findCssAndGetText($page, '.search-stats')),
            $matches
        );
        $this->assertTrue($matches[1] > 0);
    }

    /**
     * Test default limit sorting
     *
     * @return void
     */
    public function testDefaultLimitSorting(): void
    {
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);
        // By default, everything is sorted alphabetically:
        $this->assertEquals(
            'Article Book Book Chapter Conference Proceeding eBook Electronic Journal Microfilm Serial',
            $this->findCssAndGetText($page, '#limit_format')
        );
        // Change the language:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
        // Still sorted alphabetically, even though in a different language:
        $this->assertEquals(
            'Artikel Buch Buchkapitel E-Book Elektronisch Mikrofilm Schriftenreihe Tagungsbericht Zeitschrift',
            $this->findCssAndGetText($page, '#limit_format')
        );
    }

    /**
     * Test limit sorting with order override
     *
     * @return void
     */
    public function testLimitSortingWithOrderOverride(): void
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Advanced_Settings' => [
                        'limitOrderOverride' => [
                            'format' => 'Book::eBook',
                        ],
                    ],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);
        // By default, everything is sorted alphabetically:
        $this->assertEquals(
            'Book eBook Article Book Chapter Conference Proceeding Electronic Journal Microfilm Serial',
            $this->findCssAndGetText($page, '#limit_format')
        );
        // Change the language:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
        // Still sorted alphabetically, even though in a different language:
        $this->assertEquals(
            'Buch E-Book Artikel Buchkapitel Elektronisch Mikrofilm Schriftenreihe Tagungsbericht Zeitschrift',
            $this->findCssAndGetText($page, '#limit_format')
        );
    }

    /**
     * Data provider for testHierarchicalFacetsFilters
     *
     * @return array
     */
    public static function hierarchicalFacetFiltersProvider(): array
    {
        return [
            'default sort' => [
                null,
                null,
                'top',
            ],
            'top level alphabetical' => [
                'top',
                'all',
                'top',
            ],
            'all alphabetical' => [
                'all',
                'top',
                'all',
            ],
            'count' => [
                'count',
                'all',
                'count',
            ],
            'default sort (inherited)' => [
                null,
                'top',
                'top',
            ],
            'top level alphabetical (inherited)' => [
                null,
                'top',
                'top',
            ],
            'all alphabetical (inherited)' => [
                null,
                'all',
                'all',
            ],
            'count (inherited)' => [
                null,
                'count',
                'count',
            ],
        ];
    }

    /**
     * Test that hierarchical facet filters work properly.
     *
     * @param ?string $sort         Sort option
     * @param ?string $defaultSort  Default sort option
     * @param string  $expectedSort Expected sort order of options
     *
     * @dataProvider hierarchicalFacetFiltersProvider
     *
     * @return void
     */
    public function testHierarchicalFacetsFilters(?string $sort, ?string $defaultSort, string $expectedSort): void
    {
        $config = [
            'facets' => [
                'Results' => [
                    'hierarchical_facet_str_mv' => 'hierarchy',
                ],
                'SpecialFacets' => [
                    'hierarchical[]' => 'hierarchical_facet_str_mv',
                ],
                'Advanced' => [
                    'hierarchical_facet_str_mv' => 'Hierarchy',
                ],
                'Advanced_Settings' => [
                    'translated_facets[]' => 'hierarchical_facet_str_mv:Facets',
                ],
            ],
        ];

        if (null !== $sort) {
            $config['facets']['Advanced_Settings']['hierarchicalFacetSortOptions']['hierarchical_facet_str_mv'] = $sort;
        }
        if (null !== $defaultSort) {
            $config['facets']['SpecialFacets']['hierarchicalFacetSortOptions']['hierarchical_facet_str_mv']
                = $defaultSort;
        }
        $this->changeConfigs($config + $this->getCacheClearPermissionConfig());

        // Clear object cache to ensure clean state:
        $this->clearObjectCache();

        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);

        // Check hierarchy filter:
        $filter = $this->findCss($page, '#limit_hierarchical_facet_str_mv');
        $options = [];
        foreach ($filter->findAll('css', 'option') as $option) {
            $options[$option->getValue()] = $option->getHtml();
        }
        $expectedOptions = $this->getExpectedHierarchicalFacetOptions($expectedSort);
        $this->assertSame($expectedOptions, $options);

        // Select second options, do a search and verify that the filter is active:
        $this->clickCss($page, '#limit_hierarchical_facet_str_mv option', null, 1);
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);
        $expectedValue = $this->getExpectedHierarchicalFacetFilterText($expectedSort, 1);
        $this->assertAppliedFilter($page, 0, 'hierarchy', $expectedValue);
    }
}
