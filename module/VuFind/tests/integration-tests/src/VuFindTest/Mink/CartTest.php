<?php

/**
 * Mink cart test class.
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

use function count;
use function is_object;

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
        static::failIfDataExists();
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
        $this->waitForPageLoad($page);
        return $page;
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
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Click the "add to cart" button with nothing selected; fail if this does
     * not display an appropriate message.
     *
     * @param Element $page         Page element
     * @param string  $updateCartId ID of Add to cart button
     *
     * @return void
     */
    protected function tryAddingNothingToCart(
        Element $page,
        string $updateCartId
    ) {
        // This test is a bit timing-sensitive, so introduce a retry loop before
        // completely failing.
        for ($clickRetry = 0; $clickRetry <= 4; $clickRetry++) {
            $this->clickCss($page, $updateCartId);
            $content = $page->find('css', $this->popoverContentSelector);
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
     * @param Element $page         Page element
     * @param string  $updateCartId ID of Add to cart button
     *
     * @return void
     */
    protected function tryAddingDuplicatesToCart(
        Element $page,
        string $updateCartId
    ) {
        // This test is a bit timing-sensitive, so introduce a retry loop before
        // completely failing.
        for ($clickRetry = 0; $clickRetry <= 4; $clickRetry++) {
            $this->clickCss($page, $updateCartId);
            $content = $page->find('css', $this->popoverContentSelector);
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
     * @param Element $page         Page element
     * @param string  $updateCartId ID of Add to cart button
     * @param string  $selectAllId  ID of select all checkbox
     *
     * @return void
     */
    protected function addCurrentPageToCart(
        Element $page,
        string $updateCartId,
        $selectAllId = '#addFormCheckboxSelectAll'
    ) {
        $selectAll = $this->findCss($page, $selectAllId);
        $selectAll->check();
        // Make sure all items are checked:
        $checkboxCount = count($page->findAll('css', '.checkbox-select-item'));
        $this->waitStatement(
            '$(".checkbox-select-item:checked").length === ' . $checkboxCount
        );
        $this->clickCss($page, $updateCartId);
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
        $this->clickCss($page, '#cartItems');
    }

    /**
     * Set up a generic cart test by running a search and putting its results
     * into the cart, then opening the lightbox so that additional actions may
     * be attempted.
     *
     * @param array $extraConfigs Extra config settings
     *
     * @return Element
     */
    protected function setUpGenericCartTest($extraConfigs = [])
    {
        // Activate the cart:
        $extraConfigs['config']['Site'] = ['showBookBag' => true];
        $this->changeConfigs($extraConfigs);

        $page = $this->getSearchResultsPage();
        $this->waitStatement('$(".cart-add:not(:hidden)").length === 2');
        $this->addCurrentPageToCartUsingButtons($page);
        $this->assertEquals('2', $this->findCssAndGetText($page, '#cartItems strong'));

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
        $this->assertIsObject($warning);
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
                        'bookbagTogglesInSearch' => false,
                    ],
                ],
            ]
        );

        $page = $this->getSearchResultsPage();

        // Click "add" without selecting anything.
        $this->tryAddingNothingToCart($page, '#updateCart');
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
                        'bookbagTogglesInSearch' => false,
                    ],
                ],
            ]
        );

        $page = $this->getSearchResultsPage();

        // Now select the same things twice:
        $this->addCurrentPageToCart($page, '#updateCart');
        $this->assertEquals('2', $this->findCssAndGetText($page, '#cartItems strong'));
        $this->tryAddingDuplicatesToCart($page, '#updateCart');
        $this->assertEquals('2', $this->findCssAndGetText($page, '#cartItems strong'));
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
                        'bookbagTogglesInSearch' => false,
                    ],
                ],
            ]
        );

        $page = $this->getSearchResultsPage();

        // Now select the same things twice:
        $this->addCurrentPageToCart($page, '#updateCart');
        $this->assertEquals('1', $this->findCssAndGetText($page, '#cartItems strong'));
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
                $this->findCssAndGetText($page, '#cartItems')
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
        $this->closeLightbox($page);

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
        $this->closeLightbox($page);

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
                        'bookbagTogglesInSearch' => false,
                    ],
                ],
            ]
        );
        $page = $this->getSearchResultsPage();
        $this->addCurrentPageToCart(
            $page,
            '#bottom_updateCart',
            '#bottom_addFormCheckboxSelectAll'
        );
        $this->assertEqualsWithTimeout(
            '2',
            function () use ($page) {
                return $this->findCssAndGetText($page, '#cartItems strong');
            }
        );
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
            $this->findCssAndGetText($page, '.modal .alert-success')
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
        $submit = $this->findCss($page, '.modal-body input[name=submitButton]');
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
        // Use a local fake URL as the export URL (we only check that the redirect
        // goes to the correct address so it doesn't matter that the target page
        // returns a 404 error):
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
        $windowNames = $session->getWindowNames();
        $windowCount = count($session->getWindowNames());
        $submit = $this->findCss($page, '.modal-body input[name=submitButton]');
        $submit->click();
        $this->assertEqualsWithTimeout(
            $windowCount + 1,
            function () use ($session) {
                return count($session->getWindowNames());
            }
        );
        $newWindows = array_diff($session->getWindowNames(), $windowNames);
        $this->assertCount(1, $newWindows);
        $session->switchToWindow(reset($newWindows));
        $this->assertEqualsWithTimeout(
            $exportUrl,
            [$session, 'getCurrentUrl']
        );
    }

    /**
     * Get the search history data.
     *
     * @return array
     */
    protected function getSearchHistory()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $matches = $page->findAll('css', '#recent-searches td:nth-child(2) a');
        $callback = function ($match) {
            return $match->getText();
        };
        return array_map($callback, $matches);
    }

    /**
     * Test that the print control works.
     *
     * @return void
     */
    public function testCartPrint()
    {
        $page = $this->setUpGenericCartTest();

        // First try clicking without selecting anything:
        $this->clickCss($page, '.cart-controls button[name=print]');
        $this->checkForNonSelectedMessage($page);

        // Now do it for real -- we should get redirected.
        $this->selectAllItemsInCart($page);
        $this->clickCss($page, '.cart-controls button[name=print]');
        $this->assertEqualsWithTimeout(
            'print=true&id[]=Solr|testsample1&id[]=Solr|testsample2',
            [$this, 'getCurrentQueryString']
        );

        // Printing should not have added anything to the search history beyond
        // the initial search that set everything up.
        $this->assertEquals(
            ['id:(testsample1 OR testsample2)'],
            $this->getSearchHistory()
        );
    }

    /**
     * Assert visibility
     *
     * @param array  $combo    Current Site configuration
     * @param bool[] $elements Array of element visibility states indexed by name
     * @param string $name     Name of element to check
     * @param string $exp      Expected visibility
     *
     * @return void
     */
    protected function assertVisible($combo, $elements, $name, $exp)
    {
        $message = $elements[$name]
            ? $name . " should be hidden.\n" . print_r($combo, true)
            : $name . " should be visible.\n" . print_r($combo, true);
        $this->assertEquals($exp, $elements[$name], $message);
    }

    /**
     * Run tests on a specified configuration
     *
     * @param Element $page  Page element
     * @param array   $combo Site configuration to test
     *
     * @return array
     */
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
        $this->assertVisible(
            $combo,
            $elements,
            'bulkUpdateCart',
            $combo['showBookBag'] && ($combo['showBulkOptions'] || !$combo['bookbagTogglesInSearch'])
        );
        $this->assertVisible(
            $combo,
            $elements,
            'resultCartBtns',
            $combo['showBookBag'] && $combo['bookbagTogglesInSearch']
        );
        $this->assertVisible(
            $combo,
            $elements,
            'resultCheckbox',
            $elements['bulkEmail'] || $elements['bulkUpdateCart']
        );
        return $elements;
    }

    /**
     * Test toolbar visibility configuration combinations
     *
     * @return void
     */
    public function testToolbarVisibilityConfigCombinations()
    {
        $page = $this->getSearchResultsPage();
        $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => false,
                'bookbagTogglesInSearch' => false,
            ]
        );
        $this->runConfigCombo(
            $page,
            [
                'showBookBag' => false,
                'showBulkOptions' => false,
                'bookbagTogglesInSearch' => true,
            ]
        );
        $this->runConfigCombo(
            $page,
            [
                'showBookBag' => false,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => false,
            ]
        );
        $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => false,
                'bookbagTogglesInSearch' => true,
            ]
        );
        $this->runConfigCombo(
            $page,
            [
                'showBookBag' => true,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => false,
            ]
        );
        $this->runConfigCombo(
            $page,
            [
                'showBookBag' => false,
                'showBulkOptions' => true,
                'bookbagTogglesInSearch' => true,
            ]
        );
        $this->runConfigCombo(
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
