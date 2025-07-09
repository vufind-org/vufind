<?php

/**
 * Trait for tests involving config related services.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2025.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use VuFind\Config\Config;
use VuFind\Config\ConfigManager;
use VuFind\Config\Handler\PluginManager as ConfigHandlerPluginManager;
use VuFind\Config\PathResolver;
use VuFind\Config\PluginManager as ConfigPluginManager;
use VuFindTest\Container\MockContainer;

use function defined;
use function strlen;

/**
 * Trait for tests involving config related services.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait ConfigRelatedServicesTrait
{
    /**
     * Get a config file path resolver.
     *
     * @param ?string $baseDir     Optional directory to override APPLICATION_PATH
     * @param ?string $localDir    Optional directory to override LOCAL_OVERRIDE_DIR
     * @param ?string $localSubDir Optional subdirectory for local directories (for simplified fixture dirs)
     *
     * @return PathResolver
     */
    protected function getPathResolver(
        ?string $baseDir = null,
        ?string $localDir = null,
        ?string $localSubDir = null,
    ): PathResolver {
        return $this->getContainerWithConfigRelatedServices(
            baseDir: $baseDir,
            localDir: $localDir,
            localSubDir: $localSubDir,
        )->get(PathResolver::class);
    }

    /**
     * Get a container with config related services.
     *
     * @param ?array  $moduleConfig Optional config to override
     * APPLICATION_PATH . '/module/VuFind/config/module.config.php'
     * @param ?string $baseDir      Optional directory to override APPLICATION_PATH
     * @param ?string $localDir     Optional directory to override LOCAL_OVERRIDE_DIR
     * @param ?string $localSubDir  Optional subdirectory for local directories (for simplified fixture dirs)
     *
     * @return MockContainer
     */
    protected function getContainerWithConfigRelatedServices(
        ?array $moduleConfig = null,
        ?string $baseDir = null,
        ?string $localDir = null,
        ?string $localSubDir = null
    ): MockContainer {
        $container = new MockContainer($this);
        $this->addConfigRelatedServicesToContainer($container, $moduleConfig, $baseDir, $localDir, $localSubDir);
        return $container;
    }

    /**
     * Add config related services to a mock container.
     *
     * @param MockContainer $container    Mock Container
     * @param ?array        $moduleConfig Optional config to override
     * APPLICATION_PATH . '/module/VuFind/config/module.config.php'
     * @param ?string       $baseDir      Optional directory to override APPLICATION_PATH
     * @param ?string       $localDir     Optional directory to override LOCAL_OVERRIDE_DIR
     * @param ?string       $localSubDir  Optional subdirectory for local directories (for simplified fixture dirs)
     *
     * @return void
     */
    protected function addConfigRelatedServicesToContainer(
        MockContainer $container,
        ?array $moduleConfig = null,
        ?string $baseDir = null,
        ?string $localDir = null,
        ?string $localSubDir = null
    ): void {
        $moduleConfig ??= include APPLICATION_PATH . '/module/VuFind/config/module.config.php';
        $baseDir ??= APPLICATION_PATH;
        $localDir ??= defined('LOCAL_OVERRIDE_DIR') && strlen(trim(LOCAL_OVERRIDE_DIR)) > 0
            ? LOCAL_OVERRIDE_DIR
            : null;

        $configHandlerPluginManager = new ConfigHandlerPluginManager(
            $container,
            $moduleConfig['vufind']['plugin_managers']['config_handler']
        );
        $container->set(ConfigHandlerPluginManager::class, $configHandlerPluginManager);

        $pathResolver = PathResolver::getPathResolverForDirectories(
            $configHandlerPluginManager,
            $baseDir,
            $localDir,
            $localSubDir
        );
        $container->set(PathResolver::class, $pathResolver);

        $configManager = new ConfigManager($configHandlerPluginManager, $pathResolver);
        $container->set(ConfigManager::class, $configManager);

        $configPluginManager = new ConfigPluginManager($container, $moduleConfig['vufind']['config_reader']);
        $container->set(ConfigPluginManager::class, $configPluginManager);
    }

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
     * @return MockObject&ConfigPluginManager
     */
    protected function getMockConfigPluginManager(
        array $configs,
        array $default = [],
        ?InvocationOrder $getExpect = null,
        ?InvocationOrder $hasExpect = null
    ): ConfigPluginManager {
        $manager = $this->createMock(ConfigPluginManager::class);
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
     * @return MockObject&ConfigPluginManager
     */
    protected function getMockFailingConfigPluginManager(
        \Throwable $exception
    ): ConfigPluginManager {
        $manager = $this->createMock(ConfigPluginManager::class);
        $manager->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will($this->throwException($exception));
        return $manager;
    }
}
