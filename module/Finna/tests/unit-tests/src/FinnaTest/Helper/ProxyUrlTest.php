<?php

/**
 * ProxyUrl helper test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\Helper;

use Finna\View\Helper\Root\ProxyUrl;
use Finna\View\Helper\Root\ProxyUrlFactory;
use Generator;
use VuFind\Config\Config;
use VuFind\Config\PluginManager;
use VuFind\Net\IpAddressUtils;
use VuFindTest\Feature\FixtureTrait;

/**
 * ProxyUrl helper test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ProxyUrlTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Mock container
     *
     * @var \VuFindTest\Container\MockContainer
     */
    protected $container;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new \VuFindTest\Container\MockContainer($this);
    }

    /**
     * Function to get expected invoke test data
     *
     * @return array
     */
    public static function getTestInvokeData(): Generator
    {
        yield 'url with default config' => [
          'https://proxytesturl.fi',
          'https://proxytesturl.fi',
          'permissions_default.ini',
          'config_default.ini',
        ];
        yield 'url with ezproxy enabled' => [
          'https://proxytesturl.fi',
          'http://local.test.proxyurl/login?qurl=https%3A%2F%2Fproxytesturl.fi',
          'permissions_default.ini',
          'config_with_ezproxy.ini',
        ];
    }

    /**
     * Test invoking the helper
     *
     * @param string $url           Url to proxy
     * @param string $expected      Expected value
     * @param string $permissionIni Path to the test permissions.ini
     * @param string $configIni     Path to the test config.ini
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(string $url, string $expected, string $permissionIni, string $configIni): void
    {
        $permissionsFixture = $this->getFixture('proxyurl/' . $permissionIni, 'Finna');
        $configFixture = $this->getFixture('proxyurl/' . $configIni, 'Finna');
        $config = new Config(parse_ini_string($configFixture, true));
        $permissions = new Config(parse_ini_string($permissionsFixture, true));
        $factory = new ProxyUrlFactory();

        $configPluginManager = $this->container->createMock(PluginManager::class, ['get']);
        $configPluginManager->expects($this->any())->method('get')->willReturnCallback(
            function ($param) use ($config, $permissions) {
                return $param === 'config' ? $config : $permissions;
            }
        );
        $this->container->set(\VuFind\Config\PluginManager::class, $configPluginManager);

        $ipAddressUtils = $this->container->createMock(IpAddressUtils::class, []);
        $this->container->set(IpAddressUtils::class, $ipAddressUtils);

        $cacheManager = $this->container->createMock(\VuFind\Cache\Manager::class, ['getCache']);
        $cache = $this->container->createMock(\Laminas\Cache\Storage\StorageInterface::class, []);
        $cacheManager->expects($this->any())->method('getCache')->willReturn($cache);
        $this->container->set(\VuFind\Cache\Manager::class, $cacheManager);

        $proxyUrlHelper = $factory($this->container, ProxyUrl::class);
        $result = ($proxyUrlHelper)($url);
        $this->assertEquals($expected, $result);
    }
}
