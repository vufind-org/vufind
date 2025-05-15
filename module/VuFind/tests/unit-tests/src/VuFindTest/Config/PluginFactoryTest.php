<?php

/**
 * Config Factory Test Class
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

namespace VuFindTest\Config;

use VuFind\Config\Config;
use VuFind\Config\ConfigManager;
use VuFind\Config\PluginFactory;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\PathResolverTrait;

/**
 * Config Factory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PluginFactoryTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use PathResolverTrait;

    /**
     * Plugin factory instance.
     *
     * @var PluginFactory
     */
    protected $factory;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->factory = new PluginFactory();
    }

    /**
     * Wrapper around factory.
     *
     * @param array $config Configuration to load
     *
     * @return Config
     */
    protected function getConfig($config = []): Config
    {
        $name = 'test-config';
        $mockConfigManager = $this->createMock(ConfigManager::class);
        $mockConfigManager->expects($this->any())
            ->method('get')
            ->with($name)
            ->willReturn($config);

        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(ConfigManager::class, $mockConfigManager);
        return ($this->factory)($container, $name);
    }

    /**
     * Data provider for testReadOnlyConfig().
     *
     * @return array
     */
    public static function readOnlyConfigProvider(): array
    {
        return [
            'empty config' => [['Section1' => []]],
            'override config' => [['Section1' => ['z' => 'good']]],
        ];
    }

    /**
     * Test configuration is read-only.
     *
     * @param array $configArray Config
     *
     * @dataProvider readOnlyConfigProvider
     *
     * @return void
     */
    public function testReadOnlyConfig($configArray): void
    {
        $this->expectExceptionMessage('Config is immutable; cannot set z to bad');
        $config = $this->getConfig($configArray);
        $this->assertIsObject($config);
        $config->Section1->z = 'bad';
    }
}
