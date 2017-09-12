<?php
/**
 * List views (i.e. tabs/accordion) test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Mink;
use Behat\Mink\Element\Element;

/**
 * List views (i.e. tabs/accordion) test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ListViewsTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return mixed
     */
    public static function setUpBeforeClass()
    {
        return static::failIfUsersExist();
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }

    /**
     * Perform a search and return the page after submitting the form.
     *
     * @return Element
     */
    protected function gotoSearch()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:testdeweybrowse');
        $this->findCss($page, '.btn.btn-primary')->click();
        $this->snooze();
        return $page;
    }

    /**
     * Perform a search and return the page after submitting the form and
     * clicking the first record.
     *
     * @return Element
     */
    protected function gotoRecord()
    {
        $page = $this->gotoSearch();
        $this->findCss($page, '.result a.title')->click();
        $this->snooze();
        return $page;
    }

    /**
     * Test that we can save a favorite from tab mode.
     *
     * @return void
     */
    public function testFavoritesInTabMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'tabs']]]
        );

        $page = $this->gotoRecord();

        // Click save inside the tools tab
        $this->findCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a')->click();
        $this->findCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a-content .save-record')->click();
        // Make an account
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        $this->fillInAccountForm($page);
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#save_list');
        // Save to list
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#modal .close')->click();
        $this->snooze();
        // Check saved items status
        $this->findCss($page, '#information_cd588d8723d65ca0ce9439e79755fa0a-content .savedLists ul');
    }

    /**
     * Test that we can save a favorite from accordion mode.
     *
     * @return void
     */
    public function testFavoritesInAccordionMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'accordion']]]
        );

        $page = $this->gotoRecord();

        // Click save inside the tools tab
        $this->findCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a')->click();
        $this->findCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a-content .save-record')->click();
        $this->snooze();
        // Login
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        // Make list
        $this->findCss($page, '#make-list')->click();
        $this->snooze();
        $this->findCss($page, '#list_title')->setValue('Test List');
        $this->findCss($page, '#list_desc')->setValue('Just. THE BEST.');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // Save to list
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#modal .close')->click();
        $this->snooze();
        // Check saved items status
        // Not visible, but still exists
        $this->findCss($page, '#information_cd588d8723d65ca0ce9439e79755fa0a-content .savedLists ul');
    }

    /**
     * Test localStorage saving from tab mode.
     *
     * @return void
     */
    protected function localStorageDance()
    {
        $page = $this->gotoRecord();
        $session = $this->getMinkSession();

        // Reload the page to close all results
        $session->reload();
        // Did our saved one open automatically?
        $this->findCss($page, '.result.embedded');

        // Close it
        $this->findCss($page, '.result a.title')->click();
        // Did our result stay closed?
        $session->reload();
        $result = $page->find('css', '.result.embedded');
        $this->assertFalse(is_object($result));

        // Open it
        $this->findCss($page, '.result a.title')->click();
        $this->snooze();
        // Search for anything else
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('anything else');
        $this->findCss($page, '.btn.btn-primary')->click();
        // Come back
        $page = $this->gotoSearch();
        // Did our result close after not being being in the last search?
        $result = $page->find('css', '.result.embedded');
        $this->assertFalse(is_object($result));
    }

    /**
     * Test localStorage saving from tab mode.
     *
     * @return void
     */
    public function testSavedOpenInTabsMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'tabs']]]
        );
        $this->localStorageDance();
    }

    /**
     * Test localStorage saving from accordion mode.
     *
     * @return void
     */
    public function testSavedOpenInAccordionMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'accordion']]]
        );
        $this->localStorageDance();
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1']);
    }
}
