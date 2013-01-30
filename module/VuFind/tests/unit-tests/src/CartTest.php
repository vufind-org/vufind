<?php
/**
 * Cart Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFind\Test;

/**
 * Cart Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class CartTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Mock record loader
     *
     * @param \VuFind\Record\Loader
     */
    protected $loader;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loader = $this->getMock('VuFind\Record\Loader');
    }

    /**
     * Build a mock cart object.
     *
     * @param int   $maxSize Maximum size of cart contents
     * @param bool  $active  Is cart enabled?
     * @param array $cookies Current cookie values
     *
     * @return \VuFind\Cart
     */
    protected function getCart($maxSize = 100, $active = true, $cookies = array())
    {
        return $this->getMock(
            'VuFind\Cart', array('setCookie'),
            array($this->loader, $maxSize, $active, $cookies)
        );
    }

    /**
     * Check that the cart is empty by default.
     *
     * @return void
     */
    public function testEmptyCart()
    {
        $cart = $this->getCart();
        $this->assertTrue($cart->isEmpty());
        $this->assertEquals(array(), $cart->getItems());
    }

    /**
     * Check that the cart correctly registers a maximum size.
     *
     * @return void
     */
    public function testMaxSize()
    {
        $cart = $this->getCart(50);
        $this->assertEquals(50, $cart->getMaxSize());
    }

    /**
     * Test that the cart can fill up.
     *
     * @return void
     */
    public function testFullCart()
    {
        $cart = $this->getCart(2); // create a very small cart
        $this->assertFalse($cart->isFull());
        $this->assertEquals(array('success' => true), $cart->addItem('VuFind|a'));
        $this->assertFalse($cart->isFull());
        $this->assertEquals(array('success' => true), $cart->addItem('VuFind|b'));
        $this->assertTrue($cart->isFull());
        $this->assertEquals(
            array('success' => false, 'notAdded' => 1), $cart->addItem('VuFind|c')
        );
    }

    /**
     * Test an active cart.
     *
     * @return void
     */
    public function testActiveCart()
    {
        $cart = $this->getCart();
        $this->assertTrue($cart->isActive());
    }

    /**
     * Test a disabled cart.
     *
     * @return void
     */
    public function testDisabledCart()
    {
        $cart = $this->getCart(100, false);
        $this->assertFalse($cart->isActive());
    }

    /**
     * Test that the save method writes the expected cookies.
     *
     * @return void
     */
    public function testCookieWrite()
    {
        $cart = $this->getCart();
        $cart->expects($this->at(0))
            ->method('setCookie')
            ->with($this->equalTo('vufind_cart'), $this->equalTo('Aa'));
        $cart->expects($this->at(1))
            ->method('setCookie')
            ->with($this->equalTo('vufind_cart_src'), $this->equalTo('VuFind'));
        $cart->addItem('VuFind|a');
    }

    /**
     * Test the contains method.
     *
     * @return void
     */
    public function testContains()
    {
        $cart = $this->getCart();
        $this->assertFalse($cart->contains('VuFind|a'));
        $cart->addItem('VuFind|a');
        $this->assertTrue($cart->contains('VuFind|a'));
    }

    /**
     * Test the "empty cart" method.
     *
     * @return void
     */
    public function testCartCanBeEmptied()
    {
        $cart = $this->getCart();
        $cart->addItem('VuFind|a');
        $this->assertFalse($cart->isEmpty());
        $cart->emptyCart();
        $this->assertTrue($cart->isEmpty());
    }

    /**
     * Test the "remove items" method.
     *
     * @return void
     */
    public function testRemoveItems()
    {
        $cart = $this->getCart();
        $cart->addItems(array('VuFind|a', 'VuFind|b', 'VuFind|c'));
        $cart->removeItems(array('VuFind|a', 'VuFind|b'));
        $this->assertTrue($cart->contains('VuFind|c'));
        $this->assertFalse($cart->contains('VuFind|a'));
        $this->assertFalse($cart->contains('VuFind|b'));
    }

    /**
     * Test the "get record details" method.
     *
     * @return void
     */
    public function testGetRecordDetails()
    {
        $this->loader->expects($this->once())
            ->method('loadBatch')
            ->with($this->equalTo(array('VuFind|a')))
            ->will($this->returnValue('success'));
        $cart = $this->getCart();
        $cart->addItem('VuFind|a');
        $this->assertEquals('success', $cart->getRecordDetails());
    }

    /**
     * Test loading values from a VuFind 1.x-style cookie.
     *
     * @return void
     */
    public function testVF1Cookie()
    {
        $cart = $this->getCart(100, true, array('vufind_cart' => "a\tb\tc"));
        $this->assertEquals(3, count($cart->getItems()));
        $this->assertTrue($cart->contains('VuFind|a'));
        $this->assertTrue($cart->contains('VuFind|b'));
        $this->assertTrue($cart->contains('VuFind|c'));
    }

    /**
     * Test loading values from a VuFind 2.x-style cookie.
     *
     * @return void
     */
    public function testVF2Cookie()
    {
        $cookies = array(
            'vufind_cart' => "Aa\tBb\tCc",
            'vufind_cart_src' => "VuFind\tSummon\tWorldCat"
        );
        $cart = $this->getCart(100, true, $cookies);
        $this->assertEquals(3, count($cart->getItems()));
        $this->assertTrue($cart->contains('VuFind|a'));
        $this->assertTrue($cart->contains('Summon|b'));
        $this->assertTrue($cart->contains('WorldCat|c'));
    }
}