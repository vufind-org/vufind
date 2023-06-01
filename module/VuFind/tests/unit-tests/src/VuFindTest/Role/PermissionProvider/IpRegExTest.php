<?php

/**
 * IpRegEx ServerParam Test Class
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

use VuFind\Role\PermissionProvider\IpRegEx;

/**
 * IpRegEx ServerParam Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IpRegExTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a permission provider with the specified IP assigned.
     *
     * @param string $ipAddr IP address to send to provider.
     *
     * @return IpRegEx
     */
    protected function getPermissionProvider($ipAddr)
    {
        $mockRequest = $this->getMockBuilder(
            \Laminas\Http\PhpEnvironment\Request::class
        )->disableOriginalConstructor()->getMock();
        $mockIpReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIpReader->expects($this->once())->method('getUserIp')
            ->will($this->returnValue($ipAddr));
        return new IpRegEx($mockRequest, $mockIpReader);
    }

    /**
     * Test a matching regular expression.
     *
     * @return void
     */
    public function testMatchingRegEx()
    {
        $regEx = '/123\.124\..*/';
        $provider = $this->getPermissionProvider('123.124.125.126');
        $this->assertEquals(
            ['guest', 'loggedin'],
            $provider->getPermissions($regEx)
        );
    }

    /**
     * Test an array of non-matching regular expressions.
     *
     * @return void
     */
    public function testNonMatchingRegExArray()
    {
        $regEx = [
            '/123\.124\..*/',
            '/125\.126\..*/',
        ];
        $provider = $this->getPermissionProvider('129.124.125.126');
        $this->assertEquals([], $provider->getPermissions($regEx));
    }
}
