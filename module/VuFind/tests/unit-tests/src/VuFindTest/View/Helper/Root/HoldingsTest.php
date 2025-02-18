<?php

/**
 * Holdings view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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

namespace VuFindTest\View\Helper\Root;

use VuFind\ILS\Logic\AvailabilityStatus;

/**
 * Holdings view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HoldingsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testBarcodeVisibilityBehavior()
     *
     * @return array
     */
    public static function barcodeVisibilityBehaviorProvider(): array
    {
        return [
            'default' => [[], true, true],
            'enabled' => [['display_items_without_barcodes' => true], true, true],
            'disabled' => [['display_items_without_barcodes' => false], true, false],
        ];
    }

    /**
     * Test appropriate barcode display behavior for various configurations.
     *
     * @param array $config                  Configuration options to test
     * @param bool  $expectedBarcodeResult   Expected result for items with barcodes
     * @param bool  $expectedNoBarcodeResult Expected result for items without
     * barcodes
     *
     * @return void
     *
     * @dataProvider barcodeVisibilityBehaviorProvider
     */
    public function testBarcodeVisibilityBehavior(
        array $config,
        bool $expectedBarcodeResult,
        bool $expectedNoBarcodeResult
    ): void {
        // Create a helper object:
        $helper = new \VuFind\View\Helper\Root\Holdings(['Catalog' => $config]);
        $this->assertEquals(
            $expectedBarcodeResult,
            $helper->holdingIsVisible(
                [
                    'availability' => new AvailabilityStatus(true, 'Available'),
                    'barcode' => '1234',
                ]
            )
        );
        $this->assertEquals(
            $expectedNoBarcodeResult,
            $helper->holdingIsVisible(['availability' => new AvailabilityStatus(true, 'Available')])
        );
    }
}
