<?php

/**
 * Mink record actions test class.
 *
 * PHP version 8
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

use Behat\Mink\Element\Element;

use function count;
use function intval;
use function strlen;

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
 */
final class RecordActionsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\AutocompleteTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\SearchSortTrait;
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
     * @param string $query Search query to perform.
     *
     * @return Element
     */
    protected function gotoRecord(string $query = 'Dewey'): Element
    {
        $page = $this->performSearch($query);
        $this->clickCss($page, '.result a.title');
        return $page;
    }

    /**
     * Make new account
     *
     * @param Element $page     Page element
     * @param string  $username Username to create
     *
     * @return void
     */
    protected function makeAccount(Element $page, string $username): void
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
     * @return void
     */
    public function testAddComment(): void
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Click add comment without logging in
        // TODO Rewrite for comment and login coming
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->findCss($page, '.comment-form');
        $this->assertEquals(// Can Comment?
            'You must be logged in first',
            $this->findCssAndGetText($page, 'form.comment-form .btn.btn-primary')
        );
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, $this->openModalSelector); // Lightbox open
        $this->findCss($page, '.modal [name="username"]');
        // Create new account
        $this->makeAccount($page, 'username1');
        $this->waitForLightboxHidden();
        // Make sure page updated for login
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->waitForPageLoad($page);
        $this->assertEquals(// Can Comment?
            'Add your comment',
            $this->findCssAndGetValue($page, 'form.comment-form .btn.btn-primary')
        );
        // "Add" empty comment
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->unFindCss($page, '.comment');
        // Add comment
        $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', 'one');
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
     *
     * @depends testAddComment
     */
    public function testAddCommentWithCaptcha(): void
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
            $this->findCssAndGetText($page, 'form.comment-form .btn.btn-primary')
        );
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, $this->openModalSelector); // Lightbox open
        $this->findCss($page, $this->openModalUsernameFieldSelector);
        // Log in to existing account
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        // Make sure page updated for login
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->assertEquals(// Can Comment?
            'Add your comment',
            $this->findCssAndGetValue($page, 'form.comment-form .btn.btn-primary')
        );
        // "Add" empty comment
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->unFindCss($page, '.comment');
        // Add comment without CAPTCHA
        $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', 'one');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->assertEquals(
            'CAPTCHA not passed',
            $this->findCssAndGetText($page, '.modal-body .alert-danger')
        );
        $this->closeLightbox($page);
        // Now fix the CAPTCHA
        $this->findCssAndSetValue($page, 'form.comment-form [name="demo_captcha"]', 'demo');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->findCss($page, '.comment');
        // Remove comment
        $this->clickCss($page, '.comment .delete');
        $this->unFindCss($page, '.comment');
        // Logout
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Add tags to a record
     *
     * @param Element $page Page object
     * @param string  $tags Tag(s) to add
     * @param ?string $user Username to log in with (null if already logged in)
     * @param ?string $pass Password to log in with (null if already logged in)
     *
     * @return void
     */
    protected function addTagsToRecord(
        Element $page,
        string $tags,
        ?string $user = null,
        ?string $pass = null
    ): void {
        $this->clickCss($page, '.tag-record');
        // Login if necessary
        if (!empty($user) && !empty($pass)) {
            $this->fillInLoginForm($page, $user, $pass);
            $this->submitLoginForm($page);
        }
        // Add tags
        $this->findCssAndSetValue($page, '.modal #addtag_tag', $tags);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals('Tags Saved', $this->findCssAndGetText($page, '.modal-body .alert-success'));
        $this->closeLightbox($page);
    }

    /**
     * Test adding tags on records.
     *
     * @return void
     *
     * @depends testAddComment
     */
    public function testAddTag(): void
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Click to add tag
        $this->clickCss($page, '.tag-record');
        // Lightbox login open?
        $this->findCss($page, $this->openModalUsernameFieldSelector);
        // Make account
        $this->makeAccount($page, 'username2');
        // Add tag exists?
        $this->findCss($page, '.modal #addtag_tag');
        $this->closeLightbox($page);
        $this->clickCss($page, '.logoutOptions a.logout');
        $this->addTagsToRecord($page, 'one 2 "three 4" five', 'username2', 'test');
        // Count tags
        $this->waitForPageLoad($page);
        $tags = $page->findAll('css', '.tagList .tag');
        $this->assertCount(4, $tags);
        $tvals = [];
        foreach ($tags as $t) {
            $tvals[] = $this->findCssAndGetText($t, 'a');
        }
        sort($tvals);
        $this->assertEquals($tvals, ['2', 'five', 'one', 'three 4']);
        // Remove a tag
        $this->clickCss($tags[0], 'button');
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
        $this->findCss($page, $this->openModalUsernameFieldSelector);
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
     *
     * @depends testAddTag
     */
    public function testTagSearch(): void
    {
        // First try an undefined tag:
        $page = $this->performSearch('tag-not-in-system', 'tag');
        $this->assertEquals('No Results!', $this->findCssAndGetText($page, 'h2'));
        // Now try a tag defined earlier, with a couple more instances added:
        $page = $this->goToRecord('id:"<angle>brackets&ampersands"');
        $this->addTagsToRecord($page, 'five', 'username2', 'test');
        $page = $this->goToRecord('id:"017791359-1"');
        $this->addTagsToRecord($page, 'five');
        // Now perform the search:
        $page = $this->performSearch('five', 'tag');
        $this->assertResultTitles($page, 3, 'Dewey browse test', '<HTML> The Basics');
        $this->assertSelectedSort($page, 'title');
    }

    /**
     * Data provider for testTagSearchSort
     *
     * @return array
     */
    public static function getTagSearchSortData(): array
    {
        return [
            [1, 'author', 'Fake Record 1 with multiple relators/', 'Dewey browse test'],
            [2, 'year DESC', '<HTML> The Basics', 'Fake Record 1 with multiple relators/'],
            [3, 'year', 'Fake Record 1 with multiple relators/', '<HTML> The Basics'],
        ];
    }

    /**
     * Test sorting the tag search results.
     *
     * @param int    $index         Sort drop-down index to test
     * @param string $expectedSort  Expected sort value at $index
     * @param string $expectedFirst Expected first title after sorting
     * @param string $expectedLast  Expected last title after sorting
     *
     * @return void
     *
     * @dataProvider getTagSearchSortData
     *
     * @depends testTagSearch
     */
    public function testTagSearchSort(
        int $index,
        string $expectedSort,
        string $expectedFirst,
        string $expectedLast
    ): void {
        $page = $this->performSearch('five', 'tag');
        $this->clickCss($page, $this->sortControlSelector . ' option', null, $index);
        $this->waitForPageLoad($page);
        $this->assertResultTitles($page, 3, $expectedFirst, $expectedLast);
        $this->assertSelectedSort($page, $expectedSort);
    }

    /**
     * Test that default autocomplete behavior is correct on a non-default search handler.
     *
     * @return void
     *
     * @depends testTagSearch
     */
    public function testTagAutocomplete(): void
    {
        $session = $this->getMinkSession();
        $page = $this->getSearchHomePage($session);
        $acItem = $this->assertAutocompleteValueAndReturnItem($page, 'fiv', 'five', 'tag');
        $acItem->click();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=five&type=tag',
            $session->getCurrentUrl()
        );
        $expected = 'Showing 1 - 3 results of 3';
        $this->assertEquals(
            $expected,
            substr(
                $this->findCssAndGetText($page, '.search-stats'),
                0,
                strlen($expected)
            )
        );
    }

    /**
     * Test adding case sensitive tags on records.
     *
     * @return void
     *
     * @depends testAddTag
     */
    public function testAddSensitiveTag(): void
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
        $this->findCssAndSetValue($page, '.modal #addtag_tag', 'one ONE "new tag" ONE "THREE 4"');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals('Tags Saved', $this->findCssAndGetText($page, '.modal-body .alert-success'));
        $this->closeLightbox($page);
        // Count tags
        $this->waitForPageLoad($page);
        $tags = $page->findAll('css', '.tagList .tag');
        $this->assertCount(6, $tags);
    }

    /**
     * Set up and access the Tag Admin page.
     *
     * @param string $subPage The tag admin sub-page (optional)
     *
     * @return Element
     */
    protected function goToTagAdmin(string $subPage = ''): Element
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => ['admin_enabled' => 1],
                    'Social' => ['case_sensitive_tags' => 'true'],
                ],
            ],
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Admin/Tags' . $subPage));
        return $session->getPage();
    }

    /**
     * Test that the tag admin module works.
     *
     * @return void
     *
     * @depends testTagSearch
     * @depends testAddSensitiveTag
     */
    public function testTagAdminHome(): void
    {
        // Go to admin page:
        $page = $this->goToTagAdmin();
        $this->assertEquals(
            'Total Users Total Resources Total Tags Unique Tags Anonymous Tags 1 3 8 6 0',
            $this->findCss($page, 'table.table-striped')->getText()
        );
    }

    /**
     * Test that listing tags in Admin works.
     *
     * @return void
     *
     * @depends testTagSearch
     * @depends testAddSensitiveTag
     */
    public function testTagAdminList(): void
    {
        $page = $this->goToTagAdmin('/List');

        // We expect the three feedback entries created by the previous test:
        $this->assertCount(8, $page->findAll('css', 'input[name="ids[]"]'));

        // We expect specific form name and site URL values:
        $this->assertEquals('All username2', $this->findCss($page, '#user_id')->getText());
        // We need to do a case-insensitive comparison here because different database engines
        // may make different decisions about uppercase-first vs. lowercase-first:
        $this->assertEquals(
            strtolower('All five new tag ONE one THREE 4 three 4'),
            strtolower($this->findCss($page, '#tag_id')->getText())
        );

        // Apply a filter to see just the "five" tag (we need to extract the ID value
        // from the text of the list).
        $firstTag = $this->findCss($page, 'td')->getText();
        $tagId = preg_replace('/five \((.*)\)/', '$1', $firstTag);
        $this->assertTrue(intval($tagId) > 0, "Could not extract integer from '$firstTag'");
        $this->findCss($page, '#tag_id')->setValue($tagId);
        $this->clickCss($page, '#taglistsubmit');
        $this->waitForPageLoad($page);
        $this->assertCount(3, $page->findAll('css', 'input[name="ids[]"]'));

        // Now delete the tags and confirm that they are gone:
        $this->clickCss($page, 'input[name="deletePage"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Warning! You are about to delete 3 resource tag(s)',
            $this->findCss($page, '.alert-info')->getText()
        );
        $this->clickCss($page, 'input[value="Yes"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '3 tag(s) deleted',
            $this->findCss($page, '.alert-success')->getText()
        );
        $this->assertCount(0, $page->findAll('css', 'input[name="ids[]"]'));

        // Clear the filter; there should be two items left:
        $page->clickLink('Clear Filter');
        $this->waitForPageLoad($page);
        $this->assertCount(5, $page->findAll('css', 'input[name="ids[]"]'));
    }

    /**
     * Test that managing tags in Admin works.
     *
     * @return void
     *
     * @depends testTagAdminList
     */
    public function testTagAdminManage(): void
    {
        $page = $this->goToTagAdmin('/Manage');

        // First, delete the first tag:
        $this->findCss($page, '#type')->setValue('tag');
        $this->clickCss($page, 'input[value="Submit"]');
        $this->waitForPageLoad($page);
        // We need to do a case-insensitive comparison here because different database engines
        // may make different decisions about uppercase-first vs. lowercase-first:
        $this->assertEquals(
            strtolower('new tag ONE one THREE 4 three 4'),
            strtolower($this->findCss($page, '#tag_id')->getText())
        );
        $this->clickCss($page, 'input[value="Delete Tags"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Warning! You are about to delete 1 resource tag(s)',
            $this->findCss($page, '.alert-info')->getText()
        );
        $this->assertStringContainsString(
            'Tag: new tag (',
            $this->findCss($page, '.alert-info', index: 1)->getText()
        );
        $this->clickCss($page, 'input[value="Yes"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '1 tag(s) deleted',
            $this->findCss($page, '.alert-success')->getText()
        );

        // Now, start to delete tags by the first title, but opt out:
        $this->findCss($page, '#type')->setValue('resource');
        $this->clickCss($page, 'input[value="Submit"]');
        $this->waitForPageLoad($page);
        $this->assertStringMatchesFormat(
            'dewey browse test (%d)',
            $this->findCss($page, '#resource_id')->getText()
        );
        $this->clickCss($page, 'input[value="Delete Tags"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Warning! You are about to delete 4 resource tag(s)',
            $this->findCss($page, '.alert-info')->getText()
        );
        $this->assertStringContainsString(
            'You are using the following filter - Username: All, Tag: All, Resource: dewey browse test (',
            $this->findCss($page, '.alert-info', index: 1)->getText()
        );
        $this->clickCss($page, 'input[value="No"]');
        $this->waitForPageLoad($page);

        // We can now clean up the remaining tags by wiping them out by username (which should be the default):
        $this->assertEquals('user', $this->findCss($page, '#type')->getValue());
        $this->clickCss($page, 'input[value="Submit"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'username2',
            $this->findCss($page, '#user_id')->getText()
        );
        $this->clickCss($page, 'input[value="Delete Tags"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Warning! You are about to delete 4 resource tag(s)',
            $this->findCss($page, '.alert-info')->getText()
        );
        $this->assertStringContainsString(
            'You are using the following filter - Username: username2 (',
            $this->findCss($page, '.alert-info', index: 1)->getText()
        );
        $this->clickCss($page, 'input[value="Yes"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '4 tag(s) deleted',
            $this->findCss($page, '.alert-success')->getText()
        );
    }

    /**
     * Test record view email.
     *
     * @return void
     */
    public function testEmail(): void
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
        $this->findCss($page, $this->openModalUsernameFieldSelector);
        // Make account
        $this->makeAccount($page, 'emailmaniac');
        // Make sure Lightbox redirects to email view
        $this->findCss($page, '.modal #email_to');
        // Type invalid email
        $this->findCssAndSetValue($page, '.modal #email_to', 'blargarsaurus');
        $this->findCssAndSetValue($page, '.modal #email_from', 'asdf@asdf.com');
        $this->findCssAndSetValue($page, '.modal #email_message', 'message');
        // Send text to false email
        $this->findCssAndSetValue($page, '.modal #email_to', 'asdf@vufind.org');
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
        $this->findCss($page, ' [name="username"]');
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
        $this->findCssAndSetValue($page, '.modal #email_to', 'asdf@vufind.org');
        $this->findCssAndSetValue($page, '.modal #email_from', 'asdf@vufind.org');
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
    public function testSMS(): void
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
        $this->findCssAndSetValue($page, '.modal #sms_to', '');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->checkFieldIsInvalid($page, '.modal #sms_to');
        // - too short
        $this->findCssAndSetValue($page, '.modal #sms_to', '123');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->checkFieldIsInvalid($page, '.modal #sms_to');
        // - too long
        $this->findCssAndSetValue($page, '.modal #sms_to', '12345678912345678912345679');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->checkFieldIsInvalid($page, '.modal #sms_to');
        // - too lettery
        $this->findCssAndSetValue($page, '.modal #sms_to', '123abc');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->checkFieldIsInvalid($page, '.modal #sms_to');
        // - just right
        $this->findCssAndSetValue($page, '.modal #sms_to', '8005555555');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page); // wait for form submission to catch missing carrier
        $this->checkFieldIsValid($page, '.modal #sms_to');

        // - pretty just right
        $this->findCssAndSetValue($page, '.modal #sms_to', '(800) 555-5555');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page); // wait for form submission to catch missing carrier
        $this->checkFieldIsValid($page, '.modal #sms_to');
        // Send text to false number
        $this->findCssAndSetValue($page, '.modal #sms_to', '(800) 555-5555');
        $this->findCss($page, '.modal #sms_provider option');
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
        $page = $this->performSearch('Dewey');
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
    public function testRatingDisabled(): void
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
    public static function getTestRatingData(): array
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
        $this->clickCss($page, $this->openModalButtonLinkSelector);
        // Lightbox login open?
        $this->findCss($page, $this->openModalUsernameFieldSelector);
        // Make account
        $this->makeAccount($page, 'username2');
        $this->waitForPageLoad($page);
        $this->closeLightbox($page);
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.logoutOptions a.logout');
        // Click rating link:
        $this->clickCss($page, $ratingLink);
        // Click login link in lightbox:
        $this->clickCss($page, $this->openModalButtonLinkSelector);
        $this->fillInLoginForm($page, 'username2', 'test');
        $this->submitLoginForm($page);
        // Click rating link again:
        $this->waitForPageLoad($page);
        $this->clickCss($page, $ratingLink);
        // Add rating
        $this->clickCss($page, '.modal form div.star-rating label', null, 10);
        $this->waitForPageLoad($page);
        $this->assertEquals('Rating Saved', $this->findCssAndGetText($page, '.alert-success'));
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
        $this->assertEquals('Rating Saved', $this->findCssAndGetText($page, '.alert-success'));
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
        $this->findCss($page, $this->openModalUsernameFieldSelector);
        $this->makeAccount($page, 'username3');
        $this->waitForPageLoad($page);

        // Add rating
        $this->clickCss($page, $ratingLink);
        $this->clickCss($page, '.modal form div.star-rating label', null, 10);
        $this->waitForPageLoad($page);
        $this->assertEquals('Rating Saved', $this->findCssAndGetText($page, '.alert-success'));
        // Check result
        $this->waitForPageLoad($page);
        $inputs = $page->findAll('css', $checked);
        $this->assertCount(1, $inputs);
        $this->assertEquals('70', $inputs[0]->getValue());

        // Login with third account
        $this->clickCss($page, '.logoutOptions a.logout');
        $this->clickCss($page, '#loginOptions a');
        $this->findCss($page, $this->openModalUsernameFieldSelector);
        $this->makeAccount($page, 'username4');
        $this->waitForPageLoad($page);

        // Add comment with rating
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->waitForPageLoad($page);
        $this->findCss($page, '.comment-form');
        $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', 'one');
        $this->clickCss($page, 'form.comment-form div.star-rating label', null, 10);
        // Check that "Clear" link is present before submitting:
        $this->findCss($page, 'form.comment-form a');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        // Check result (wait for the value to update):
        $this->assertEqualsWithTimeout(
            [1, '80'],
            function () use ($page, $checked) {
                $inputs = $page->findAll('css', $checked);
                return [count($inputs), $inputs ? $inputs[0]->getValue() : null];
            }
        );
        if ($allowRemove) {
            // Clear rating when adding another comment
            $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', 'two');
            $this->clickCss($page, 'form.comment-form a');
            $this->clickCss($page, 'form.comment-form .btn-primary');
            // Check result (wait for the value to update):
            $this->assertEqualsWithTimeout(
                [1, '70'],
                function () use ($page, $checked) {
                    $inputs = $page->findAll('css', $checked);
                    return [count($inputs), $inputs ? $inputs[0]->getValue() : null];
                }
            );
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
    public function testRefWorksExportButton(): void
    {
        // Go to a record view
        $page = $this->gotoRecord();
        // Click the first Export option in the drop-down menu
        $this->clickCss($page, '.export-toggle');
        $this->clickCss($page, '#export-options li a');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Send to RefWorks',
            $this->findCssAndGetValue($page, '#export-form input.btn.btn-primary')
        );
    }

    /**
     * Retry cleanup method in case of failure during testRating.
     *
     * @return void
     */
    protected function removeUsername2And3And4(): void
    {
        static::removeUsers(['username2', 'username3', 'username4']);
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
