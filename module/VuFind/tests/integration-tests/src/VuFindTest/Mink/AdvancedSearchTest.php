<?php
/**
 * Mink test class to test advanced search.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
use Behat\Mink\Session;

/**
 * Mink test class to test advanced search.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class AdvancedSearchTest extends \VuFindTest\Integration\MinkTestCase
{
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
            if ($this->checkVisibility($link)
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
            if ($this->checkVisibility($link)
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
    public function testAdvancedSearch()
    {
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);

        // Add a group
        $session->executeScript("addGroup()");
        $this->findCss($page, '#group1');

        // Add a search term
        $session->executeScript("addSearch(0)"); // add_search_link_0 click
        $this->findCss($page, '#search0_3');
        // No visible x next to lonely search term
        $this->findCss($page, '#search1_0 .adv-term-remove.hidden');
        // Add a search term in another group
        $session->executeScript("addSearch(1)"); // add_search_link_1 click
        $this->findCss($page, '#search1_1');
        // Visible x next to lonely search term
        $this->findCss($page, '#search1_0 .adv-term-remove:not(.hidden)');

        // Enter search for bride of the tomb
        $this->findCss($page, '#search_lookfor0_0')->setValue('bride');
        $this->findCss($page, '#search_lookfor0_1')->setValue('tomb');
        $this->findCss($page, '#search_type0_1')->selectOption('Title');
        $this->findCss($page, '#search_lookfor0_2')->setValue('garbage');
        $this->findCss($page, '#search_lookfor0_3')->setValue('1883');
        $this->findCss($page, '#search_type0_3')->selectOption('year');
        $this->findCss($page, '#search_lookfor1_0')->setValue('miller');

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search
        $this->assertEquals(
            '(All Fields:bride AND Title:tomb AND All Fields:garbage AND Year of Publication:1883) AND (All Fields:miller)',
            $this->findCss($page, '.adv_search_terms strong')->getHtml()
        );

        // Test edit search
        $this->editAdvancedSearch($page);
        $this->assertEquals('bride', $this->findCss($page, '#search_lookfor0_0')->getValue());
        $this->assertEquals('tomb', $this->findCss($page, '#search_lookfor0_1')->getValue());
        $this->assertEquals('Title', $this->findCss($page, '#search_type0_1')->getValue());
        $this->assertEquals('garbage', $this->findCss($page, '#search_lookfor0_2')->getValue());
        $this->assertEquals('1883', $this->findCss($page, '#search_lookfor0_3')->getValue());
        $this->assertEquals('year', $this->findCss($page, '#search_type0_3')->getValue());
        $this->assertEquals('miller', $this->findCss($page, '#search_lookfor1_0')->getValue());

        // Term removal
        $session->executeScript("deleteSearch(0, 2)"); // search0_2 x click
        $this->assertNull($page->findById('search0_3'));
        // Terms collapsing up
        $this->assertEquals('1883', $this->findCss($page, '#search_lookfor0_2')->getValue());
        $this->assertEquals('year', $this->findCss($page, '#search_type0_2')->getValue());

        // Group removal
        $session->executeScript("deleteGroup(0)");

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search (second group only)
        $this->assertEquals(
            '(All Fields:miller)',
            $this->findCss($page, '.adv_search_terms strong')->getHtml()
        );

        // Test edit search (modified search is restored properly)
        $this->editAdvancedSearch($page);
        $this->assertEquals('miller', $this->findCss($page, '#search_lookfor0_0')->getValue());

        // Clear test
        $multiSel = $this->findCss($page, '#limit_callnumber-first');
        $multiSel->selectOption('~callnumber-first:"A - General Works"', true);
        $multiSel->selectOption('~callnumber-first:"D - World History"', true);
        $this->assertEquals(2, count($multiSel->getValue()));

        $this->findCss($page, '.adv-submit .clear-btn')->press();
        $this->assertEquals('', $this->findCss($page, '#search_lookfor0_0')->getValue());
        $this->assertEquals(0, count($multiSel->getValue()));
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
            'Book Book Chapter Conference Proceeding eBook Electronic Journal Microfilm',
            $this->findCss($page, "#limit_format")->getText()
        );
        // Change the language:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
        // Still sorted alphabetically, even though in a different language:
        $this->assertEquals(
            'Buch Buchkapitel E-Book Elektronisch Mikrofilm Tagungsbericht Zeitschrift',
            $this->findCss($page, "#limit_format")->getText()
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
                            'format' => 'Book::eBook'
                        ]
                    ]
                ]
            ]
        );
        $session = $this->getMinkSession();
        $page = $this->goToAdvancedSearch($session);
        // By default, everything is sorted alphabetically:
        $this->assertEquals(
            'Book eBook Book Chapter Conference Proceeding Electronic Journal Microfilm',
            $this->findCss($page, "#limit_format")->getText()
        );
        // Change the language:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
        // Still sorted alphabetically, even though in a different language:
        $this->assertEquals(
            'Buch E-Book Buchkapitel Elektronisch Mikrofilm Tagungsbericht Zeitschrift',
            $this->findCss($page, "#limit_format")->getText()
        );
    }
}
