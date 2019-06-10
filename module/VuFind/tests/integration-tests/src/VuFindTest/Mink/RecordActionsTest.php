<?php
/**
 * Mink record actions test class.
 *
 * PHP version 7
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
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Mink;

/**
 * Mink record actions test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class RecordActionsTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\AutoRetryTrait;
    use \VuFindTest\Unit\UserCreationTrait;

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

    /**
     * Move the current page to a record by performing a search.
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function gotoRecord()
    {
        $page = $this->performSearch('Dewey');
        $this->findCss($page, '.result a.title')->click();
        return $page;
    }

    /**
     * Make new account
     *
     * @param \Behat\Mink\Element\Element $page     Page element
     * @param string                      $username Username to create
     *
     * @return void
     */
    protected function makeAccount($page, $username)
    {
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm(
            $page, ['username' => $username, 'email' => $username . '@vufind.org']
        );
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
    }

    /**
     * Test adding comments on records.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testAddComment()
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Click add comment without logging in
        // TODO Rewrite for comment and login coming
        $this->findCss($page, '.record-tabs .usercomments')->click();
        $this->snooze();
        $this->findCss($page, '.comment-form');
        $this->assertEquals(// Can Comment?
            'You must be logged in first',
            $this->findCss($page, 'form.comment-form .btn.btn-primary')->getText()
        );
        $this->findCss($page, 'form.comment-form .btn-primary')->click();
        $this->findCss($page, '.modal.in'); // Lightbox open
        $this->findCss($page, '.modal [name="username"]');
        // Create new account
        $this->makeAccount($page, 'username1');
        // Make sure page updated for login
        // $page = $this->gotoRecord();
        $this->findCss($page, '.record-tabs .usercomments')->click();
        $this->assertEquals(// Can Comment?
            'Add your comment',
            $this->findCss($page, 'form.comment-form .btn.btn-primary')->getValue()
        );
        // "Add" empty comment
        $this->findCss($page, 'form.comment-form .btn-primary')->click();
        $this->assertNull($page->find('css', '.comment'));
        // Add comment
        $this->findCss($page, 'form.comment-form [name="comment"]')->setValue('one');
        $this->findCss($page, 'form.comment-form .btn-primary')->click();
        $this->findCss($page, '.comment');
        // Remove comment
        $this->findCss($page, '.comment .delete')->click();
        $this->snooze(); // wait for UI update
        $this->assertNull($page->find('css', '.comment'));
        // Logout
        $this->findCss($page, '.logoutOptions a.logout')->click();
    }

    /**
     * Test adding tags on records.
     *
     * @retryCallback removeUsername2
     *
     * @return void
     */
    public function testAddTag()
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Click to add tag
        $this->snooze();
        $this->findCss($page, '.tag-record')->click();
        $this->snooze();
        // Lightbox login open?
        $this->findCss($page, '.modal.in [name="username"]');
        // Make account
        $this->makeAccount($page, 'username2');
        // Add tag exists?
        $this->findCss($page, '.modal #addtag_tag');
        $this->findCss($page, '.modal .close')->click();
        $this->snooze(); // wait for display to update
        $this->findCss($page, '.logoutOptions a.logout')->click();
        $this->snooze();
        // Login
        // $page = $this->gotoRecord();
        $this->findCss($page, '.tag-record')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Add tags
        $this->findCss($page, '.modal #addtag_tag')->setValue('one 2 "three 4" five');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $success = $this->findCss($page, '.modal-body .alert-success');
        $this->assertEquals('Tags Saved', $success->getText());
        $this->findCss($page, '.modal .close')->click();
        // Count tags
        $this->snooze(); // wait for UI update
        $tags = $page->findAll('css', '.tagList .tag');
        $this->assertEquals(4, count($tags));
        $tvals = [];
        foreach ($tags as $i => $t) {
            $link = $t->find('css', 'a');
            $tvals[] = $link->getText();
        }
        sort($tvals);
        $this->assertEquals($tvals, ['2', 'five', 'one', 'three 4']);
        // Remove a tag
        $tags[0]->find('css', 'button')->click();
        $this->snooze(); // wait for UI update
        $tags = $page->findAll('css', '.tagList .tag');
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
        $this->findCss($page, '.logoutOptions a.logout')->click();
        $this->snooze(); // wait for UI update

        // Flat tags
        $this->assertNull($page->find('css', '.tagList .tag.selected'));
        $this->assertNull($page->find('css', '.tagList .tag .fa'));
        // Login with second account
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->findCss($page, '.modal.in [name="username"]');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // $page = $this->gotoRecord();
        // Check selected == 0
        $this->assertNull($page->find('css', '.tagList .tag.selected'));
        $this->findCss($page, '.tagList .tag');
        $this->findCss($page, '.tagList .tag .fa-plus');
        // Click one
        $this->findCss($page, '.tagList .tag button')->click();
        $this->snooze();
        // Check selected == 1
        $this->findCss($page, '.tagList .tag.selected');
        // Click again
        $this->findCss($page, '.tagList .tag button')->click();
        $this->snooze();
        // Check selected == 0
        $this->assertNull($page->find('css', '.tagList .tag.selected'));
        $this->findCss($page, '.logoutOptions a.logout')->click();
    }

    /**
     * Test searching for one of the tags created above.
     *
     * @return void
     */
    public function testTagSearch()
    {
        // First try an undefined tag:
        $page = $this->performSearch('tag-not-in-system', 'tag');
        $this->assertEquals('No Results!', $this->findCss($page, 'h2')->getText());
        // Now try a tag defined earlier:
        $page = $this->performSearch('five', 'tag');
        $expected = 'Showing 1 - 1 results of 1 for search \'five\'';
        $this->assertEquals(
            $expected, substr(
                $this->findCss($page, '.search-stats')->getText(), 0,
                strlen($expected)
            )
        );
    }

    /**
     * Test adding case sensitive tags on records.
     *
     * @return void
     */
    public function testAddSensitiveTag()
    {
        // Set up configs:
        $this->changeConfigs(
            [
                'config' => [
                    'Social' => ['case_sensitive_tags' => 'true']
                ]
            ]
        );
        // Login
        $page = $this->gotoRecord();
        $this->findCss($page, '.tag-record')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Add tags
        $this->findCss($page, '.modal #addtag_tag')->setValue('one ONE "new tag" ONE "THREE 4"');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        $success = $this->findCss($page, '.modal-body .alert-success');
        $this->assertEquals('Tags Saved', $success->getText());
        $this->findCss($page, '.modal .close')->click();
        // Count tags
        $this->snooze();
        $tags = $page->findAll('css', '.tagList .tag');
        $this->assertEquals(6, count($tags));
    }

    /**
     * Test record view email.
     *
     * @retryCallback removeEmailManiac
     *
     * @return void
     */
    public function testEmail()
    {
        // Set up configs:
        $this->changeConfigs(
            [
                'config' => [
                    'Mail' => ['testOnly' => 1],
                ]
            ]
        );

        // Go to a record view
        $page = $this->gotoRecord();
        // Click email record without logging in
        $this->findCss($page, '.mail-record')->click();
        $this->snooze();
        $this->findCss($page, '.modal.in [name="username"]');
        // Make account
        $this->makeAccount($page, 'emailmaniac');
        // Make sure Lightbox redirects to email view
        $this->findCss($page, '.modal #email_to');
        // Type invalid email
        $this->findCss($page, '.modal #email_to')->setValue('blargarsaurus');
        $this->findCss($page, '.modal #email_from')->setValue('asdf@asdf.com');
        $this->findCss($page, '.modal #email_message')->setValue('message');
        // Send text to false email
        $this->snooze();
        $this->findCss($page, '.modal #email_to')->setValue('asdf@vufind.org');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // Check for confirmation message
        $this->findCss($page, '.modal .alert-success');
        $this->findCss($page, '.modal .close')->click();
        // Logout
        $this->findCss($page, '.logoutOptions a.logout')->click();

        // Go to a record view
        $page = $this->gotoRecord();
        // Click email record without logging in
        $this->findCss($page, '.mail-record')->click();
        $this->snooze();
        $this->findCss($page, '.modal.in [name="username"]');
        // Login in Lightbox
        $this->fillInLoginForm($page, 'emailmaniac', 'test');
        $this->submitLoginForm($page);
        // Make sure Lightbox redirects to email view
        $this->findCss($page, '.modal #email_to');
        // Close lightbox
        $this->findCss($page, '.modal .close')->click();
        $this->snooze();
        // Click email
        $this->findCss($page, '.mail-record')->click();
        $this->snooze();
        $this->findCss($page, '.modal #email_to');
        // Send text to false email
        $this->findCss($page, '.modal #email_to')->setValue('asdf@vufind.org');
        $this->findCss($page, '.modal #email_from')->setValue('asdf@vufind.org');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // Check for confirmation message and close lightbox
        $this->findCss($page, '.modal .alert-success');
        $this->findCss($page, '.modal .close')->click();
        $this->snooze();
        // Logout
        $this->findCss($page, '.logoutOptions a.logout')->click();
    }

    /**
     * Test record view SMS.
     *
     * @return void
     */
    public function testSMS()
    {
        // Set up configs:
        $this->changeConfigs(
            [
                'config' => [
                    'Mail' => ['testOnly' => 1],
                ]
            ]
        );

        // Go to a record view
        $page = $this->gotoRecord();
        // Click SMS
        $this->findCss($page, '.sms-record')->click();
        // Type invalid phone numbers
        // - too empty
        $this->findCss($page, '.modal #sms_to')->setValue('');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '.modal .sms-error');
        // - too short
        $this->findCss($page, '.modal #sms_to')->setValue('123');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '.modal .sms-error');
        // - too long
        $this->findCss($page, '.modal #sms_to')->setValue('12345678912345678912345679');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '.modal .sms-error');
        // - too lettery
        $this->findCss($page, '.modal #sms_to')->setValue('123abc');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->findCss($page, '.modal .sms-error');
        // - just right
        $this->findCss($page, '.modal #sms_to')->setValue('8005555555');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze(); // wait for form submission to catch missing carrier
        $this->assertNull($page->find('css', '.modal .sms-error'));
        // - pretty just right
        $this->findCss($page, '.modal #sms_to')->setValue('(800) 555-5555');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze(); // wait for form submission to catch missing carrier
        $this->assertNull($page->find('css', '.modal .sms-error'));
        // Send text to false number
        $this->findCss($page, '.modal #sms_to')->setValue('(800) 555-5555');
        $optionElement = $this->findCss($page, '.modal #sms_provider option');
        $page->selectFieldOption('sms_provider', 'verizon');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // Check for confirmation message
        $this->findCss($page, '.modal .alert-success');
    }

    /**
     * Retry cleanup method in case of failure during testAddTag.
     *
     * @return void
     */
    protected function removeUsername2()
    {
        static::removeUsers(['username2']);
    }

    /**
     * Retry cleanup method in case of failure during testEmail.
     *
     * @return void
     */
    protected function removeEmailManiac()
    {
        static::removeUsers(['emailmaniac']);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1', 'username2', 'emailmaniac']);
    }
}
