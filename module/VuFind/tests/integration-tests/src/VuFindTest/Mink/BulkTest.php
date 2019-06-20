<?php
/**
 * Mink bulk action test class.
 *
 * PHP version 7
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
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class BulkTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\AutoRetryTrait;
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
     * Get a reference to a standard search results page.
     *
     * @return Element
     */
    protected function getSearchResultsPage()
    {
        $session = $this->getMinkSession();
        $path = '/Search/Results?lookfor=id%3A(testsample1+OR+testsample2)';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        // Hide autocomplete menu
        $this->findCss($page, '#side-panel-format .title')->click();
        return $page;
    }

    /**
     * Set up a generic bulk test by configuring VuFind to include bulk options
     * and then running a search.
     *
     * @return Element
     */
    protected function setUpGenericBulkTest($checkBoxes = true)
    {
        // Activate the bulk options:
        $this->changeConfigs(
            ['config' =>
                [
                    'Site' => ['showBulkOptions' => true],
                    'Mail' => ['testOnly' => 1],
                ],
            ]
        );

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
    protected function checkForNonSelectedMessage(Element $page)
    {
        $warning = $this->findCss($page, '.modal-body .alert-danger');
        $this->assertEquals(
            'No items were selected. '
            . 'Please click on a checkbox next to an item and try again.',
            $warning->getText()
        );
    }

    /**
     * Assert that the "login required" message is visible in the lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function checkForLoginMessage(Element $page)
    {
        $warning = $page->find('css', '.modal-body .alert-danger');
        $this->assertTrue(is_object($warning));
        $this->assertEquals(
            'You must be logged in first',
            $warning->getText()
        );
    }

    /**
     * Test that the email control works.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testBulkEmail()
    {
        $page = $this->setUpGenericBulkTest();
        $button = $this->findCss($page, '#ribbon-email');

        // First try clicking without selecting anything:
        $button->click();
        $this->snooze();
        $this->checkForNonSelectedMessage($page);
        $page->find('css', '.modal-body .btn')->click();
        $this->snooze();

        // Now do it for real -- we should get a login prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $button->click();
        $this->snooze();
        $this->checkForLoginMessage($page);

        // Create an account.
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();

        $this->findCssAndSetValue($page, '.modal #email_from', 'asdf@asdf.com');
        $this->findCssAndSetValue($page, '.modal #email_message', 'message');
        $this->findCssAndSetValue(
            $page, '.modal #email_to', 'demian.katz@villanova.edu'
        );
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        /* TODO: add back this check when everything is working (as of this
         * writing, the pop-up message is inexplicably missing... but we should
         * fix this soon!
        // Check for confirmation message
        $this->assertEquals(
            'Your item(s) were emailed',
            $this->findCss($page, '.modal-body .alert-success')->getText()
        );
         */
    }

    /**
     * Test that the save control works.
     *
     * @return void
     */
    public function testBulkSave()
    {
        $page = $this->setUpGenericBulkTest();
        $button = $this->findCss($page, '#ribbon-save');

        // First try clicking without selecting anything:
        $button->click();
        $this->snooze();
        $this->checkForNonSelectedMessage($page);
        $page->find('css', '.modal-body .btn')->click();
        $this->snooze();

        // Now do it for real -- we should get a login prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $button->click();
        $this->snooze();
        $this->checkForLoginMessage($page);

        // Log in to account created in previous test.
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);

        // Save the favorites.
        $this->findCss($page, '.modal-body input[name=submit]')->click();
        $this->snooze();
        $result = $this->findCss($page, '.modal-body .alert-success');
        $this->assertEquals(
            'Your item(s) were saved successfully. Go to List.', $result->getText()
        );
        // Make sure the link in the success message contains a valid list ID:
        $result = $this->findCss($page, '.modal-body .alert-success a');
        $this->assertRegExp(
            '|href="[^"]*/MyResearch/MyList/[0-9]+"|',
            $result->getOuterHtml()
        );

        // Click the close button.
        $submit = $this->findCss($page, '.modal-body .btn');
        $this->assertEquals('close', $submit->getText());
        $submit->click();
    }

    /**
     * Test that the export control works.
     *
     * @return void
     */
    public function testBulkExport()
    {
        $page = $this->setUpGenericBulkTest();
        $button = $this->findCss($page, '#ribbon-export');

        // First try clicking without selecting anything:
        $button->click();
        $this->snooze();
        $this->checkForNonSelectedMessage($page);
        $page->find('css', '.modal-body .btn')->click();
        $this->snooze();

        // Now do it for real -- we should get a lightbox prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $button->click();
        $this->snooze();

        // Select EndNote option
        $select = $this->findCss($page, '#format');
        $select->selectOption('EndNote');

        // Do the export:
        $submit = $this->findCss($page, '.modal-body input[name=submit]');
        $submit->click();
        $this->snooze();
        $result = $this->findCss($page, '.modal-body .alert .text-center .btn');
        $this->assertEquals('Download File', $result->getText());
    }

    /**
     * Test that the print control works.
     *
     * @return void
     */
    public function testBulkPrint()
    {
        $session = $this->getMinkSession();
        $page = $this->setUpGenericBulkTest();
        $button = $this->findCss($page, '#ribbon-print');

        // First try clicking without selecting anything:
        $button->click();
        $this->snooze();
        $this->checkForNonSelectedMessage($page);
        $page->find('css', '.modal-body .btn')->click();
        $this->snooze();

        // Now do it for real -- we should get redirected.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $button->click();
        $this->snooze();
        list(, $params) = explode('?', $session->getCurrentUrl());
        $this->assertEquals(
            'print=true&id[]=Solr|testsample1&id[]=Solr|testsample2',
            str_replace(['%5B', '%5D', '%7C'], ['[', ']', '|'], $params)
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers('username1');
    }
}
