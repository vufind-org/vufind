<?php
/**
 * Mink favorites test class.
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
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Mink;
use Behat\Mink\Element\Element;

/**
 * Mink favorites test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FavoritesTest extends \VuFindTest\Unit\MinkTestCase
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
        $this->findCss($page, '.searchForm [name="lookfor"]')->setValue('Dewey');
        $this->findCss($page, '.btn.btn-primary')->click();
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
        return $page;
    }

    /**
     * Strip off the hash segment of a URL.
     *
     * @param string $url URL to strip
     *
     * @return string
     */
    protected function stripHash($url)
    {
        $parts = explode('#', $url);
        return $parts[0];
    }

    /**
     * Test adding a record to favorites (from the record page) while creating a
     * new account.
     *
     * @return void
     */
    public function testAddRecordToFavoritesNewAccount()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $page = $this->gotoRecord();

        $this->findCss($page, '.save-record')->click();
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        // Empty
        $this->findCss($page, '.modal-body .btn.btn-primary.disabled');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();

        // Invalid email
        $this->fillInAccountForm($page, ['email' => 'blargasaurus']);

        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        // Correct
        $this->findCss($page, '#account_email')->setValue('username1@ignore.com');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#save_list');
        // Make list
        $this->findCss($page, '#make-list')->click();
        $this->snooze();
        // Empty
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#list_title')->setValue('Test List');
        $this->findCss($page, '#list_desc')->setValue('Just. THE BEST.');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->assertEquals($this->findCss($page, '#save_list option[selected]')->getHtml(), 'Test List');
        $this->findCss($page, '#add_mytags')->setValue('test1 test2 "test 3"');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '.modal .alert.alert-success');
        $this->findCss($page, '.modal-body .btn.btn-default')->click();
        // Check list page
        $session = $this->getMinkSession();
        $recordURL = $this->stripHash($session->getCurrentUrl());
        $this->snooze();
        $this->findCss($page, '.savedLists a')->click();
        $this->snooze();
        $this->findCss($page, '.resultItemLine1 a')->click();
        $this->assertEquals($recordURL, $this->stripHash($session->getCurrentUrl()));
        $this->findCss($page, '.logoutOptions a[title="Log Out"]')->click();
    }

    /**
     * Test adding a record to favorites (from the record page) using an existing
     * account that is not yet logged in.
     *
     * @return void
     */
    public function testAddRecordToFavoritesLogin()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $page = $this->gotoRecord();

        $this->findCss($page, '.save-record')->click();
        // Login
        // - empty
        $this->submitLoginForm($page);
        $this->assertLightboxWarning($page, 'Login information cannot be blank.');
        // - wrong
        $this->fillInLoginForm($page, 'username1', 'superwrong');
        $this->submitLoginForm($page);
        $this->assertLightboxWarning($page, 'Invalid login -- please try again.');
        // - for real
        $this->fillInLoginForm($page, null, 'test');
        $this->submitLoginForm($page);
        // Make sure we don't have Favorites because we have another populated list
        $this->assertNull($page->find('css', '.modal-body #save_list'));
        // Make Two Lists
        // - One for the next test
        $this->findCss($page, '#make-list')->click();
        $this->findCss($page, '#list_title')->setValue('Future List');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $this->findCss($page, '#save_list option[selected]')->getHtml(),
            'Future List'
        );
        // - One for now
        $this->findCss($page, '#make-list')->click();
        $this->findCss($page, '#list_title')->setValue('Login Test List');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $this->findCss($page, '#save_list option[selected]')->getHtml(),
            'Login Test List'
        );
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '.modal .alert.alert-success');
    }

    /**
     * Test adding a record to favorites (from the record page) using an existing
     * account that is already logged in.
     *
     * @return void
     */
    public function testAddRecordToFavoritesLoggedIn()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $page = $this->gotoRecord();
        // Login
        $this->findCss($page, '#loginOptions a')->click();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        // Save Record
        $this->findCss($page, '.save-record')->click();
        $this->snooze();
        $this->findCss($page, '#save_list');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '.modal .alert.alert-success');
    }

    /**
     * Test adding a record to favorites (from the search results) while creating a
     * new account.
     *
     * @return void
     */
    public function testAddSearchItemToFavoritesNewAccount()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $page = $this->gotoSearch();

        $this->findCss($page, '.save-record')->click();
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        // Empty
        $this->findCss($page, '.modal-body .btn.btn-primary.disabled');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->fillInAccountForm(
            $page, ['username' => 'username2', 'email' => 'blargasaurus']
        );
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '#account_email')->setValue('username2@ignore.com');
        // Test taken
        $this->findCss($page, '#account_username')->setValue('username1');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '#account_firstname');
        $this->findCss($page, '#account_username')->setValue('username2');
        // Correct
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '#save_list');
        // Make list
        $this->findCss($page, '#make-list')->click();
        $this->snooze();
        // Empty
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '#list_title')->setValue('Test List');
        $this->findCss($page, '#list_desc')->setValue('Just. THE BEST.');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $this->findCss($page, '#save_list option[selected]')->getHtml(),
            'Test List'
        );
        $this->findCss($page, '#add_mytags')->setValue('test1 test2 "test 3"');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '.alert.alert-info'); // .success?
        $this->findCss($page, '.modal-header .close')->click();
        // Check list page
        $this->snooze();
        $this->findCss($page, '.result a.title')->click();
        $this->snooze();
        $session = $this->getMinkSession();
        $recordURL = $session->getCurrentUrl();
        $this->findCss($page, '.savedLists a')->click();
        $this->snooze();
        $this->findCss($page, '.resultItemLine1 a')->click();
        $this->snooze();
        $this->assertEquals($recordURL, $session->getCurrentUrl());
        $this->findCss($page, '.logoutOptions a[title="Log Out"]')->click();
    }

    /**
     * Test adding a record to favorites (from the search results) using an existing
     * account that is not yet logged in.
     *
     * @return void
     */
    public function testAddSearchItemToFavoritesLogin()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $page = $this->gotoSearch();

        $this->findCss($page, '.save-record')->click();
        // Login
        // - empty
        $this->submitLoginForm($page);
        $this->assertLightboxWarning($page, 'Login information cannot be blank.');
        // - for real
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Make sure we don't have Favorites because we have another populated list
        $this->assertNull($page->find('css', '.modal-body #save_list'));
        // Make Two Lists
        // - One for the next test
        $this->findCss($page, '#make-list')->click();
        $this->findCss($page, '#list_title')->setValue('Future List');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $this->findCss($page, '#save_list option[selected]')->getHtml(),
            'Future List'
        );
        // - One for now
        $this->findCss($page, '#make-list')->click();
        $this->findCss($page, '#list_title')->setValue('Login Test List');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $this->findCss($page, '#save_list option[selected]')->getHtml(),
            'Login Test List'
        );
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '.alert.alert-info'); // .success?
    }

    /**
     * Test adding a record to favorites (from the search results) using an existing
     * account that is already logged in.
     *
     * @return void
     */
    public function testAddSearchItemToFavoritesLoggedIn()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $page = $this->gotoSearch();
        // Login
        $this->findCss($page, '#loginOptions a')->click();
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Save Record
        $this->findCss($page, '.save-record')->click();
        $this->snooze();
        $this->findCss($page, '#save_list');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '.alert.alert-info'); // .success?
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1', 'username2']);
    }
}
