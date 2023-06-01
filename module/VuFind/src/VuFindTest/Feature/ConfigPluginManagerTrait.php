<?php

/**
 * Trait for configuration handling in tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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

namespace VuFindTest\Feature;

use Laminas\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use VuFind\Config\PluginManager;

/**
 * Trait for configuration handling in tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait ConfigPluginManagerTrait
{
    /**
     * Get a mock configuration plugin manager with the given configuration "files"
     * available.
     *
     * @param array            $configs   An associative array of configurations
     * where key is the file (e.g. 'config') and value an array of configuration
     * sections and directives
     * @param array            $default   Default configuration to return when no
     * entry is found in $configs
     * @param ?InvocationOrder $getExpect The expected invocation order for the get()
     * method (null for any)
     * @param ?InvocationOrder $hasExpect The expected invocation order for the has()
     * method (null for any)
     *
     * @return MockObject&PluginManager
     */
    protected function getMockConfigPluginManager(
        array $configs,
        array $default = [],
        InvocationOrder $getExpect = null,
        InvocationOrder $hasExpect = null
    ): PluginManager {
        $manager = $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($getExpect ?? $this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will(
                $this->returnCallback(
                    function ($config) use ($configs, $default): Config {
                        return new Config($configs[$config] ?? $default);
                    }
                )
            );
        $manager->expects($hasExpect ?? $this->any())
            ->method('has')
            ->with($this->isType('string'))
            ->will(
                $this->returnCallback(
                    function ($config) use ($configs): bool {
                        return isset($configs[$config]);
                    }
                )
            );
        return $manager;
    }

    /**
     * Get a mock configuration plugin manager that will throw an exception.
     *
     * @param \Throwable $exception Exception to throw
     *
     * @return MockObject&PluginManager
     */
    protected function getMockFailingConfigPluginManager(
        \Throwable $exception
    ): PluginManager {
        $manager = $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will($this->throwException($exception));
        return $manager;
    }
}
