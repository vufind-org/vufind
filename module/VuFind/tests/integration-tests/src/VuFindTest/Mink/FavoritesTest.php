<?php
/**
 * Mink cart test class.
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

/**
 * Mink cart test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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

    protected function gotoSearch($session)
    {
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $page->find('css', '.searchForm [name="lookfor"]')->setValue('Dewey');
        $page->find('css', '.btn.btn-primary')->click();
        return $page;
    }

    protected function gotoRecord($session)
    {
        $page = $this->gotoSearch($session);
        $page->find('css', '.result a.title')->click();
        return $page;
    }

    protected function stripHash($url)
    {
        $parts = explode('#', $url);
        return $parts[0];
    }

    public function testAddRecordToFavoritesNewAccount()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoRecord($session);

        $page->findById('save-record')->click();
        $page->find('css', '.modal-body .createAccountLink')->click();
        // Empty
        $this->assertNotNull(
            $page->find('css', '.modal-body .btn.btn-primary.disabled')
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();

        // Invalid email
        $this->fillInAccountForm($page, ['email' => 'blargasaurus']);
        $this->assertNull(
            $page->find('css', '.modal-body .btn.btn-primary.disabled')
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        // Correct
        $page->findById('account_email')->setValue('username1@ignore.com');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->findById('save_list'));
        // Make list
        $page->findById('make-list')->click();
        // Empty
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->findById('list_title'));
        $page->findById('list_title')->setValue('Test List');
        $page->findById('list_desc')->setValue('Just. THE BEST.');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertEquals($page->find('css', '#save_list option[selected]')->getHtml(), 'Test List');
        $page->findById('add_mytags')->setValue('test1 test2 "test 3"');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.alert.alert-info')); // .success?
        $page->find('css', '.modal-body .btn.btn-default')->click();
        // Check list page
        $recordURL = $this->stripHash($session->getCurrentUrl());
        $page->find('css', '#savedLists a')->click();
        $page->find('css', '.resultItemLine1 a')->click();
        $this->assertEquals($recordURL, $this->stripHash($session->getCurrentUrl()));
        $page->find('css', '.logoutOptions a[title="Log Out"]')->click();
        $session->stop();
    }

    public function testAddRecordToFavoritesLogin()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoRecord($session);

        $page->findById('save-record')->click();
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
        $page->findById('make-list')->click();
        $page->findById('list_title')->setValue('Future List');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $page->find('css', '#save_list option[selected]')->getHtml(),
            'Future List'
        );
        // - One for now
        $page->findById('make-list')->click();
        $page->findById('list_title')->setValue('Login Test List');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $page->find('css', '#save_list option[selected]')->getHtml(),
            'Login Test List'
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.alert.alert-info')); // .success?
        $session->stop();
    }

    public function testAddRecordToFavoritesLoggedIn()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoRecord($session);
        // Login
        $page->find('css', '#loginOptions a')->click();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $session->reload();
        // Save Record
        $page->findById('save-record')->click();
        $this->assertNotNull($page->find('css', '#save_list'));
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.alert.alert-info')); // .success?
        $session->stop();
    }

    public function testAddSearchItemToFavoritesNewAccount()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoSearch($session);

        $page->find('css', '.save-record')->click();
        $page->find('css', '.modal-body .createAccountLink')->click();
        // Empty
        $this->assertNotNull(
            $page->find('css', '.modal-body .btn.btn-primary.disabled')
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->fillInAccountForm(
            $page, ['username' => 'username2', 'email' => 'blargasaurus']
        );
        $this->assertNull(
            $page->find('css', '.modal-body .btn.btn-primary.disabled')
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $page->findById('account_email')->setValue('username2@ignore.com');
        // Test taken
        $page->findById('account_username')->setValue('username1');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->findById('account_firstname'));
        $page->findById('account_username')->setValue('username2');
        // Correct
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->findById('save_list'));
        // Make list
        $page->findById('make-list')->click();
        // Empty
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->findById('list_title'));
        $page->findById('list_title')->setValue('Test List');
        $page->findById('list_desc')->setValue('Just. THE BEST.');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $page->find('css', '#save_list option[selected]')->getHtml(),
            'Test List'
        );
        $page->findById('add_mytags')->setValue('test1 test2 "test 3"');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.alert.alert-info')); // .success?
        $page->find('css', '.modal-header .close')->click();
        // Check list page
        $page->find('css', '.result a.title')->click();
        $recordURL = $session->getCurrentUrl();
        $page->find('css', '.savedLists a')->click();
        $page->find('css', '.resultItemLine1 a')->click();
        $this->assertEquals($session->getCurrentUrl(), $recordURL);
        $page->find('css', '.logoutOptions a[title="Log Out"]')->click();
        $session->stop();
    }

    public function testAddSearchItemToFavoritesLogin()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoSearch($session);

        $page->find('css', '.save-record')->click();
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
        $page->findById('make-list')->click();
        $page->findById('list_title')->setValue('Future List');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $page->find('css', '#save_list option[selected]')->getHtml(),
            'Future List'
        );
        // - One for now
        $page->findById('make-list')->click();
        $page->findById('list_title')->setValue('Login Test List');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertEquals(
            $page->find('css', '#save_list option[selected]')->getHtml(),
            'Login Test List'
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.alert.alert-info')); // .success?
        $session->stop();
    }

    public function testAddSearchItemToFavoritesLoggedIn()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoSearch($session);
        // Login
        $page->find('css', '#loginOptions a')->click();
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        $session->reload();
        // Save Record
        $page->find('css', '.save-record')->click();
        $this->assertNotNull($page->find('css', '#save_list'));
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.alert.alert-info')); // .success?
        $session->stop();
    }
/*
    public function testAddSearchToFavoritesNewAccount()
    {
    }

    public function testAddSearchToFavoritesLogin()
    {
    }

    public function testAddSearchToFavoritesLoggedIn()
    {
    } */

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
