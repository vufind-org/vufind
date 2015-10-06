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
use VuFindTest\Auth\DatabaseTest;

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
    protected static $hash;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$hash = substr(md5(time()), 0, 16);
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

    protected function gotoRecord($session)
    {
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $page->find('css', '.btn.btn-primary')->click();
        $page->find('css', '.result a.title')->click();
        return $page;
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
        $this->assertNotNull($page->findById('account_firstname'));
        // Invalid email
        $page->findById('account_firstname')->setValue('Tester');
        $page->findById('account_lastname')->setValue('McTestenson');
        $page->findById('account_email')->setValue('blargasaurus');
        $page->findById('account_username')->setValue(self::$hash);
        $page->findById('account_password')->setValue('test');
        $page->findById('account_password2')->setValue('test');
        $this->assertNull(
            $page->find('css', '.modal-body .btn.btn-primary.disabled')
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->findById('account_firstname'));
        // Correct
        $page->findById('account_email')->setValue(self::$hash . '@ignore.com');
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
        $recordURL = $session->getCurrentUrl();
        $page->find('css', '#savedLists a')->click();
        $page->find('css', '.resultItemLine1 a')->click();
        $this->assertEquals($session->getCurrentUrl(), $recordURL);
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
        $username = '.modal-body [name="username"]';
        $password = '.modal-body [name="password"]';
        $this->assertNotNull($page->find('css', $username));
        $this->assertNotNull($page->find('css', $password));
        // Login
        // - empty
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', $username));
        // - for real
        $page->find('css', $username)->setValue(self::$hash);
        $page->find('css', $password)->setValue('test');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
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

    public funciton cleanup()
    {

    }

    /*
    public function testAddRecordToFavoritesLoggedIn()
    {
    }

    public function testAddSearchToFavoritesNewAccount()
    {
    }

    public function testAddSearchToFavoritesLogin()
    {
    }

    public function testAddSearchToFavoritesLoggedIn()
    {
    }
    */
}
