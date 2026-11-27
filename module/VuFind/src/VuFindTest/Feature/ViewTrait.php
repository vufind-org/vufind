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
use Laminas\View\Helper\Doctype;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\HelperPluginManager;
use Laminas\View\HelperPluginManagerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\View;
use Laminas\View\ViewInterface;
use Psr\Container\ContainerInterface;
use VuFind\Application;
use VuFind\Application\UriRegistry;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Escaper\Escaper;
use VuFind\Http\RouteHelper;
use VuFind\Search\Memory;
use VuFind\Search\Params\PluginManager;
use VuFind\View\Helper\Root\CleanHtml;
use VuFind\View\Helper\Root\CleanHtmlFactory;
use VuFind\View\Helper\Root\SearchMemory;
use VuFind\View\Renderer\LaminasTemplateRenderer;
use VuFind\View\Renderer\TemplateRendererInterface;
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
    use AutowireTrait;

    /**
     * Get a working AssetManager helper.
     *
     * @param HelperPluginManager $helperPluginManager View helper plugin manager
     *
     * @return AssetManager
     */
    protected function getAssetManager(HelperPluginManager $helperPluginManager): AssetManager
    {
        $container = new MockContainer($this);
        $container->get(\VuFind\Security\NonceGenerator::class)->method('getNonce')->willReturn('');
        $escaper = new Escaper();
        $doctype = new Doctype();
        $services = [
            \Laminas\View\Helper\Doctype::class => new \Laminas\View\Helper\Doctype(),
            \VuFind\View\Helper\Root\HeadLink::class
                => new \VuFind\View\Helper\Root\HeadLink(new \Laminas\View\Helper\HeadLink($escaper, $doctype)),
            \Laminas\View\Helper\HeadStyle::class => new \Laminas\View\Helper\HeadStyle($escaper, $doctype),
            \Laminas\View\Helper\InlineScript::class => new \Laminas\View\Helper\InlineScript($escaper, $doctype),
            \VuFind\View\Helper\Root\Url::class => new \VuFind\View\Helper\Root\Url(
                $this->createMock(Application::class),
                $this->createMock(RouteHelper::class)
            ),
        ];
        foreach ($services as $key => $value) {
            $helperPluginManager->setService($key, $value);
        }
        $container->set(HelperPluginManagerInterface::class, $helperPluginManager);
        $uriRegistry = $this->createMock(UriRegistry::class);
        $uriRegistry->method('get')->with('BaseUrlPath')->willReturn('/');
        $container->set(UriRegistry::class, $uriRegistry);
        $factory = new AssetManagerFactory();
        $assetManager = $factory($container, AssetManager::class);

        return $assetManager;
    }

    /**
     * Get helper plugin manager.
     *
     * @param array               $plugins   Plugins
     * @param ?ContainerInterface $container Container
     *
     * @return HelperPluginManager
     */
    protected function getHelperPluginManager(
        array $plugins = [],
        ?ContainerInterface $container = null
    ): HelperPluginManager {
        $helperPluginManager = new HelperPluginManager($container ?? $this->createMock(ContainerInterface::class), []);
        foreach ($plugins as $key => $value) {
            $helperPluginManager->setService($key, $value);
        }
        return $helperPluginManager;
    }

    /**
     * Get a working renderer.
     *
     * @param ?HelperPluginManager $helperPluginManager Configured helper plugin manager
     * @param string               $theme               Theme directory to load from
     *
     * @return PhpRenderer
     */
    protected function getPhpRenderer(?HelperPluginManager $helperPluginManager = null, $theme = 'bootstrap5')
    {
        $resolver = new \Laminas\View\Resolver\TemplatePathStack();

        // This assumes that all themes will be testing inherit directly from root with no intermediate themes. Probably
        // safe for most test situations, though other scenarios are possible.
        $resolver->setPaths(
            [
                $this->getPathForTheme('root'),
                $this->getPathForTheme($theme),
            ]
        );

        $helperPluginManager ??= $this->getHelperPluginManager();
        if (!$helperPluginManager->has('assetManager')) {
            $helperPluginManager->setService('assetManager', $this->getAssetManager($helperPluginManager));
        }
        return new PhpRenderer($helperPluginManager, $resolver);
    }

    /**
     * Get a working template renderer.
     *
     * @param ?HelperPluginManager $helperPluginManager Configured helper plugin manager
     * @param string               $theme               Theme directory to load from
     * @param ?PhpRenderer         $renderer            Laminas PHP renderer
     *
     * @return TemplateRendererInterface
     */
    protected function getTemplateRenderer(
        ?HelperPluginManager $helperPluginManager = null,
        $theme = 'bootstrap5',
        $renderer = null
    ): TemplateRendererInterface {
        $renderer ??= $this->getPhpRenderer($helperPluginManager, $theme);
        return $this->getAutowiredObject(
            LaminasTemplateRenderer::class,
            [
                'config' => [],
                ViewInterface::class => new View($renderer, $helperPluginManager, 'layout/layout', 'content'),
                'ViewRenderer' => $renderer,
            ]
        );
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
     * Get mock SearchMemory view helper.
     *
     * @param ?Memory        $memory       Optional search memory
     * @param ?RouteHelper   $routeHelper  Route helper
     * @param ?EscapeHtml    $escapeHtml   EscapeHtml helper
     * @param ?PluginManager $searchParams SearchParams helper
     *
     * @return SearchMemory
     */
    protected function getSearchMemoryViewHelper(
        ?Memory $memory = null,
        ?RouteHelper $routeHelper = null,
        ?EscapeHtml $escapeHtml = null,
        ?PluginManager $searchParams = null
    ): SearchMemory {
        if (null === $memory) {
            $memory = $this->createMock(Memory::class);
            $memory->method('getLastSearchId')->willReturn(-123);
        }
        $routeHelper ??= $this->createMock(RouteHelper::class);
        $escapeHtml ??= new EscapeHtml(new Escaper());
        $searchParams ??= $this->createMock(PluginManager::class);
        return new SearchMemory($memory, $routeHelper, $escapeHtml, $searchParams);
    }

    /**
     * Create the cleanHtml helper.
     *
     * @return CleanHtml
     */
    protected function createCleanHtmlHelper(): CleanHtml
    {
        // The FilesystemOptions class is final and cannot be mocked, so create our own as a workaround:
        $cacheOptions = new class () extends AdapterOptions {
            /**
             * Get cache dir.
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
