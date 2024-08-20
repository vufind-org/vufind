<?php

/**
 * InsecureCookie PermissionProvider Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

use VuFind\Cookie\CookieManager;
use VuFind\Role\PermissionProvider\InsecureCookie;

/**
 * InsecureCookie PermissionProvider Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class InsecureCookieTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the provider.
     *
     * @param string|string[] $options       Cookie(s) to check
     * @param array           $expectedRoles Expected roles from provider
     *
     * @dataProvider getPermissionsProvider
     *
     * @return void
     */
    public function testGetPermissions(string|array $options, array $expectedRoles)
    {
        $cookieProvider = $this->createMock(CookieManager::class);
        $map = [
            'foo' => '1',
            'bar' => '1',
        ];
        $callback = function ($key) use ($map) {
            return $map[$key] ?? null;
        };
        $cookieProvider->method('get')->willReturnCallback($callback);
        $provider = new InsecureCookie($cookieProvider);
        $this->assertEquals($expectedRoles, $provider->getPermissions($options));
    }

    /**
     * Data provider for testGetPermissions()
     *
     * @return array
     */
    public static function getPermissionsProvider(): array
    {
        $granted = ['guest', 'loggedin'];
        $notGranted = [];
        return [
            'single string with value' => ['foo', $granted],
            'single string, unset' => ['baz', $notGranted],
            'single string with value in array' => [['foo'], $granted],
            'multiple strings with values' => [['foo', 'bar'], $granted],
            'mixed set/unset cookies' => [['foo', 'baz'], $notGranted],
            'multiple unset cookies' => [['xyzzy', 'baz'], $notGranted],
        ];
    }
}
