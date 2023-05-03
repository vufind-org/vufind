<?php

/**
 * Mink record actions test class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011-2023.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink record actions test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
final class RecordActionsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Move the current page to a record by performing a search.
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function gotoRecord()
    {
        $page = $this->performSearch('Dewey');
        $this->clickCss($page, '.result a.title');
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
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm(
            $page,
            ['username' => $username, 'email' => $username . '@vufind.org']
        );
        $this->clickCss($page, '#accountForm .btn.btn-primary');
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
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->findCss($page, '.comment-form');
        $this->assertEquals(// Can Comment?
            'You must be logged in first',
            $this->findCss($page, 'form.comment-form .btn.btn-primary')->getText()
        );
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, '.modal.in'); // Lightbox open
        $this->findCss($page, '.modal [name="username"]');
        // Create new account
        $this->makeAccount($page, 'username1');
        $this->waitForLightboxHidden();
        // Make sure page updated for login
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->waitForPageLoad($page);
        $this->assertEquals(// Can Comment?
            'Add your comment',
            $this->findCss($page, 'form.comment-form .btn.btn-primary')->getValue()
        );
        // "Add" empty comment
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->unFindCss($page, '.comment');
        // Add comment
        $this->findCss($page, 'form.comment-form [name="comment"]')->setValue('one');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, '.comment');
        // Remove comment
        $this->clickCss($page, '.comment .delete');
        $this->unFindCss($page, '.comment');
        // Logout
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Test adding comments on records (with Captcha enabled).
     *
     * @return void
     */
    public function testAddCommentWithCaptcha()
    {
        // Set up configs:
        $this->changeConfigs(
            [
                'config' => [
                    'Captcha' => ['types' => ['demo'], 'forms' => '*'],
                ],
            ]
        );
        // Go to a record view
        $page = $this->gotoRecord();
        // Click add comment without logging in
        // TODO Rewrite for comment and login coming
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->findCss($page, '.comment-form');
        $this->assertEquals(// Can Comment?
            'You must be logged in first',
            $this->findCss($page, 'form.comment-form .btn.btn-primary')->getText()
        );
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, '.modal.in'); // Lightbox open
        $this->findCss($page, '.modal [name="username"]');
        // Log in to existing account
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        // Make sure page updated for login
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->assertEquals(// Can Comment?
            'Add your comment',
            $this->findCss($page, 'form.comment-form .btn.btn-primary')->getValue()
        );
        // "Add" empty comment
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->unFindCss($page, '.comment');
        // Add comment without CAPTCHA
        $this->findCss($page, 'form.comment-form [name="comment"]')->setValue('one');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->assertEquals(
            'CAPTCHA not passed',
            $this->findCss($page, '.modal-body .alert-danger')->getText()
        );
        $this->clickCss($page, '.modal-body button');
        // Now fix the CAPTCHA
        $this->findCss($page, 'form.comment-form [name="demo_captcha"]')
            ->setValue('demo');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, '.comment');
        // Remove comment
        $this->clickCss($page, '.comment .delete');
        $this->unFindCss($page, '.comment');
        // Logout
        $this->clickCss($page, '.logoutOptions a.logout');
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
        $this->clickCss($page, '.tag-record');
        // Lightbox login open?
        $this->findCss($page, '.modal.in [name="username"]');
        // Make account
        $this->makeAccount($page, 'username2');
        // Add tag exists?
        $this->findCss($page, '.modal #addtag_tag');
        $this->closeLightbox($page);
        $this->clickCss($page, '.logoutOptions a.logout');
        // Login
        // $page = $this->gotoRecord();
        $this->clickCss($page, '.tag-record');
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Add tags
        $this->findCss($page, '.modal #addtag_tag')->setValue('one 2 "three 4" five');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $success = $this->findCss($page, '.modal-body .alert-success');
        $this->assertEquals('Tags Saved', $success->getText());
        $this->closeLightbox($page);
        // Count tags
        $this->waitForPageLoad($page);
        $tags = $page->findAll('css', '.tagList .tag');
        $this->assertCount(4, $tags);
        $tvals = [];
        foreach ($tags as $t) {
            $link = $t->find('css', 'a');
            $tvals[] = $link->getText();
        }
        sort($tvals);
        $this->assertEquals($tvals, ['2', 'five', 'one', 'three 4']);
        // Remove a tag
        $tags[0]->find('css', 'button')->click();
        $this->waitForPageLoad($page);
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
        $this->clickCss($page, '.logoutOptions a.logout');
        $this->waitForPageLoad($page);

        // Flat tags
        $this->assertNull($page->find('css', '.tagList .tag.selected'));
        $this->assertNull($page->find('css', '.tagList .tag .tag-submit'));
        // Login with second account
        $this->clickCss($page, '#loginOptions a');
        $this->findCss($page, '.modal.in [name="username"]');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        // $page = $this->gotoRecord();
        // Check selected == 0
        $this->unFindCss($page, '.tagList .tag.selected');
        $this->findCss($page, '.tagList .tag');
        $this->findCss($page, '.tagList .tag .tag-submit');
        // Click one
        $this->clickCss($page, '.tagList .tag button');
        // Check selected == 1
        $this->findCss($page, '.tagList .tag.selected');
        // Click again
        $this->clickCss($page, '.tagList .tag button');
        // Check selected == 0
        $this->unFindCss($page, '.tagList .tag.selected');
        $this->clickCss($page, '.logoutOptions a.logout');
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
            $expected,
            substr(
                $this->findCss($page, '.search-stats')->getText(),
                0,
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
                    'Social' => ['case_sensitive_tags' => 'true'],
                ],
            ]
        );
        // Login
        $page = $this->gotoRecord();
        $this->clickCss($page, '.tag-record');
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Add tags
        $this->findCss($page, '.modal #addtag_tag')->setValue('one ONE "new tag" ONE "THREE 4"');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $success = $this->findCss($page, '.modal-body .alert-success');
        $this->assertEquals('Tags Saved', $success->getText());
        $this->closeLightbox($page);
        // Count tags
        $this->waitForPageLoad($page);
        $tags = $page->findAll('css', '.tagList .tag');
        $this->assertCount(6, $tags);
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
                ],
            ]
        );

        // Go to a record view
        $page = $this->gotoRecord();
        // Click email record without logging in
        $this->clickCss($page, '.mail-record');
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
        $this->findCss($page, '.modal #email_to')->setValue('asdf@vufind.org');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        // Check for confirmation message
        $this->findCss($page, '.modal .alert-success');
        $this->closeLightbox($page);
        // Logout
        $this->clickCss($page, '.logoutOptions a.logout');

        // Go to a record view
        $page = $this->gotoRecord();
        // Click email record without logging in
        $this->clickCss($page, '.mail-record');
        $this->findCss($page, '.modal.in [name="username"]');
        // Login in Lightbox
        $this->fillInLoginForm($page, 'emailmaniac', 'test');
        $this->submitLoginForm($page);
        // Make sure Lightbox redirects to email view
        $this->findCss($page, '.modal #email_to');
        // Close lightbox
        $this->closeLightbox($page);
        // Click email
        $this->clickCss($page, '.mail-record');
        $this->findCss($page, '.modal #email_to');
        // Send text to false email
        $this->findCss($page, '.modal #email_to')->setValue('asdf@vufind.org');
        $this->findCss($page, '.modal #email_from')->setValue('asdf@vufind.org');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        // Check for confirmation message and close lightbox
        $this->findCss($page, '.modal .alert-success');
        $this->closeLightbox($page);
        // Logout
        $this->clickCss($page, '.logoutOptions a.logout');
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
                ],
            ]
        );

        // Go to a record view
        $page = $this->gotoRecord();
        // Click SMS
        $this->clickCss($page, '.sms-record');
        // Type invalid phone numbers
        // - too empty
        $this->findCss($page, '.modal #sms_to')->setValue('');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '.modal .sms-error');
        // - too short
        $this->findCss($page, '.modal #sms_to')->setValue('123');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '.modal .sms-error');
        // - too long
        $this->findCss($page, '.modal #sms_to')->setValue('12345678912345678912345679');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '.modal .sms-error');
        // - too lettery
        $this->findCss($page, '.modal #sms_to')->setValue('123abc');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '.modal .sms-error');
        // - just right
        $this->findCss($page, '.modal #sms_to')->setValue('8005555555');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page); // wait for form submission to catch missing carrier
        $this->assertNull($page->find('css', '.modal .sms-error'));

        $this->unFindCss($page, '.modal .sms-error');
        // - pretty just right
        $this->findCss($page, '.modal #sms_to')->setValue('(800) 555-5555');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page); // wait for form submission to catch missing carrier
        $this->assertNull($page->find('css', '.modal .sms-error'));
        $this->unFindCss($page, '.modal .sms-error');
        // Send text to false number
        $this->findCss($page, '.modal #sms_to')->setValue('(800) 555-5555');
        $optionElement = $this->findCss($page, '.modal #sms_provider option');
        $page->selectFieldOption('sms_provider', 'verizon');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        // Check for confirmation message
        $this->findCss($page, '.modal .alert-success');
    }

    /**
     * Test record view print button.
     *
     * @return void
     */
    public function testPrint(): void
    {
        // Go to a record view (manually search so we can access $session)
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')->setValue('Dewey');
        $this->findCss($page, '.btn.btn-primary')->click();
        $this->clickCss($page, '.result a.title');

        // Click Print
        $this->clickCss($page, '.print-record');
        $this->waitForPageLoad($page);

        // Make sure we're printing
        $this->assertEqualsWithTimeout(
            'print=1',
            function () {
                return $this->getCurrentQueryString(true);
            }
        );
    }

    /**
     * Test rating disabled.
     *
     * @return void
     */
    public function testRatingDisabled()
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Check that rating is not displayed:
        $this->unFindCss($page, 'div.rating');
    }

    /**
     * Data provider for testRating
     *
     * @return array
     */
    public function getTestRatingData(): array
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * Test star ratings on records.
     *
     * @param bool $allowRemove Value for remove_rating config
     *
     * @dataProvider getTestRatingData
     *
     * @retryCallback removeUsername2And3And4
     *
     * @return void
     */
    public function testRating($allowRemove): void
    {
        // Set up configs:
        $this->changeConfigs(
            [
                'config' => [
                    'Social' => [
                        'rating' => true,
                        'remove_rating' => $allowRemove,
                    ],
                ],
            ]
        );
        $this->removeUsername2And3And4();

        $ratingLink = 'div.rating-average a';
        $checked = 'div.rating-average input:checked';

        // Go to a record view
        $page = $this->gotoRecord();
        // Click to add rating
        $this->clickCss($page, $ratingLink);
        // Click login link in lightbox:
        $this->clickCss($page, '.modal.in a.btn');
        // Lightbox login open?
        $this->findCss($page, '.modal.in [name="username"]');
        // Make account
        $this->makeAccount($page, 'username2');
        $this->waitForPageLoad($page);
        $this->closeLightbox($page);
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.logoutOptions a.logout');
        // Click rating link:
        $this->clickCss($page, $ratingLink);
        // Click login link in lightbox:
        $this->clickCss($page, '.modal.in a.btn');
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Click rating link again:
        $this->waitForPageLoad($page);
        $this->clickCss($page, $ratingLink);
        // Add rating
        $this->clickCss($page, '.modal form div.star-rating label', null, 10);
        $this->waitForPageLoad($page);
        $success = $this->findCss($page, '.alert-success');
        $this->assertEquals('Rating Saved', $success->getText());
        // Check result
        $this->waitForPageLoad($page);
        $inputs = $page->findAll('css', $checked);
        $this->assertCount(1, $inputs);
        $this->assertEquals('100', $inputs[0]->getValue());
        // Update rating
        $this->clickCss($page, $ratingLink);
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.modal form div.star-rating label', null, 5);
        $this->waitForPageLoad($page);
        $success = $this->findCss($page, '.alert-success');
        $this->assertEquals('Rating Saved', $success->getText());
        // Check result
        $inputs = $page->findAll('css', $checked);
        $this->assertCount(1, $inputs);
        $this->assertEquals('50', $inputs[0]->getValue());

        if ($allowRemove) {
            // Delete rating
            $this->clickCss($page, $ratingLink);
            $this->clickCss($page, '.modal-body .btn.btn-default');
            $this->waitForPageLoad($page);
            // Check result
            $inputs = $page->findAll('css', $checked);
            $this->assertCount(1, $inputs);
            $this->assertEquals('', $inputs[0]->getValue());
            // Add it back
            $this->clickCss($page, $ratingLink);
            $this->waitForPageLoad($page);
            $this->clickCss($page, '.modal form div.star-rating label', null, 5);
            $this->waitForPageLoad($page);
        } else {
            // Check that remove button is not present
            $this->clickCss($page, $ratingLink);
            $this->waitForPageLoad($page);
            $this->unFindCss($page, '.modal-body .btn.btn-default');
            $this->closeLightbox($page);
        }

        // Login with second account
        $this->clickCss($page, '.logoutOptions a.logout');
        $this->clickCss($page, '#loginOptions a');
        $this->findCss($page, '.modal.in [name="username"]');
        $this->makeAccount($page, 'username3');
        $this->waitForPageLoad($page);

        // Add rating
        $this->clickCss($page, $ratingLink);
        $this->clickCss($page, '.modal form div.star-rating label', null, 10);
        $this->waitForPageLoad($page);
        $success = $this->findCss($page, '.alert-success');
        $this->assertEquals('Rating Saved', $success->getText());
        // Check result
        $this->waitForPageLoad($page);
        $inputs = $page->findAll('css', $checked);
        $this->assertCount(1, $inputs);
        $this->assertEquals('70', $inputs[0]->getValue());

        // Login with third account
        $this->clickCss($page, '.logoutOptions a.logout');
        $this->clickCss($page, '#loginOptions a');
        $this->findCss($page, '.modal.in [name="username"]');
        $this->makeAccount($page, 'username4');
        $this->waitForPageLoad($page);

        // Add comment with rating
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->findCss($page, '.comment-form');
        $this->findCss($page, 'form.comment-form [name="comment"]')->setValue('one');
        $this->clickCss($page, 'form.comment-form div.star-rating label', null, 10);
        // Check that "Clear" link is present before submitting:
        $this->findCss($page, 'form.comment-form a');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        // Check result
        $this->waitForPageLoad($page);
        $inputs = $page->findAll('css', $checked);
        $this->assertCount(1, $inputs);
        $this->assertEquals('80', $inputs[0]->getValue());
        if ($allowRemove) {
            // Clear rating when adding another comment
            $this->findCss($page, 'form.comment-form [name="comment"]')->setValue('two');
            $this->clickCss($page, 'form.comment-form a');
            $this->clickCss($page, 'form.comment-form .btn-primary');
            // Check result
            $this->waitForPageLoad($page);
            $inputs = $page->findAll('css', $checked);
            $this->assertCount(1, $inputs);
            $this->assertEquals('70', $inputs[0]->getValue());
        } else {
            // Check that the "Clear" link is no longer available:
            $this->unFindCss($page, 'form.comment-form a');
        }

        // Logout
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Test export button found in toolbar
     *
     * @return void
     */
    public function testRefWorksExportButton()
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Click the first Export option in the drop-down menu
        $this->clickCss($page, '.export-toggle');
        $this->clickCss($page, '#export-options li a');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Send to RefWorks',
            $this->findCss($page, '#export-form input.btn.btn-primary')->getValue()
        );
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
     * Retry cleanup method in case of failure during testRating.
     *
     * @return void
     */
    protected function removeUsername2And3And4()
    {
        static::removeUsers(['username2', 'username3', 'username4']);
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
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'username2', 'username3', 'username4', 'emailmaniac']);
    }
}
