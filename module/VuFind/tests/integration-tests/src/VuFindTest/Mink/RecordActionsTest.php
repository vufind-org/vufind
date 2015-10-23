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
    use \VuFindTest\Unit\UserCreationTrait;

    protected static $hash;
    protected static $hash2;

    /**
     * Standard setup method.
     *
     * @return void
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

    protected function gotoRecord($session)
    {
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $page->find('css', '.searchForm [name="lookfor"]')->setValue('Dewey');
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
        $this->fillInAccountForm($page);
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
    public function testAddTag()
    {
        // Change the theme:
        $this->changeConfigs(
            ['config' => ['Site' => ['theme' => 'bootstrap3']]]
        );

        $session = $this->getMinkSession();
        $session->start();
        // Go to a record view
        $page = $this->gotoRecord($session);
        // Click to add tag
        $page->findByid('tagRecord')->click();
        // Lightbox login open?
        $this->assertNotNull($page->find('css', '.modal.in [name="username"]'));
        // Make account
        $page->find('css', '.modal-body .createAccountLink')->click();
        $this->fillInAccountForm(
            $page, ['username'=>'username2', 'email'=>'test2@com.com']
        );
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $this->assertNotNull($page->find('css', '.modal #addtag_tag'));
        $page->find('css', '.modal .close')->click();
        $page->find('css', '.logoutOptions a[title="Log Out"]')->click();
        // Login
        $page = $this->gotoRecord($session); // redirects to search home???
        $page->findByid('tagRecord')->click();
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        $this->assertNotNull($page->find('css', '.modal #addtag_tag'));
        // Add tags
        $page->find('css', '.modal #addtag_tag')->setValue('one 2 "three 4" five');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $success = $page->find('css', '.modal-body .alert-info');
        $this->assertTrue(is_object($success));
        $this->assertEquals('Tags Saved', $success->getText());
        $page->find('css', '.modal .close')->click();
        // Count tags
        $tags = $page->findAll('css', '#tagList .tag');
        $this->assertEquals(4, count($tags));
        $tvals = [];
        foreach ($tags as $i=>$t) {
            $link = $t->find('css', 'a');
            $tvals[] = $link->getText();
        }
        sort($tvals);
        $this->assertEquals($tvals, ['2', 'five', 'one', 'three 4']);
        // Remove a tag
        $tags[0]->find('css', 'button')->click();
        // Count tags with missing
        $sum = 0;
        foreach ($tags as $t) {
            $link = $t->find('css', 'button');
            if ($link) {
                $sum += intval($link->getText());
            }
        }
        $this->assertEquals(3, $sum);
        // Log out
        $page->find('css', '.logoutOptions a[title="Log Out"]')->click();
        // Flat tags
        $this->assertNull($page->find('css', '#tagList .tag.selected'));
        $this->assertNull($page->find('css', '#tagList .tag .fa'));
        // Login with second account
        $page->find('css', '#loginOptions a')->click();
        $this->assertNotNull($page->find('css', '.modal.in [name="username"]'));
        $this->fillInLoginForm($page, 'username1', 'test');
        $page->find('css', '.modal-body .btn.btn-primary')->click();
        $page = $this->gotoRecord($session);
        // Check selected == 0
        $this->assertNull($page->find('css', '#tagList .tag.selected'));
        $this->assertNotNull($page->find('css', '#tagList .tag'));
        $this->assertNotNull($page->find('css', '#tagList .tag .fa-plus'));
        // Click one
        $page->find('css', '#tagList .tag button')->click();
        // Check selected == 1
        $this->assertNotNull($page->find('css', '#tagList .tag.selected'));
        // Click again
        $page->find('css', '#tagList .tag button')->click();
        // Check selected == 0
        $this->assertNull($page->find('css', '#tagList .tag.selected'));
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
        static::removeUsers(['username1', 'username2']);
    }
}