<?php
/**
 * Mink test class to test advanced search.
 *
 * PHP version 5
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

/**
 * Mink test class to test advanced search.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AdvancedSearchTest extends \VuFindTest\Unit\MinkTestCase
{
    /**
     * Test persistent
     *
     * @return void
     */
    public function testPersistent()
    {
        // Go to the advanced search page
        $session = $this->getMinkSession();
        $path = '/Search/Advanced';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
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
     * Test that the home page is available.
     *
     * @return void
     */
    public function testBootstrapThree()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        // Go to the advanced search page
        $session = $this->getMinkSession();
        $path = '/Search/Advanced';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();

        // Add a group
        $session->executeScript("addGroup()");
        $this->snooze();
        $this->findCss($page, '#group1');

        // Add a search term
        $session->executeScript("addSearch(0)"); // add_search_link_0 click
        $this->snooze();
        $this->findCss($page, '#search0_3');
        // No visible x next to lonely search term
        $this->findCss($page, '#search1_0 .close.hidden');
        // Add a search term in another group
        $session->executeScript("addSearch(1)"); // add_search_link_1 click
        $this->findCss($page, '#search1_1');
        // Visible x next to lonely search term
        $this->findCss($page, '#search1_0 .close:not(.hidden)');

        // Enter search for bride of the tomb
        $this->findCss($page, '#search_lookfor0_0')->setValue('bride');
        $this->findCss($page, '#search_lookfor0_1')->setValue('tomb');
        $this->findCss($page, '#search_type0_1')->selectOption('Title');
        $this->findCss($page, '#search_lookfor0_2')->setValue('garbage');
        $this->findCss($page, '#search_lookfor0_3')->setValue('1883');
        $this->findCss($page, '#search_type0_3')->selectOption('year');

        // Submit search form
        $this->findCss($page, '[type=submit]')->press();

        // Check for proper search
        $this->assertEquals(
            '(All Fields:bride AND Title:tomb AND All Fields:garbage AND Year of Publication:1883)',
            $this->findCss($page, '.adv_search_terms strong')->getHtml()
        );

        // Test edit search
        $links = $page->findAll('css', '.adv_search_links a');
        foreach ($links as $link) {
            if ($this->checkVisibility($link)
                && $link->getHtml() == 'Edit this Advanced Search'
            ) {
                $link->click();
                break;
            }
        }
        $this->assertEquals('bride', $this->findCss($page, '#search_lookfor0_0')->getValue());
        $this->assertEquals('tomb',  $this->findCss($page, '#search_lookfor0_1')->getValue());
        $this->assertEquals('Title', $this->findCss($page, '#search_type0_1')->getValue());
        $this->assertEquals('garbage',  $this->findCss($page, '#search_lookfor0_2')->getValue());
        $this->assertEquals('1883',  $this->findCss($page, '#search_lookfor0_3')->getValue());
        $this->assertEquals('year',  $this->findCss($page, '#search_type0_3')->getValue());

        // Term removal
        $session->executeScript("deleteSearch(0, 2)"); // search0_2 x click
        $this->assertNull($page->findById('search0_3'));
        // Terms collapsing up
        $this->assertEquals('1883', $this->findCss($page, '#search_lookfor0_2')->getValue());
        $this->assertEquals('year', $this->findCss($page, '#search_type0_2')->getValue());
    }
}
