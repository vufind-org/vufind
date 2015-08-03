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

/**
 * Mink cart test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class CartTest extends \VuFindTest\Unit\MinkTestCase
{
    /**
     * Test that the home page is available.
     *
     * @return void
     */
    public function testAddToCart()
    {
        // Activate the cart:
        $this->changeConfigs(
            ['config' =>
                ['Site' => ['showBookBag' => true, 'theme' => 'bootprint3']]
            ]
        );

        $session = $this->getMinkSession();
        $session->start();
        $path = '/Search/Results?lookfor=id%3A(testsample1+OR+testsample2)';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();

        // Click "add" without selecting anything:
        $updateCart = $page->find('css', '#updateCart');
        $this->assertTrue(is_object($updateCart));
        $updateCart->click();
        $content = $page->find('css', '.popover-content');
        $this->assertTrue(is_object($content));
        $this->assertEquals(
            'No items were selected. '
            . 'Please click on a checkbox next to an item and try again.',
            $content->getText()
        );

        // Now actually select something:
        $selectAll = $page->find('css', '#addFormCheckboxSelectAll');
        $selectAll->check();
        $updateCart->click();
        $this->assertEquals('2', $page->find('css', '#cartItems strong')->getText());

        // Open the cart and empty it:
        $viewCart = $page->find('css', '#cartItems');
        $this->assertTrue(is_object($viewCart));
        $viewCart->click();
        $cartSelectAll = $page->find('css', '.modal-dialog .checkbox-select-all');
        $cartSelectAll->check();
        $delete = $page->find('css', '#cart-delete-label');
        $delete->click();
        $deleteConfirm = $page->find('css', '#cart-confirm-delete');
        $this->assertTrue(is_object($deleteConfirm));
        $deleteConfirm->click();
        $close = $page->find('css', 'button.close');
        $close->click();
        $this->assertEquals('0', $page->find('css', '#cartItems strong')->getText());
    }
}
