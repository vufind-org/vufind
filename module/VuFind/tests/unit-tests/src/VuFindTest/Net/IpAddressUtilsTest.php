<?php

/**
 * IpAddressUtils Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Net;

use VuFind\Net\IpAddressUtils;

/**
 * IpAddressUtils Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IpAddressUtilsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test normalizeIp()
     *
     * @return void
     */
    public function testNormalizeIp()
    {
        $utils = new IpAddressUtils();
        $this->assertEquals(
            hex2bin('00000000000000000000000000000001'),
            $utils->normalizeIp('::1')
        );
        $this->assertEquals(
            hex2bin('0000000000000000000000007f000001'),
            $utils->normalizeIp('127.0.0.1')
        );
        // Example from http://www.gestioip.net/docu/ipv6_address_examples.html
        $this->assertEquals(
            hex2bin('20010db80a0b12f00000000000000001'),
            $utils->normalizeIp('2001:db8:a0b:12f0::1')
        );
    }

    /**
     * Test isInRange()
     *
     * @return void
     */
    public function testIsInRange()
    {
        $utils = new IpAddressUtils();
        $this->assertFalse($utils->isInRange('127.0.0.1', ['127.0.0.0']));
        $this->assertTrue($utils->isInRange('127.0.0.1', ['127.0.0.1']));
        $this->assertTrue($utils->isInRange('127.0.0.1', ['127.0.0']));
        $this->assertFalse($utils->isInRange('127.0.0.1', []));
        $this->assertFalse($utils->isInRange('127.0.0.1', ['']));
        $this->assertTrue($utils->isInRange('127.0.0.1', ['127.0.0.0-127.0.0.2']));
        $this->assertTrue(
            $utils->isInRange(
                '127.0.0.1',
                ['192.168.0.1-192.168.0.2', '127.0.0.0-127.0.0.2']
            )
        );
        $this->assertFalse(
            $utils->isInRange(
                '127.0.0.1',
                ['192.168.0.1-192.168.0.2', '127.0.0.2-127.0.0.4']
            )
        );
        $this->assertTrue(
            $utils->isInRange(
                '2001:db8::ef90:1',
                ['2001:db8::ef90:0-2001:db8::ef90:2']
            )
        );
        $this->assertTrue(
            $utils->isInRange(
                '2001:db8::ef90:1',
                ['2001:0db8::ef90:1']
            )
        );
        $this->assertTrue(
            $utils->isInRange(
                '2001:db8::ef90:1',
                ['2001:0db8']
            )
        );
        $this->assertFalse(
            $utils->isInRange(
                '2001:db8::ef90:1',
                ['2001:0db9']
            )
        );
    }
}
