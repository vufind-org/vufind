<?php

/**
 * IpRange ServerParam Test Class
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

namespace VuFindTest\Role\PermissionProvider;

use VuFind\Net\IpAddressUtils;
use VuFind\Role\PermissionProvider\IpRange;

/**
 * IpRange ServerParam Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IpRangeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a permission provider with the specified IP assigned.
     *
     * @param string         $ipAddr IP address to send to provider.
     * @param IpAddressUtils $utils  IP address utils to use
     *
     * @return IpRange
     */
    protected function getPermissionProvider($ipAddr, IpAddressUtils $utils): IpRange
    {
        $mockRequest = $this->getMockBuilder(
            \Laminas\Http\PhpEnvironment\Request::class
        )->disableOriginalConstructor()->getMock();
        $mockIpReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIpReader->expects($this->once())->method('getUserIp')
            ->will($this->returnValue($ipAddr));
        return new IpRange($mockRequest, $utils, $mockIpReader);
    }

    /**
     * Test a matching range.
     *
     * @return void
     */
    public function testMatchingRange()
    {
        // In this example, we'll pass the IP address as the options to the provider.
        // Note that we're not actually testing the range checking itself, because
        // we're mocking out the IpAddressUtils; we're just confirming that the parts
        // fit together correctly.
        $ipAddr = '123.124.125.126';
        $utils = $this->getMockBuilder(IpAddressUtils::class)
            ->disableOriginalConstructor()
            ->getMock();
        $utils->expects($this->once())->method('isInRange')
            ->with($this->equalTo($ipAddr), $this->equalTo([$ipAddr]))
            ->will($this->returnValue(true));
        $provider = $this->getPermissionProvider($ipAddr, $utils);
        $this->assertEquals(
            ['guest', 'loggedin'],
            $provider->getPermissions($ipAddr)
        );
    }

    /**
     * Test an array of non-matching ranges.
     *
     * @return void
     */
    public function testNonMatchingRegExArray()
    {
        // In this example, we'll pass the IP address as the options to the provider.
        // Note that we're not actually testing the range checking itself, because
        // we're mocking out the IpAddressUtils; we're just confirming that the parts
        // fit together correctly.
        $ipAddr = '123.124.125.126';
        $options = [
            '1.2.3.4-1.2.3.7',
            '2.3.4.5',
        ];
        $utils = $this->getMockBuilder(IpAddressUtils::class)
            ->disableOriginalConstructor()
            ->getMock();
        $utils->expects($this->once())->method('isInRange')
            ->with($this->equalTo($ipAddr), $this->equalTo($options))
            ->will($this->returnValue(false));
        $provider = $this->getPermissionProvider($ipAddr, $utils);
        $this->assertEquals([], $provider->getPermissions($options));
    }
}
