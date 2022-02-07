<?php
/**
 * Mink cart test class.
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

use Behat\Mink\Element\Element;

/**
 * Mink cart test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
final class CartTest extends \VuFindTest\Integration\MinkTestCase
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
        static::failIfUsersExist();
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
     * Get a reference to a standard search results page.
     *
     * @param string $id Record ID to load.
     *
     * @return Element
     */
    protected function getRecordPage($id)
    {
        $session = $this->getMinkSession();
        $path = '/Record/' . urlencode($id);
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
     * Click the "add to cart" button with duplicate IDs selected; fail if this does
     * not display an appropriate message.
     *
     * @param Element $page       Page element
     * @param Element $updateCart Add to cart button
     *
     * @return void
     */
    protected function tryAddingDuplicatesToCart(Element $page, Element $updateCart)
    {
        // This test is a bit timing-sensitive, so introduce a retry loop before
        // completely failing.
        for ($clickRetry = 0; $clickRetry <= 4; $clickRetry++) {
            $updateCart->click();
            $content = $page->find('css', '.popover-content');
            if (is_object($content)) {
                $this->assertEquals(
                    '0 item(s) added to your Book Bag 2 item(s) are either '
                    . 'already in your Book Bag or could not be added',
                    $content->getText()
                );
                return;
            }
        }
        $this->fail('Too many retries on check for error message.');
    }

    /**
     * Add the current page of results to the cart (using the select all bulk
     * controls).
     *
     * @param Element $page        Page element
     * @param Element $updateCart  Add to cart button
     * @param string  $selectAllId ID of select all checkbox
     *
     * @return void
     */
    protected function addCurrentPageToCart(
        Element $page,
        Element $updateCart,
        $selectAllId = '#addFormCheckboxSelectAll'
    ) {
        $selectAll = $page->find('css', $selectAllId);
        $selectAll->check();
        $updateCart->click();
    }

    /**
     * Add the current page of results to the cart (using the individual add
     * buttons).
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function addCurrentPageToCartUsingButtons(Element $page)
    {
        foreach ($page->findAll('css', '.cart-add') as $button) {
            $button->click();
        }
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
     * @param array  $extraConfigs Extra config settings
     * @param string $selectAllId  ID of select all checkbox
     *
     * @return Element
     */
    protected function setUpGenericCartTest($extraConfigs = [])
    {
        // Activate the cart:
        $extraConfigs['config']['Site'] = ['showBookBag' => true];
        $this->changeConfigs($extraConfigs);

        $page = $this->getSearchResultsPage();
        $this->waitForPageLoad($page);
        $this->addCurrentPageToCartUsingButtons($page);
        $this->assertEquals('2', $this->findCss($page, '#cartItems strong')->getText());

        // Open the cart and empty it:
        $this->openCartLightbox($page);
        $this->waitForPageLoad($page);

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
     * Test that adding nothing to the cart triggers an appropriate message.
     *
     * @return void
     */
    public function testAddingNothing()
    {
        // Activate the cart:
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'showBookBag' => true,
                        'bookbagTogglesInSearch' => false
                    ]
                ]
            ]
        );

        $page = $this->getSearchResultsPage();

        // Click "add" without selecting anything.
        $updateCart = $this->findCss($page, '#updateCart');
        $this->tryAddingNothingToCart($page, $updateCart);
    }

    /**
     * Test that adding the same records to the cart multiple times triggers an
     * appropriate message.
     *
     * @return void
     */
    public function testAddingDuplicates()
    {
        // Activate the cart:
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'showBookBag' => true,
                        'bookbagTogglesInSearch' => false
                    ]
                ]
            ]
        );

        $page = $this->getSearchResultsPage();

        // Now select the same things twice:
        $updateCart = $this->findCss($page, '#updateCart');
        $this->addCurrentPageToCart($page, $updateCart);
        $this->assertEquals('2', $this->findCss($page, '#cartItems strong')->getText());
        $this->tryAddingDuplicatesToCart($page, $updateCart);
        $this->assertEquals('2', $this->findCss($page, '#cartItems strong')->getText());
    }

    /**
     * Test that the cart limit is enforced from search results.
     *
     * @return void
     */
    public function testOverfillingCart()
    {
        // Activate the cart:
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'showBookBag' => true,
                        'bookBagMaxSize' => 1,
                        'bookbagTogglesInSearch' => false
                    ]
                ]
            ]
        );

        $page = $this->getSearchResultsPage();

        // Now select the same things twice:
        $updateCart = $this->findCss($page, '#updateCart');
        $this->addCurrentPageToCart($page, $updateCart);
        $this->assertEquals('1', $this->findCss($page, '#cartItems strong')->getText());
    }

    /**
     * Test that the cart limit is enforced from record pages.
     *
     * @return void
     */
    public function testOverfillingCartFromRecordPage()
    {
        // Activate the cart:
        $this->changeConfigs(
            ['config' => ['Site' => ['showBookBag' => true, 'bookBagMaxSize' => 1]]]
        );

        $page = $this->getRecordPage('testsample1');

        // Test that we can toggle the cart item back and forth:
        $cartItems = $this->findCss($page, '#cartItems');
        $add = $this->findCss($page, '.cart-add');
        $remove = $this->findCss($page, '.cart-remove');
        $add->click();
        $this->assertEquals('Book Bag: 1 items (Full)', $cartItems->getText());
        $remove->click();
        $this->assertEquals('Book Bag: 0 items', $cartItems->getText());
        $add->click();
        $this->assertEquals('Book Bag: 1 items (Full)', $cartItems->getText());

        // Now move to another page and try to add a second item -- it should
        // not be added due to cart limit:
        $page = $this->getRecordPage('testsample2');
        $cartItems = $this->findCss($page, '#cartItems');
        $add = $this->findCss($page, '.cart-add');
        $add->click();
        $this->assertEquals('Book Bag: 1 items (Full)', $cartItems->getText());
    }

    /**
     * Test that the record "add to cart" button functions.
     *
     * @return void
     */
    public function testAddingMultipleRecordsFromRecordPage()
    {
        // Activate the cart:
        $this->changeConfigs(
            ['config' => ['Site' => ['showBookBag' => true]]]
        );

        // Test that we can add multiple records:
        for ($x = 1; $x <= 3; $x++) {
            $page = $this->getRecordPage('testsample' . $x);
            $this->clickCss($page, '.cart-add');
            $this->assertEquals(
                'Book Bag: ' . $x . ' items',
                $this->findCss($page, '#cartItems')->getText()
            );
        }
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
        $this->clickCss($page, '#cart-confirm-delete');
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
        $this->waitStatement('$("#cartItems strong").text() === "0"');
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
        $this->waitStatement('$("#cartItems strong").text() === "0"');
    }

    /**
     * Test that we can put items in the cart using the bottom checkbox/button.
     *
     * @return void
     */
    public function testFillCartUsingBottomControls()
    {
        // Activate the cart:
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'showBookBag' => true,
                        'bookbagTogglesInSearch' => false
                    ]
                ]
            ]
        );
        $page = $this->getSearchResultsPage();
        $this->addCurrentPageToCart(
            $page,
            $this->findCss($page, '#bottom_updateCart'),
            '#bottom_addFormCheckboxSelectAll'
        );
        $this->waitStatement('$("#cartItems strong").text() === "2"');
    }

    /**
     * Test that we can put items in the cart and then remove them outside of
     * the lightbox.
     *
     * @return void
     */
    public function testFillAndEmptyCartWithoutLightbox()
    {
        // Turn on limit by path setting; there used to be a bug where cookie
        // paths were set inconsistently between JS and server-side code. This
        // test should catch any regressions in that area.
        $page = $this->setUpGenericCartTest(
            ['config' => ['Cookies' => ['limit_by_path' => 1]]]
        );

        // Go to the cart page and activate the "empty" control:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Cart');
        $empty = $this->findCss($page, '#cart-empty-label');
        $empty->click();
        $emptyConfirm = $this->findCss($page, '#cart-confirm-empty');
        $emptyConfirm->click();

        // Confirm that the cart has truly been emptied:
        $this->waitStatement('$("#cartItems strong").text() === "0"');
    }

    /**
     * Test that the email control works.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testCartEmail()
    {
        $page = $this->setUpGenericCartTest(
            ['config' => ['Mail' => ['testOnly' => 1]]]
        );
        $button = $this->findCss($page, '.cart-controls button[name=email]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($page);

        // Now do it for real -- we should get a login prompt.
        $this->selectAllItemsInCart($page);
        $button->click();
        $this->waitForPageLoad($page);
        $this->checkForLoginMessage($page);

        // Create an account.
        $this->clickCss($page, '.modal-body .createAccountLink');
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
        // Check for confirmation message
        $this->assertEquals(
            'Your item(s) were emailed',
            $this->findCss($page, '.modal .alert-success')->getText()
        );
    }

    /**
     * Test that the save control works.
     *
     * @depends testCartEmail
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
        $this->waitForPageLoad($page);
        $this->checkForLoginMessage($page);

        // Log in to account created in previous test.
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);

        // Save the favorites.
        $this->clickCss($page, '.modal-body input[name=submit]');
        $result = $this->findCss($page, '.modal-body .alert-success');
        $this->assertEquals(
            'Your item(s) were saved successfully. Go to List.',
            $result->getText()
        );
        // Make sure the link in the success message contains a valid list ID:
        $result = $this->findCss($page, '.modal-body .alert-success a');
        $this->assertMatchesRegularExpression(
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
        $exportUrl = $this->getVuFindUrl('/Content/export_test');
        $page = $this->setUpGenericCartTest(
            [
                'config' => [
                    'Export' => [
                        'VuFind' => 'record,bulk',
                    ],
                ],
                'export' => [
                    'VuFind' => [
                        'requiredMethods[]' => 'getTitle',
                        'redirectUrl' => $exportUrl,
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
        $select->selectOption('VuFind');

        // Do the export:
        $session = $this->getMinkSession();
        $windowCount = count($session->getWindowNames());
        $submit = $this->findCss($page, '.modal-body input[name=submit]');
        $submit->click();
        $this->snooze();
        $windows = $session->getWindowNames();
        $this->assertEquals($windowCount + 1, count($windows));
        $session->switchToWindow($windows[$windowCount]);
        $this->waitForPageLoad($session->getPage());
        $this->assertEquals(
            $exportUrl,
            $session->getCurrentUrl()
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
        $this->waitForPageLoad($page);
        [, $params] = explode('?', $session->getCurrentUrl());
        $this->assertEquals(
            'print=true&id[]=Solr|testsample1&id[]=Solr|testsample2',
            str_replace(['%5B', '%5D', '%7C'], ['[', ']', '|'], $params)
        );
    }

    protected function assertVisible($combo, $elements, $name, $exp)
    {
        $message = $elements[$name]
            ? $name . " should be hidden.\n" . print_r($combo, true)
            : $name . " should be visible.\n" . print_r($combo, true);
        $this->assertEquals($elements[$name], $exp, $message);
    }

    protected function runConfigCombo($page, $combo)
    {
        $this->changeConfigs(['config' => ['Site' => $combo]]);
        $this->getMinkSession()->reload();
        $this->waitForPageLoad($page);
        $elements = [
            'headerBtn'  => $page->find('css', '#cartItems') !== null,
            'bulkEmail'  => $page->find('css', '#ribbon-email') !== null,
            'bulkUpdateCart' => $page->find('css', '#updateCart') !== null,
            'resultCartBtns'   => $page->find('css', '.result .btn-bookbag-toggle') !== null,
            'resultCheckbox'   => $page->find('css', '.result .checkbox-select-item') !== null,
        ];
        // Expected
        $this->assertVisible($combo, $elements, 'headerBtn', $combo['showBookBag']);
        $this->assertVisible($combo, $elements, 'bulkEmail', $combo['showBulkOptions']);
        $this->assertVisible($combo, $elements, 'bulkUpdateCart', $combo['showBookBag'] && ($combo['showBulkOptions'] || !$combo['bookbagTogglesInSearch']));
        $this->assertVisible($combo, $elements, 'resultCartBtns', $combo['showBookBag'] && $combo['bookbagTogglesInSearch']);
        $this->assertVisible($combo, $elements, 'resultCheckbox', $elements['bulkEmail'] || $elements['bulkUpdateCart']);
        return $elements;
    }

    public function testToolbarVisibilityConfigCombinations()
    {
        $page = $this->getSearchResultsPage();
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => false,
                'bookbagTogglesInSearch' => false,
            ]
        );
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => false,
                'showBulkOptions' => false,
                'bookbagTogglesInSearch' => true,
            ]
        );
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => false,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => false,
            ]
        );
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => false,
                'bookbagTogglesInSearch' => true,
            ]
        );
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => false,
            ]
        );
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => false,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => true,
            ]
        );
        $elements = $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => true,
            ]
        );
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
