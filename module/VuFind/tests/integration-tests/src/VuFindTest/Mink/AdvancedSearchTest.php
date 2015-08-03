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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Mink;

/**
 * Mink test class to test advanced search.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class AdvancedSearchTest extends \VuFindTest\Unit\MinkTestCase
{
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
        $session->start();
        $path = '/Search/Advanced';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();

        // Add a group
        $session->executeScript("addGroup()");
        $this->assertNotNull($page->findById('group1'));

        // Add a search term
        $session->executeScript("addSearch(0)"); // add_search_link_0 click
        $this->assertNotNull($page->findById('search0_3'));
        // No visible x next to lonely search term
        $this->assertNotNull($page->find('css', '#search1_0 .close.hidden'));
        // Add a search term in another group
        $session->executeScript("addSearch(1)"); // add_search_link_1 click
        $this->assertNotNull($page->findById('search1_1'));
        // Visible x next to lonely search term
        $this->assertNotNull($page->find('css', '#search1_0 .close:not(.hidden)'));

        // Enter search for bride of the tomb
        $page->findById('search_lookfor0_0')->setValue('bride');
        $page->findById('search_lookfor0_1')->setValue('tomb');
        $page->findById('search_type0_1')->selectOption('Title');
        $page->findById('search_lookfor0_2')->setValue('garbage');
        $page->findById('search_lookfor0_3')->setValue('1883');
        $page->findById('search_type0_3')->selectOption('year');

        // Term removal
        $session->executeScript("deleteSearch(0, 2)"); // search0_2 x click
        $this->assertNull($page->findById('search0_3'));
        // Terms collapsing up
        $this->assertEquals('1883', $page->findById('search_lookfor0_2')->getValue());
        $this->assertEquals('year', $page->findById('search_type0_2')->getValue());

        // Submit search form
        $page->find('css', '[type=submit]')->press();

        // Check for proper search
        $this->assertEquals(
            '(All Fields:bride AND Title:tomb AND Year of Publication:1883)',
            $page->find('css', '.adv_search_terms strong')->getText()
        );

        // Test edit search
        $page->find('css', '.adv_search_links > a:first-child')->click();
        $this->assertEquals('bride', $page->findById('search_lookfor0_0')->getValue());
        $this->assertEquals('tomb',  $page->findById('search_lookfor0_1')->getValue());
        $this->assertEquals('Title', $page->findById('search_type0_1')->getValue());
        $this->assertEquals('1883',  $page->findById('search_lookfor0_2')->getValue());
        $this->assertEquals('year',  $page->findById('search_type0_2')->getValue());
    }
}
