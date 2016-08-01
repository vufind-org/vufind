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
 * Mink cart test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CartTest extends \VuFindTest\Unit\MinkTestCase
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
     * Get a reference to a standard search results page.
     *
     * @return Element
     */
    protected function getSearchResultsPage()
    {
        $session = $this->getMinkSession();
        $path = '/Search/Results?lookfor=id%3A(testsample1+OR+testsample2)';
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Click the "add to cart" button with nothing selected; fail if this does
     * not display an appropriate message.
     *
     * @param Element $page       Page element
     * @param Element $updateCart Add to cart button
     *
     * @return void
     */
    protected function tryAddingNothingToCart(Element $page, Element $updateCart)
    {
        // This test is a bit timing-sensitive, so introduce a retry loop before
        // completely failing.
        for ($clickRetry = 0; $clickRetry <= 4; $clickRetry++) {
            $updateCart->click();
            $content = $page->find('css', '.popover-content');
            if (is_object($content)) {
                $this->assertEquals(
                    'No items were selected. '
                    . 'Please click on a checkbox next to an item and try again.',
                    $content->getText()
                );
                return;
            }
        }
        $this->fail('Too many retries on check for error message.');
    }

    /**
     * Add the current page of results to the cart.
     *
     * @param Element $page       Page element
     * @param Element $updateCart Add to cart button
     *
     * @return void
     */
    protected function addCurrentPageToCart(Element $page, Element $updateCart)
    {
        $selectAll = $page->find('css', '#addFormCheckboxSelectAll');
        $selectAll->check();
        $updateCart->click();
    }

    /**
     * Open the cart lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function openCartLightbox(Element $page)
    {
        $viewCart = $page->find('css', '#cartItems');
        $this->assertTrue(is_object($viewCart));
        $viewCart->click();
    }

    /**
     * Set up a generic cart test by running a search and putting its results
     * into the cart, then opening the lightbox so that additional actions may
     * be attempted.
     *
     * @return Element
     */
    protected function setUpGenericCartTest($extraConfigs = [])
    {
        // Activate the cart:
        $extraConfigs['config']['Site']
            = ['showBookBag' => true, 'theme' => 'bootprint3'];
        $extraConfigs['config']['Mail']
            = ['testOnly' => 1];
        $this->changeConfigs($extraConfigs);

        $page = $this->getSearchResultsPage();

        // Click "add" without selecting anything.
        $updateCart = $this->findCss($page, '#updateCart');
        $this->tryAddingNothingToCart($page, $updateCart);

        // Now actually select something:
        $this->addCurrentPageToCart($page, $updateCart);
        $this->assertEquals('2', $this->findCss($page, '#cartItems strong')->getText());

        // Open the cart and empty it:
        $this->openCartLightbox($page);

        return $page;
    }

    /**
     * Assert that the open cart lightbox is empty.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function checkEmptyCart(Element $page)
    {
        $info = $this->findCss($page, '.modal-body .form-inline .alert-info');
        $this->assertEquals('Your Book Bag is empty.', $info->getText());
    }

    /**
     * Assert that the "no items were selected" message is visible in the cart
     * lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function checkForNonSelectedMessage(Element $page)
    {
        $warning = $this->findCss($page, '.modal-body .alert');
        $this->assertEquals(
            'No items were selected. '
            . 'Please click on a checkbox next to an item and try again.',
            $warning->getText()
        );
    }

    /**
     * Assert that the "login required" message is visible in the cart lightbox.
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
     * Select all of the items currently in the cart lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function selectAllItemsInCart(Element $page)
    {
        $cartSelectAll = $page->find('css', '.modal-dialog .checkbox-select-all');
        $cartSelectAll->check();
    }

    /**
     * Test that we can put items in the cart and then remove them with the
     * delete control.
     *
     * @return void
     */
    public function testFillAndDeleteFromCart()
    {
        $page = $this->setUpGenericCartTest();
        $delete = $this->findCss($page, '#cart-delete-label');

        // First try deleting without selecting anything:
        $delete->click();
        $this->findCss($page, '#cart-confirm-delete')->click();
        $this->checkForNonSelectedMessage($page);

        // Now actually select the records to delete:
        $this->selectAllItemsInCart($page);
        $delete->click();
        $deleteConfirm = $this->findCss($page, '#cart-confirm-delete');
        $deleteConfirm->click();
        $this->checkEmptyCart($page);

        // Close the lightbox:
        $close = $this->findCss($page, 'button.close');
        $close->click();

        // Confirm that the cart has truly been emptied:
        $this->snooze(); // wait for display to update
        $this->assertEquals('0', $this->findCss($page, '#cartItems strong')->getText());
    }

    /**
     * Test that we can put items in the cart and then remove them with the
     * empty button.
     *
     * @return void
     */
    public function testFillAndEmptyCart()
    {
        $page = $this->setUpGenericCartTest();

        // Activate the "empty" control:
        $empty = $this->findCss($page, '#cart-empty-label');
        $empty->click();
        $emptyConfirm = $this->findCss($page, '#cart-confirm-empty');
        $emptyConfirm->click();
        $this->checkEmptyCart($page);

        // Close the lightbox:
        $close = $this->findCss($page, 'button.close');
        $close->click();

        // Confirm that the cart has truly been emptied:
        $this->snooze(); // wait for display to update
        $this->assertEquals('0', $this->findCss($page, '#cartItems strong')->getText());
    }

    /**
     * Test that the email control works.
     *
     * @return void
     */
    public function testCartEmail()
    {
        $page = $this->setUpGenericCartTest();
        $button = $this->findCss($page, '.cart-controls button[name=email]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);

        // Now do it for real -- we should get a login prompt.
        $this->selectAllItemsInCart($page);
        $button->click();
        $this->snooze();
        $this->checkForLoginMessage($page);

        // Create an account.
        $this->findCss($page, '.modal-body .createAccountLink')->click();
        $this->fillInAccountForm($page);
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();

        $this->findCss($page, '.modal #email_from')->setValue('asdf@asdf.com');
        $this->findCss($page, '.modal #email_message')->setValue('message');
        $this->findCss($page, '.modal #email_to')
            ->setValue('demian.katz@villanova.edu');
        $this->findCss($page, '.modal-body .btn.btn-primary')->click();
        $this->snooze();
        // Check for confirmation message
        $this->assertEquals(
            'Your item(s) were emailed',
            $this->findCss($page, '.modal .alert-success')->getText()
        );
    }

    /**
     * Test that the save control works.
     *
     * @return void
     */
    public function testCartSave()
    {
        $page = $this->setUpGenericCartTest();
        $button = $this->findCss($page, '.cart-controls button[name=saveCart]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);

        // Now do it for real -- we should get a login prompt.
        $this->selectAllItemsInCart($page);
        $button->click();
        $this->snooze();
        $this->checkForLoginMessage($page);

        // Log in to account created in previous test.
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);

        // Save the favorites.
        $this->snooze();
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
    public function testCartExport()
    {
        $page = $this->setUpGenericCartTest();
        $button = $this->findCss($page, '.cart-controls button[name=export]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);

        // Now do it for real -- we should get an export option list:
        $this->selectAllItemsInCart($page);
        $button->click();

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
     * Test that the export control works when redirecting to a third-party site.
     *
     * @return void
     */
    public function testCartExportToThirdParty()
    {
        $page = $this->setUpGenericCartTest(
            [
                'config' => [
                    'Export' => [
                        'Google' => 'record,bulk',
                    ],
                ],
                'export' => [
                    'Google' => [
                        'requiredMethods[]' => 'getTitle',
                        'redirectUrl' => 'https://www.google.com',
                        'headers[]' => 'Content-type: text/plain; charset=utf-8',
                    ],
                ],
            ]
        );
        $button = $this->findCss($page, '.cart-controls button[name=export]');

        // Go to export option list:
        $this->selectAllItemsInCart($page);
        $button->click();

        // Select EndNote option
        $select = $this->findCss($page, '#format');
        $select->selectOption('Google');

        // Do the export:
        $submit = $this->findCss($page, '.modal-body input[name=submit]');
        $submit->click();
        $this->snooze();
        $this->assertEquals(
            'https://www.google.com/', $this->getMinkSession()->getCurrentUrl()
        );
    }

    /**
     * Test that the print control works.
     *
     * @return void
     */
    public function testCartPrint()
    {
        $session = $this->getMinkSession();
        $page = $this->setUpGenericCartTest();
        $button = $this->findCss($page, '.cart-controls button[name=print]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);

        // Now do it for real -- we should get redirected.
        $this->selectAllItemsInCart($page);
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
