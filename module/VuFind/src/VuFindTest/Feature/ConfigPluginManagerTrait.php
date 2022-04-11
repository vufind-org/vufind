<?php

/**
 * Trait for configuration handling in tests.
 *
 * PHP version 7
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
     * @param array $configs An associative array of configurations where key is the
     * file (e.g. 'config') and value an array of configuration sections and
     * directives
     *
     * @return MockObject
     */
    protected function getMockConfigPluginManager(array $configs): MockObject
    {
        $manager = $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will(
                $this->returnCallback(
                    function ($config) use ($configs): Config {
                        return new Config($configs[$config] ?? []);
                    }
                )
            );
        return $manager;
    }
}
