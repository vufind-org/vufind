<?php

/**
 * Trait for tests involving Laminas Views.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Laminas\Cache\Storage\Adapter\AdapterOptions;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\View\Renderer\PhpRenderer;
use Psr\Container\ContainerInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\View\Helper\Root\CleanHtml;
use VuFind\View\Helper\Root\CleanHtmlFactory;
use VuFind\View\Helper\Root\SearchMemory;
use VuFindTest\Container\MockContainer;
use VuFindTheme\View\Helper\AssetManager;
use VuFindTheme\View\Helper\AssetManagerFactory;

/**
 * Trait for tests involving Laminas Views.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait ViewTrait
{
    /**
     * Get a working AssetManager helper.
     *
     * @param PhpRenderer $renderer View for helper
     *
     * @return AssetManager
     */
    protected function getAssetManager(PhpRenderer $renderer): AssetManager
    {
        $container = new MockContainer($this);
        $factory = new AssetManagerFactory();
        $helper = $factory($container, AssetManager::class);
        $helper->setView($renderer);
        return $helper;
    }

    /**
     * Get a working renderer.
     *
     * @param array  $plugins Custom VuFind plug-ins to register
     * @param string $theme   Theme directory to load from
     *
     * @return PhpRenderer
     */
    protected function getPhpRenderer($plugins = [], $theme = 'bootstrap5')
    {
        $resolver = new \Laminas\View\Resolver\TemplatePathStack();

        // This assumes that all themes will be testing inherit directly
        // from root with no intermediate themes. Probably safe for most
        // test situations, though other scenarios are possible.
        $resolver->setPaths(
            [
                $this->getPathForTheme('root'),
                $this->getPathForTheme($theme),
            ]
        );
        $renderer = new PhpRenderer();
        $renderer->setResolver($resolver);
        $pluginManager = $renderer->getHelperPluginManager();
        if (!isset($plugins['assetManager'])) {
            $plugins['assetManager'] = $this->getAssetManager($renderer);
        }
        foreach ($plugins as $key => $value) {
            $pluginManager->setService($key, $value);
        }
        return $renderer;
    }

    /**
     * Get the directory for a given theme.
     *
     * @param string $theme Theme directory name
     *
     * @return string
     */
    protected function getPathForTheme($theme)
    {
        return APPLICATION_PATH . '/themes/' . $theme . '/templates';
    }

    /**
     * Get mock SearchMemory view helper
     *
     * @param ?\VuFind\Search\Memory $memory Optional search memory
     *
     * @return SearchMemory
     */
    protected function getSearchMemoryViewHelper($memory = null): SearchMemory
    {
        if (null === $memory) {
            $memory = $this->getMockBuilder(\VuFind\Search\Memory::class)
                ->disableOriginalConstructor()->getMock();
            $memory->expects($this->any())
                ->method('getLastSearchId')
                ->willReturn(-123);
        }
        return new \VuFind\View\Helper\Root\SearchMemory($memory);
    }

    /**
     * Create the cleanHtml helper
     *
     * @return CleanHtml
     */
    protected function createCleanHtmlHelper(): CleanHtml
    {
        // The FilesystemOptions class is final and cannot be mocked, so create our own as a workaround:
        $cacheOptions = new class () extends AdapterOptions {
            /**
             * Get cache dir
             *
             * @return string
             */
            public function getCacheDir(): string
            {
                return '';
            }
        };
        $cache = $this->createMock(StorageInterface::class);
        $cache->expects($this->any())
            ->method('getOptions')
            ->willReturn($cacheOptions);
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->any())
            ->method('getCache')
            ->willReturn($cache);
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->any())
            ->method('getConfigArray')
            ->willReturn([]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('get')
            ->willReturnCallback(
                function ($class) use ($cacheManager, $configManager) {
                    return match ($class) {
                        CacheManager::class => $cacheManager,
                        ConfigManagerInterface::class => $configManager,
                    };
                }
            );
        return (new CleanHtmlFactory())($container, CleanHtml::class);
    }
}
