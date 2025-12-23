<?php

/**
 * List views (i.e. tabs/accordion) test class.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
final class ListViewsTest extends \VuFindTest\Integration\MinkTestCase
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
     * Perform a search and return the page after submitting the form.
     *
     * @return Element
     */
    protected function gotoSearch()
    {
        $page = $this->getSearchHomePage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:testdeweybrowse');
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);
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
        $this->clickCss($page, '.result a.title');
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Test that we can add a comment from tab mode.
     *
     * @return void
     */
    public function testCommentsInTabMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'tabs']]]
        );
        $page = $this->gotoRecord();

        // Open the user comments tab and confirm that login is required:
        $this->clickCss($page, '#usercomments_cd588d8723d65ca0ce9439e79755fa0a');
        $this->assertSame(
            'You must be logged in first',
            $this->findCssAndGetText($page, '.comment-form .btn-primary')
        );
        // Make an account
        $this->clickCss($page, '.comment-form .btn-primary');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page, ['username' => 'commenter', 'email' => 'commenter@ignore.com']);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        // Save comment
        $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', 'one');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        $this->assertEquals('one', $this->findCssAndGetText($page, '.comment-text'));
    }

    /**
     * Test that we can add a comment from accordion mode.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testCommentsInTabMode')]
    public function testCommentsInAccordionMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'accordion']]]
        );

        $page = $this->gotoRecord();

        // Open the comments tab:
        $this->clickCss($page, '#usercomments_cd588d8723d65ca0ce9439e79755fa0a');
        $this->assertSame(
            'You must be logged in first',
            $this->findCssAndGetText($page, '.comment-form .btn-primary')
        );
        // Log in:
        $this->clickCss($page, '.comment-form .btn-primary');
        $this->fillInLoginForm($page, 'commenter', 'test');
        $this->submitLoginForm($page);
        // Add comment
        $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', 'two');
        $this->clickCss($page, 'form.comment-form .btn-primary');
        // Confirm comments exist:
        $this->assertEquals('one', $this->findCssAndGetText($page, '.comment-text'));
        $this->assertEquals('two', $this->findCssAndGetText($page, '.comment-text', index: 1));
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
        $this->clickCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a');
        $this->clickCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a-content .save-record');
        // Make an account
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '#save_list');
        // Save to list
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->closeLightbox($page);
        $this->waitForPageLoad($page);
        // Check saved items status
        $this->clickCss($page, '#information_cd588d8723d65ca0ce9439e79755fa0a');
        $this->findCss($page, '#information_cd588d8723d65ca0ce9439e79755fa0a-content .savedLists ul');
    }

    /**
     * Test that we can save a favorite from accordion mode.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testFavoritesInTabMode')]
    public function testFavoritesInAccordionMode()
    {
        // Change the theme:
        $this->changeConfigs(
            ['searches' => ['List' => ['view' => 'accordion']]]
        );

        $page = $this->gotoRecord();

        // Click save inside the tools tab
        $this->clickCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a');
        $this->clickCss($page, '#tools_cd588d8723d65ca0ce9439e79755fa0a-content .save-record');
        // Login
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        // Make list
        $this->clickCss($page, '#make-list');
        $this->findCssAndSetValue($page, '#list_title', 'Test List');
        $this->findCssAndSetValue($page, '#list_desc', 'Just. THE BEST.');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        // Save to list
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->closeLightbox($page);
        // Check saved items status
        // Not visible, but still exists
        $this->clickCss($page, '#information_cd588d8723d65ca0ce9439e79755fa0a');
        $this->waitForPageLoad($page);
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
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.result a.title');
        // Did our result stay closed?
        $this->waitForPageLoad($page);
        $session->reload();
        $this->unFindCss($page, '.result.embedded');

        // Open it
        $this->clickCss($page, '.result a.title');
        $this->waitForPageLoad($page);
        // Search for anything else
        $page = $this->performSearch('anything else');
        $this->waitForPageLoad($page);
        // Come back
        $page = $this->gotoSearch();
        // Did our result close after not being being in the last search?
        $result = $page->find('css', '.result.embedded');
        $this->assertIsNotObject($result);
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
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['commenter', 'username1']);
    }
}
