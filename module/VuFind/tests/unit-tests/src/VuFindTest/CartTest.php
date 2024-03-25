<?php

/**
 * Cart Test Class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Cookie\CookieManager;

/**
 * Cart Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CartTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Mock record loader
     *
     * @var MockObject&\VuFind\Record\Loader
     */
    protected $loader;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->loader = $this->getMockBuilder(\VuFind\Record\Loader::class)
            ->onlyMethods(['loadBatch'])
            ->setConstructorArgs(
                [
                $this->createMock(\VuFindSearch\Service::class),
                $this->createMock(\VuFind\RecordDriver\PluginManager::class),
                ]
            )->getMock();
    }

    /**
     * Build a mock cookie manager.
     *
     * @param array  $cookies  Current cookie values
     * @param string $path     Cookie base path (default = /)
     * @param string $domain   Cookie domain
     * @param bool   $secure   Are cookies secure only? (default = false)
     * @param bool   $httpOnly Are cookies HTTP only? (default = false)
     *
     * @return CookieManager
     */
    protected function getMockCookieManager(
        $cookies = [],
        $path = '/',
        $domain = null,
        $secure = false,
        $httpOnly = false
    ) {
        return $this->getMockBuilder(\VuFind\Cookie\CookieManager::class)
            ->onlyMethods(['set'])
            ->setConstructorArgs([$cookies, $path, $domain, $secure, $httpOnly])
            ->getMock();
    }

    /**
     * Build a mock cart object.
     *
     * @param int                 $maxSize Maximum size of cart contents
     * @param bool                $active  Is cart enabled?
     * @param array|CookieManager $cookies Current cookie values (or ready-to-use
     * cookie manager)
     *
     * @return \VuFind\Cart
     */
    protected function getCart($maxSize = 100, $active = true, $cookies = [])
    {
        if (!($cookies instanceof CookieManager)) {
            $cookies = $this->getMockCookieManager($cookies);
        }
        return new \VuFind\Cart($this->loader, $cookies, $maxSize, $active);
    }

    /**
     * Test cookie domain setting.
     *
     * @return void
     */
    public function testCookieDomain()
    {
        $manager = $this->getMockCookieManager([], '/', '.example.com');
        $cart = $this->getCart(100, true, $manager);
        $this->assertEquals('.example.com', $cart->getCookieDomain());
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
        $this->assertEquals([], $cart->getItems());
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
        $this->assertEquals(['success' => true], $cart->addItem('Solr|a'));
        $this->assertFalse($cart->isFull());
        $this->assertEquals(['success' => true], $cart->addItem('Solr|b'));
        $this->assertTrue($cart->isFull());
        $this->assertEquals(
            ['success' => false, 'notAdded' => 1],
            $cart->addItem('Solr|c')
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
        $manager = $this->getMockCookieManager();
        $this->expectConsecutiveCalls($manager, 'set', [['vufind_cart', 'Aa'], ['vufind_cart_src', 'Solr']]);
        $cart = $this->getCart(100, true, $manager);
        $cart->addItem('Solr|a');
    }

    /**
     * Test the contains method.
     *
     * @return void
     */
    public function testContains()
    {
        $cart = $this->getCart();
        $this->assertFalse($cart->contains('Solr|a'));
        $cart->addItem('Solr|a');
        $this->assertTrue($cart->contains('Solr|a'));
    }

    /**
     * Test the "empty cart" method.
     *
     * @return void
     */
    public function testCartCanBeEmptied()
    {
        $cart = $this->getCart();
        $cart->addItem('Solr|a');
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
        $cart->addItems(['Solr|a', 'Solr|b', 'Solr|c']);
        $cart->removeItems(['Solr|a', 'Solr|b']);
        $this->assertTrue($cart->contains('Solr|c'));
        $this->assertFalse($cart->contains('Solr|a'));
        $this->assertFalse($cart->contains('Solr|b'));
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
            ->with($this->equalTo(['Solr|a']))
            ->will($this->returnValue('success'));
        $cart = $this->getCart();
        $cart->addItem('Solr|a');
        $this->assertEquals('success', $cart->getRecordDetails());
    }

    /**
     * Test loading values from a VuFind 1.x-style cookie.
     *
     * @return void
     */
    public function testVF1Cookie()
    {
        $cart = $this->getCart(100, true, ['vufind_cart' => "a\tb\tc"]);
        $this->assertCount(3, $cart->getItems());
        $this->assertTrue($cart->contains('Solr|a'));
        $this->assertTrue($cart->contains('Solr|b'));
        $this->assertTrue($cart->contains('Solr|c'));
    }

    /**
     * Test loading values from a VuFind 2.x-style cookie.
     *
     * @return void
     */
    public function testVF2Cookie()
    {
        $cookies = [
            'vufind_cart' => "Aa\tBb\tCc",
            'vufind_cart_src' => "Solr\tSummon\tWorldCat",
        ];
        $cart = $this->getCart(100, true, $cookies);
        $this->assertCount(3, $cart->getItems());
        $this->assertTrue($cart->contains('Solr|a'));
        $this->assertTrue($cart->contains('Summon|b'));
        $this->assertTrue($cart->contains('WorldCat|c'));
    }
}
