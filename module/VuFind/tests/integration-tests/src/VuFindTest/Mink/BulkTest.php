<?php

/**
 * Mink bulk action test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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

use Behat\Mink\Element\Element;

/**
 * Mink bulk action test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class BulkTest extends \VuFindTest\Integration\MinkTestCase
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
     * Get a reference to a standard search results page.
     *
     * @return Element
     */
    protected function getSearchResultsPage(): Element
    {
        $session = $this->getMinkSession();
        $path = '/Search/Results?lookfor=id%3A(testsample1+OR+testsample2)';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        // Hide autocomplete menu
        $this->clickCss($page, '#side-panel-format .title');
        return $page;
    }

    /**
     * Set up a generic bulk test by configuring VuFind to include bulk options
     * and then running a search.
     *
     * @param array $extraConfig Extra config settings
     *
     * @return Element
     */
    protected function setUpGenericBulkTest($extraConfig = []): Element
    {
        $extraConfig['config']['Site'] = ['showBulkOptions' => true];
        $extraConfig['config']['Mail'] = ['testOnly' => 1];
        // Activate the bulk options:
        $this->changeConfigs($extraConfig);

        return $this->getSearchResultsPage();
    }

    /**
     * Assert that the "no items were selected" message is visible in the
     * lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function checkForNonSelectedMessage(Element $page): void
    {
        $this->assertEquals(
            'No items were selected. '
            . 'Please click on a checkbox next to an item and try again.',
            $this->findCssAndGetText($page, '.modal-body .alert-danger')
        );
    }

    /**
     * Assert that the "Selection of %%count%% items exceeds the limit of %%limit%% for this action.
     * Please select fewer items." message is visible in the lightbox.
     *
     * @param Element $page  Page element
     * @param int     $count Number of selected items
     * @param int     $limit Action limit
     *
     * @return void
     */
    protected function checkForLimitExceededMessage(Element $page, $count, $limit): void
    {
        $this->assertEquals(
            'Selection of ' . $count . ' items exceeds the limit of '
            . $limit . ' for this action. Please select fewer items.',
            $this->findCssAndGetText($page, '.modal-body .alert-danger')
        );
    }

    /**
     * Assert that the "login required" message is visible in the lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function checkForLoginMessage(Element $page): void
    {
        $warning = $this->findCss($page, '.modal-body .alert-danger');
        $this->assertIsObject($warning);
        $this->assertEquals(
            'You must be logged in first',
            $warning->getText()
        );
    }

    /**
     * Test that the email control works.
     *
     * @return void
     */
    public function testBulkEmail(): void
    {
        $page = $this->setUpGenericBulkTest();

        // First try clicking without selecting anything:
        $this->clickCss($page, '#ribbon-email');
        $this->checkForNonSelectedMessage($page);
        $this->closeLightbox($page, true);

        // Now do it for real -- we should get a login prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $this->waitStatement('$("input.checkbox-select-item:checked").length === 2');
        $this->clickCss($page, '#ribbon-email');
        $this->checkForLoginMessage($page);

        // Create an account.
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->waitForPageLoad($page);
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');

        $this->findCssAndSetValue($page, '.modal #email_from', 'asdf@asdf.com');
        $this->findCssAndSetValue($page, '.modal #email_message', 'message');
        $this->findCssAndSetValue(
            $page,
            '.modal #email_to',
            'demian.katz@villanova.edu'
        );
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Your item(s) were emailed',
            $this->findCssAndGetText($page, '.modal-body .alert-success')
        );
    }

    /**
     * Test that the save control works.
     *
     * @depends testBulkEmail
     *
     * @return void
     */
    public function testBulkSave(): void
    {
        $page = $this->setUpGenericBulkTest();

        // First try clicking without selecting anything:
        $this->clickCss($page, '#ribbon-save');
        $this->checkForNonSelectedMessage($page);
        $this->closeLightbox($page, true);

        // Now do it for real -- we should get a login prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $this->clickCss($page, '#ribbon-save');
        $this->waitForPageLoad($page);
        $this->checkForLoginMessage($page);

        // Log in to account created in previous test.
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);

        // Save the favorites.
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.modal-body input[name=submitButton]');
        $this->assertEquals(
            'Your item(s) were saved successfully. Go to List.',
            $this->findCssAndGetText($page, '.modal-body .alert-success')
        );
        // Make sure the link in the success message contains a valid list ID:
        $result = $this->findCss($page, '.modal-body .alert-success a');
        $this->assertMatchesRegularExpression(
            '|href="[^"]*/MyResearch/MyList/[0-9]+"|',
            $result->getOuterHtml()
        );

        // Click the close button.
        $this->closeLightbox($page, true);
    }

    /**
     * Test that we can bulk-delete records from a favorites list.
     *
     * @return void
     *
     * @depends testBulkSave
     */
    public function testBulkDeleteFromList(): void
    {
        // Log in to account that owns the list:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/MyResearch/Favorites');
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // Go to the list:
        $this->clickCss($page, 'a.user-list-link');
        $this->waitForPageLoad($page);

        // First try clicking without selecting anything:
        $this->clickCss($page, 'button[name="delete"]');
        $this->checkForNonSelectedMessage($page);
        $this->closeLightbox($page, true);

        // Now do it for real:
        $page->find('css', '#myresearchCheckAll')->check();
        $this->clickCss($page, 'button[name="delete"]');
        $this->waitForPageLoad($page);

        // Confirm contents of confirmation box:
        $this->assertEquals(
            'Title: Journal of rational emotive therapy : Title: Rational living.',
            $this->findCssAndGetText($page, '#modal ul.record-list')
        );
        $this->clickCss($page, '#modal input[type="submit"]');
        $this->waitForPageLoad($page);

        // If all records were deleted, success message should be visible in
        // lightbox, and delete button should be gone after lightbox is closed.
        $this->assertEquals(
            'Your saved item(s) were deleted.',
            $this->findCssAndGetText($page, '.modal .alert-success')
        );
        $this->closeLightbox($page, true);
        $this->unfindCss($page, 'button[name="delete"]');
    }

    /**
     * Test that the export control works.
     *
     * @return void
     */
    public function testBulkExport(): void
    {
        $page = $this->setUpGenericBulkTest();
        $button = $this->findCss($page, '#ribbon-export');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);
        $this->closeLightbox($page, true);

        // Now do it for real -- we should get a lightbox prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $button->click();

        // Select EndNote option
        $select = $this->findCss($page, '#format');
        $select->selectOption('EndNote');

        // Do the export:
        $submit = $this->findCss($page, '.modal-body input[name=submitButton]');
        $submit->click();
        $result = $this->findCss($page, '.modal-body .alert .text-center .btn');
        $this->assertEquals('Download File', $result->getText());
    }

    /**
     * Test that the print control works.
     *
     * @return void
     */
    public function testBulkPrint(): void
    {
        $session = $this->getMinkSession();
        $page = $this->setUpGenericBulkTest();
        $button = $this->findCss($page, '#ribbon-print');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);
        $page->find('css', '.modal-body .btn')->click();

        // Now do it for real -- we should get redirected.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $button->click();
        [, $params] = explode('?', $session->getCurrentUrl());
        $this->assertEquals(
            'print=true&id[]=Solr|testsample1&id[]=Solr|testsample2',
            str_replace(['%5B', '%5D', '%7C'], ['[', ']', '|'], $params)
        );
    }

    /**
     * Test that the print control works.
     *
     * @return void
     *
     * @depends testBulkEmail
     */
    public function testBulkActionLimits(): void
    {
        $session = $this->getMinkSession();
        $page = $this->setUpGenericBulkTest([
            'config' => [
                'BulkActions' => [
                    'limits' => [
                        'default' => 1,
                        'email' => 1,
                        'export' => 2,
                        'print' => 1,
                        'saveCart' => 2,
                        'delete' => 1,
                    ],
                ],
                'Export' => [
                    'EndNote' => 'record,bulk',
                    'MARC' => 'record,bulk',
                ],
            ],
            'export' => [
                'EndNote' => [
                    'requiredMethods' => ['getTitle'],
                    'limit' => 1,
                ],
                'MARC' => [
                    'requiredMethods' => ['getMarcReader'],
                    'limit' => 2,
                ],
            ],
        ]);
        $page->find('css', '#addFormCheckboxSelectAll')->check();

        // check email limit
        $this->clickCss($page, '#ribbon-email');
        $this->waitForPageLoad($page);
        $this->checkForLimitExceededMessage($page, 2, 1);
        $this->closeLightbox($page, true);

        // check print limit
        $this->clickCss($page, '#ribbon-print');
        $this->waitForPageLoad($page);
        $this->checkForLimitExceededMessage($page, 2, 1);
        $this->closeLightbox($page, true);

        // check saveCart limit without exceeding limit
        $this->clickCss($page, '#ribbon-save');
        $this->waitForPageLoad($page);
        $this->checkForLoginMessage($page);

        // Log in to account created in previous test.
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);

        // Save the favorites.
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.modal-body input[name=submitButton]');
        $this->assertEquals(
            'Your item(s) were saved successfully. Go to List.',
            $this->findCssAndGetText($page, '.modal-body .alert-success')
        );

        // check export limit exceeded
        $this->clickCss($page, '#ribbon-export');
        $this->waitForPageLoad($page);
        $select = $this->findCss($page, '#format');
        $select->selectOption('EndNote');
        $submit = $this->findCss($page, '.modal-body input[name=submitButton]');
        $submit->click();
        $this->checkForLimitExceededMessage($page, 2, 1);
        $this->closeLightbox($page);

        // check export limit not exceeded
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $this->clickCss($page, '#ribbon-export');
        $this->waitForPageLoad($page);
        $select = $this->findCss($page, '#format');
        $select->selectOption('MARC');
        $submit = $this->findCss($page, '.modal-body input[name=submitButton]');
        $submit->click();
        $this->assertEquals(
            'Download File',
            $this->findCssAndGetText($page, '.modal-body .alert .text-center .btn')
        );

        // check delete limit exceeded
        $session->visit($this->getVuFindUrl() . '/MyResearch/Favorites');
        $page = $session->getPage();
        $this->waitForPageLoad($page);

        // go to the list:
        $this->clickCss($page, 'a.user-list-link');
        $this->waitForPageLoad($page);

        // try deleting to many items
        $page->find('css', '#myresearchCheckAll')->check();
        $this->clickCss($page, 'button[name="delete"]');
        $this->checkForLimitExceededMessage($page, 2, 1);
        $this->closeLightbox($page, true);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers('username1');
    }
}
