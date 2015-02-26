<?php
/**
 * Cart view helper Test Class
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
namespace VuFindTest\View\Helper\Root;

/**
 * Cart view helper Test Class
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
     * Test the helper
     *
     * @return void
     */
    public function testCart()
    {
        // Create a mock cart object:
        $mockLoader = $this->getMock(
            'VuFind\Record\Loader', [],
            [
                $this->getMock('VuFindSearch\Service'),
                $this->getMock('VuFind\RecordDriver\PluginManager')
            ]
        );
        $cart = $this->getMock(
            'VuFind\Cart', null, [$mockLoader]
        );

        // Create a helper object:
        $helper = new \VuFind\View\Helper\Root\Cart($cart);

        // Test that __invoke returns the object that was passed to the constructor:
        $this->assertEquals($cart, $helper());
    }
}