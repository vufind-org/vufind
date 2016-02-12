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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
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
 * @category VuFind2
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
        $this->findCss($page, '.searchForm [name="lookfor"]')
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

        $this->findCss($page, '#tools_testdeweybrowse')->click();
        $this->findCss($page, '#tools_testdeweybrowse-tab .save-record')->click();
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        $this->fillInAccountForm($page);
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#save_list');
        // Make list
        $this->findCss($page, '#make-list')->click();
        $this->snooze();
        // Empty
        $this->findCss($page, '#list_title')->setValue('Test List');
        $this->findCss($page, '#list_desc')->setValue('Just. THE BEST.');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->assertEquals($this->findCss($page, '#save_list option[selected]')->getHtml(), 'Test List');
        $this->findCss($page, '#add_mytags')->setValue('test1 test2 "test 3"');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // TODO: finish this test; as of this writing, there is no useful
        // feedback provided about saved items in tabbed mode.
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
