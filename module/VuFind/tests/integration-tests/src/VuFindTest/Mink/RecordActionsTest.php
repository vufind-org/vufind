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
    protected static $hash2;

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

    protected function gotoRecord($session, $searchTerm = '')
    {
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $page->find('css', '.searchForm [name="lookfor"]')->setValue($searchTerm);
        $page->find('css', '.btn.btn-primary')->click();
        $page->find('css', '.result a.title')->click();
        return $page;
    }

    /**
     * Test adding comments on records.
     *
     * @return void
     */
    public function testAddComment()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        // Go to a record view
        $page = $this->gotoRecord($session);
        // Click add comment without logging in
        // TODO Rewrite for comment and login coming
        $page->findById('usercomments')->click();
        $this->assertNotNull($page->find('css', '#comment[disabled]'));
        $page->find('css', 'form.comment .btn-primary')->click();
        $this->assertNotNull($page->find('css', '.modal.in')); // Lightbox open
        $this->assertNotNull($page->find('css', '.modal [name="username"]'));
        // Create new account
        $page->find('css', '.modal-body .createAccountLink')->click();
        $page->findById('account_firstname')->setValue('Record');
        $page->findById('account_lastname')->setValue('McTestenson');
        $page->findById('account_email')->setValue(self::$hash . '@ignore.com');
        $page->findById('account_username')->setValue(self::$hash);
        $page->findById('account_password')->setValue('test');
        $page->findById('account_password2')->setValue('test');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        // Make sure page updated for login
        $this->assertNull($page->find('css', '#comment[disabled]')); // Can Comment?
        $this->assertNull($page->find('css', '.comment.row'));
        // Add comment
        $page->findById('comment')->setValue('one');
        $page->find('css', 'form.comment .btn-primary')->click();
        $this->assertNotNull($page->find('css', '.comment.row'));
        // "Add" empty comment
        $page->find('css', 'form.comment .btn-primary')->click();
        $this->assertNotNull($page->find('css', '.comment.row'));
        // Remove comment
        $page->find('css', '.comment.row .delete')->click();
        $this->assertNull($page->find('css', '.comment.row'));
        // Logout
        $page->find('css', '.logoutOptions a[title="Log Out"]')->click();
        $session->stop();
    }

    /**
     * Test adding comments on records.
     *
     * @return void
     */
    public function asdftestAddTag()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        $page = $this->gotoRecord($session);

        // Go to a record view
        // Click add comment without logging in
        // Login in Lightbox
        // Make sure Lightbox redirects to comment view
        // Close lightbox
        // Make sure page updated for login
        // Click add comment
        // Add comment
        // Make sure comment appeared
        // Logout
    }

    /**
     * Test record view email.
     *
     * @return void
     */
    public function asdftestEmail()
    {
        // Change the theme:
        $this->changeConfigs(
            array('config' => array('Site' => array('theme' => 'bootstrap3')))
        );

        // Go to the advanced search page
        $session = $this->getMinkSession();
        $session->start();

        // Go to a record view
        // Click email record without logging in
        // Login in Lightbox
        // Make sure Lightbox redirects to email view
        // Close lightbox
        // Click email
        // Type invalid email
        // Make sure form cannot submit
        // Send text to false email
        // Check for confirmation message
        // Logout
    }

    /**
     * Test record view SMS.
     *
     * @return void
     */
    public function asdftestSMS()
    {
        // Change the theme:
        $this->changeConfigs(
            array('config' => array('Site' => array('theme' => 'bootstrap3')))
        );

        // Go to the advanced search page
        $session = $this->getMinkSession();
        $session->start();

        // Go to a record view
        // Click SMS
        // Make sure Lightbox redirects to SMS view
        // TODO: Validator
        // Send text to false number
        // Check for confirmation message
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        // If CI is not running, all tests were skipped, so no work is necessary:
        $test = new FavoritesTest();
        if (!$test->continuousIntegrationRunning()) {
            return;
        }

        // Delete test user
        $test = new FavoritesTest();
        $userTable = $test->getTable('User');
        $user = $userTable->getByUsername(self::$hash, false);
        if (empty($user)) {
            //throw new \Exception('Problem deleting expected user.');
        } else {
            $user->delete();
        }
    }
}