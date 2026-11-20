<?php

/**
 * ContentPages Plugin Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Sitemap\Plugin;

use Laminas\Router\RouteStackInterface;
use VuFind\Http\RouteHelper;
use VuFind\Sitemap\Plugin\ContentPages;
use VuFindTest\Container\MockContainer;
use VuFindTheme\ThemeInfo;

/**
 * ContentPages Plugin Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ContentPagesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\AutowireTrait;
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Mock container.
     *
     * @var MockContainer
     */
    protected $container = null;

    /**
     * Theme data for testing.
     *
     * @var array
     */
    protected $themeInfoData = [
        'templates/content/**/*.phtml' => [
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/asklibrary_en.phtml',
                'relativeFile' => 'templates/content/asklibrary_en.phtml',
            ],
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/asklibrary.phtml',
                'relativeFile' => 'templates/content/asklibrary.phtml',
            ],
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/content.phtml',
                'relativeFile' => 'templates/content/content.phtml',
            ],
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/faq.phtml',
                'relativeFile' => 'templates/content/faq.phtml',
            ],
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/help/search.phtml',
                'relativeFile' => 'templates/content/help/search.phtml',
            ],
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/help/search_en.phtml',
                'relativeFile' => 'templates/content/help/search_en.phtml',
            ],
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/markdown.phtml',
                'relativeFile' => 'templates/content/markdown.phtml',
            ],
        ],
        'templates/content/**/*.md' => [
            [
                'theme' => 'bootstrap5',
                'file' => '/themepath/templates/content/example.md',
                'relativeFile' => 'templates/content/example.md',
            ],
        ],
    ];

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new MockContainer($this);
    }

    /**
     * Get a ContentPages object from its factory.
     *
     * @param array                $config    Configuration
     * @param ?RouteStackInterface $router    Router object
     * @param ?ThemeInfo           $themeInfo ThemeInfo object
     *
     * @return ContentPages
     */
    protected function getContentPages(
        array $config = [],
        ?RouteStackInterface $router = null,
        ?ThemeInfo $themeInfo = null
    ): ContentPages {
        // Set up configuration:
        $this->container->set(
            \VuFind\Config\ConfigManagerInterface::class,
            $this->getMockConfigManager(compact('config'))
        );

        // Set up other dependencies:
        $this->container->set('RouteHelper', $router ?? $this->getMockRouteHelper());
        $this->container
            ->set(ThemeInfo::class, $themeInfo ?? $this->getMockThemeInfo());
        // Build the object:
        return $this->getAutowiredObject(ContentPages::class, $this->container);
    }

    /**
     * Get mock route helper.
     *
     * @return RouteHelper
     */
    protected function getMockRouteHelper(): RouteHelper
    {
        $routeHelper = $this->container->get(RouteHelper::class);
        // Callback to ensure that ContentPages is passing expected
        // parameters, and to convert them into a format we can test:
        $callback = function (string $name, array $routeParams) {
            $this->assertSame(['page'], array_keys($routeParams));
            return $name . '/' . $routeParams['page'];
        };
        $routeHelper->method('getUrlFromRoute')->willReturnCallback($callback);
        return $routeHelper;
    }

    /**
     * Get mock ThemeInfo object.
     *
     * @return ThemeInfo
     */
    protected function getMockThemeInfo(): ThemeInfo
    {
        $themeInfo = $this->container->get(ThemeInfo::class);
        $themeInfo->expects($this->exactly(2))->method('findInThemes')
            ->willReturnCallback(
                function ($paths) {
                    return $this->themeInfoData[reset($paths)] ?? null;
                }
            );
        return $themeInfo;
    }

    /**
     * Test URL generation without languages.
     *
     * @return void
     */
    public function testWithoutLanguages(): void
    {
        $plugin = $this->getContentPages([]);
        // Without language settings, asklibrary_en and asklibrary are
        // treated as separate pages:
        $this->assertSame(
            [
                'content-page/asklibrary_en',
                'content-page/asklibrary',
                'content-page/faq',
                'content-page/help/search',
                'content-page/help/search_en',
                'content-page/example',
            ],
            iterator_to_array($plugin->getUrls())
        );
    }

    /**
     * Test URL generation with languages.
     *
     * @return void
     */
    public function testWithLanguages(): void
    {
        $plugin = $this->getContentPages(['Languages' => ['en' => 'English']]);
        // With language settings, asklibrary_en and asklibrary are
        // treated as the same page:
        $this->assertSame(
            [
                'content-page/asklibrary',
                'content-page/faq',
                'content-page/help/search',
                'content-page/example',
            ],
            iterator_to_array($plugin->getUrls())
        );
    }
}
