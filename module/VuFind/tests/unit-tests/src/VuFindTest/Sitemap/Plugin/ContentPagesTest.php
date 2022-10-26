<?php
/**
 * ContentPages Plugin Test Class
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Sitemap\Plugin;

use Laminas\Router\RouteStackInterface;
use VuFind\Sitemap\Plugin\ContentPages;
use VuFind\Sitemap\Plugin\ContentPagesFactory;
use VuFindTest\Container\MockContainer;
use VuFindTheme\ThemeInfo;

/**
 * ContentPages Plugin Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ContentPagesTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Mock container
     *
     * @var MockContainer
     */
    protected $container = null;

    /**
     * Theme data for testing
     *
     * @var array
     */
    protected $themeInfoData = [
        [
          'theme' => 'bootstrap3',
          'file' => '/themepath/templates/content/asklibrary_en.phtml',
          'relativeFile' => 'templates/content/asklibrary_en.phtml',
        ],
        [
          'theme' => 'bootstrap3',
          'file' => '/themepath/templates/content/asklibrary.phtml',
          'relativeFile' => 'templates/content/asklibrary.phtml',
        ],
        [
          'theme' => 'bootstrap3',
          'file' => '/themepath/templates/content/content.phtml',
          'relativeFile' => 'templates/content/content.phtml',
        ],
        [
          'theme' => 'bootstrap3',
          'file' => '/themepath/templates/content/faq.phtml',
          'relativeFile' => 'templates/content/faq.phtml',
        ],
        [
          'theme' => 'bootstrap3',
          'file' => '/themepath/templates/content/markdown.phtml',
          'relativeFile' => 'templates/content/markdown.phtml',
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
     * Get a ContentPages object from its factory
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
            \VuFind\Config\PluginManager::class,
            $this->getMockConfigPluginManager(compact('config'))
        );

        // Set up other dependencies:
        $this->container->set('HttpRouter', $router ?? $this->getMockRouter());
        $this->container
            ->set(ThemeInfo::class, $themeInfo ?? $this->getMockThemeInfo());
        // Build the object:
        $factory = new ContentPagesFactory();
        return $factory($this->container, ContentPages::class);
    }

    /**
     * Get mock router object
     *
     * @return RouteStackInterface
     */
    protected function getMockRouter(): RouteStackInterface
    {
        $router = $this->container->get(RouteStackInterface::class);
        // Callback to ensure that ContentPages is passing expected
        // parameters, and to convert them into a format we can test:
        $callback = function () {
            [$params, $options] = func_get_args();
            $this->assertEquals(['page'], array_keys($params));
            $this->assertEquals(['name'], array_keys($options));
            return $options['name'] . '/' . $params['page'];
        };
        $router->expects($this->any())->method('assemble')
            ->will($this->returnCallback($callback));
        return $router;
    }

    /**
     * Get mock ThemeInfo object
     *
     * @return ThemeInfo
     */
    protected function getMockThemeInfo(): ThemeInfo
    {
        $expectedTemplates = [
            'templates/content/*.phtml',
            'templates/content/*.md',
        ];
        $themeInfo = $this->container->get(ThemeInfo::class);
        $themeInfo->expects($this->once())->method('findInThemes')
            ->with($this->equalTo($expectedTemplates))
            ->will($this->returnValue($this->themeInfoData));
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
        $this->assertEquals(
            [
                'content-page/asklibrary_en',
                'content-page/asklibrary',
                'content-page/faq',
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
        $this->assertEquals(
            [
                'content-page/asklibrary',
                'content-page/faq',
            ],
            iterator_to_array($plugin->getUrls())
        );
    }
}
