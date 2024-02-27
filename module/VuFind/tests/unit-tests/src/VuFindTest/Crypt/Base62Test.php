<?php

/**
 * Base62 Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Crypt;

use VuFind\Crypt\Base62;

/**
 * Base62 Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class Base62Test extends \PHPUnit\Framework\TestCase
{
    /**
     * Test encoding.
     *
     * @param string $input    Input
     * @param string $expected Expected output
     *
     * @dataProvider exampleProvider
     *
     * @return void
     */
    public function testEncode($input, $expected)
    {
        $base62 = new Base62();
        $this->assertEquals($expected, $base62->encode($input));
    }

    /**
     * Test decoding.
     *
     * @param string $expected Expected output
     * @param string $input    Input
     *
     * @dataProvider exampleProvider
     *
     * @return void
     */
    public function testDecode($expected, $input)
    {
        $base62 = new Base62();
        $this->assertEquals($expected, $base62->decode($input));
    }

    /**
     * Data provider for tests.
     *
     * @return array
     */
    public static function exampleProvider()
    {
        // format: base 10 number, base 62 number
        return [
            ['2', '2'],
            ['6234', '1cY'],
            ['1437846', '6234'],
        ];
    }
}
